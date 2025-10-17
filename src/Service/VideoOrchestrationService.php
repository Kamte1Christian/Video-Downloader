<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;

/**
 * Service d'orchestration pour gérer le flux complet sans base de données
 * Téléchargement -> Transcodage -> Packaging HLS
 * Les fichiers finaux sont envoyés directement au navigateur
 */
class VideoOrchestrationService
{
    private VideoDownloaderService $downloaderService;
    private FFmpegTranscodeService $transcodeService;
    private HLSPackagingService $hlsService;
    private LoggerInterface $logger;
    private Filesystem $filesystem;
    private string $sessionPath;

    public function __construct(
        VideoDownloaderService $downloaderService,
        FFmpegTranscodeService $transcodeService,
        HLSPackagingService $hlsService,
        LoggerInterface $logger,
        string $projectDir
    ) {
        $this->downloaderService = $downloaderService;
        $this->transcodeService = $transcodeService;
        $this->hlsService = $hlsService;
        $this->logger = $logger;
        $this->filesystem = new Filesystem();
        $this->sessionPath = $projectDir . '/var/sessions';
        
        if (!$this->filesystem->exists($this->sessionPath)) {
            $this->filesystem->mkdir($this->sessionPath);
        }
    }

    /**
     * Traitement complet : téléchargement + transcodage optionnel
     */
    public function processVideo(
        string $url,
        string $format = 'best',
        string $type = 'video',
        array $transcodeOptions = []
    ): array {
        $sessionId = uniqid('session_', true);
        $sessionDir = $this->sessionPath . '/' . $sessionId;
        $this->filesystem->mkdir($sessionDir);

        $this->logger->info('Starting video processing', [
            'session_id' => $sessionId,
            'url' => $url,
            'type' => $type
        ]);

        try {
            // Étape 1: Téléchargement
            $this->logger->info('Downloading video...');
            $downloadResult = $this->downloaderService->downloadToDirectory(
                $url,
                $sessionDir,
                $format,
                $type
            );

            $result = [
                'session_id' => $sessionId,
                'original_file' => $downloadResult['filepath'],
                'original_filename' => $downloadResult['original_filename'],
                'size' => $downloadResult['size'],
                'extension' => $downloadResult['extension'],
            ];

            // Étape 2: Transcodage (si demandé)
            if (!empty($transcodeOptions) && $transcodeOptions['enabled'] ?? false) {
                $this->logger->info('Transcoding video...');
                
                $transcodedFile = $this->transcodeService->transcode(
                    $downloadResult['filepath'],
                    $transcodeOptions
                );

                $result['transcoded_file'] = $transcodedFile;
                $result['transcoded_filename'] = basename($transcodedFile);
                $result['transcoded_size'] = filesize($transcodedFile);
            }

            $this->logger->info('Video processing completed', $result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Video processing failed', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
            
            // Nettoyer en cas d'erreur
            if ($this->filesystem->exists($sessionDir)) {
                $this->filesystem->remove($sessionDir);
            }
            
            throw $e;
        }
    }

    /**
     * Traitement avec packaging HLS
     */
    public function processVideoWithHLS(
        string $url,
        array $hlsOptions = []
    ): array {
        $sessionId = uniqid('hls_', true);
        $sessionDir = $this->sessionPath . '/' . $sessionId;
        $this->filesystem->mkdir($sessionDir);

        $this->logger->info('Starting HLS processing', [
            'session_id' => $sessionId,
            'url' => $url
        ]);

        try {
            // Étape 1: Téléchargement
            $downloadResult = $this->downloaderService->downloadToDirectory(
                $url,
                $sessionDir,
                'best',
                'video'
            );

            // Étape 2: Créer le package HLS
            $this->logger->info('Creating HLS package...');
            $hlsResult = $this->hlsService->createHLSPackageInDirectory(
                $downloadResult['filepath'],
                $sessionDir,
                $hlsOptions
            );

            // Étape 3: Créer une archive ZIP avec tout le package HLS
            $zipFile = $sessionDir . '/hls_package.zip';
            $this->createHLSZipArchive($hlsResult['output_dir'], $zipFile);

            return [
                'session_id' => $sessionId,
                'zip_file' => $zipFile,
                'zip_filename' => 'hls_package.zip',
                'zip_size' => filesize($zipFile),
                'master_playlist' => $hlsResult['master_playlist'],
                'variants' => $hlsResult['variants'],
            ];

        } catch (\Exception $e) {
            $this->logger->error('HLS processing failed', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
            
            if ($this->filesystem->exists($sessionDir)) {
                $this->filesystem->remove($sessionDir);
            }
            
            throw $e;
        }
    }

    /**
     * Traitement audio uniquement
     */
    public function processAudio(
        string $url,
        string $audioFormat = 'mp3',
        array $audioOptions = []
    ): array {
        $sessionId = uniqid('audio_', true);
        $sessionDir = $this->sessionPath . '/' . $sessionId;
        $this->filesystem->mkdir($sessionDir);

        try {
            // Télécharger la vidéo
            $downloadResult = $this->downloaderService->downloadToDirectory(
                $url,
                $sessionDir,
                'best',
                'video'
            );

            // Extraire l'audio
            $this->logger->info('Extracting audio...');
            $audioFile = $this->transcodeService->extractAudioToDirectory(
                $downloadResult['filepath'],
                $sessionDir,
                $audioFormat,
                $audioOptions
            );

            return [
                'session_id' => $sessionId,
                'audio_file' => $audioFile,
                'audio_filename' => basename($audioFile),
                'size' => filesize($audioFile),
            ];

        } catch (\Exception $e) {
            if ($this->filesystem->exists($sessionDir)) {
                $this->filesystem->remove($sessionDir);
            }
            throw $e;
        }
    }

    /**
     * Récupère un fichier depuis une session
     */
    public function getFileFromSession(string $sessionId, string $filename): ?string
    {
        $filePath = $this->sessionPath . '/' . $sessionId . '/' . $filename;
        
        if ($this->filesystem->exists($filePath)) {
            return $filePath;
        }

        return null;
    }

    /**
     * Nettoie une session après téléchargement
     */
    public function cleanupSession(string $sessionId): void
    {
        $sessionDir = $this->sessionPath . '/' . $sessionId;
        
        if ($this->filesystem->exists($sessionDir)) {
            $this->filesystem->remove($sessionDir);
            $this->logger->info('Session cleaned', ['session_id' => $sessionId]);
        }
    }

    /**
     * Nettoie les sessions expirées
     */
    public function cleanupOldSessions(int $maxAge = 3600): int
    {
        $cleaned = 0;
        $sessions = glob($this->sessionPath . '/*', GLOB_ONLYDIR);

        foreach ($sessions as $session) {
            if ((time() - filemtime($session)) > $maxAge) {
                $this->filesystem->remove($session);
                $cleaned++;
            }
        }

        $this->logger->info("Cleaned $cleaned old sessions");
        return $cleaned;
    }

    /**
     * Crée une archive ZIP du package HLS
     */
    private function createHLSZipArchive(string $sourceDir, string $zipFile): void
    {
        $zip = new \ZipArchive();
        
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create ZIP archive');
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    /**
     * Génère des thumbnails et les retourne
     */
    public function generateThumbnails(string $url, int $count = 5): array
    {
        $sessionId = uniqid('thumb_', true);
        $sessionDir = $this->sessionPath . '/' . $sessionId;
        $this->filesystem->mkdir($sessionDir);

        try {
            // Télécharger la vidéo
            $downloadResult = $this->downloaderService->downloadToDirectory(
                $url,
                $sessionDir,
                'best',
                'video'
            );

            // Générer les thumbnails
            $thumbnails = $this->transcodeService->generateThumbnailsInDirectory(
                $downloadResult['filepath'],
                $sessionDir,
                $count
            );

            return [
                'session_id' => $sessionId,
                'thumbnails' => array_map('basename', $thumbnails),
            ];

        } catch (\Exception $e) {
            if ($this->filesystem->exists($sessionDir)) {
                $this->filesystem->remove($sessionDir);
            }
            throw $e;
        }
    }

    public function getFilePath(string $sessionId, string $filename): ?string
    {
        $filePath = $this->sessionPath . '/' . $sessionId . '/' . $filename;
        
        if ($this->filesystem->exists($filePath)) {
            return $filePath;
        }

        return null;
    }
}