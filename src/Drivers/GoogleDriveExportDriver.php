<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Drivers;

use ByteXR\DynamicReporter\Contracts\ExternalExportDriver;
use ByteXR\DynamicReporter\Exceptions\ExportDriverException;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class GoogleDriveExportDriver implements ExternalExportDriver
{
    protected ?GoogleClient $client = null;

    protected ?Drive $driveService = null;

    protected int $chunkSize = 5 * 1024 * 1024;

    protected ?string $folderId = null;

    public function __construct(?string $folderId = null)
    {
        $this->folderId = $folderId ?? config('dynamic-reporter.google_drive.folder_id');
    }

    public function getDriverName(): string
    {
        return 'google_drive';
    }

    public function getLabel(): string
    {
        return 'Save to Google Drive';
    }

    public static function isConfigured(): bool
    {
        if (! class_exists(GoogleClient::class)) {
            return false;
        }

        $hasGoogleDisk = config('filesystems.disks.google') !== null;
        $hasGoogleConfig = config('services.google.client_id') !== null
            || config('dynamic-reporter.google_drive.credentials_path') !== null;

        return $hasGoogleDisk || $hasGoogleConfig;
    }

    public function upload(string $filePath, string $fileName, ?string $mimeType = null): string
    {
        $this->ensureInitialized();

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => $this->folderId ? [$this->folderId] : [],
        ]);

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new ExportDriverException("Unable to read file: {$filePath}");
        }

        $file = $this->driveService->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType ?? $this->detectMimeType($filePath),
            'uploadType' => 'multipart',
            'fields' => 'id, webViewLink',
        ]);

        return $file->webViewLink ?? "https://drive.google.com/file/d/{$file->id}/view";
    }

    public function uploadChunked(
        string $filePath,
        string $fileName,
        ?string $mimeType = null,
        ?callable $progressCallback = null,
    ): string {
        $this->ensureInitialized();

        $fileSize = filesize($filePath);

        if ($fileSize === false) {
            throw new ExportDriverException("Unable to get file size: {$filePath}");
        }

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => $this->folderId ? [$this->folderId] : [],
        ]);

        $this->client->setDefer(true);

        $request = $this->driveService->files->create($fileMetadata, [
            'fields' => 'id, webViewLink',
        ]);

        $media = new \Google\Http\MediaFileUpload(
            $this->client,
            $request,
            $mimeType ?? $this->detectMimeType($filePath),
            null,
            true,
            $this->chunkSize
        );

        $media->setFileSize($fileSize);

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new ExportDriverException("Unable to open file for reading: {$filePath}");
        }

        $status = false;
        $bytesUploaded = 0;

        try {
            while (! $status && ! feof($handle)) {
                $chunk = fread($handle, $this->chunkSize);

                if ($chunk === false) {
                    throw new ExportDriverException("Error reading file chunk");
                }

                $status = $media->nextChunk($chunk);
                $bytesUploaded += strlen($chunk);

                if ($progressCallback !== null) {
                    $progressCallback($bytesUploaded, $fileSize);
                }
            }
        } finally {
            fclose($handle);
            $this->client->setDefer(false);
        }

        if ($status === false) {
            throw new ExportDriverException("Failed to complete chunked upload");
        }

        return $status->webViewLink ?? "https://drive.google.com/file/d/{$status->id}/view";
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function setChunkSize(int $bytes): self
    {
        $this->chunkSize = max(256 * 1024, $bytes);

        return $this;
    }

    public function setFolderId(?string $folderId): self
    {
        $this->folderId = $folderId;

        return $this;
    }

    protected function ensureInitialized(): void
    {
        if ($this->client !== null && $this->driveService !== null) {
            return;
        }

        if (! class_exists(GoogleClient::class)) {
            throw new ExportDriverException(
                "Google API client is not installed. Run: composer require google/apiclient"
            );
        }

        $this->client = $this->createGoogleClient();
        $this->driveService = new Drive($this->client);
    }

    protected function createGoogleClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setApplicationName(config('app.name', 'Laravel Dynamic Reporter'));
        $client->setScopes([Drive::DRIVE_FILE]);

        $credentialsPath = config('dynamic-reporter.google_drive.credentials_path');

        if ($credentialsPath !== null && file_exists($credentialsPath)) {
            $client->setAuthConfig($credentialsPath);
        } elseif (config('services.google.client_id') !== null) {
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
        } else {
            throw new ExportDriverException(
                "Google Drive credentials not configured. Set GOOGLE_DRIVE_CREDENTIALS_PATH or services.google config."
            );
        }

        $accessToken = config('dynamic-reporter.google_drive.access_token');

        if ($accessToken !== null) {
            $client->setAccessToken($accessToken);
        }

        return $client;
    }

    protected function detectMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
