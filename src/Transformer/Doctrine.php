<?php

declare(strict_types=1);

namespace ErrorHeroModule\Transformer;

use Doctrine\ORM\EntityManager;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;
use function explode;
use function implode;
use function rtrim;
use function strtolower;

final class Doctrine extends TransformerAbstract implements TransformerInterface
{
    public static function transform(ContainerInterface $container, array $configuration): ContainerInterface
    {
        Assert::isInstanceOf($container, ServiceManager::class);

        /** @var EntityManager $entityManager */
        $entityManager          = $container->get(EntityManager::class);
        $doctrineDBALConnection = $entityManager->getConnection();

        /**
         * @var array{
         *          driverClass: string,
         *          user: string,
         *          password: string,
         *          dbname: string,
         *          host: string,
         *          port: int|string,
         *          driverOptions: array|null
         * } $params
         */
        $params        = $doctrineDBALConnection->getParams();
        $driverOptions = $params['driverOptions'] ?? [];

        $driverClass               = $params['driverClass'];
        $driverNamespaces          = explode('\\', $driverClass);
        $fullUnderscoredDriverName = strtolower(implode('_', $driverNamespaces));
        $driverName                = rtrim($fullUnderscoredDriverName, '_driver');
        [, $driverName] = explode('driver_', $driverName);

        $dbAdapterConfig = [
            'username'       => $params['user'],
            'password'       => $params['password'],
            'driver'         => $driverName,
            'database'       => $params['dbname'],
            'host'           => $params['host'],
            'port'           => $params['port'],
            'driver_options' => $driverOptions,
        ];

        $logger = parent::getLoggerInstance($configuration, $dbAdapterConfig);

        return $container->configure([
            'services' => [
                'ErrorHeroModuleLogger' => $logger,
            ],
        ]);
    }
}
