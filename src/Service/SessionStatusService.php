<?php

namespace App\Service;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Psr\Log\LoggerInterface;

class SessionStatusService
{
    private \Redis $redis;
    private LoggerInterface $logger;
    private int $ttl = 7200; // 2 heures

    public function __construct(
        string $redisUrl,
        LoggerInterface $logger
    ) {
        $this->redis = RedisAdapter::createConnection($redisUrl);
        $this->logger = $logger;
    }

    /**
     * Crée une nouvelle session
     */
    public function createSession(string $sessionId, array $metadata = []): void
    {
        $data = [
            'session_id' => $sessionId,
            'status' => 'pending',
            'progress' => 0,
            'created_at' => time(),
            'updated_at' => time(),
            'metadata' => $metadata,
            'result' => null,
        ];

        $this->redis->setex(
            "session:$sessionId",
            $this->ttl,
            json_encode($data)
        );

        $this->logger->info('Session created', ['session_id' => $sessionId]);
    }

    /**
     * Met à jour le statut d'une session
     */
    public function updateStatus(
        string $sessionId,
        string $status,
        int $progress = 0,
        ?array $result = null
    ): void {
        $currentData = $this->getSessionData($sessionId);

        if (!$currentData) {
            throw new \RuntimeException("Session not found: $sessionId");
        }

        $currentData['status'] = $status;
        $currentData['progress'] = $progress;
        $currentData['updated_at'] = time();

        if ($result !== null) {
            $currentData['result'] = $result;
        }

        $this->redis->setex(
            "session:$sessionId",
            $this->ttl,
            json_encode($currentData)
        );

        $this->logger->debug('Session status updated', [
            'session_id' => $sessionId,
            'status' => $status,
            'progress' => $progress
        ]);
    }

    /**
     * Récupère le statut d'une session
     */
    public function getStatus(string $sessionId): ?array
    {
        return $this->getSessionData($sessionId);
    }

    /**
     * Récupère les données complètes d'une session
     */
    private function getSessionData(string $sessionId): ?array
    {
        $data = $this->redis->get("session:$sessionId");

        if ($data === false) {
            return null;
        }

        return json_decode($data, true);
    }

    /**
     * Supprime une session
     */
    public function deleteSession(string $sessionId): void
    {
        $this->redis->del("session:$sessionId");
    }

    /**
     * Récupère toutes les sessions actives
     */
    public function getActiveSessions(): array
    {
        $keys = $this->redis->keys('session:*');
        $sessions = [];

        foreach ($keys as $key) {
            $data = $this->redis->get($key);
            if ($data) {
                $sessions[] = json_decode($data, true);
            }
        }

        return $sessions;
    }

    /**
     * Nettoie les sessions expirées
     */
    public function cleanupExpiredSessions(): int
    {
        $cleaned = 0;
        $keys = $this->redis->keys('session:*');

        foreach ($keys as $key) {
            $data = $this->redis->get($key);
            if ($data) {
                $sessionData = json_decode($data, true);
                $age = time() - $sessionData['created_at'];

                if ($age > $this->ttl) {
                    $this->redis->del($key);
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }
}