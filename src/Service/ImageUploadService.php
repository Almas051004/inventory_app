<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

/**
 * Service for uploading images to imgcdn.dev
 */
class ImageUploadService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ErrorHandlerService $errorHandler,
        private TranslatorInterface $translator,
        private string $imgcdnApiKey,
        private string $imgcdnApiUrl
    ) {
    }

    /**
     * Uploads image to imgcdn.dev
     *
     * @param UploadedFile $file Uploaded file
     * @return string|null URL of uploaded image or null on error
     */
    public function uploadImage(UploadedFile $file): ?string
    {
        try {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedTypes)) {
                throw new \Exception($this->translator->trans('image_upload.unsupported_file_type'));
            }

            if ($file->getSize() > 20 * 1024 * 1024) {
                throw new \Exception($this->translator->trans('image_upload.file_too_large'));
            }

            $formData = new FormDataPart([
                'source' => DataPart::fromPath($file->getPathname(), $file->getClientOriginalName(), $file->getMimeType()),
                'key' => $this->imgcdnApiKey,
                'format' => 'json',
            ]);

            $response = $this->httpClient->request('POST', $this->imgcdnApiUrl, [
                'headers' => array_merge($formData->getPreparedHeaders()->toArray(), [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Symfony HttpClient',
                ]),
                'body' => $formData->bodyToString(),
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent();

            if ($statusCode !== 200) {
                throw new \Exception($this->translator->trans('image_upload.http_error', [
                    '%status_code%' => $statusCode,
                    '%response%' => $content
                ]));
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception($this->translator->trans('image_upload.invalid_json_response'));
            }

            if (!isset($data['status_code']) || $data['status_code'] !== 200) {
                $error = isset($data['error']['message']) ? $data['error']['message'] : $this->translator->trans('image_upload.unknown_upload_error');

                // Если это дубликат (code 101) - не считаем ошибкой
                if (isset($data['error']['code']) && $data['error']['code'] === 101) {
                    error_log("Image duplicate detected: " . $error);
                    return null; // Возвращаем null для дубликатов - пусть контроллер обработает
                }

                throw new \Exception($error);
            }

            if (isset($data['image']['url'])) {
                return $data['image']['url'];
            } else {
                throw new \Exception($this->translator->trans('image_upload.url_not_found'));
            }

        } catch (\Exception $e) {
            $errorId = $this->errorHandler->logException($e, 'Image upload to imgcdn.dev');
            return null;
        }
    }

    /**
     * Deletes image (stub, as imgcdn.dev does not provide deletion API)
     *
     * @param string $imageUrl Image URL
     * @return bool Always returns true, as deletion is not supported
     */
    public function deleteImage(string $imageUrl): bool
    {
        return true;
    }
}
