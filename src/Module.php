<?php

declare(strict_types=1);

namespace ErrorHeroModule;

use Doctrine\ORM\EntityManager;
use ErrorHeroModule\Transformer\DoctrineTransformer;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\ModuleManager\Feature\DependencyIndicatorInterface;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Laminas\ServiceManager\ServiceManager;

final class Module implements ConfigProviderInterface, DependencyIndicatorInterface
{
    public function init(ModuleManager $moduleManager): void
    {
        $eventManager = $moduleManager->getEventManager();
        $eventManager->attach(ModuleEvent::EVENT_LOAD_MODULES_POST, [$this, 'doctrineTransform']);
    }

    public function doctrineTransform(ModuleEvent $moduleEvent): void
    {
        /** @var ServiceManager $container */
        $container        = $moduleEvent->getParam('ServiceManager');
        $hasEntityManager = $container->has(EntityManager::class);

        if (!$hasEntityManager) {
            return;
        }

        DoctrineTransformer::transform($container);
    }

    public function getConfig(): array
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getModuleDependencies(): array
    {
        return [
            'DoctrineModule',
            'DoctrineORMModule',
        ];
    }
}
