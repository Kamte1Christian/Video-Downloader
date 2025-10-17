<?php

namespace App\Controller;

use App\Message\VideoDownloadMessage;
use App\Message\AudioExtractionMessage;
use App\Message\HLSPackagingMessage;
use App\Message\ThumbnailGenerationMessage;
use App\Service\VideoDownloaderService;
use App\Service\VideoOrchestrationService;
use App\Service\SessionStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Messenger\MessageBusInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/video')]
class VideoDownloaderController extends AbstractController
{
    public function __construct(
        private VideoDownloaderService $downloaderService,
        private VideoOrchestrationService $orchestrationService,
        private SessionStatusService $statusService,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {}

    /**
     * Page d'accueil avec formulaire
     */
    #[Route('/', name: 'video_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('video_downloader/index.html.twig');
    }

    /**
     * Récupère les informations d'une vidéo
     */
    #[Route('/info', name: 'video_info', methods: ['POST'])]
    public function getVideoInfo(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $url = $data['url'] ?? null;

            if (!$url) {
                return $this->json(['error' => 'URL is required'], 400);
            }

            $info = $this->downloaderService->getVideoInfo($url);

            return $this->json([
                'success' => true,
                'data' => [
                    'title' => $info['title'] ?? 'Unknown',
                    'duration' => $info['duration'] ?? 0,
                    'thumbnail' => $info['thumbnail'] ?? null,
                    'formats' => $this->extractFormats($info),
                    'uploader' => $info['uploader'] ?? 'Unknown',
                    'upload_date' => $info['upload_date'] ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get video info', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Télécharge une vidéo (asynchrone avec queue)
     */
    #[Route('/download', name: 'video_download', methods: ['POST'])]
    public function downloadVideo(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $url = $data['url'] ?? null;
            $format = $data['format'] ?? 'best';
            $type = $data['type'] ?? 'video';
            $async = $data['async'] ?? true; // Par défaut asynchrone

            if (!$url) {
                return $this->json(['error' => 'URL is required'], 400);
            }

            $transcodeOptions = [];
            if (isset($data['transcode']) && $data['transcode']) {
                $transcodeOptions = [
                    'enabled' => true,
                    'video_bitrate' => $data['video_bitrate'] ?? '2000k',
                    'audio_bitrate' => $data['audio_bitrate'] ?? '128k',
                    'resolution' => $data['resolution'] ?? null,
                    'format' => $data['output_format'] ?? 'mp4',
                ];
            }

            if ($async) {
                // Mode asynchrone : envoyer à la queue
                $sessionId = uniqid('session_', true);
                
                // Créer la session dans Redis
                $this->statusService->createSession($sessionId, [
                    'url' => $url,
                    'format' => $format,
                    'type' => $type
                ]);

                // Envoyer le message à la queue
                $message = new VideoDownloadMessage(
                    $sessionId,
                    $url,
                    $format,
                    $type,
                    $transcodeOptions
                );
                $this->messageBus->dispatch($message);

                return $this->json([
                    'success' => true,
                    'session_id' => $sessionId,
                    'status' => 'pending',
                    'message' => 'Video download queued',
                    'status_url' => $this->generateUrl('video_status', ['sessionId' => $sessionId])
                ]);
            } else {
                // Mode synchrone : traiter immédiatement
                $result = $this->orchestrationService->processVideo(
                    $url,
                    $format,
                    $type,
                    $transcodeOptions
                );

                return $this->json([
                    'success' => true,
                    'session_id' => $result['session_id'],
                    'filename' => $result['transcoded_filename'] ?? $result['original_filename'],
                    'size' => $result['transcoded_size'] ?? $result['size'],
                    'download_url' => $this->generateUrl('video_download_file', [
                        'sessionId' => $result['session_id'],
                        'filename' => $result['transcoded_filename'] ?? $result['original_filename']
                    ])
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to download video', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Télécharge l'audio uniquement (asynchrone avec queue)
     */
    #[Route('/download-audio', name: 'video_download_audio', methods: ['POST'])]
    public function downloadAudio(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $url = $data['url'] ?? null;
            $format = $data['format'] ?? 'mp3';
            $async = $data['async'] ?? true;

            if (!$url) {
                return $this->json(['error' => 'URL is required'], 400);
            }

            $audioOptions = [
                'bitrate' => $data['bitrate'] ?? '192k',
                'sample_rate' => $data['sample_rate'] ?? '44100',
            ];

            if ($async) {
                $sessionId = uniqid('audio_', true);
                
                $this->statusService->createSession($sessionId, [
                    'url' => $url,
                    'format' => $format,
                    'type' => 'audio'
                ]);

                $message = new AudioExtractionMessage(
                    $sessionId,
                    $url,
                    $format,
                    $audioOptions
                );
                $this->messageBus->dispatch($message);

                return $this->json([
                    'success' => true,
                    'session_id' => $sessionId,
                    'status' => 'pending',
                    'message' => 'Audio extraction queued',
                    'status_url' => $this->generateUrl('video_status', ['sessionId' => $sessionId])
                ]);
            } else {
                $result = $this->orchestrationService->processAudio($url, $format, $audioOptions);

                return $this->json([
                    'success' => true,
                    'session_id' => $result['session_id'],
                    'filename' => $result['audio_filename'],
                    'size' => $result['size'],
                    'download_url' => $this->generateUrl('video_download_file', [
                        'sessionId' => $result['session_id'],
                        'filename' => $result['audio_filename']
                    ])
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to extract audio', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crée un package HLS (asynchrone avec queue)
     */
    #[Route('/hls', name: 'video_hls', methods: ['POST'])]
    public function createHLS(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $url = $data['url'] ?? null;
            $async = $data['async'] ?? true;

            if (!$url) {
                return $this->json(['error' => 'URL is required'], 400);
            }

            $hlsOptions = $data['hls_options'] ?? [];

            if ($async) {
                $sessionId = uniqid('hls_', true);
                
                $this->statusService->createSession($sessionId, [
                    'url' => $url,
                    'type' => 'hls'
                ]);

                $message = new HLSPackagingMessage($sessionId, $url, $hlsOptions);
                $this->messageBus->dispatch($message);

                return $this->json([
                    'success' => true,
                    'session_id' => $sessionId,
                    'status' => 'pending',
                    'message' => 'HLS packaging queued',
                    'status_url' => $this->generateUrl('video_status', ['sessionId' => $sessionId])
                ]);
            } else {
                $result = $this->orchestrationService->processVideoWithHLS($url, $hlsOptions);

                return $this->json([
                    'success' => true,
                    'session_id' => $result['session_id'],
                    'filename' => $result['zip_filename'],
                    'size' => $result['zip_size'],
                    'variants' => $result['variants'],
                    'download_url' => $this->generateUrl('video_download_file', [
                        'sessionId' => $result['session_id'],
                        'filename' => $result['zip_filename']
                    ])
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to create HLS package', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Génère des thumbnails (asynchrone avec queue)
     */
    #[Route('/thumbnails', name: 'video_thumbnails', methods: ['POST'])]
    public function generateThumbnails(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $url = $data['url'] ?? null;
            $count = $data['count'] ?? 5;
            $async = $data['async'] ?? true;

            if (!$url) {
                return $this->json(['error' => 'URL is required'], 400);
            }

            if ($async) {
                $sessionId = uniqid('thumb_', true);
                
                $this->statusService->createSession($sessionId, [
                    'url' => $url,
                    'type' => 'thumbnails',
                    'count' => $count
                ]);

                $message = new ThumbnailGenerationMessage($sessionId, $url, $count);
                $this->messageBus->dispatch($message);

                return $this->json([
                    'success' => true,
                    'session_id' => $sessionId,
                    'status' => 'pending',
                    'message' => 'Thumbnail generation queued',
                    'status_url' => $this->generateUrl('video_status', ['sessionId' => $sessionId])
                ]);
            } else {
                $result = $this->orchestrationService->generateThumbnails($url, $count);

                $thumbnailUrls = array_map(
                    fn($thumb) => $this->generateUrl('video_download_file', [
                        'sessionId' => $result['session_id'],
                        'filename' => $thumb
                    ]),
                    $result['thumbnails']
                );

                return $this->json([
                    'success' => true,
                    'session_id' => $result['session_id'],
                    'thumbnails' => $thumbnailUrls,
                    'count' => count($result['thumbnails'])
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate thumbnails', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifie le statut d'une session
     */
    #[Route('/status/{sessionId}', name: 'video_status', methods: ['GET'])]
    public function checkStatus(string $sessionId): JsonResponse
    {
        try {
            $status = $this->statusService->getStatus($sessionId);

            if (!$status) {
                return $this->json([
                    'success' => false,
                    'error' => 'Session not found'
                ], 404);
            }

            $response = [
                'success' => true,
                'session_id' => $sessionId,
                'status' => $status['status'],
                'progress' => $status['progress'],
                'created_at' => $status['created_at'],
                'updated_at' => $status['updated_at'],
            ];

            // Si terminé, ajouter les infos de téléchargement
            if ($status['status'] === 'completed' && isset($status['result'])) {
                $result = $status['result'];
                
                if (isset($result['transcoded_filename'])) {
                    $filename = $result['transcoded_filename'];
                } elseif (isset($result['audio_filename'])) {
                    $filename = $result['audio_filename'];
                } elseif (isset($result['zip_filename'])) {
                    $filename = $result['zip_filename'];
                } elseif (isset($result['original_filename'])) {
                    $filename = $result['original_filename'];
                } else {
                    $filename = null;
                }

                if ($filename) {
                    $response['download_url'] = $this->generateUrl('video_download_file', [
                        'sessionId' => $sessionId,
                        'filename' => $filename
                    ]);
                    $response['filename'] = $filename;
                    $response['size'] = $result['size'] ?? $result['transcoded_size'] ?? $result['zip_size'] ?? 0;
                }

                // Pour les thumbnails
                if (isset($result['thumbnails'])) {
                    $response['thumbnails'] = array_map(
                        fn($thumb) => $this->generateUrl('video_download_file', [
                            'sessionId' => $sessionId,
                            'filename' => $thumb
                        ]),
                        $result['thumbnails']
                    );
                }
            }

            // Si échoué, ajouter l'erreur
            if ($status['status'] === 'failed' && isset($status['result']['error'])) {
                $response['error'] = $status['result']['error'];
            }

            return $this->json($response);

        } catch (\Exception $e) {
            $this->logger->error('Failed to check status', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Télécharge un fichier depuis une session
     */
    #[Route('/download/{sessionId}/{filename}', name: 'video_download_file', methods: ['GET'])]
    public function downloadFile(string $sessionId, string $filename): Response
    {
        try {
            $filePath = $this->orchestrationService->getFileFromSession($sessionId, $filename);

            if (!$filePath) {
                return $this->json(['error' => 'File not found'], 404);
            }

            $response = new BinaryFileResponse($filePath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            );

            // Nettoyer la session après le téléchargement
            $response->deleteFileAfterSend(false);
            
            // Ajouter un callback pour nettoyer après l'envoi
            $orchestrationService = $this->orchestrationService;
            $statusService = $this->statusService;
            register_shutdown_function(function() use ($sessionId, $orchestrationService, $statusService) {
                $orchestrationService->cleanupSession($sessionId);
                $statusService->deleteSession($sessionId);
            });

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Failed to download file', [
                'session_id' => $sessionId,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'File download failed'
            ], 500);
        }
    }

    /**
     * Liste toutes les sessions actives
     */
    #[Route('/sessions', name: 'video_sessions', methods: ['GET'])]
    public function listSessions(): JsonResponse
    {
        try {
            $sessions = $this->statusService->getActiveSessions();

            return $this->json([
                'success' => true,
                'count' => count($sessions),
                'sessions' => $sessions
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to list sessions', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Nettoie les anciennes sessions
     */
    #[Route('/cleanup', name: 'video_cleanup', methods: ['POST'])]
    public function cleanup(): JsonResponse
    {
        try {
            $cleanedFiles = $this->orchestrationService->cleanupOldSessions();
            $cleanedSessions = $this->statusService->cleanupExpiredSessions();

            return $this->json([
                'success' => true,
                'cleaned_files' => $cleanedFiles,
                'cleaned_sessions' => $cleanedSessions,
                'message' => "Cleaned $cleanedFiles file sessions and $cleanedSessions Redis sessions"
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup sessions', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annule une session en cours
     */
    #[Route('/cancel/{sessionId}', name: 'video_cancel', methods: ['POST'])]
    public function cancelSession(string $sessionId): JsonResponse
    {
        try {
            $status = $this->statusService->getStatus($sessionId);

            if (!$status) {
                return $this->json([
                    'success' => false,
                    'error' => 'Session not found'
                ], 404);
            }

            // Marquer comme annulée
            $this->statusService->updateStatus($sessionId, 'cancelled', 0);

            // Nettoyer les fichiers
            $this->orchestrationService->cleanupSession($sessionId);

            return $this->json([
                'success' => true,
                'message' => 'Session cancelled',
                'session_id' => $sessionId
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extrait les formats disponibles
     */
    private function extractFormats(array $info): array
    {
        $formats = [];
        
        if (isset($info['formats']) && is_array($info['formats'])) {
            foreach ($info['formats'] as $format) {
                $formats[] = [
                    'format_id' => $format['format_id'] ?? 'unknown',
                    'ext' => $format['ext'] ?? 'unknown',
                    'quality' => $format['format_note'] ?? $format['quality'] ?? 'unknown',
                    'filesize' => $format['filesize'] ?? 0,
                    'vcodec' => $format['vcodec'] ?? 'none',
                    'acodec' => $format['acodec'] ?? 'none',
                ];
            }
        }

        return $formats;
    }
}
