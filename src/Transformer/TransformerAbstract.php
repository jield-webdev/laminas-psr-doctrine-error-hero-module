<?php

declare(strict_types=1);

namespace ErrorHeroModule\Transformer;

use Laminas\Db\Adapter\Adapter;
use Laminas\Log\Logger;
use Laminas\Log\PsrLoggerAdapter;

abstract class TransformerAbstract
{
    /** @var string */
    private const DB = 'db';

    /**
     * @return array{array{name: string, options: array}}
     */
    private static function getWriterConfig(array $configuration): array
    {
        return $configuration['log']['ErrorHeroModuleLogger']['writers'];
    }

    /**
     * @return mixed[]
     */
    protected static function getDbAdapterConfig(array $configuration): array
    {
        $writers = self::getWriterConfig($configuration);
        $config  = $configuration[self::DB];

        if (!isset($config['adapters'])) {
            return $config;
        }

        foreach ($writers as $writer) {
            if ($writer['name'] === self::DB) {
                $adapterName = $writer['options'][self::DB];
                break;
            }
        }

        return isset($adapterName)
            ? ($config['adapters'][$adapterName] ?? $config)
            : $config;
    }

    protected static function getLoggerInstance(array $configuration, array $dbConfig): PsrLoggerAdapter
    {
        $writers = self::getWriterConfig($configuration);
        foreach ($writers as &$writer) {
            if ($writer['name'] === self::DB) {
                $writer['options'][self::DB] = new Adapter($dbConfig);
                break;
            }
        }

        return new PsrLoggerAdapter(new Logger(['writers' => $writers]));
    }
}
