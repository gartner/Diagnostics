<?php

namespace ZFTool\Controller;

use Interop\Container\ContainerInterface;
use Laminas\Console\Adapter\AdapterInterface;
use Laminas\Console\ColorInterface;
use Laminas\Console\Request as ConsoleRequest;
use Laminas\Http\Header\Accept;
use Laminas\Http\Request;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ConsoleModel;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Laminas\Diagnostics\Check\Callback;
use Laminas\Diagnostics\Check\CheckInterface;
use Laminas\Diagnostics\Result\Collection;
use Laminas\Diagnostics\Result\FailureInterface;
use Laminas\Diagnostics\Result\ResultInterface;
use Laminas\Diagnostics\Result\SuccessInterface;
use Laminas\Diagnostics\Result\WarningInterface;
use ZFTool\Diagnostics\Exception\RuntimeException;
use ZFTool\Diagnostics\Reporter\BasicConsole;
use ZFTool\Diagnostics\Reporter\VerboseConsole;
use ZFTool\Diagnostics\Runner;

class DiagnosticsController extends AbstractActionController
{
    /** @var  AdapterInterface */
    protected $console;
    
    /** @var  array */
    protected $config;
    
    /** @var  ModuleManager */
    protected $moduleManager;
    
    /** @var  ContainerInterface */
    protected $serviceLocator;
    
    public function __construct(
        array $config,
        ModuleManager $moduleManager,
        ContainerInterface $serviceLocator,
        AdapterInterface $console = null
    ) {
        $this->config         = $config;
        $this->moduleManager  = $moduleManager;
        $this->serviceLocator = $serviceLocator;
        $this->console        = $console;
    }

    public function runAction()
    {
        $console = $this->console;
        $config  = $this->config;
        $mm      = $this->moduleManager;

        $verbose        = $this->params()->fromRoute('verbose', false);
        $debug          = $this->params()->fromRoute('debug', false);
        $quiet          = !$verbose && !$debug && $this->params()->fromRoute('quiet', false);
        $breakOnFailure = $this->params()->fromRoute('break', false);
        $checkGroupName = $this->params()->fromRoute('filter', false);

        // Get basic diag configuration
        $config = isset($config['diagnostics']) ? $config['diagnostics'] : array();

        // Collect diag tests from modules
        $modules = $mm->getLoadedModules(false);
        foreach ($modules as $moduleName => $module) {
            if (is_callable(array($module, 'getDiagnostics'))) {
                $checks = $module->getDiagnostics();
                if (is_array($checks)) {
                    $config[$moduleName] = $checks;
                }

                // Exit the loop early if we found check definitions for
                // the only check group that we want to run.
                if ($checkGroupName && $moduleName == $checkGroupName) {
                    break;
                }
            }
        }

        // Filter array if a check group name has been provided
        if ($checkGroupName) {
            $config = array_intersect_ukey($config, array($checkGroupName => 1), 'strcasecmp');

            if (empty($config)) {
                $m = new ConsoleModel();
                $m->setResult($this->colorize(sprintf(
                    "Unable to find a group of diagnostic checks called \"%s\". Try to use module name (i.e. \"%s\").\n",
                    $checkGroupName,
                    'Application'
                ), ColorInterface::YELLOW));
                $m->setErrorLevel(1);

                return $m;
            }
        }

        // Check if there are any diagnostic checks defined
        if (empty($config)) {
            $m = new ConsoleModel();
            $m->setResult(
                $this->colorize(
                    "There are no diagnostic checks currently enabled for this application - please add one or more " .
                    "entries into config \"diagnostics\" array or add getDiagnostics() method to your Module class. " .
                    "\n\nMore info: https://github.com/zendframework/ZFTool/blob/master/docs/" .
                    "DIAGNOSTICS.md#adding-checks-to-your-module\n"
                , ColorInterface::YELLOW)
            );
            $m->setErrorLevel(1);

            return $m;
        }

        // Analyze check definitions and construct check instances
        $checkCollection = array();
        foreach ($config as $checkGroupName => $checks) {
            foreach ($checks as $checkLabel => $check) {
                // Do not use numeric labels.
                if (!$checkLabel || is_numeric($checkLabel)) {
                    $checkLabel = false;
                }

                // Handle a callable.
                if (is_callable($check)) {
                    $check = new Callback($check);
                    if ($checkLabel) {
                        $check->setLabel($checkGroupName . ': ' . $checkLabel);
                    }

                    $checkCollection[] = $check;
                    continue;
                }

                // Handle check object instance.
                if (is_object($check)) {
                    if (!$check instanceof CheckInterface) {
                        throw new RuntimeException(
                            'Cannot use object of class "' . get_class($check). '" as check. '.
                            'Expected instance of Laminas\Diagnostics\Check\CheckInterface'
                        );

                    }

                    // Use duck-typing for determining if the check allows for setting custom label
                    if ($checkLabel && is_callable(array($check, 'setLabel'))) {
                        $check->setLabel($checkGroupName . ': ' . $checkLabel);
                    }
                    $checkCollection[] = $check;
                    continue;
                }

                // Handle an array containing callback or identifier with optional parameters.
                if (is_array($check)) {
                    if (!count($check)) {
                        throw new RuntimeException(
                            'Cannot use an empty array() as check definition in "'.$checkGroupName.'"'
                        );
                    }

                    // extract check identifier and store the remainder of array as parameters
                    $testName = array_shift($check);
                    $params = $check;

                } elseif (is_scalar($check)) {
                    $testName = $check;
                    $params = array();

                } else {
                    throw new RuntimeException(
                        'Cannot understand diagnostic check definition "' . gettype($check). '" in "'.$checkGroupName.'"'
                    );
                }

                // Try to expand check identifier using Service Locator
                if (is_string($testName) && $this->serviceLocator->has($testName)) {
                    if (0 < count($params)) {
                        $check = $this->serviceLocator->build($testName, $params);
                    } else {
                        $check = $this->serviceLocator->get($testName);
                    }

                // Try to use the ZendDiagnostics namespace
                } elseif (is_string($testName) && class_exists('Laminas\\Diagnostics\\Check\\' . $testName)) {
                    $class = new \ReflectionClass('Laminas\\Diagnostics\\Check\\' . $testName);
                    $check = $class->newInstanceArgs($params);

                // Try to use the ZFTool namespace
                } elseif (is_string($testName) && class_exists('ZFTool\\Diagnostics\\Check\\' . $testName)) {
                    $class = new \ReflectionClass('ZFTool\\Diagnostics\\Check\\' . $testName);
                    $check = $class->newInstanceArgs($params);

                // Check if provided with a callable inside an array
                } elseif (is_callable($testName)) {
                    $check = new Callback($testName, $params);
                    if ($checkLabel) {
                        $check->setLabel($checkGroupName . ': ' . $checkLabel);
                    }

                    $checkCollection[] = $check;
                    continue;

                // Try to expand check using class name
                } elseif (is_string($testName) && class_exists($testName)) {
                    $class = new \ReflectionClass($testName);
                    $check = $class->newInstanceArgs($params);

                } else {
                    throw new RuntimeException(
                        'Cannot find check class or service with the name of "' . $testName . '" ('.$checkGroupName.')'
                    );
                }

                if (!$check instanceof CheckInterface) {
                    // not a real check
                    throw new RuntimeException(
                        'The check object of class '.get_class($check).' does not implement '.
                        'Laminas\Diagnostics\Check\CheckInterface'
                    );
                }

                // Use duck-typing for determining if the check allows for setting custom label
                if ($checkLabel && is_callable(array($check, 'setLabel'))) {
                    $check->setLabel($checkGroupName . ': ' . $checkLabel);
                }

                $checkCollection[] = $check;
            }
        }

        // Configure check runner
        $runner = new Runner();
        $runner->addChecks($checkCollection);
        $runner->getConfig()->setBreakOnFailure($breakOnFailure);

        if (!$quiet && $this->getRequest() instanceof ConsoleRequest) {
            if ($verbose || $debug) {
                $runner->addReporter(new VerboseConsole($this->console, $debug));
            } else {
                $runner->addReporter(new BasicConsole($this->console));
            }
        }

        // Run tests
        $results = $runner->run();

        $request = $this->getRequest();

        // Return result
        if ($request instanceof ConsoleRequest) {
            // Return appropriate error code in console
            $model = new ConsoleModel();
            $model->setVariable('results', $results);

            if ($results->getFailureCount() > 0) {
                $model->setErrorLevel(1);
            } else {
                $model->setErrorLevel(0);
            }
            return $model;
        }

        if ($request instanceof Request) {
            $defaultAccept = new Accept();
            $defaultAccept->addMediaType('text/html');

            $acceptHeader = $request->getHeader('Accept', $defaultAccept);

            $viewModel = function () use ($results) {
                $model = new ViewModel();
                $model->setVariable('results', $results);
                return $model;
            };

            if ($acceptHeader->match('text/html')) {
                // Display results as a web page
                $model = $viewModel();
            } else if ($acceptHeader->match('application/json')) {
                // Display results as json
                $model = new JsonModel();
                $model->setVariables($this->getResultCollectionToArray($results));
            } else {
                $model = $viewModel();
            }
            return $model;
        }
    }

    /**
     * @param ResultInterface $result
     * @return string
     */
    protected function getResultName(ResultInterface $result)
    {
        switch (true) {
            case $result instanceof SuccessInterface:
                return 'success';
            case $result instanceof WarningInterface:
                return 'warning';
            case $result instanceof FailureInterface:
                return 'failure';
            case $result instanceof ResultInterface:
                return 'skip';
            default:
                return 'unknown';
        }
    }

    /**
     * @param Collection $results
     * @return array
     */
    protected function getResultCollectionToArray(Collection $results)
    {
        foreach ($results as $item) {
            $result = $results[$item];
            $data[$item->getLabel()] = array(
                'result' => $this->getResultName($result),
                'message' => $result->getMessage(),
                'data' => $result->getData(),
            );
        }

        return array(
            'details' => $data,
            'success' => $results->getSuccessCount(),
            'warning' => $results->getWarningCount(),
            'failure' => $results->getFailureCount(),
            'skip' => $results->getSkipCount(),
            'unknown' => $results->getUnknownCount(),
            'passed' => $results->getFailureCount() === 0,
        );
    }

    protected function colorize($string, $color = null, $bgColor = null)
    {
        if ($this->console instanceof AdapterInterface) {
            return $this->console->colorize($string, $color, $bgColor);
        }

        return $string;
    }

}
