<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Сервис для кэширования часто используемых данных
 */
class CacheService
{
    public function __construct(
        private CacheInterface $cache
    ) {
    }

    /**
     * Кэширование популярных инвентарей (на 10 минут)
     */
    public function getCachedPopularInventories(callable $callback): array
    {
        return $this->cache->get('popular_inventories', function (ItemInterface $item) use ($callback) {
            $item->expiresAfter(600); // 10 минут
            return $callback();
        });
    }

    /**
     * Кэширование последних инвентарей (на 5 минут)
     */
    public function getCachedLatestInventories(callable $callback): array
    {
        return $this->cache->get('latest_inventories', function (ItemInterface $item) use ($callback) {
            $item->expiresAfter(300); // 5 минут
            return $callback();
        });
    }

    /**
     * Кэширование популярных тегов (на 10 минут)
     */
    public function getCachedPopularTags(callable $callback): array
    {
        return $this->cache->get('popular_tags', function (ItemInterface $item) use ($callback) {
            $item->expiresAfter(600); // 10 минут
            return $callback();
        });
    }

    /**
     * Кэширование статистики инвентаря (на 15 минут)
     */
    public function getCachedInventoryStats(int $inventoryId, callable $callback): array
    {
        return $this->cache->get("inventory_stats_{$inventoryId}", function (ItemInterface $item) use ($callback) {
            $item->expiresAfter(900); // 15 минут
            return $callback();
        });
    }

    /**
     * Инвалидация кэша статистики инвентаря
     */
    public function invalidateInventoryStats(int $inventoryId): void
    {
        $this->cache->delete("inventory_stats_{$inventoryId}");
    }

    /**
     * Инвалидация кэша популярных инвентарей
     */
    public function invalidatePopularInventories(): void
    {
        $this->cache->delete('popular_inventories');
    }

    /**
     * Инвалидация кэша последних инвентарей
     */
    public function invalidateLatestInventories(): void
    {
        $this->cache->delete('latest_inventories');
    }

    /**
     * Инвалидация кэша популярных тегов
     */
    public function invalidatePopularTags(): void
    {
        $this->cache->delete('popular_tags');
    }

    /**
     * Очистка всего кэша приложения
     */
    public function clearAllCache(): void
    {
        $this->cache->clear();
    }
}
