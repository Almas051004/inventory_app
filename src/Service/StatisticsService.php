<?php

namespace App\Service;

use App\Entity\Inventory;
use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;

class StatisticsService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Получить полную статистику для инвентаря
     */
    public function getInventoryStatistics(Inventory $inventory): array
    {
        $inventoryId = $inventory->getId();

        // Получаем общее количество элементов через SQL запрос
        $totalItems = $this->getTotalItemsCount($inventory);

        $statistics = [
            'total_items' => $totalItems,
            'numeric_fields' => [],
            'text_fields' => [],
            'created_date_range' => $this->getCreatedDateRangeSql($inventoryId),
            'updated_date_range' => $this->getUpdatedDateRangeSql($inventoryId),
        ];

        // Получаем статистику для числовых полей через SQL агрегации
        $statistics['numeric_fields'] = $this->getNumericFieldsStatisticsSql($inventory, $inventoryId);

        // Получаем статистику для текстовых полей через SQL агрегации
        $statistics['text_fields'] = $this->getTextFieldsStatisticsSql($inventory, $inventoryId);

        return $statistics;
    }

    /**
     * Подсчет количества элементов
     */
    public function getTotalItemsCount(Inventory $inventory): int
    {
        return $this->entityManager->getRepository(Item::class)->count(['inventory' => $inventory]);
    }

    /**
     * Получить диапазон дат создания через SQL
     */
    private function getCreatedDateRangeSql(int $inventoryId): ?array
    {
        $conn = $this->entityManager->getConnection();
        $sql = "
            SELECT
                MIN(created_at) as min_date,
                MAX(created_at) as max_date
            FROM items
            WHERE inventory_id = :inventory_id
        ";

        $stmt = $conn->executeQuery($sql, ['inventory_id' => $inventoryId]);
        $result = $stmt->fetchAssociative();

        if (!$result || $result['min_date'] === null) {
            return null;
        }

        return [
            'min' => $result['min_date'],
            'max' => $result['max_date'],
        ];
    }

    /**
     * Получить диапазон дат создания (старый метод для совместимости)
     */
    private function getCreatedDateRange(array $items): ?array
    {
        if (empty($items)) {
            return null;
        }

        $dates = [];
        foreach ($items as $item) {
            $createdAt = $item->getCreatedAt();
            if ($createdAt !== null) {
                $dates[] = $createdAt;
            }
        }

        if (empty($dates)) {
            return null;
        }

        return [
            'min' => min($dates)->format('Y-m-d H:i:s'),
            'max' => max($dates)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Получить диапазон дат обновления через SQL
     */
    private function getUpdatedDateRangeSql(int $inventoryId): ?array
    {
        $conn = $this->entityManager->getConnection();
        $sql = "
            SELECT
                MIN(COALESCE(updated_at, created_at)) as min_date,
                MAX(COALESCE(updated_at, created_at)) as max_date
            FROM items
            WHERE inventory_id = :inventory_id
        ";

        $stmt = $conn->executeQuery($sql, ['inventory_id' => $inventoryId]);
        $result = $stmt->fetchAssociative();

        if (!$result || $result['min_date'] === null) {
            return null;
        }

        return [
            'min' => $result['min_date'],
            'max' => $result['max_date'],
        ];
    }

    /**
     * Получить диапазон дат обновления (старый метод для совместимости)
     */
    private function getUpdatedDateRange(array $items): ?array
    {
        if (empty($items)) {
            return null;
        }

        $dates = [];
        foreach ($items as $item) {
            $updatedAt = $item->getUpdatedAt();
            if ($updatedAt !== null) {
                $dates[] = $updatedAt;
            }
        }

        // Если нет дат обновления, используем даты создания
        if (empty($dates)) {
            foreach ($items as $item) {
                $createdAt = $item->getCreatedAt();
                if ($createdAt !== null) {
                    $dates[] = $createdAt;
                }
            }
        }

        if (empty($dates)) {
            return null;
        }

        return [
            'min' => min($dates)->format('Y-m-d H:i:s'),
            'max' => max($dates)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Статистика для числовых полей через SQL агрегации
     */
    private function getNumericFieldsStatisticsSql(Inventory $inventory, int $inventoryId): array
    {
        $numericFields = [];
        $intFields = ['int1', 'int2', 'int3'];

        foreach ($intFields as $field) {
            if ($inventory->{"getCustom{$field}State"}()) {
                $fieldName = $inventory->{"getCustom{$field}Name"}();
                $columnName = "custom_{$field}_value";

                $conn = $this->entityManager->getConnection();
                $sql = "
                    SELECT
                        COUNT({$columnName}) as count,
                        MIN({$columnName}) as min_val,
                        MAX({$columnName}) as max_val,
                        AVG({$columnName}) as avg_val,
                        SUM({$columnName}) as sum_val
                    FROM items
                    WHERE inventory_id = :inventory_id
                      AND {$columnName} IS NOT NULL
                ";

                $stmt = $conn->executeQuery($sql, ['inventory_id' => $inventoryId]);
                $result = $stmt->fetchAssociative();

                if ($result && $result['count'] > 0) {
                    $numericFields[$field] = [
                        'name' => $fieldName,
                        'count' => (int) $result['count'],
                        'min' => (float) $result['min_val'],
                        'max' => (float) $result['max_val'],
                        'average' => (float) $result['avg_val'],
                        'sum' => (float) $result['sum_val'],
                    ];
                }
            }
        }

        return $numericFields;
    }

    /**
     * Статистика для числовых полей (старый метод для совместимости)
     */
    private function getNumericFieldsStatistics(Inventory $inventory, array $items): array
    {
        $numericFields = [];
        $intFields = ['int1', 'int2', 'int3'];

        foreach ($intFields as $field) {
            if ($inventory->{"getCustom{$field}State"}()) {
                $fieldName = $inventory->{"getCustom{$field}Name"}();
                $values = [];

                foreach ($items as $item) {
                    $value = $item->{"getCustom{$field}Value"}();
                    if ($value !== null) {
                        $values[] = $value;
                    }
                }

                if (!empty($values)) {
                    $numericFields[$field] = [
                        'name' => $fieldName,
                        'count' => count($values),
                        'min' => min($values),
                        'max' => max($values),
                        'average' => array_sum($values) / count($values),
                        'sum' => array_sum($values),
                    ];
                }
            }
        }

        return $numericFields;
    }

    /**
     * Статистика для текстовых полей через SQL (наиболее частые значения)
     */
    private function getTextFieldsStatisticsSql(Inventory $inventory, int $inventoryId): array
    {
        $textFields = [];
        $stringFields = ['string1', 'string2', 'string3'];
        $textFieldsList = ['text1', 'text2', 'text3'];

        $allFields = array_merge($stringFields, $textFieldsList);

        foreach ($allFields as $field) {
            if ($inventory->{"getCustom{$field}State"}()) {
                $fieldName = $inventory->{"getCustom{$field}Name"}();
                $columnName = "custom_{$field}_value";

                $conn = $this->entityManager->getConnection();

                // Получаем общее количество и уникальные значения
                $sql = "
                    SELECT
                        COUNT({$columnName}) as total_values,
                        COUNT(DISTINCT TRIM({$columnName})) as unique_values
                    FROM items
                    WHERE inventory_id = :inventory_id
                      AND {$columnName} IS NOT NULL
                      AND TRIM({$columnName}) != ''
                ";

                $stmt = $conn->executeQuery($sql, ['inventory_id' => $inventoryId]);
                $statsResult = $stmt->fetchAssociative();

                // Получаем топ 5 наиболее частых значений
                $sql = "
                    SELECT
                        TRIM({$columnName}) as value,
                        COUNT(*) as frequency
                    FROM items
                    WHERE inventory_id = :inventory_id
                      AND {$columnName} IS NOT NULL
                      AND TRIM({$columnName}) != ''
                    GROUP BY TRIM({$columnName})
                    ORDER BY frequency DESC, value ASC
                    LIMIT 5
                ";

                $stmt = $conn->executeQuery($sql, ['inventory_id' => $inventoryId]);
                $frequentResults = $stmt->fetchAllAssociative();

                $mostFrequent = [];
                foreach ($frequentResults as $row) {
                    // Данные передаются как есть, экранирование будет на клиенте
                    $mostFrequent[$row['value']] = (int) $row['frequency'];
                }

                if ($statsResult && $statsResult['total_values'] > 0) {
                    $textFields[$field] = [
                        'name' => $fieldName,
                        'total_values' => (int) $statsResult['total_values'],
                        'unique_values' => (int) $statsResult['unique_values'],
                        'most_frequent' => $mostFrequent,
                    ];
                }
            }
        }

        return $textFields;
    }

    /**
     * Статистика для текстовых полей (старый метод для совместимости)
     */
    private function getTextFieldsStatistics(Inventory $inventory, array $items): array
    {
        $textFields = [];
        $stringFields = ['string1', 'string2', 'string3'];
        $textFieldsList = ['text1', 'text2', 'text3'];

        $allFields = array_merge($stringFields, $textFieldsList);

        foreach ($allFields as $field) {
            if ($inventory->{"getCustom{$field}State"}()) {
                $fieldName = $inventory->{"getCustom{$field}Name"}();
                $values = [];

                foreach ($items as $item) {
                    $value = $item->{"getCustom{$field}Value"}();
                    if ($value !== null && trim($value) !== '') {
                        $values[] = trim($value);
                    }
                }

                if (!empty($values)) {
                    $frequency = array_count_values($values);
                    arsort($frequency); // Сортируем по частоте

                    $textFields[$field] = [
                        'name' => $fieldName,
                        'total_values' => count($values),
                        'unique_values' => count($frequency),
                        'most_frequent' => array_slice($frequency, 0, 5, true), // Топ 5 наиболее частых
                    ];
                }
            }
        }

        return $textFields;
    }

    /**
     * Получить статистику для конкретного числового поля
     */
    public function getNumericFieldStats(Inventory $inventory, string $field): ?array
    {
        $stats = $this->getNumericFieldsStatisticsSql($inventory, $inventory->getId());
        return $stats[$field] ?? null;
    }

    /**
     * Получить статистику для конкретного текстового поля
     */
    public function getTextFieldStats(Inventory $inventory, string $field): ?array
    {
        $stats = $this->getTextFieldsStatisticsSql($inventory, $inventory->getId());
        return $stats[$field] ?? null;
    }
}
