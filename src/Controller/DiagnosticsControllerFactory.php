<?php
declare(strict_types=1);

namespace ZFTool\Controller;

use Interop\Container\ContainerInterface;
use Zend\Console\Adapter\AbstractAdapter;
use Zend\ServiceManager\Factory\FactoryInterface;

class DiagnosticsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $console = $container->get('console');

        if (!($console instanceof AbstractAdapter)) {
            $console = null;
        }
            
        return new DiagnosticsController(
            $container->get('Configuration'),
            $container->get('ModuleManager'),
            $container,
            $console
        );

    }
}
