<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class VideoDownloaderService
{
    private string $tempPath;
    private Filesystem $filesystem;

    public function __construct(string $projectDir)
    {
        // Utiliser un dossier temporaire
        $this->tempPath = $projectDir . '/var/temp_downloads';
        $this->filesystem = new Filesystem();
        
        if (!$this->filesystem->exists($this->tempPath)) {
            $this->filesystem->mkdir($this->tempPath);
        }
    }

    public function getVideoInfo(string $url): array
    {
        $process = new Process([
            'yt-dlp',
            '--dump-json',
            '--no-playlist',
            $url
        ]);

        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();
        return json_decode($output, true);
    }

    public function download(string $url, string $format = 'best', string $type = 'video'): array
    {
        // Générer un nom de fichier unique
        $filename = uniqid() . '_' . time();
        $outputPath = $this->tempPath . '/' . $filename;

        if ($type === 'audio') {
            $command = [
                'yt-dlp',
                '-x',
                '--audio-format', 'mp3',
                '--audio-quality', '0',
                '-o', $outputPath . '.%(ext)s',
                $url
            ];
        } else {
            $formatOption = $format === 'best' ? 'bestvideo+bestaudio/best' : $format;
            $command = [
                'yt-dlp',
                '-f', $formatOption,
                '--merge-output-format', 'mp4',
                '-o', $outputPath . '.%(ext)s',
                $url
            ];
        }

        $process = new Process($command);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Trouver le fichier téléchargé
        $files = glob($outputPath . '.*');
        if (empty($files)) {
            throw new \RuntimeException('Download failed: file not found');
        }

        $downloadedFile = $files[0];
        $fileInfo = pathinfo($downloadedFile);

        return [
            'temp_filename' => basename($downloadedFile),
            'original_filename' => $this->sanitizeFilename($fileInfo['filename']) . '.' . $fileInfo['extension'],
            'filepath' => $downloadedFile,
            'size' => filesize($downloadedFile),
            'extension' => $fileInfo['extension']
        ];
    }

    /**
     * Télécharge une vidéo dans un répertoire spécifique
     */
    public function downloadToDirectory(
        string $url,
        string $outputDir,
        string $format = 'best',
        string $type = 'video'
    ): array {
        if (!$this->filesystem->exists($outputDir)) {
            $this->filesystem->mkdir($outputDir);
        }

        $filename = uniqid() . '_' . time();
        $outputPath = $outputDir . '/' . $filename;

        if ($type === 'audio') {
            $command = [
                'yt-dlp',
                '-x',
                '--audio-format', 'mp3',
                '--audio-quality', '0',
                '-o', $outputPath . '.%(ext)s',
                $url
            ];
        } else {
            $formatOption = $format === 'best' ? 'bestvideo+bestaudio/best' : $format;
            $command = [
                'yt-dlp',
                '-f', $formatOption,
                '--merge-output-format', 'mp4',
                '-o', $outputPath . '.%(ext)s',
                $url
            ];
        }

        $process = new Process($command);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Trouver le fichier téléchargé
        $files = glob($outputPath . '.*');
        if (empty($files)) {
            throw new \RuntimeException('Download failed: file not found');
        }

        $downloadedFile = $files[0];
        $fileInfo = pathinfo($downloadedFile);

        return [
            'temp_filename' => basename($downloadedFile),
            'original_filename' => $this->sanitizeFilename($fileInfo['filename']) . '.' . $fileInfo['extension'],
            'filepath' => $downloadedFile,
            'size' => filesize($downloadedFile),
            'extension' => $fileInfo['extension']
        ];
    }

    public function getFilePath(string $filename): ?string
    {
        $filePath = $this->tempPath . '/' . $filename;
        
        if ($this->filesystem->exists($filePath)) {
            return $filePath;
        }

        return null;
    }

    public function deleteFile(string $filename): bool
    {
        $filePath = $this->tempPath . '/' . $filename;
        
        if ($this->filesystem->exists($filePath)) {
            $this->filesystem->remove($filePath);
            return true;
        }

        return false;
    }

    public function cleanOldFiles(int $maxAge = 3600): void
    {
        // Nettoyer les fichiers de plus de 1 heure
        $files = glob($this->tempPath . '/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                $this->filesystem->remove($file);
            }
        }
    }

    private function sanitizeFilename(string $filename): string
    {
        // Nettoyer le nom de fichier
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
        return substr($filename, 0, 200); // Limiter la longueur
    }
}