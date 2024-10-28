<?php

declare(strict_types=1);

namespace ErrorHeroModule\Command;

use ErrorHeroModule\Handler\Logging;
use ErrorHeroModule\HeroTrait;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function ErrorHeroModule\isExcludedException;

abstract class BaseLoggingCommand extends Command
{
    use HeroTrait;

    private const string DISPLAY_SETTINGS = 'display-settings';

    private array $errorHeroModuleConfig;

    private Logging $logging;

    /**
     * MUST BE CALLED after __construct(), as service extends this base class may use dependency injection
     *
     * With `Laminas\ServiceManager`, you don't need to do anything \m/, there is `initializers` config already for it.
     */
    public function init(array $errorHeroModuleConfig, Logging $logging): void
    {
        $this->errorHeroModuleConfig = $errorHeroModuleConfig;
        $this->logging               = $logging;
    }

    /**
     * @throws Throwable
     */
    #[Override]
    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->phpError();
            return parent::run(input: $input, output: $output);
        } catch (Throwable $throwable) {
        }

        $this->exceptionError(throwable: $throwable);

        // show default view if display_errors setting = 0.
        return $this->showDefaultConsoleView(output: $output);
    }

    /**
     * @throws Throwable
     */
    private function exceptionError(Throwable $throwable): void
    {
        if (
            isset($this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['exclude-exceptions'])
            && isExcludedException(
                excludeExceptionsConfig: $this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['exclude-exceptions'],
                throwable: $throwable
            )
        ) {
            throw $throwable;
        }

        $this->logging->handleErrorException(throwable: $throwable);

        if ($this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['display_errors']) {
            throw $throwable;
        }
    }

    private function showDefaultConsoleView(OutputInterface $output): int
    {
        $table = new Table(output: $output);
        $table->setColumnMaxWidth(columnIndex: 0, width: 147);
        $table
            ->setRows(rows: [
                [$this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['console']['message']],
            ]);
        $table->render();

        return 1;
    }
}
