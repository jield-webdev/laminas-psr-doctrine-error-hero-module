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
use Override;

final class Module implements ConfigProviderInterface, DependencyIndicatorInterface
{
    public function init(ModuleManager $moduleManager): void
    {
        $eventManager = $moduleManager->getEventManager();
        $eventManager->attach(eventName: ModuleEvent::EVENT_LOAD_MODULES_POST, listener: $this->doctrineTransform(...));
    }

    public function doctrineTransform(ModuleEvent $moduleEvent): void
    {
        /** @var ServiceManager $container */
        $container        = $moduleEvent->getParam(name: 'ServiceManager');
        $hasEntityManager = $container->has(name: EntityManager::class);

        if (!$hasEntityManager) {
            return;
        }

        DoctrineTransformer::transform(container: $container);
    }

    #[Override]
    public function getConfig(): array
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    #[Override]
    public function getModuleDependencies(): array
    {
        return [
            'DoctrineModule',
            'DoctrineORMModule',
        ];
    }
}
