<?php

namespace App\Service;

use App\Entity\Inventory;
use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Сервис для генерации кастомных ID элементов инвентаря
 */
class CustomIdGenerator
{
    public const TYPE_FIXED_TEXT = 'fixed_text';
    public const TYPE_RANDOM_20BIT = 'random_20bit';
    public const TYPE_RANDOM_32BIT = 'random_32bit';
    public const TYPE_RANDOM_6DIGIT = 'random_6digit';
    public const TYPE_RANDOM_9DIGIT = 'random_9digit';
    public const TYPE_GUID = 'guid';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_SEQUENCE = 'sequence';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Генерирует кастомный ID для нового элемента
     */
    public function generateCustomId(Inventory $inventory): string
    {
        $format = $inventory->getCustomIdFormat();

        if (empty($format) || !is_array($format) || !isset($format['parts']) || empty($format['parts'])) {
            // Если формат не задан, используем дефолтный формат
            return $this->generateDefaultId($inventory);
        }

        $parts = [];
        foreach ($format['parts'] as $part) {
            $parts[] = $this->generatePart($part, $inventory);
        }

        $generatedId = implode('', $parts);

        // Проверяем уникальность и генерируем заново при необходимости
        $attempts = 0;
        $maxAttempts = 100; // Предотвращаем бесконечный цикл

        while ($attempts < $maxAttempts && !$this->isUnique($generatedId, $inventory)) {
            // Если ID не уникален, генерируем заново
            $parts = [];
            foreach ($format['parts'] as $part) {
                $parts[] = $this->generatePart($part, $inventory);
            }
            $generatedId = implode('', $parts);
            $attempts++;
        }

        if ($attempts >= $maxAttempts) {
            throw new \RuntimeException('Не удалось сгенерировать уникальный ID после ' . $maxAttempts . ' попыток');
        }

        return $generatedId;
    }

    /**
     * Проверяет уникальность ID в рамках инвентаря
     */
    private function isUnique(string $customId, Inventory $inventory): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Item::class, 'i')
            ->where('i.inventory = :inventory')
            ->andWhere('i.customId = :customId')
            ->setParameter('inventory', $inventory)
            ->setParameter('customId', $customId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count == 0;
    }

    /**
     * Проверяет, соответствует ли custom_id формату инвентаря
     */
    public function validateCustomId(string $customId, Inventory $inventory): bool
    {
        $format = $inventory->getCustomIdFormat();

        if (empty($format) || !is_array($format) || !isset($format['parts'])) {
            // Если формат не задан, проверяем дефолтный формат
            return $this->validateDefaultId($customId, $inventory);
        }

        // Для валидации нужно реализовать парсинг custom_id по частям
        // Это сложная задача, поэтому пока возвращаем true
        // В будущем можно реализовать полноценную валидацию
        return true;
    }

    /**
     * Генерирует часть ID по конфигурации
     */
    private function generatePart(array $partConfig, Inventory $inventory): string
    {
        $type = $partConfig['type'] ?? '';

        switch ($type) {
            case self::TYPE_FIXED_TEXT:
                return $partConfig['text'] ?? '';

            case self::TYPE_RANDOM_20BIT:
                return $this->generateRandomNumber(20, $partConfig['leading_zeros'] ?? false);

            case self::TYPE_RANDOM_32BIT:
                return $this->generateRandomNumber(32, $partConfig['leading_zeros'] ?? false);

            case self::TYPE_RANDOM_6DIGIT:
                return $this->generateRandomNumber(6, true);

            case self::TYPE_RANDOM_9DIGIT:
                return $this->generateRandomNumber(9, true);

            case self::TYPE_GUID:
                return $this->generateGuid();

            case self::TYPE_DATETIME:
                return $this->generateDateTime($partConfig['format'] ?? 'Y-m-d_H-i-s');

            case self::TYPE_SEQUENCE:
                return $this->generateSequence($inventory, $partConfig);

            default:
                return '';
        }
    }

    /**
     * Генерирует случайное число с указанным количеством бит
     */
    private function generateRandomNumber(int $bits, bool $leadingZeros = false): string
    {
        $maxValue = (1 << $bits) - 1; // 2^bits - 1
        $number = random_int(0, $maxValue);

        if ($leadingZeros) {
            $digits = $bits <= 10 ? $bits : 10; // Для 20-bit используем 10 цифр, для 32-bit тоже
            return str_pad((string)$number, $digits, '0', STR_PAD_LEFT);
        }

        return (string)$number;
    }

    /**
     * Генерирует GUID
     */
    private function generateGuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }

    /**
     * Генерирует дату/время
     */
    private function generateDateTime(string $format): string
    {
        return date($format);
    }

    /**
     * Генерирует последовательность
     */
    private function generateSequence(Inventory $inventory, array $partConfig): string
    {
        $startValue = $partConfig['start_value'] ?? 1;
        $step = $partConfig['step'] ?? 1;
        $leadingZeros = $partConfig['leading_zeros'] ?? false;
        $digits = $partConfig['digits'] ?? 0;

        // Получаем все существующие custom_id из этого инвентаря
        $existingIds = $this->entityManager->createQueryBuilder()
            ->select('i.customId')
            ->from(Item::class, 'i')
            ->where('i.inventory = :inventory')
            ->setParameter('inventory', $inventory)
            ->getQuery()
            ->getScalarResult();

        $existingIds = array_column($existingIds, 'customId');

        // Для простоты, если это первый элемент, возвращаем start_value
        if (empty($existingIds)) {
            $nextValue = $startValue;
        } else {
            // Пытаемся найти максимальное числовое значение
            // Это упрощенная реализация - в реальности нужно парсить custom_id по формату
            $maxValue = 0;
            foreach ($existingIds as $existingId) {
                // Ищем все числа в строке и берем максимальное
                if (preg_match_all('/\d+/', $existingId, $matches)) {
                    foreach ($matches[0] as $number) {
                        $maxValue = max($maxValue, (int)$number);
                    }
                }
            }
            $nextValue = max($startValue, $maxValue + $step);
        }

        if ($leadingZeros && $digits > 0) {
            return str_pad((string)$nextValue, $digits, '0', STR_PAD_LEFT);
        }

        return (string)$nextValue;
    }

    /**
     * Генерирует дефолтный ID (если формат не задан)
     */
    private function generateDefaultId(Inventory $inventory): string
    {
        // Дефолтный формат: ITEM-{последовательность}
        $sequence = $this->generateSequence($inventory, [
            'start_value' => 1,
            'step' => 1,
            'leading_zeros' => true,
            'digits' => 4
        ]);

        return 'ITEM-' . $sequence;
    }

    /**
     * Проверяет дефолтный ID
     */
    private function validateDefaultId(string $customId, Inventory $inventory): bool
    {
        return preg_match('/^ITEM-\d{4,}$/', $customId) === 1;
    }

    /**
     * Получить доступные типы элементов для формата ID
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_FIXED_TEXT => [
                'name' => 'Fixed Text',
                'description' => 'Фиксированный текст с поддержкой Unicode',
                'has_text' => true,
            ],
            self::TYPE_RANDOM_20BIT => [
                'name' => '20-bit Random',
                'description' => '20-битное случайное число',
                'has_leading_zeros' => true,
            ],
            self::TYPE_RANDOM_32BIT => [
                'name' => '32-bit Random',
                'description' => '32-битное случайное число',
                'has_leading_zeros' => true,
            ],
            self::TYPE_RANDOM_6DIGIT => [
                'name' => '6-digit Random',
                'description' => '6-значное случайное число с ведущими нулями',
            ],
            self::TYPE_RANDOM_9DIGIT => [
                'name' => '9-digit Random',
                'description' => '9-значное случайное число с ведущими нулями',
            ],
            self::TYPE_GUID => [
                'name' => 'GUID',
                'description' => 'Генерирует GUID (UUID)',
            ],
            self::TYPE_DATETIME => [
                'name' => 'Date/Time',
                'description' => 'Дата и время создания элемента',
                'has_format' => true,
            ],
            self::TYPE_SEQUENCE => [
                'name' => 'Sequence',
                'description' => 'Последовательность (максимальное значение + шаг)',
                'has_sequence_config' => true,
            ],
        ];
    }
}
