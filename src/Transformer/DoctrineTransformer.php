<?php

declare(strict_types=1);

namespace ErrorHeroModule\Transformer;

use ErrorHeroModule\Handler\Writer\DoctrineWriterFactory;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

final class DoctrineTransformer extends TransformerAbstract implements TransformerInterface
{
    public static function transform(ContainerInterface $container): ContainerInterface
    {
        Assert::isInstanceOf($container, ServiceManager::class);

        $writers = [
            [
                'name' => (new DoctrineWriterFactory())($container)
            ]
        ];

        $logger = parent::getLoggerInstance($writers);

        return $container->configure([
            'services' => [
                'ErrorHeroModuleLogger' => $logger,
            ],
        ]);
    }
}
