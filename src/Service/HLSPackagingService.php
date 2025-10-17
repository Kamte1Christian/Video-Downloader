<?php

namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;

/**
 * Service de packaging HLS pour le streaming adaptatif
 */
class HLSPackagingService
{
    private string $hlsPath;
    private Filesystem $filesystem;
    private LoggerInterface $logger;
    private FFmpegTranscodeService $transcodeService;

    // Configuration HLS par défaut
    private const DEFAULT_CONFIG = [
        'segment_duration' => 6,
        'list_size' => 0, // 0 = tous les segments
        'delete_threshold' => 1,
    ];

    // Variantes de qualité prédéfinies
    private const QUALITY_VARIANTS = [
        [
            'name' => '1080p',
            'width' => 1920,
            'height' => 1080,
            'video_bitrate' => '5000k',
            'audio_bitrate' => '192k',
            'bandwidth' => 5200000,
        ],
        [
            'name' => '720p',
            'width' => 1280,
            'height' => 720,
            'video_bitrate' => '3000k',
            'audio_bitrate' => '128k',
            'bandwidth' => 3100000,
        ],
        [
            'name' => '480p',
            'width' => 854,
            'height' => 480,
            'video_bitrate' => '1500k',
            'audio_bitrate' => '128k',
            'bandwidth' => 1600000,
        ],
        [
            'name' => '360p',
            'width' => 640,
            'height' => 360,
            'video_bitrate' => '800k',
            'audio_bitrate' => '96k',
            'bandwidth' => 900000,
        ],
    ];

    public function __construct(
        string $projectDir,
        FFmpegTranscodeService $transcodeService,
        LoggerInterface $logger
    ) {
        $this->hlsPath = $projectDir . '/public/hls';
        $this->transcodeService = $transcodeService;
        $this->logger = $logger;
        $this->filesystem = new Filesystem();
        
        if (!$this->filesystem->exists($this->hlsPath)) {
            $this->filesystem->mkdir($this->hlsPath);
        }
    }

    /**
     * Crée un package HLS complet avec plusieurs variantes
     */
    public function createHLSPackage(string $inputFile, array $options = []): array
    {
        $jobId = uniqid('hls_', true);
        $outputDir = $this->hlsPath . '/' . $jobId;
        $this->filesystem->mkdir($outputDir);

        $this->logger->info('Starting HLS packaging', [
            'input' => $inputFile,
            'output_dir' => $outputDir,
            'job_id' => $jobId
        ]);

        // Déterminer les variantes à créer
        $variants = $options['variants'] ?? self::QUALITY_VARIANTS;
        
        // Filtrer les variantes selon la résolution source
        $sourceInfo = $this->transcodeService->getVideoInfo($inputFile);
        $variants = $this->filterVariantsBySourceResolution($variants, $sourceInfo);

        $variantPlaylists = [];

        // Créer chaque variante
        foreach ($variants as $variant) {
            $variantDir = $outputDir . '/' . $variant['name'];
            $this->filesystem->mkdir($variantDir);

            $playlistFile = $this->createVariantHLS(
                $inputFile,
                $variantDir,
                $variant,
                $options
            );

            $variantPlaylists[] = [
                'name' => $variant['name'],
                'playlist' => $playlistFile,
                'bandwidth' => $variant['bandwidth'],
                'resolution' => $variant['width'] . 'x' . $variant['height'],
            ];
        }

        // Créer la master playlist
        $masterPlaylist = $this->createMasterPlaylist($outputDir, $variantPlaylists);

        $result = [
            'job_id' => $jobId,
            'master_playlist' => $masterPlaylist,
            'variants' => $variantPlaylists,
            'output_dir' => $outputDir,
        ];

        $this->logger->info('HLS packaging completed', $result);

        return $result;
    }

    /**
     * Crée une variante HLS spécifique
     */
    private function createVariantHLS(
        string $inputFile,
        string $outputDir,
        array $variant,
        array $options
    ): string {
        $playlistName = 'playlist.m3u8';
        $segmentPattern = 'segment_%03d.ts';
        
        $config = array_merge(self::DEFAULT_CONFIG, $options);

        $command = [
            'ffmpeg',
            '-i', $inputFile,
            
            // Encodage vidéo
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-profile:v', 'main',
            '-level', '4.0',
            '-b:v', $variant['video_bitrate'],
            '-maxrate', $variant['video_bitrate'],
            '-bufsize', $this->calculateBufferSize($variant['video_bitrate']),
            '-vf', "scale={$variant['width']}:{$variant['height']}",
            '-g', '48', // GOP size (2 secondes à 24fps)
            '-keyint_min', '48',
            '-sc_threshold', '0',
            
            // Encodage audio
            '-c:a', 'aac',
            '-b:a', $variant['audio_bitrate'],
            '-ar', '48000',
            '-ac', '2',
            
            // Configuration HLS
            '-f', 'hls',
            '-hls_time', (string)$config['segment_duration'],
            '-hls_list_size', (string)$config['list_size'],
            '-hls_delete_threshold', (string)$config['delete_threshold'],
            '-hls_segment_filename', $outputDir . '/' . $segmentPattern,
            '-hls_playlist_type', 'vod', // Video on Demand
            '-hls_flags', 'independent_segments',
            
            // Output
            $outputDir . '/' . $playlistName
        ];

        $this->logger->debug('Creating HLS variant', [
            'variant' => $variant['name'],
            'command' => implode(' ', $command)
        ]);

        $process = new Process($command);
        $process->setTimeout(3600);
        
        try {
            $process->mustRun();
        } catch (\Exception $e) {
            $this->logger->error('HLS variant creation failed', [
                'variant' => $variant['name'],
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('HLS packaging failed: ' . $e->getMessage());
        }

        return $outputDir . '/' . $playlistName;
    }

    /**
     * Crée la master playlist
     */
    private function createMasterPlaylist(string $outputDir, array $variants): string
    {
        $masterPlaylist = "#EXTM3U\n";
        $masterPlaylist .= "#EXT-X-VERSION:3\n\n";

        foreach ($variants as $variant) {
            $bandwidth = $variant['bandwidth'];
            $resolution = $variant['resolution'];
            $relativePath = basename(dirname($variant['playlist'])) . '/playlist.m3u8';

            $masterPlaylist .= "#EXT-X-STREAM-INF:BANDWIDTH=$bandwidth,RESOLUTION=$resolution\n";
            $masterPlaylist .= "$relativePath\n\n";
        }

        $masterFile = $outputDir . '/master.m3u8';
        file_put_contents($masterFile, $masterPlaylist);

        return $masterFile;
    }

    /**
     * Crée un HLS audio-only
     */
    public function createAudioOnlyHLS(string $inputFile, array $options = []): array
    {
        $jobId = uniqid('hls_audio_', true);
        $outputDir = $this->hlsPath . '/' . $jobId;
        $this->filesystem->mkdir($outputDir);

        $config = array_merge(self::DEFAULT_CONFIG, $options);

        $command = [
            'ffmpeg',
            '-i', $inputFile,
            '-vn', // Pas de vidéo
            '-c:a', 'aac',
            '-b:a', '128k',
            '-ar', '48000',
            '-f', 'hls',
            '-hls_time', (string)$config['segment_duration'],
            '-hls_list_size', (string)$config['list_size'],
            '-hls_segment_filename', $outputDir . '/segment_%03d.ts',
            '-hls_playlist_type', 'vod',
            $outputDir . '/playlist.m3u8'
        ];

        $process = new Process($command);
        $process->setTimeout(1800);
        $process->mustRun();

        return [
            'job_id' => $jobId,
            'playlist' => $outputDir . '/playlist.m3u8',
            'output_dir' => $outputDir,
        ];
    }

    /**
     * Crée un HLS avec encryption (AES-128)
     */
    public function createEncryptedHLS(string $inputFile, array $options = []): array
    {
        $jobId = uniqid('hls_enc_', true);
        $outputDir = $this->hlsPath . '/' . $jobId;
        $this->filesystem->mkdir($outputDir);

        // Générer la clé d'encryption
        $keyFile = $outputDir . '/key.key';
        $keyInfoFile = $outputDir . '/keyinfo';
        $key = random_bytes(16);
        file_put_contents($keyFile, $key);

        // Créer le fichier keyinfo
        $keyInfo = "key.key\n";
        $keyInfo .= "$keyFile\n";
        $keyInfo .= bin2hex(random_bytes(16));
        file_put_contents($keyInfoFile, $keyInfo);

        $config = array_merge(self::DEFAULT_CONFIG, $options);

        $command = [
            'ffmpeg',
            '-i', $inputFile,
            '-c:v', 'libx264',
            '-c:a', 'aac',
            '-f', 'hls',
            '-hls_time', (string)$config['segment_duration'],
            '-hls_key_info_file', $keyInfoFile,
            '-hls_segment_filename', $outputDir . '/segment_%03d.ts',
            '-hls_playlist_type', 'vod',
            $outputDir . '/playlist.m3u8'
        ];

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->mustRun();

        return [
            'job_id' => $jobId,
            'playlist' => $outputDir . '/playlist.m3u8',
            'key_file' => $keyFile,
            'output_dir' => $outputDir,
            'encrypted' => true,
        ];
    }

    /**
     * Filtre les variantes selon la résolution source
     */
    private function filterVariantsBySourceResolution(array $variants, array $sourceInfo): array
    {
        $sourceWidth = 0;
        
        foreach ($sourceInfo['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $sourceWidth = $stream['width'] ?? 0;
                break;
            }
        }

        if ($sourceWidth === 0) {
            return $variants; // Retourner toutes les variantes si on ne peut pas déterminer
        }

        // Garder seulement les variantes <= résolution source
        return array_filter($variants, function($variant) use ($sourceWidth) {
            return $variant['width'] <= $sourceWidth;
        });
    }

    /**
     * Calcule la taille du buffer basée sur le bitrate
     */
    private function calculateBufferSize(string $bitrate): string
    {
        $value = (int)filter_var($bitrate, FILTER_SANITIZE_NUMBER_INT);
        $bufferSize = $value * 2; // 2x le bitrate
        return $bufferSize . 'k';
    }

    /**
     * Nettoie les anciens packages HLS
     */
    public function cleanupOldPackages(int $maxAge = 86400): int
    {
        $cleaned = 0;
        $directories = glob($this->hlsPath . '/hls_*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            if ((time() - filemtime($dir)) > $maxAge) {
                $this->filesystem->remove($dir);
                $cleaned++;
            }
        }

        $this->logger->info("Cleaned $cleaned old HLS packages");
        return $cleaned;
    }

    /**
     * Obtient les informations d'un package HLS
     */
    public function getPackageInfo(string $jobId): ?array
    {
        $packageDir = $this->hlsPath . '/' . $jobId;
        
        if (!$this->filesystem->exists($packageDir)) {
            return null;
        }

        $masterPlaylist = $packageDir . '/master.m3u8';
        
        if (!file_exists($masterPlaylist)) {
            return null;
        }

        $variants = [];
        $variantDirs = glob($packageDir . '/*', GLOB_ONLYDIR);
        
        foreach ($variantDirs as $variantDir) {
            $playlistFile = $variantDir . '/playlist.m3u8';
            if (file_exists($playlistFile)) {
                $segments = glob($variantDir . '/*.ts');
                $variants[] = [
                    'name' => basename($variantDir),
                    'segments' => count($segments),
                    'playlist' => $playlistFile,
                ];
            }
        }

        return [
            'job_id' => $jobId,
            'master_playlist' => $masterPlaylist,
            'variants' => $variants,
            'created_at' => filemtime($packageDir),
        ];
    }
}