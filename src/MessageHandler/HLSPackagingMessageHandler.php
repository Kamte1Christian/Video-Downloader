<?php

namespace App\MessageHandler;

use App\Message\HLSPackagingMessage;
use App\Service\VideoOrchestrationService;
use App\Service\SessionStatusService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class HLSPackagingMessageHandler
{
    public function __construct(
        private VideoOrchestrationService $orchestrationService,
        private SessionStatusService $statusService,
        private LoggerInterface $logger
    ) {}

    public function __invoke(HLSPackagingMessage $message): void
    {
        $sessionId = $message->getSessionId();

        try {
            $this->logger->info('Processing HLS packaging', [
                'session_id' => $sessionId
            ]);

            $this->statusService->updateStatus($sessionId, 'processing', 0);

            $result = $this->orchestrationService->processVideoWithHLS(
                $message->getUrl(),
                $message->getHlsOptions()
            );

            $this->statusService->updateStatus($sessionId, 'completed', 100, $result);

        } catch (\Exception $e) {
            $this->logger->error('HLS packaging failed', [
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
