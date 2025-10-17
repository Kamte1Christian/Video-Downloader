<?php

namespace App\MessageHandler;

use App\Message\ThumbnailGenerationMessage;
use App\Service\VideoOrchestrationService;
use App\Service\SessionStatusService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ThumbnailGenerationMessageHandler
{
    public function __construct(
        private VideoOrchestrationService $orchestrationService,
        private SessionStatusService $statusService,
        private LoggerInterface $logger
    ) {}

    public function __invoke(ThumbnailGenerationMessage $message): void
    {
        $sessionId = $message->getSessionId();

        try {
            $this->statusService->updateStatus($sessionId, 'processing', 0);

            $result = $this->orchestrationService->generateThumbnails(
                $message->getUrl(),
                $message->getCount()
            );

            $this->statusService->updateStatus($sessionId, 'completed', 100, $result);

        } catch (\Exception $e) {
            $this->logger->error('Thumbnail generation failed', [
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
