<?php

namespace App\Service;

use Parsedown;

class MarkdownService
{
    private Parsedown $parsedown;

    public function __construct()
    {
        $this->parsedown = new Parsedown();
        // Настройки безопасности
        $this->parsedown->setSafeMode(true);
        $this->parsedown->setMarkupEscaped(true);
    }

    /**
     * Преобразует Markdown текст в HTML
     */
    public function parse(string $markdown): string
    {
        if (empty($markdown)) {
            return '';
        }

        return $this->parsedown->text($markdown);
    }

    /**
     * Преобразует Markdown текст в HTML с дополнительной очисткой
     */
    public function parseSafe(string $markdown): string
    {
        $html = $this->parse($markdown);

        // Дополнительная очистка от потенциально опасных тегов
        return strip_tags($html, '<p><br><strong><em><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><code><pre><a><img>');
    }
}
