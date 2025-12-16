<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Contracts;

interface ExternalExportDriver
{
    /**
     * Get the unique identifier for this driver.
     */
    public function getDriverName(): string;

    /**
     * Get the display label for this driver.
     */
    public function getLabel(): string;

    /**
     * Check if this driver is available/configured in the host application.
     */
    public static function isConfigured(): bool;

    /**
     * Upload a file to the external service.
     *
     * @return string The URL or identifier of the uploaded file
     */
    public function upload(string $filePath, string $fileName, ?string $mimeType = null): string;

    /**
     * Upload a large file using chunked/resumable upload strategy.
     * This prevents timeouts on large files.
     *
     * @param callable(int $bytesUploaded, int $totalBytes): void|null $progressCallback
     * @return string The URL or identifier of the uploaded file
     */
    public function uploadChunked(
        string $filePath,
        string $fileName,
        ?string $mimeType = null,
        ?callable $progressCallback = null,
    ): string;

    /**
     * Get the chunk size for chunked uploads (in bytes).
     */
    public function getChunkSize(): int;
}
