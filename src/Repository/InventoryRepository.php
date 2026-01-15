<?php

namespace App\Repository;

use App\Entity\Inventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inventory>
 */
class InventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inventory::class);
    }

    /**
     * Search inventories by full-text search query
     */
    public function searchInventories(string $query, ?int $categoryId = null, ?array $tagIds = null, string $sortBy = 'created_at', string $sortOrder = 'DESC', int $limit = 20, int $offset = 0): array
    {
        // Use native SQL for full-text search
        $conn = $this->getEntityManager()->getConnection();

        $whereConditions = [];
        $parameters = [];
        $parameterTypes = [];

        // Full-text search if query provided
        if (!empty($query)) {
            $whereConditions[] = "(MATCH(i.title, i.description) AGAINST (:search_query IN NATURAL LANGUAGE MODE) OR i.title LIKE :like_query OR i.description LIKE :like_query)";
            $parameters['search_query'] = $query;
            $parameters['like_query'] = '%' . $query . '%';
            $parameterTypes['search_query'] = ParameterType::STRING;
            $parameterTypes['like_query'] = ParameterType::STRING;
        }

        // Filter by category
        if ($categoryId !== null) {
            $whereConditions[] = "i.category_id = :category_id";
            $parameters['category_id'] = $categoryId;
            $parameterTypes['category_id'] = ParameterType::INTEGER;
        }

        // Filter by tags
        if (!empty($tagIds)) {
            $placeholders = str_repeat('?,', count($tagIds) - 1) . '?';
            $whereConditions[] = "t.id IN ($placeholders)";
            // Add tag IDs directly to parameters array
            foreach ($tagIds as $tagId) {
                $parameters[] = $tagId;
                $parameterTypes[] = ParameterType::INTEGER;
            }
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Validate and sanitize sortOrder
        $sortOrder = strtoupper($sortOrder);
        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            $sortOrder = 'DESC';
        }

        // Sorting
        $orderBy = 'i.created_at DESC';
        switch ($sortBy) {
            case 'title':
                $orderBy = "i.title $sortOrder";
                break;
            case 'created_at':
                $orderBy = "i.created_at $sortOrder";
                break;
            case 'updated_at':
                $orderBy = "i.updated_at $sortOrder";
                break;
            case 'popularity':
                $orderBy = "item_count $sortOrder";
                break;
        }

        $sql = "
            SELECT i.*, u.username, u.email, c.name as category_name, COUNT(DISTINCT item.id) as item_count
            FROM inventory i
            LEFT JOIN user u ON i.creator_id = u.id
            LEFT JOIN category c ON i.category_id = c.id
            LEFT JOIN inventory_tags it ON i.id = it.inventory_id
            LEFT JOIN tags t ON it.tag_id = t.id
            LEFT JOIN items item ON i.id = item.inventory_id
            $whereClause
            GROUP BY i.id, u.username, u.email, c.name
            ORDER BY $orderBy
            LIMIT $limit OFFSET $offset
        ";

        $stmt = $conn->executeQuery($sql, $parameters, $parameterTypes);
        $results = $stmt->fetchAllAssociative();

        // Convert to array format expected by controller
        return array_map(function($row) {
            return [
                0 => (object) [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'imageUrl' => $row['image_url'],
                    'isPublic' => (bool) $row['is_public'],
                    'createdAt' => new \DateTime($row['created_at']),
                    'updatedAt' => $row['updated_at'] ? new \DateTime($row['updated_at']) : null,
                ],
                'username' => $row['username'],
                'email' => $row['email'],
                'category_name' => $row['category_name'],
                'item_count' => (int) $row['item_count']
            ];
        }, $results);
    }

    /**
     * Get popular inventories (by item count)
     */
    public function getPopularInventories(int $limit = 5): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT i.*, u.username, u.email, c.name as category_name, COUNT(item.id) as item_count
            FROM inventory i
            LEFT JOIN user u ON i.creator_id = u.id
            LEFT JOIN category c ON i.category_id = c.id
            LEFT JOIN items item ON i.id = item.inventory_id
            GROUP BY i.id, u.username, u.email, c.name
            ORDER BY item_count DESC
            LIMIT $limit
        ";

        $stmt = $conn->executeQuery($sql);
        $results = $stmt->fetchAllAssociative();

        // Convert to array format expected by controller (same as searchInventories)
        return array_map(function($row) {
            return [
                0 => (object) [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'imageUrl' => $row['image_url'],
                    'isPublic' => (bool) $row['is_public'],
                    'createdAt' => new \DateTime($row['created_at']),
                    'updatedAt' => $row['updated_at'] ? new \DateTime($row['updated_at']) : null,
                ],
                'username' => $row['username'],
                'email' => $row['email'],
                'category_name' => $row['category_name'],
                'item_count' => (int) $row['item_count']
            ];
        }, $results);
    }

    /**
     * Get latest inventories
     */
    public function getLatestInventories(int $limit = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT i.*, u.username, u.email, c.name as category_name
            FROM inventory i
            LEFT JOIN user u ON i.creator_id = u.id
            LEFT JOIN category c ON i.category_id = c.id
            ORDER BY i.created_at DESC
            LIMIT $limit
        ";

        $stmt = $conn->executeQuery($sql);
        $results = $stmt->fetchAllAssociative();

        // Convert to array format expected by controller (same as searchInventories)
        return array_map(function($row) {
            return [
                0 => (object) [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'imageUrl' => $row['image_url'],
                    'isPublic' => (bool) $row['is_public'],
                    'createdAt' => new \DateTime($row['created_at']),
                    'updatedAt' => $row['updated_at'] ? new \DateTime($row['updated_at']) : null,
                ],
                'username' => $row['username'],
                'email' => $row['email'],
                'category_name' => $row['category_name']
            ];
        }, $results);
    }

    /**
     * Count search results
     */
    public function countSearchResults(string $query = '', ?int $categoryId = null, ?array $tagIds = null): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $whereConditions = [];
        $parameters = [];
        $parameterTypes = [];

        // Full-text search if query provided
        if (!empty($query)) {
            $whereConditions[] = "(MATCH(i.title, i.description) AGAINST (:search_query IN NATURAL LANGUAGE MODE) OR i.title LIKE :like_query OR i.description LIKE :like_query)";
            $parameters['search_query'] = $query;
            $parameters['like_query'] = '%' . $query . '%';
            $parameterTypes['search_query'] = ParameterType::STRING;
            $parameterTypes['like_query'] = ParameterType::STRING;
        }

        // Filter by category
        if ($categoryId !== null) {
            $whereConditions[] = "i.category_id = :category_id";
            $parameters['category_id'] = $categoryId;
            $parameterTypes['category_id'] = ParameterType::INTEGER;
        }

        // Filter by tags
        if (!empty($tagIds)) {
            $placeholders = str_repeat('?,', count($tagIds) - 1) . '?';
            $whereConditions[] = "t.id IN ($placeholders)";
            // Add tag IDs directly to parameters array
            foreach ($tagIds as $tagId) {
                $parameters[] = $tagId;
                $parameterTypes[] = ParameterType::INTEGER;
            }
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT COUNT(DISTINCT i.id) as count
            FROM inventory i
            LEFT JOIN inventory_tags it ON i.id = it.inventory_id
            LEFT JOIN tags t ON it.tag_id = t.id
            $whereClause
        ";

        $stmt = $conn->executeQuery($sql, $parameters, $parameterTypes);
        $result = $stmt->fetchAssociative();

        return (int) $result['count'];
    }

    /**
     * Get inventories owned by a user
     */
    public function findByCreator(int $userId): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.creator', 'c')
            ->leftJoin('i.category', 'cat')
            ->leftJoin('i.items', 'item')
            ->addSelect('c.username, c.email, cat.name as category_name, COUNT(item.id) as item_count')
            ->where('i.creator = :userId')
            ->setParameter('userId', $userId)
            ->groupBy('i.id, c.username, c.email, cat.name')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get inventories where user has write access
     */
    public function findByWriteAccess(int $userId): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.creator', 'c')
            ->leftJoin('i.category', 'cat')
            ->leftJoin('i.items', 'item')
            ->leftJoin('i.accesses', 'acc')
            ->addSelect('c.username, c.email, cat.name as category_name, COUNT(item.id) as item_count')
            ->where('acc.user = :userId')
            ->setParameter('userId', $userId)
            ->groupBy('i.id, c.username, c.email, cat.name')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
