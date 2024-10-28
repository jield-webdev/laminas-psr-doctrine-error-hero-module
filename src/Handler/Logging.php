<?php

declare(strict_types=1);

namespace ErrorHeroModule\Handler;

use ErrorException;
use ErrorHeroModule\Handler\Formatter\Json;
use ErrorHeroModule\Handler\Writer\DoctrineWriter;
use ErrorHeroModule\Handler\Writer\Mail;
use ErrorHeroModule\HeroConstant;
use Exception;
use InvalidArgumentException;
use Laminas\Diactoros\Stream;
use Laminas\Http\Header\Cookie;
use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Http\PhpEnvironment\Request as HttpRequest;
use Laminas\Log\Logger;
use Laminas\Log\PsrLoggerAdapter;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\Stdlib\ParametersInterface;
use Laminas\Stdlib\RequestInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Throwable;
use Webmozart\Assert\Assert;
use function basename;
use function get_current_user;
use function getcwd;
use function implode;
use function php_uname;
use function str_replace;
use const PHP_BINARY;
use const PHP_EOL;

final class Logging
{
    private array $emailReceivers = [];

    private readonly string $emailSender;

    /** @var string */
    private const string PRIORITY = 'priority';

    /** @var string */
    private const string ERROR_TYPE = 'errorType';

    /** @var string */
    private const string ERROR_FILE = 'errorFile';

    /** @var string */
    private const string ERROR_LINE = 'errorLine';

    /** @var string */
    private const string TRACE = 'trace';

    /** @var string */
    private const string ERROR_MESSAGE = 'errorMessage';

    /** @var string */
    private const string SERVER_URL = 'server_url';

    public function __construct(
        private readonly PsrLoggerAdapter    $psrLoggerAdapter,
        array                                $errorHeroModuleLocalConfig,
        private readonly ?Message            $message = null,
        private readonly ?TransportInterface $mailMessageTransport = null,
        private readonly bool                $includeFilesToAttachments = true
    )
    {
        $this->emailReceivers = $errorHeroModuleLocalConfig['email-notification-settings']['email-to-send'];
        $this->emailSender    = $errorHeroModuleLocalConfig['email-notification-settings']['email-from'];
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestData(?RequestInterface $request): array
    {
        if (!$request instanceof HttpRequest) {
            return [];
        }

        Assert::isInstanceOf(value: $request, class: HttpRequest::class);

        /** @var ParametersInterface $query */
        $query = $request->getQuery();
        /** @var ParametersInterface $post */
        $post = $request->getPost();
        /** @var ParametersInterface $files */
        $files = $request->getFiles();

        $content = $request->getContent();

        if ($content instanceof Stream) {
            $content = (string)$content;
        }

        $queryData     = $query->toArray();
        $requestMethod = $request->getMethod();
        $bodyData      = $post->toArray();
        $rawData       = str_replace(search: PHP_EOL, replace: '', subject: $content);
        $filesData     = $this->includeFilesToAttachments
            ? $files->toArray()
            : [];

        $cookie     = $request->getCookie();
        $cookieData = $cookie instanceof Cookie
            ? $cookie->getArrayCopy()
            : [];
        $ipAddress  = (new RemoteAddress())->getIpAddress();

        return [
            'request_method' => $requestMethod,
            'query_data'     => $queryData,
            'body_data'      => $bodyData,
            'raw_data'       => $rawData,
            'files_data'     => $filesData,
            'cookie_data'    => $cookieData,
            'ip_address'     => $ipAddress,
        ];
    }

    /**
     * @return array{
     *      priority: int,
     *      errorType: string,
     *      errorFile: string,
     *      errorLine: int,
     *      trace: string,
     *      errorMessage: string
     *  }
     */
    private function collectErrorExceptionData(Throwable $throwable): array
    {
        if (
            $throwable instanceof ErrorException && null !== Logging::getPsrPrioryFromSeverity(severity: $throwable->getSeverity())
        ) {
            //We need to use the new PSR7 severity level, these can be fetched
            //From the psrPriorityMap in this class


            $priority  = Logging::getPsrPrioryFromSeverity(severity: $throwable->getSeverity());
            $errorType = HeroConstant::ERROR_TYPE[$throwable->getSeverity()];
        } else {
            $priority  = LogLevel::ERROR;
            $errorType = $throwable::class;
        }

        $errorFile     = $throwable->getFile();
        $errorLine     = $throwable->getLine();
        $traceAsString = $throwable->getTraceAsString();
        $errorMessage  = $throwable->getMessage();

        return [
            self::PRIORITY      => $priority,
            self::ERROR_TYPE    => $errorType,
            self::ERROR_FILE    => $errorFile,
            self::ERROR_LINE    => $errorLine,
            self::TRACE         => $traceAsString,
            self::ERROR_MESSAGE => $errorMessage,
        ];
    }

    public static function getPsrPrioryFromSeverity(int $severity, bool $fromLegacy = true): string
    {
        if ($fromLegacy) {
            $severity = Logger::$errorPriorityMap[$severity];
        }

        //Flip the array above and convert into a match statement
        return match ($severity) {
            Logger::EMERG  => LogLevel::EMERGENCY,
            Logger::ALERT  => LogLevel::ALERT,
            Logger::CRIT   => LogLevel::CRITICAL,
            Logger::ERR    => LogLevel::ERROR,
            Logger::WARN   => LogLevel::WARNING,
            Logger::NOTICE => LogLevel::NOTICE,
            Logger::INFO   => LogLevel::INFO,
            Logger::DEBUG  => LogLevel::DEBUG,
            default        => throw new InvalidArgumentException(message: 'Invalid severity level: ' . $severity),
        };
    }

    /**
     * @return array{
     *      server_url: string,
     *      url: string,
     *      file: string,
     *      line: int,
     *      error_type: string,
     *      trace: string,
     *      request_data: array<string, mixed>
     * }
     */
    private function collectErrorExceptionExtraData(array $collectedExceptionData, ?RequestInterface $request): array
    {
        if (!$request instanceof HttpRequest) {
            $argv      = $_SERVER['argv'] ?? [];
            $serverUrl = php_uname(mode: 'n');
            $url       = $serverUrl . ':' . basename(path: (string)getcwd())
                . ' ' . get_current_user()
                . '$ ' . PHP_BINARY;

            $params = implode(separator: ' ', array: $argv);
            $url    .= $params;
        } else {
            $http      = $request->getUri();
            $serverUrl = $http->getScheme() . '://' . $http->getHost();
            $url       = $http->toString();
        }

        return [
            self::SERVER_URL => $serverUrl,
            'url'            => $url,
            'file'           => $collectedExceptionData[self::ERROR_FILE],
            'line'           => $collectedExceptionData[self::ERROR_LINE],
            'error_type'     => $collectedExceptionData[self::ERROR_TYPE],
            self::TRACE      => $collectedExceptionData[self::TRACE],
            'request_data'   => $this->getRequestData(request: $request),
        ];
    }

    /**
     * @throws RuntimeException When cannot connect to DB in the first place.
     */
    private function isExists(
        string $errorFile,
        int    $errorLine,
        string $errorMessage,
        string $url,
        string $errorType
    ): bool
    {
        /** @var Logger $logger */
        $logger = $this->psrLoggerAdapter->getLogger();

        $writers = $logger->getWriters()->toArray();
        foreach ($writers as $writer) {
            if ($writer instanceof DoctrineWriter) {

                try {
                    if ($writer->isExists(errorFile: $errorFile, errorLine: $errorLine, errorMessage: $errorMessage, url: $url, errorType: $errorType)) {
                        return true;
                    }
                } catch (Exception $e) {
                    throw new ${!${''} = $e::class}($e->getMessage());
                }

            }
        }

        return false;
    }

    private function sendMail(string $priority, string $errorMessage, array $extra, string $subject): void
    {
        if (!$this->message instanceof Message || !$this->mailMessageTransport instanceof TransportInterface) {
            return;
        }

        if ($this->emailReceivers === []) {
            return;
        }

        $this->message->setFrom(emailOrAddressList: $this->emailSender);
        $this->message->setSubject(subject: $subject);

        $filesData = $extra['request_data']['files_data'] ?? [];
        foreach ($this->emailReceivers as $emailReceiver) {
            $this->message->setTo(emailOrAddressList: $emailReceiver);
            $writer = new Mail(
                mailMessage: $this->message,
                transport: $this->mailMessageTransport,
                filesData: $filesData
            );
            $writer->setFormatter(formatter: new Json());

            (new Logger())->addWriter(writer: $writer)
                ->log(priority: $priority, message: $errorMessage, extra: $extra);
        }
    }

    public function handleErrorException(Throwable $throwable, ?RequestInterface $request = null): void
    {
        $collectedExceptionData = $this->collectErrorExceptionData(throwable: $throwable);
        /**
         * @var array{url: string, server_url: string, mixed} $extra
         */
        $extra     = $this->collectErrorExceptionExtraData(collectedExceptionData: $collectedExceptionData, request: $request);
        $serverUrl = $extra[self::SERVER_URL];

        try {
            if (
                $this->isExists(
                    errorFile: $collectedExceptionData[self::ERROR_FILE],
                    errorLine: $collectedExceptionData[self::ERROR_LINE],
                    errorMessage: $collectedExceptionData[self::ERROR_MESSAGE],
                    url: $extra['url'],
                    errorType: $collectedExceptionData[self::ERROR_TYPE]
                )
            ) {
                return;
            }

            unset($extra[self::SERVER_URL]);

            $this->psrLoggerAdapter->log(
                level: $collectedExceptionData[self::PRIORITY],
                message: $collectedExceptionData[self::ERROR_MESSAGE],
                context: $extra
            );
        } catch (RuntimeException $runtimeException) {
            $collectedExceptionData = $this->collectErrorExceptionData(throwable: $runtimeException);
            $extra                  = $this->collectErrorExceptionExtraData(collectedExceptionData: $collectedExceptionData, request: $request);
            unset($extra[self::SERVER_URL]);
        }

        $this->sendMail(
            priority: $collectedExceptionData[self::PRIORITY],
            errorMessage: $collectedExceptionData[self::ERROR_MESSAGE],
            extra: $extra,
            subject: '[' . $serverUrl . '] ' . $collectedExceptionData[self::ERROR_TYPE] . ' has thrown'
        );
    }
}
