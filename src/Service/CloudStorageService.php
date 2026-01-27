<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Spatie\Dropbox\Client;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service for uploading files to cloud storage (Dropbox)
 */
class CloudStorageService
{
    private ?Client $dropboxClient = null;

    public function __construct(
        private TranslatorInterface $translator,
        private string $dropboxAccessToken,
        private ?string $dropboxRefreshToken,
        private string $dropboxAppKey,
        private string $dropboxAppSecret,
        private LoggerInterface $logger
    ) {
        if (!empty($this->dropboxAccessToken) && !empty($this->dropboxAppKey) && !empty($this->dropboxAppSecret)) {
            $tokenProvider = new DropboxRefreshableTokenProvider(
                $this->dropboxAccessToken,
                $this->dropboxRefreshToken,
                $this->dropboxAppKey,
                $this->dropboxAppSecret
            );
            $this->dropboxClient = new Client($tokenProvider);
        } elseif (!empty($this->dropboxAccessToken)) {
            $this->dropboxClient = new Client($this->dropboxAccessToken);
        }
    }

    /**
     * Uploads JSON data to Dropbox
     *
     * @param array $data Data to upload
     * @param string $filename Filename without extension
     * @return string|null Path to uploaded file or null on error
     */
    public function uploadJsonData(array $data, string $filename): ?string
    {
        try {
            if (!$this->dropboxClient) {
                throw new \Exception('Dropbox client not configured');
            }

            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($jsonContent === false) {
                throw new \Exception('Failed to encode JSON data');
            }

            $timestamp = date('Y-m-d_H-i-s');
            $fullFilename = $filename . '_' . $timestamp . '.json';
            $path = '/' . $fullFilename;

            $this->dropboxClient->upload($path, $jsonContent);

            return $path;

        } catch (\Exception $e) {
            $this->logger->error('CloudStorageService upload failed', [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Checks if Dropbox is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return $this->dropboxClient !== null;
    }

    /**
     * Gets account info from Dropbox
     *
     * @return array|null
     */
    public function getAccountInfo(): ?array
    {
        try {
            if (!$this->dropboxClient) {
                return null;
            }

            return $this->dropboxClient->getAccountInfo();
        } catch (\Exception $e) {
            $this->logger->error('Failed to get Dropbox account info', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
