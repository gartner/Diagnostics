<?php
return array(
    'ZFTool' => array(
        'disable_usage' => false,    // set to true to disable showing available ZFTool commands in Console.
    ),

    'controllers' => array(
        'factories' => [
            'ZFTool\Controller\Diagnostics' => ZFTool\Controller\DiagnosticsControllerFactory::class,
        ],
    ),

    'view_manager' => array(
        'template_map' => array(
            'zf-tool/diagnostics/run' => __DIR__ . '/../view/diagnostics/run.phtml',
        )
    ),

    'console' => array(
        'router' => array(
            'routes' => array(
                'zftool-diagnostics' => array(
                    'options' => array(
                        'route'    => '(diagnostics|diag) [-v|--verbose]:verbose [--debug] [-q|--quiet]:quiet [-b|--break]:break [<filter>]',
                        'defaults' => array(
                            'controller' => 'ZFTool\Controller\Diagnostics',
                            'action'     => 'run',
                        ),
                    ),
                ),
            ),
        ),
    ),

    'diagnostics' => array(
        'ZF' => array(
            'PHP Version' => array('PhpVersion', '7.2.0'),
        )
    )
);
