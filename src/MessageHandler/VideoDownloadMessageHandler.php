<?php

namespace App\MessageHandler;

use App\Message\VideoDownloadMessage;
use App\Service\VideoOrchestrationService;
use App\Service\SessionStatusService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class VideoDownloadMessageHandler
{
    public function __construct(
        private VideoOrchestrationService $orchestrationService,
        private SessionStatusService $statusService,
        private LoggerInterface $logger
    ) {}

    public function __invoke(VideoDownloadMessage $message): void
    {
        $sessionId = $message->getSessionId();

        try {
            $this->logger->info('Processing video download', [
                'session_id' => $sessionId,
                'url' => $message->getUrl()
            ]);

            // Mettre à jour le statut
            $this->statusService->updateStatus($sessionId, 'processing', 0);

            // Traiter la vidéo
            $result = $this->orchestrationService->processVideo(
                $message->getUrl(),
                $message->getFormat(),
                $message->getType(),
                $message->getTranscodeOptions()
            );

            // Marquer comme complété
            $this->statusService->updateStatus($sessionId, 'completed', 100, $result);

            $this->logger->info('Video download completed', [
                'session_id' => $sessionId
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Video download failed', [
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
