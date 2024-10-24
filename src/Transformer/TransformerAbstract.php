<?php

declare(strict_types=1);

namespace ErrorHeroModule\Transformer;

use Laminas\Log\Logger;
use Laminas\Log\PsrLoggerAdapter;

abstract class TransformerAbstract
{
    protected static function getLoggerInstance(array $writers): PsrLoggerAdapter
    {
        return new PsrLoggerAdapter(new Logger(['writers' => $writers]));
    }
}
