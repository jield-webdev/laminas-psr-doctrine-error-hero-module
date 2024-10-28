<?php

namespace ErrorHeroModule;

use ErrorHeroModule\Command\BaseLoggingCommandInitializer;
use ErrorHeroModule\Handler\Logging;
use ErrorHeroModule\Handler\LoggingFactory;
use ErrorHeroModule\Listener\Mvc;
use ErrorHeroModule\Listener\MvcFactory;
use Laminas\Log\PsrLoggerAbstractAdapterFactory;

return [
    'service_manager' => [
        'abstract_factories' => [
            PsrLoggerAbstractAdapterFactory::class,
        ],
        'factories'          => [
            Mvc::class     => MvcFactory::class,
            Logging::class => LoggingFactory::class,
        ],
        'initializers'       => [
            BaseLoggingCommandInitializer::class,
        ],
    ],
    'listeners'       => [
        Mvc::class,
    ],
    'view_manager'    => [
        'template_map' => [
            'error-hero-module/error-default' => __DIR__ . '/../view/error-hero-module/error-default.phtml',
        ],
    ],
];
