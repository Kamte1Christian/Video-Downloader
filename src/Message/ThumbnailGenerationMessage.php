<?php

namespace App\Message;

class ThumbnailGenerationMessage
{
    private string $sessionId;
    private string $url;
    private int $count;
    private int $timestamp;

    public function __construct(
        string $sessionId,
        string $url,
        int $count = 5
    ) {
        $this->sessionId = $sessionId;
        $this->url = $url;
        $this->count = $count;
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

    public function getCount(): int
    {
        return $this->count;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
}
