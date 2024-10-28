<?php

declare(strict_types=1);

namespace ErrorHeroModule\Handler\Formatter;

use DateTime;
use Laminas\Log\Formatter\FormatterInterface;
use Laminas\Log\Formatter\Json as BaseJson;

use Override;
use function json_encode;
use function str_replace;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;

final class Json extends BaseJson implements FormatterInterface
{
    /** @var string */
    private const string TIMESTAMP = 'timestamp';

    /**
     * @param array<string, DateTime> $event event data
     * @return string formatted line to write to the log
     */
    #[Override]
    public function format($event): string
    {
        static $timestamp;

        if (! $timestamp && isset($event[self::TIMESTAMP]) && $event[self::TIMESTAMP] instanceof DateTime) {
            $timestamp = $event[self::TIMESTAMP]->format(format: $this->getDateTimeFormat());
        }

        $event[self::TIMESTAMP] = $timestamp;

        return str_replace(
            search: '\n',
            replace: PHP_EOL,
            subject: (string) json_encode(value: $event, flags: JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
