<?php

declare(strict_types=1);

namespace ErrorHeroModule\Handler\Writer;

use Doctrine\ORM\EntityManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

final class DoctrineWriterFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName = '', ?array $options = null): DoctrineWriter
    {
        return new DoctrineWriter(
            entityManager: $container->get(EntityManager::class),
            config: $container->get('config')['error-hero-module'] ?? []
        );
    }
}
