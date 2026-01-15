<?php

namespace App\Repository;

use App\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /**
     * Search items within an inventory by full-text search
     */
    public function searchItems(int $inventoryId, string $query = '', array $filters = [], string $sortBy = 'created_at', string $sortOrder = 'DESC', int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.createdBy', 'cb')
            ->leftJoin('i.likes', 'l')
            ->addSelect('cb.username, cb.email, COUNT(l.id) as like_count')
            ->where('i.inventory = :inventory_id')
            ->setParameter('inventory_id', $inventoryId)
            ->groupBy('i.id, cb.username, cb.email');

        // Full-text search if query provided (using LIKE for now)
        if (!empty($query)) {
            $qb->andWhere('i.customString1Value LIKE :like_query OR ' .
                         'i.customString2Value LIKE :like_query OR ' .
                         'i.customString3Value LIKE :like_query OR ' .
                         'i.customText1Value LIKE :like_query OR ' .
                         'i.customText2Value LIKE :like_query OR ' .
                         'i.customText3Value LIKE :like_query')
                ->setParameter('like_query', '%' . $query . '%');
        }

        // Apply filters
        foreach ($filters as $field => $value) {
            // For boolean fields, check if value is exactly false/true, for others check if not empty
            $shouldApplyFilter = false;
            if (str_starts_with($field, 'custom_bool')) {
                $shouldApplyFilter = $value !== null && $value !== '';
            } else {
                $shouldApplyFilter = !empty($value);
            }

            if ($shouldApplyFilter) {
                $fieldName = $this->mapFilterFieldToEntityField($field);
                if ($fieldName) {
                    if (is_array($value)) {
                        $qb->andWhere("i.$fieldName IN (:filter_$field)")
                            ->setParameter("filter_$field", $value);
                    } else {
                        // Use LIKE for string fields, exact match for others
                        if (str_starts_with($field, 'custom_string') || str_starts_with($field, 'custom_text') || str_starts_with($field, 'custom_link')) {
                            $qb->andWhere("i.$fieldName LIKE :filter_$field")
                                ->setParameter("filter_$field", '%' . $value . '%');
                        } else {
                            $qb->andWhere("i.$fieldName = :filter_$field")
                                ->setParameter("filter_$field", $value);
                        }
                    }
                }
            }
        }

        // Sorting
        $this->applySorting($qb, $sortBy, $sortOrder);

        $items = $qb->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        // Convert to array format expected by controller
        return array_map(function($result) {
            return [
                0 => $result[0], // Item entity
                'username' => $result['username'],
                'email' => $result['email'],
                'like_count' => (int) $result['like_count']
            ];
        }, $items);
    }

    /**
     * Count search results for items
     */
    public function countSearchResults(int $inventoryId, string $query = '', array $filters = []): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(DISTINCT i.id)')
            ->where('i.inventory = :inventory_id')
            ->setParameter('inventory_id', $inventoryId);

        // Full-text search if query provided (using LIKE for now)
        if (!empty($query)) {
            $qb->andWhere('i.customString1Value LIKE :like_query OR ' .
                         'i.customString2Value LIKE :like_query OR ' .
                         'i.customString3Value LIKE :like_query OR ' .
                         'i.customText1Value LIKE :like_query OR ' .
                         'i.customText2Value LIKE :like_query OR ' .
                         'i.customText3Value LIKE :like_query')
                ->setParameter('like_query', '%' . $query . '%');
        }

        // Apply filters
        foreach ($filters as $field => $value) {
            // For boolean fields, check if value is exactly false/true, for others check if not empty
            $shouldApplyFilter = false;
            if (str_starts_with($field, 'custom_bool')) {
                $shouldApplyFilter = $value !== null && $value !== '';
            } else {
                $shouldApplyFilter = !empty($value);
            }

            if ($shouldApplyFilter) {
                $fieldName = $this->mapFilterFieldToEntityField($field);
                if ($fieldName) {
                    if (is_array($value)) {
                        $qb->andWhere("i.$fieldName IN (:filter_$field)")
                            ->setParameter("filter_$field", $value);
                    } else {
                        // Use LIKE for string fields, exact match for others
                        if (str_starts_with($field, 'custom_string') || str_starts_with($field, 'custom_text') || str_starts_with($field, 'custom_link')) {
                            $qb->andWhere("i.$fieldName LIKE :filter_$field")
                                ->setParameter("filter_$field", '%' . $value . '%');
                        } else {
                            $qb->andWhere("i.$fieldName = :filter_$field")
                                ->setParameter("filter_$field", $value);
                        }
                    }
                }
            }
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get items for inventory with sorting and pagination
     */
    public function getItemsForInventory(int $inventoryId, string $sortBy = 'created_at', string $sortOrder = 'DESC', int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.createdBy', 'cb')
            ->leftJoin('i.likes', 'l')
            ->addSelect('cb.username, cb.email, COUNT(l.id) as like_count')
            ->where('i.inventory = :inventory_id')
            ->setParameter('inventory_id', $inventoryId)
            ->groupBy('i.id, cb.username, cb.email');

        $this->applySorting($qb, $sortBy, $sortOrder);

        $items = $qb->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        // Convert to array format expected by controller
        return array_map(function($result) {
            return [
                0 => $result[0], // Item entity
                'username' => $result['username'],
                'email' => $result['email'],
                'like_count' => (int) $result['like_count']
            ];
        }, $items);
    }

    /**
     * Map filter field name to entity field name
     */
    private function mapFilterFieldToEntityField(string $field): ?string
    {
        $mapping = [
            'custom_string1' => 'customString1Value',
            'custom_string2' => 'customString2Value',
            'custom_string3' => 'customString3Value',
            'custom_text1' => 'customText1Value',
            'custom_text2' => 'customText2Value',
            'custom_text3' => 'customText3Value',
            'custom_int1' => 'customInt1Value',
            'custom_int2' => 'customInt2Value',
            'custom_int3' => 'customInt3Value',
            'custom_bool1' => 'customBool1Value',
            'custom_bool2' => 'customBool2Value',
            'custom_bool3' => 'customBool3Value',
            'custom_link1' => 'customLink1Value',
            'custom_link2' => 'customLink2Value',
            'custom_link3' => 'customLink3Value',
            'created_by' => 'createdBy',
            'created_at' => 'createdAt',
            'updated_at' => 'updatedAt',
            'custom_id' => 'customId',
        ];

        return $mapping[$field] ?? null;
    }

    /**
     * Apply sorting to query builder
     */
    private function applySorting($qb, string $sortBy, string $sortOrder): void
    {
        // Validate and sanitize sortOrder
        $sortOrder = strtoupper($sortOrder);
        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            $sortOrder = 'DESC';
        }

        switch ($sortBy) {
            case 'custom_id':
                $qb->orderBy('i.customId', $sortOrder);
                break;
            case 'created_at':
                $qb->orderBy('i.createdAt', $sortOrder);
                break;
            case 'updated_at':
                $qb->orderBy('i.updatedAt', $sortOrder);
                break;
            case 'created_by':
                $qb->orderBy('cb.username', $sortOrder);
                break;
            case 'likes':
                $qb->orderBy('like_count', $sortOrder);
                break;
            case 'custom_string1':
                $qb->orderBy('i.customString1Value', $sortOrder);
                break;
            case 'custom_string2':
                $qb->orderBy('i.customString2Value', $sortOrder);
                break;
            case 'custom_string3':
                $qb->orderBy('i.customString3Value', $sortOrder);
                break;
            case 'custom_int1':
                $qb->orderBy('i.customInt1Value', $sortOrder);
                break;
            case 'custom_int2':
                $qb->orderBy('i.customInt2Value', $sortOrder);
                break;
            case 'custom_int3':
                $qb->orderBy('i.customInt3Value', $sortOrder);
                break;
            default:
                $qb->orderBy('i.createdAt', 'DESC');
        }
    }

}
