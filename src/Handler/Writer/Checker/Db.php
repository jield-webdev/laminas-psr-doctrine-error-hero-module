<?php

declare(strict_types=1);

namespace ErrorHeroModule\Handler\Writer\Checker;

use Closure;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Log\Writer\Db as DbWriter;

use function date;
use function strtotime;

final readonly class Db
{
    /** @var string */
    private const string OPTIONS = 'options';

    /** @var string */
    private const string COLUMN = 'column';

    /** @var string */
    private const string EXTRA = 'extra';

    public function __construct(
        private DbWriter $dbWriter,
        private array $configLoggingSettings,
        private array $logWritersConfig
    ) {
    }

    public function isExists(
        string $errorFile,
        int $errorLine,
        string $errorMessage,
        string $errorUrl,
        string $errorType
    ): bool {
        // db definition
        $db = Closure::bind(closure: static fn($dbWriter) => $dbWriter->db, newThis: null, newScope: $this->dbWriter)($this->dbWriter);

        foreach ($this->logWritersConfig as $logWriterConfig) {
            if ($logWriterConfig['name'] === 'db') {
                // table definition
                $table = $logWriterConfig[self::OPTIONS]['table'];

                // columns definition
                $timestamp  = $logWriterConfig[self::OPTIONS][self::COLUMN]['timestamp'];
                $message    = $logWriterConfig[self::OPTIONS][self::COLUMN]['message'];
                $file       = $logWriterConfig[self::OPTIONS][self::COLUMN][self::EXTRA]['file'];
                $line       = $logWriterConfig[self::OPTIONS][self::COLUMN][self::EXTRA]['line'];
                $url        = $logWriterConfig[self::OPTIONS][self::COLUMN][self::EXTRA]['url'];
                $error_type = $logWriterConfig[self::OPTIONS][self::COLUMN][self::EXTRA]['error_type'];

                $tableGateway = new TableGateway(table: $table, adapter: $db, features: null, resultSetPrototype: new ResultSet());
                $select       = $tableGateway->getSql()->select();
                $select->columns(columns: [$timestamp]);
                $select->where(predicate: [
                    $message    => $errorMessage,
                    $line       => $errorLine,
                    $url        => $errorUrl,
                    $file       => $errorFile,
                    $error_type => $errorType,
                ]);
                $select->order(order: $timestamp . ' DESC');
                $select->limit(limit: 1);

                /** @var ResultSet $resultSet */
                $resultSet = $tableGateway->selectWith(select: $select);
                if (! ($current = $resultSet->current())) {
                    return false;
                }

                $first = $current[$timestamp];
                $last  = date(format: 'Y-m-d H:i:s');

                $diff = strtotime(datetime: $last) - strtotime(datetime: (string) $first);
                if ($diff <= $this->configLoggingSettings['same-error-log-time-range']) {
                    return true;
                }

                break;
            }
        }

        return false;
    }
}
