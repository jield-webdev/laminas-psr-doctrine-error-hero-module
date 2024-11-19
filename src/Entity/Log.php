<?php

declare(strict_types=1);

namespace ErrorHeroModule\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Psr\Log\LogLevel;

#[ORM\Table]
#[ORM\Entity]
class Log implements LogEntityInterface
{
    #[ORM\Column(type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private DateTime $date;

    #[ORM\Column(nullable: false)]
    private string $priority = LogLevel::ERROR;

    #[ORM\Column(type: 'text', nullable: false)]
    private string $errorMessage = '';

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 2000, nullable: false)]
    private string $file = '';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $line = null;

    #[ORM\Column(nullable: false)]
    private string $errorType = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $trace = null;

    #[ORM\Column(name: 'request_data', type: 'json', nullable: true)]
    private ?array $requestData = null;

    public function __construct()
    {
        $this->date = new DateTime();
    }

    public function __toString(): string
    {
        return $this->getErrorMessage();
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(string $errorMessage): Log
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getParsedMessage(): string
    {
        //The error message is of format: {class} was handled successfully (acknowledging to transport).
        //We need to extract the wildcards wrapped between {} with the data from the request data array
        return preg_replace_callback(
            pattern: '/\{([^}]+)}/',
            callback: function ($matches) {
                return $this->requestData[$matches[1]] ?? '';
            },
            subject: $this->errorMessage
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Log
    {
        $this->id = $id;
        return $this;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function setDate(DateTime $date): Log
    {
        $this->date = $date;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): Log
    {
        $this->priority = $priority;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): Log
    {
        $this->url = $url;
        return $this;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): Log
    {
        $this->file = $file;
        return $this;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function setLine(?int $line): Log
    {
        $this->line = $line;
        return $this;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function setErrorType(string $errorType): Log
    {
        $this->errorType = $errorType;
        return $this;
    }

    public function getTrace(): ?string
    {
        return $this->trace;
    }

    public function setTrace(?string $trace): Log
    {
        $this->trace = $trace;
        return $this;
    }

    public function getRequestData(): ?array
    {
        return $this->requestData;
    }

    public function setRequestData(?array $requestData): Log
    {
        $this->requestData = $requestData;
        return $this;
    }
}
