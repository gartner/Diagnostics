<?php

namespace ZFTool;

use Zend\Console\Adapter\AdapterInterface as ConsoleAdapterInterface;
use Zend\EventManager\EventInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\BootstrapListenerInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module implements
    ConsoleUsageProviderInterface,
    ConfigProviderInterface,
    ConsoleBannerProviderInterface,
    BootstrapListenerInterface
{
    const NAME = 'palustrisDiagnostics - Zend Framework 3 command line Tool';

    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    public function onBootstrap(EventInterface $e)
    {
        $this->serviceLocator = $e->getApplication()->getServiceManager();
    }

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getConsoleBanner(ConsoleAdapterInterface $console)
    {
        return self::NAME;
    }

    public function getConsoleUsage(ConsoleAdapterInterface $console)
    {
        $config = $this->serviceLocator->get('config');
        if(!empty($config['ZFTool']) && !empty($config['ZFTool']['disable_usage'])) {
            return null; // usage information has been disabled
        }

        // TODO: Load strings from a translation container
        return array(

            'Diagnostics',
            'diag [options] [module name]'  => 'run diagnostics',
            array('[module name]'               , '(Optional) name of module to test'),
            array('-v --verbose'                , 'Display detailed information.'),
            array('-b --break'                  , 'Stop testing on first failure'),
            array('-q --quiet'                  , 'Do not display any output unless an error occurs.'),
            array('--debug'                     , 'Display raw debug info from tests.'),

        );
    }
}
