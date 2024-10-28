<?php

declare(strict_types=1);

namespace ErrorHeroModule;

use ArrayLookup\AtLeast;
use ErrorException;
use ErrorHeroModule\Command\BaseLoggingCommand;
use ErrorHeroModule\Listener\Mvc;
use Laminas\Mvc\MvcEvent;
use Webmozart\Assert\Assert;
use function error_get_last;
use function error_reporting;
use function ini_set;
use function is_array;
use function ob_end_flush;
use function ob_get_clean;
use function ob_get_level;
use function ob_start;
use function register_shutdown_function;
use function set_error_handler;
use function str_starts_with;
use const E_ALL;
use const E_STRICT;

trait HeroTrait
{
    private string $result = '';

    public function phpError(mixed ...$args): void
    {
        if ($this instanceof Mvc) {
            Assert::count(array: $args, number: 1);
            Assert::isInstanceOf(value: $args[0], class: MvcEvent::class);

            $this->mvcEvent = $args[0];
        }

        if (!$this->errorHeroModuleConfig['display-settings']['display_errors']) {
            error_reporting(error_level: E_ALL | E_STRICT);
            ini_set(option: 'display_errors', value: '0');
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(callback: [$this, 'phpFatalErrorHandler']);
        register_shutdown_function(callback: [$this, 'execOnShutdown']);
        set_error_handler(callback: [$this, 'phpErrorHandler']);
    }

    private static function isUncaught(string $message): bool
    {
        return str_starts_with(haystack: $message, needle: 'Uncaught');
    }

    public function phpFatalErrorHandler(string $buffer): string
    {
        $error = error_get_last();
        if ($error === null) {
            return $buffer;
        }

        return self::isUncaught(message: $error['message']) || $this->result === ''
            ? $buffer
            : $this->result;
    }

    public function execOnShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        if (self::isUncaught(message: $error['message'])) {
            return;
        }

        $errorException = new ErrorException(message: $error['message'], code: 0, severity: $error['type'], filename: $error['file'], line: $error['line']);

        // laminas-cli
        if ($this instanceof BaseLoggingCommand) {
            ob_start();
            $this->exceptionError($errorException);
            $this->result = (string)ob_get_clean();

            return;
        }

        // Laminas Mvc project
        Assert::isInstanceOf(value: $this->mvcEvent, class: MvcEvent::class);
        ob_start();
        $this->mvcEvent->setParam(name: 'exception', value: $errorException);
        $this->exceptionError($this->mvcEvent);
        $this->result = (string)ob_get_clean();
    }

    /**
     * @throws ErrorException When php error happen and error type is not excluded in the config.
     */
    public function phpErrorHandler(int $errorType, string $errorMessage, string $errorFile, int $errorLine): void
    {
        if ((error_reporting() & $errorType) === 0) {
            return;
        }

        $filter = static fn(mixed $excludePhpError): bool => $errorType === $excludePhpError ||
            (
                is_array(value: $excludePhpError)
                && $excludePhpError[0] === $errorType
                && $excludePhpError[1] === $errorMessage
            );

        if (AtLeast::once(data: $this->errorHeroModuleConfig['display-settings']['exclude-php-errors'], filter: $filter)) {
            return;
        }

        throw new ErrorException(message: $errorMessage, code: 0, severity: $errorType, filename: $errorFile, line: $errorLine);
    }
}
