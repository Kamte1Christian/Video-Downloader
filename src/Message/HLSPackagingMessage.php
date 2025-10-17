<?php

namespace App\Message;

class HLSPackagingMessage
{
    private string $sessionId;
    private string $url;
    private array $hlsOptions;
    private int $timestamp;

    public function __construct(
        string $sessionId,
        string $url,
        array $hlsOptions = []
    ) {
        $this->sessionId = $sessionId;
        $this->url = $url;
        $this->hlsOptions = $hlsOptions;
        $this->timestamp = time();
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getHlsOptions(): array
    {
        return $this->hlsOptions;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
}