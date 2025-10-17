<?php

namespace App\Message;

class VideoDownloadMessage
{
    private string $sessionId;
    private string $url;
    private string $format;
    private string $type;
    private array $transcodeOptions;
    private int $timestamp;

    public function __construct(
        string $sessionId,
        string $url,
        string $format = 'best',
        string $type = 'video',
        array $transcodeOptions = []
    ) {
        $this->sessionId = $sessionId;
        $this->url = $url;
        $this->format = $format;
        $this->type = $type;
        $this->transcodeOptions = $transcodeOptions;
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

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTranscodeOptions(): array
    {
        return $this->transcodeOptions;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
}
