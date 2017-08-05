<?php
declare(strict_types=1);

namespace ZFTool\Controller;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class DiagnosticsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        // TODO: Implement __invoke() method.
        return new DiagnosticsController(
            $container->get('console'),
            $container->get('Configuration'),
            $container->get('ModuleManager'),
            $container
        );

    }
}
