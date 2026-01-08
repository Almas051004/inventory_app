<?php

namespace App\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

class PreviewService
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Генерирует HTML превью для ссылки
     */
    public function generateLinkPreview(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        $url = trim($url);
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        // Проверка на изображение
        if ($this->isImageUrl($url, $extension)) {
            return $this->generateImagePreview($url);
        }

        // Проверка на PDF
        if ($this->isPdfUrl($url, $extension)) {
            return $this->generatePdfPreview($url);
        }

        // Для других типов ссылок возвращаем обычную ссылку
        return sprintf('<a href="%s" target="_blank" rel="noopener noreferrer" class="text-decoration-none">%s</a>',
            htmlspecialchars($url),
            htmlspecialchars($url)
        );
    }

    /**
     * Проверяет, является ли URL изображением
     */
    private function isImageUrl(string $url, string $extension): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];

        // Проверка по расширению файла
        if (in_array($extension, $imageExtensions)) {
            return true;
        }

        return false;
    }

    /**
     * Проверяет, является ли URL PDF документом
     */
    private function isPdfUrl(string $url, string $extension): bool
    {
        return $extension === 'pdf';
    }

    /**
     * Генерирует превью для изображения
     */
    private function generateImagePreview(string $url): string
    {
        $html = sprintf('
            <div class="link-preview image-preview mb-2">
                <a href="%s" target="_blank" rel="noopener noreferrer" class="d-block">
                    <img src="%s" alt="%s" class="img-thumbnail" style="max-width: 200px; max-height: 150px;" onerror="this.style.display=\'none\'">
                </a>
                <small class="text-muted d-block">%s</small>
            </div>',
            htmlspecialchars($url),
            htmlspecialchars($url),
            $this->translator->trans('preview.image_alt'),
            htmlspecialchars($url)
        );

        return $html;
    }

    /**
     * Генерирует превью для PDF
     */
    private function generatePdfPreview(string $url): string
    {
        $html = sprintf('
            <div class="link-preview pdf-preview mb-2">
                <a href="%s" target="_blank" rel="noopener noreferrer" class="d-block">
                    <div class="pdf-preview-icon d-flex align-items-center p-3 border rounded">
                        <i class="bi bi-file-earmark-pdf-fill text-danger me-3" style="font-size: 2rem;"></i>
                        <div>
                            <div class="fw-bold">%s</div>
                            <small class="text-muted">%s</small>
                        </div>
                    </div>
                </a>
                <small class="text-muted d-block">%s</small>
            </div>',
            htmlspecialchars($url),
            $this->translator->trans('preview.pdf_title'),
            $this->translator->trans('preview.click_to_open'),
            htmlspecialchars($url)
        );

        return $html;
    }
}
