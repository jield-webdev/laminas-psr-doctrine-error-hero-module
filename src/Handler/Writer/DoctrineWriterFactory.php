<?php

declare(strict_types=1);

namespace ErrorHeroModule\Handler\Writer;

use Doctrine\ORM\EntityManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class DoctrineWriterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName = '', ?array $options = null): DoctrineWriter
    {
        return new DoctrineWriter(
            $container->get(EntityManager::class),
            $container->get('config')['error-hero-module'] ?? []
        );
    }
}
