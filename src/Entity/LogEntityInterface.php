<?php

declare(strict_types=1);

namespace ErrorHeroModule\Entity;

use DateTime;

interface LogEntityInterface
{
    public function getId(): ?int;

    public function setId(?int $id): self;

    public function getDate(): DateTime;

    public function setDate(DateTime $date): self;

    public function getPriority(): string;

    public function setPriority(string $priority): self;

    public function getErrorMessage(): string;

    public function setErrorMessage(string $errorMessage): self;

    public function getUrl(): ?string;

    public function setUrl(?string $url): self;

    public function getFile(): string;

    public function setFile(string $file): self;

    public function getLine(): ?int;

    public function setLine(?int $line): self;

    public function getErrorType(): string;

    public function setErrorType(string $errorType): self;

    public function getTrace(): ?string;

    public function setTrace(?string $trace): self;

    public function getRequestData(): ?array;

    public function setRequestData(?array $requestData): self;
}