<?php

namespace App\MessageHandler;

use App\Message\AudioExtractionMessage;
use App\Service\VideoOrchestrationService;
use App\Service\SessionStatusService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AudioExtractionMessageHandler
{
    public function __construct(
        private VideoOrchestrationService $orchestrationService,
        private SessionStatusService $statusService,
        private LoggerInterface $logger
    ) {}

    public function __invoke(AudioExtractionMessage $message): void
    {
        $sessionId = $message->getSessionId();

        try {
            $this->logger->info('Processing audio extraction', [
                'session_id' => $sessionId
            ]);

            $this->statusService->updateStatus($sessionId, 'processing', 0);

            $result = $this->orchestrationService->processAudio(
                $message->getUrl(),
                $message->getAudioFormat(),
                $message->getAudioOptions()
            );

            $this->statusService->updateStatus($sessionId, 'completed', 100, $result);

            $this->logger->info('Audio extraction completed', [
                'session_id' => $sessionId
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Audio extraction failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            $this->statusService->updateStatus($sessionId, 'failed', 0, [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}