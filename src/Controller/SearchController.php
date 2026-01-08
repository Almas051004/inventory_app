<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\InventoryRepository;
use App\Repository\ItemRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    public function __construct(
        private InventoryRepository $inventoryRepository,
        private ItemRepository $itemRepository,
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
    ) {}

    /**
     * Выполняет поиск по инвентарям и элементам
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/search', name: 'app_search')]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $type = $request->query->get('type', 'all');
        $categoryId = $request->query->get('category');
        $tagIds = $request->query->all('tags', []);
        $sortBy = $request->query->get('sort', 'created_at');
        $sortOrder = $request->query->get('order', 'DESC');

        if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $sortOrder = 'DESC';
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $tagIds = array_map('intval', array_filter($tagIds));

        $results = [];
        $totalResults = 0;

        if ($type === 'inventories' || $type === 'all') {
            $inventories = $this->inventoryRepository->searchInventories(
                $query,
                $categoryId ? (int) $categoryId : null,
                $tagIds,
                $sortBy,
                $sortOrder,
                $limit,
                $offset
            );

            if ($type === 'inventories') {
                $totalResults = $this->inventoryRepository->countSearchResults(
                    $query,
                    $categoryId ? (int) $categoryId : null,
                    $tagIds
                );
                $results['inventories'] = $inventories;
            } else {
                $inventoryCount = $this->inventoryRepository->countSearchResults(
                    $query,
                    $categoryId ? (int) $categoryId : null,
                    $tagIds
                );
                $results['inventories'] = $inventories;
                $totalResults += $inventoryCount;
            }
        }

        $categories = $this->categoryRepository->findAll();
        $popularTags = $this->tagRepository->findBy([], ['id' => 'DESC'], 20);

        $currentTagObjects = [];
        if (!empty($tagIds)) {
            $currentTagObjects = $this->tagRepository->createQueryBuilder('t')
                ->where('t.id IN (:ids)')
                ->setParameter('ids', $tagIds)
                ->getQuery()
                ->getResult();
        }

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'totalResults' => $totalResults,
            'categories' => $categories,
            'popularTags' => $popularTags,
            'currentCategory' => $categoryId,
            'currentTags' => $tagIds,
            'currentTagObjects' => $currentTagObjects,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Предоставляет предложения поиска по инвентарям
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/api/search/suggestions', name: 'app_search_suggestions', methods: ['GET'])]
    public function suggestions(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $limit = 5;

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $inventories = $this->inventoryRepository->searchInventories($query, null, null, 'created_at', 'DESC', $limit, 0);

        $suggestions = [];
        foreach ($inventories as $inventory) {
            $suggestions[] = [
                'type' => 'inventory',
                'title' => $inventory[0]->getTitle(),
                'description' => $inventory[0]->getDescription(),
                'url' => $this->generateUrl('app_inventory_show', ['id' => $inventory[0]->getId()]),
                'creator' => $inventory['username'] ?? '',
            ];
        }

        return $this->json($suggestions);
    }

    /**
     * Выполняет поиск по тегам
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/api/tags/search', name: 'app_tags_search', methods: ['GET'])]
    public function searchTags(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $limit = 10;

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $tags = $this->tagRepository->createQueryBuilder('t')
            ->where('t.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($tags as $tag) {
            $results[] = [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
            ];
        }

        return $this->json($results);
    }
}
