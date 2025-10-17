<?php

namespace App\Message;

class AudioExtractionMessage
{
    private string $sessionId;
    private string $url;
    private string $audioFormat;
    private array $audioOptions;
    private int $timestamp;

    public function __construct(
        string $sessionId,
        string $url,
        string $audioFormat = 'mp3',
        array $audioOptions = []
    ) {
        $this->sessionId = $sessionId;
        $this->url = $url;
        $this->audioFormat = $audioFormat;
        $this->audioOptions = $audioOptions;
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

    public function getAudioFormat(): string
    {
        return $this->audioFormat;
    }

    public function getAudioOptions(): array
    {
        return $this->audioOptions;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
}