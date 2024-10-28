<?php

declare(strict_types=1);

namespace ErrorHeroModule\Transformer;

use ErrorHeroModule\Handler\Writer\DoctrineWriterFactory;
use Laminas\ServiceManager\ServiceManager;
use Override;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

final class DoctrineTransformer extends TransformerAbstract implements TransformerInterface
{
    #[Override]
    public static function transform(ContainerInterface $container): ContainerInterface
    {
        Assert::isInstanceOf(value: $container, class: ServiceManager::class);

        $writers = [
            [
                'name' => (new DoctrineWriterFactory())(container: $container)
            ]
        ];

        $logger = parent::getLoggerInstance(writers: $writers);

        return $container->configure(config: [
            'services' => [
                'ErrorHeroModuleLogger' => $logger,
            ],
        ]);
    }
}
