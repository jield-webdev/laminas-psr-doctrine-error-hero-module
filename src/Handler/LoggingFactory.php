<?php

declare(strict_types=1);

namespace ErrorHeroModule\Handler;

use Laminas\Log\PsrLoggerAdapter;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\TransportInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use function sprintf;

final class LoggingFactory
{
    /**
     * @throws RuntimeException When mail config is enabled
     * but mail-message and/or mail-transport config is not a service instance of Message.
     */
    public function __invoke(ContainerInterface $container): Logging
    {
        /** @var array $config */
        $config = $container->get('config');
        /** @var PsrLoggerAdapter $errorHeroModuleLogger */
        $errorHeroModuleLogger = $container->get('ErrorHeroModuleLogger');

        $errorHeroModuleLocalConfig = $config['error-hero-module'];

        $mailConfig           = $errorHeroModuleLocalConfig['email-notification-settings'];
        $mailMessageService   = null;
        $mailMessageTransport = null;

        if ($mailConfig['enable'] === true) {
            $mailMessageService = $container->get($mailConfig['mail-message']);
            if (!$mailMessageService instanceof Message) {
                throw new RuntimeException(sprintf(
                    'You are enabling email log writer, your "mail-message" config must be instanceof %s',
                    Message::class
                ));
            }

            $mailMessageTransport = $container->get($mailConfig['mail-transport']);
            if (!$mailMessageTransport instanceof TransportInterface) {
                throw new RuntimeException(sprintf(
                    'You are enabling email log writer, your "mail-transport" config must implements %s',
                    TransportInterface::class
                ));
            }
        }

        $includeFilesToAttachments = $mailConfig['include-files-to-attachments'] ?? true;

        return new Logging(
            $errorHeroModuleLogger,
            $errorHeroModuleLocalConfig,
            $mailMessageService,
            $mailMessageTransport,
            $includeFilesToAttachments
        );
    }
}
