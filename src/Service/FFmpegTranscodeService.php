<?php

namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;

class FFmpegTranscodeService
{
    private Filesystem $filesystem;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->filesystem = new Filesystem();
        $this->logger = $logger;
    }

    /**
     * Transcode une vidéo avec des options personnalisées
     */
    public function transcode(string $inputFile, array $options = []): string
    {
        if (!$this->filesystem->exists($inputFile)) {
            throw new \RuntimeException("Input file not found: $inputFile");
        }

        $outputFile = $this->generateOutputPath($inputFile, $options);
        
        $command = $this->buildTranscodeCommand($inputFile, $outputFile, $options);
        
        $this->logger->info('Starting transcoding', [
            'input' => $inputFile,
            'output' => $outputFile,
            'options' => $options
        ]);

        $process = new Process($command);
        $process->setTimeout(3600); // 1 heure max
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if (!$this->filesystem->exists($outputFile)) {
            throw new \RuntimeException('Transcoding failed: output file not found');
        }

        return $outputFile;
    }

    /**
     * Extrait l'audio d'une vidéo
     */
    public function extractAudio(string $inputFile, string $format = 'mp3', array $options = []): string
    {
        if (!$this->filesystem->exists($inputFile)) {
            throw new \RuntimeException("Input file not found: $inputFile");
        }

        $pathInfo = pathinfo($inputFile);
        $outputFile = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $format;

        $command = [
            'ffmpeg',
            '-i', $inputFile,
            '-vn', // Pas de vidéo
            '-acodec', $this->getAudioCodec($format),
            '-ab', $options['bitrate'] ?? '192k',
            '-ar', $options['sample_rate'] ?? '44100',
            '-y', // Overwrite
            $outputFile
        ];

        $this->logger->info('Extracting audio', [
            'input' => $inputFile,
            'output' => $outputFile,
            'format' => $format
        ]);

        $process = new Process($command);
        $process->setTimeout(1800);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $outputFile;
    }

    /**
     * Extrait l'audio dans un répertoire spécifique
     */
    public function extractAudioToDirectory(
        string $inputFile, 
        string $outputDir, 
        string $format = 'mp3', 
        array $options = []
    ): string {
        if (!$this->filesystem->exists($outputDir)) {
            $this->filesystem->mkdir($outputDir);
        }

        $pathInfo = pathinfo($inputFile);
        $outputFile = $outputDir . '/' . $pathInfo['filename'] . '.' . $format;

        $command = [
            'ffmpeg',
            '-i', $inputFile,
            '-vn',
            '-acodec', $this->getAudioCodec($format),
            '-ab', $options['bitrate'] ?? '192k',
            '-ar', $options['sample_rate'] ?? '44100',
            '-y',
            $outputFile
        ];

        $process = new Process($command);
        $process->setTimeout(1800);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $outputFile;
    }

    /**
     * Génère des thumbnails à partir d'une vidéo
     */
    public function generateThumbnails(string $inputFile, int $count = 5): array
    {
        if (!$this->filesystem->exists($inputFile)) {
            throw new \RuntimeException("Input file not found: $inputFile");
        }

        $pathInfo = pathinfo($inputFile);
        $outputPattern = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb_%03d.jpg';

        // Obtenir la durée de la vidéo
        $duration = $this->getVideoDuration($inputFile);
        $interval = $duration / ($count + 1);

        $thumbnails = [];
        for ($i = 1; $i <= $count; $i++) {
            $timestamp = $interval * $i;
            $outputFile = sprintf(
                $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb_%03d.jpg',
                $i
            );

            $command = [
                'ffmpeg',
                '-ss', (string)$timestamp,
                '-i', $inputFile,
                '-vframes', '1',
                '-q:v', '2',
                '-y',
                $outputFile
            ];

            $process = new Process($command);
            $process->setTimeout(60);
            $process->run();

            if ($process->isSuccessful() && $this->filesystem->exists($outputFile)) {
                $thumbnails[] = $outputFile;
            }
        }

        return $thumbnails;
    }

    /**
     * Génère des thumbnails dans un répertoire spécifique
     */
    public function generateThumbnailsInDirectory(
        string $inputFile, 
        string $outputDir, 
        int $count = 5
    ): array {
        if (!$this->filesystem->exists($outputDir)) {
            $this->filesystem->mkdir($outputDir);
        }

        $pathInfo = pathinfo($inputFile);
        $duration = $this->getVideoDuration($inputFile);
        $interval = $duration / ($count + 1);

        $thumbnails = [];
        for ($i = 1; $i <= $count; $i++) {
            $timestamp = $interval * $i;
            $outputFile = $outputDir . '/thumb_' . sprintf('%03d', $i) . '.jpg';

            $command = [
                'ffmpeg',
                '-ss', (string)$timestamp,
                '-i', $inputFile,
                '-vframes', '1',
                '-q:v', '2',
                '-y',
                $outputFile
            ];

            $process = new Process($command);
            $process->setTimeout(60);
            $process->run();

            if ($process->isSuccessful() && $this->filesystem->exists($outputFile)) {
                $thumbnails[] = $outputFile;
            }
        }

        return $thumbnails;
    }

    /**
     * Obtient la durée d'une vidéo en secondes
     */
    public function getVideoDuration(string $inputFile): float
    {
        $command = [
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $inputFile
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return (float) trim($process->getOutput());
    }

    /**
     * Obtient les informations d'une vidéo
     */
    public function getVideoInfo(string $inputFile): array
    {
        $command = [
            'ffprobe',
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $inputFile
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return json_decode($process->getOutput(), true);
    }

    /**
     * Construit la commande de transcodage
     */
    private function buildTranscodeCommand(string $inputFile, string $outputFile, array $options): array
    {
        $command = ['ffmpeg', '-i', $inputFile];

        // Codec vidéo
        if (isset($options['video_codec'])) {
            $command[] = '-c:v';
            $command[] = $options['video_codec'];
        } else {
            $command[] = '-c:v';
            $command[] = 'libx264';
        }

        // Bitrate vidéo
        if (isset($options['video_bitrate'])) {
            $command[] = '-b:v';
            $command[] = $options['video_bitrate'];
        }

        // Résolution
        if (isset($options['resolution'])) {
            $command[] = '-vf';
            $command[] = 'scale=' . $options['resolution'];
        }

        // Framerate
        if (isset($options['framerate'])) {
            $command[] = '-r';
            $command[] = $options['framerate'];
        }

        // Codec audio
        if (isset($options['audio_codec'])) {
            $command[] = '-c:a';
            $command[] = $options['audio_codec'];
        } else {
            $command[] = '-c:a';
            $command[] = 'aac';
        }

        // Bitrate audio
        if (isset($options['audio_bitrate'])) {
            $command[] = '-b:a';
            $command[] = $options['audio_bitrate'];
        }

        // Preset
        if (isset($options['preset'])) {
            $command[] = '-preset';
            $command[] = $options['preset'];
        }

        // CRF (qualité)
        if (isset($options['crf'])) {
            $command[] = '-crf';
            $command[] = $options['crf'];
        }

        $command[] = '-y'; // Overwrite
        $command[] = $outputFile;

        return $command;
    }

    /**
     * Génère le chemin de sortie
     */
    private function generateOutputPath(string $inputFile, array $options): string
    {
        $pathInfo = pathinfo($inputFile);
        $suffix = isset($options['suffix']) ? '_' . $options['suffix'] : '_transcoded';
        $extension = $options['format'] ?? 'mp4';
        
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . $suffix . '.' . $extension;
    }

    /**
     * Retourne le codec audio approprié pour le format
     */
    private function getAudioCodec(string $format): string
    {
        return match($format) {
            'mp3' => 'libmp3lame',
            'aac', 'm4a' => 'aac',
            'ogg' => 'libvorbis',
            'flac' => 'flac',
            'wav' => 'pcm_s16le',
            default => 'libmp3lame'
        };
    }
}