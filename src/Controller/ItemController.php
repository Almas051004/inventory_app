<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\Like;
use App\Repository\InventoryRepository;
use App\Repository\ItemRepository;
use App\Service\CacheService;
use App\Service\CustomIdGenerator;
use App\Service\ErrorHandlerService;
use App\Service\PreviewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/inventories/{inventoryId}/items')]
class ItemController extends AbstractController
{
    public function __construct(
        private ItemRepository $itemRepository,
        private InventoryRepository $inventoryRepository,
        private CustomIdGenerator $customIdGenerator,
        private PreviewService $previewService,
        private CacheService $cacheService,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private ErrorHandlerService $errorHandler
    ) {
    }

    /**
     * Отображает форму создания нового элемента или обрабатывает создание
     *
     * @param int $inventoryId ID инвентаря
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/create', name: 'item_create', methods: ['GET', 'POST'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function create(int $inventoryId, Request $request): Response
    {
        $inventory = $this->inventoryRepository->find($inventoryId);
        if (!$inventory) {
            throw $this->createNotFoundException($this->translator->trans('controller.item.inventory_not_found'));
        }

        $user = $this->getUser();
        if (!$this->hasItemAccess($inventory, $user)) {
            throw $this->createAccessDeniedException($this->translator->trans('controller.item.no_create_permission'));
        }

        if ($request->isMethod('POST')) {
            return $this->handleCreate($inventory, $request);
        }

        return $this->render('item/create.html.twig', [
            'inventory' => $inventory,
            'preview_service' => $this->previewService,
        ]);
    }

    /**
     * Отображает форму редактирования элемента или обрабатывает редактирование
     *
     * @param Item $item Элемент для редактирования
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/{id}/edit', name: 'item_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function edit(Item $item, Request $request): Response
    {
        $inventory = $item->getInventory();

        $user = $this->getUser();
        if (!$this->hasItemAccess($inventory, $user)) {
            throw $this->createAccessDeniedException($this->translator->trans('controller.item.no_edit_permission'));
        }

        if ($request->isMethod('POST')) {
            return $this->handleEdit($item, $request);
        }

        return $this->render('item/edit.html.twig', [
            'item' => $item,
            'inventory' => $inventory,
            'preview_service' => $this->previewService,
        ]);
    }

    /**
     * API для получения списка элементов инвентаря с фильтрацией и поиском
     *
     * @param int $inventoryId ID инвентаря
     * @param Request $request HTTP запрос
     * @return JsonResponse
     */
    #[Route('/api/items', name: 'item_api_list', methods: ['GET'])]
    public function apiList(int $inventoryId, Request $request): JsonResponse
    {
        $inventory = $this->inventoryRepository->find($inventoryId);
        if (!$inventory) {
            return new JsonResponse(['error' => 'Inventory not found'], 404);
        }

        $user = $this->getUser();
        if (!$this->hasItemReadAccess($inventory, $user)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 25);
        $sortBy = $request->query->get('sort', 'created_at');
        $sortOrder = $request->query->get('order', 'desc');

        if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $search = $request->query->get('search', '');

        $filters = [];
        $allowedFilterFields = [
            'custom_string1', 'custom_string2', 'custom_string3',
            'custom_text1', 'custom_text2', 'custom_text3',
            'custom_int1', 'custom_int2', 'custom_int3',
            'custom_bool1', 'custom_bool2', 'custom_bool3',
            'custom_link1', 'custom_link2', 'custom_link3',
            'created_by', 'created_at', 'updated_at', 'custom_id'
        ];

        foreach ($allowedFilterFields as $field) {
            $value = $request->query->get($field);
            if ($value !== null && $value !== '') {
                if (str_starts_with($field, 'custom_bool')) {
                    $filters[$field] = $value === '1';
                } elseif (str_starts_with($field, 'custom_int')) {
                    $filters[$field] = (int) $value;
                } else {
                    $filters[$field] = $value;
                }
            }
        }

        $offset = ($page - 1) * $limit;

        $items = $this->itemRepository->searchItems(
            $inventoryId,
            $search,
            $filters,
            $sortBy,
            strtoupper($sortOrder),
            $limit,
            $offset
        );

        $total = $this->itemRepository->countSearchResults($inventoryId, $search, $filters);

        $data = [];
        foreach ($items as $item) {
            $data[] = $this->formatItemData($item[0], $inventory);
        }

        return new JsonResponse([
            'items' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int) $total,
                'pages' => ceil($total / $limit),
            ],
        ]);
    }

    /**
     * API для создания нового элемента инвентаря
     *
     * @param int $inventoryId ID инвентаря
     * @param Request $request HTTP запрос
     * @return JsonResponse
     */
    #[Route('/api/create', name: 'item_api_create', methods: ['POST'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function apiCreate(int $inventoryId, Request $request): JsonResponse
    {
        $inventory = $this->inventoryRepository->find($inventoryId);
        if (!$inventory) {
            return new JsonResponse(['error' => 'Inventory not found'], 404);
        }

        $user = $this->getUser();
        if (!$this->hasItemAccess($inventory, $user)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse(['error' => $this->translator->trans('controller.item.invalid_json')], 400);
            }

            $item = new Item();
            $item->setInventory($inventory);
            $item->setCreatedBy($user);

            $customId = $this->customIdGenerator->generateCustomId($inventory);
            $item->setCustomId($customId);

            $this->populateItemFields($item, $inventory, $data);

            $this->entityManager->persist($item);
            $this->entityManager->flush();

            $this->cacheService->invalidateInventoryStats($inventoryId);
            $this->cacheService->invalidatePopularInventories();

            return new JsonResponse([
                'success' => true,
                'item' => $this->formatItemData($item, $inventory),
                'message' => $this->translator->trans('controller.item.created'),
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $this->translator->trans('controller.item.create_error', ['%error%' => $e->getMessage()])], 500);
        }
    }

    /**
     * API для массового удаления элементов инвентаря
     *
     * @param int $inventoryId ID инвентаря
     * @param Request $request HTTP запрос
     * @return JsonResponse
     */
    #[Route('/api/batch-delete', name: 'item_api_batch_delete', methods: ['DELETE'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function apiBatchDelete(int $inventoryId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse(['error' => $this->translator->trans('controller.item.invalid_json')], 400);
            }

            $itemIds = $data['item_ids'] ?? [];

            if (empty($itemIds)) {
                return new JsonResponse(['error' => $this->translator->trans('controller.item.batch_no_items')], 400);
            }

            $inventory = $this->inventoryRepository->find($inventoryId);
            if (!$inventory) {
                return new JsonResponse(['error' => 'Inventory not found'], 404);
            }

            $user = $this->getUser();
            if (!$this->hasItemAccess($inventory, $user)) {
                return new JsonResponse(['error' => 'Access denied'], 403);
            }

            $qb = $this->itemRepository->createQueryBuilder('i')
                ->where('i.inventory = :inventory')
                ->andWhere('i.id IN (:ids)')
                ->setParameter('inventory', $inventory)
                ->setParameter('ids', $itemIds);

            $items = $qb->getQuery()->getResult();

            if (empty($items)) {
                return new JsonResponse(['error' => $this->translator->trans('controller.item.batch_items_not_found')], 404);
            }

            foreach ($items as $item) {
                $this->entityManager->remove($item);
            }

            $this->entityManager->flush();

            $this->cacheService->invalidateInventoryStats($inventoryId);
            $this->cacheService->invalidatePopularInventories();

            return new JsonResponse([
                'success' => true,
                'deleted_count' => count($items),
                'message' => sprintf('Удалено элементов: %d', count($items)),
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $this->translator->trans('controller.item.batch_delete_error', ['%error%' => $e->getMessage()])], 500);
        }
    }

    /**
     * API для обновления элемента инвентаря
     *
     * @param Item $item Элемент для обновления
     * @param Request $request HTTP запрос
     * @return JsonResponse
     */
    #[Route('/api/{id}', name: 'item_api_update', methods: ['PUT'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function apiUpdate(Item $item, Request $request): JsonResponse
    {
        $inventory = $item->getInventory();

        $user = $this->getUser();
        if (!$this->hasItemAccess($inventory, $user)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse(['error' => $this->translator->trans('controller.item.invalid_json')], 400);
            }

            if (isset($data['version']) && $item->getVersion() !== (int)$data['version']) {
                return new JsonResponse([
                    'error' => $this->translator->trans('controller.item.concurrent_modification')
                ], 409);
            }

            $this->populateItemFields($item, $inventory, $data);

            $this->entityManager->flush();

            $this->cacheService->invalidateInventoryStats($inventory->getId());

            return new JsonResponse([
                'success' => true,
                'item' => $this->formatItemData($item, $inventory),
                'version' => $item->getVersion(),
                'message' => $this->translator->trans('controller.item.updated'),
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $this->translator->trans('controller.item.update_error', ['%error%' => $e->getMessage()])], 500);
        }
    }

    /**
     * API для удаления элемента инвентаря
     *
     * @param Item $item Элемент для удаления
     * @param Request $request HTTP запрос
     * @return JsonResponse
     */
    #[Route('/api/{id}', name: 'item_api_delete', methods: ['DELETE'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function apiDelete(Item $item, Request $request): JsonResponse
    {
        $inventory = $item->getInventory();

        $user = $this->getUser();
        if (!$this->hasItemAccess($inventory, $user)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            $version = $request->query->get('version');
            if ($version && $item->getVersion() !== (int)$version) {
                return new JsonResponse([
                    'error' => $this->translator->trans('controller.item.concurrent_modification_short')
                ], 409);
            }

            $this->entityManager->remove($item);
            $this->entityManager->flush();

            $this->cacheService->invalidateInventoryStats($inventory->getId());
            $this->cacheService->invalidatePopularInventories();

            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('controller.item.deleted'),
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $this->translator->trans('controller.item.delete_error', ['%error%' => $e->getMessage()])], 500);
        }
    }

    /**
     * Обрабатывает создание нового элемента инвентаря через веб-форму
     *
     * @param Inventory $inventory Инвентарь
     * @param Request $request HTTP запрос
     * @return Response
     */
    private function handleCreate(Inventory $inventory, Request $request): Response
    {
        $user = $this->getUser();
        $errors = [];
        $formData = $request->request->all();

        if (!$this->isCsrfTokenValid('create_item', $request->request->get('_token'))) {
            $errors[] = $this->translator->trans('controller.item.csrf_invalid');
        }

        if (empty($errors)) {
            try {
                $item = new Item();
                $item->setInventory($inventory);
                $item->setCreatedBy($user);

                $customId = $this->customIdGenerator->generateCustomId($inventory);
                $item->setCustomId($customId);

                $this->populateItemFields($item, $inventory, $request->request->all());

                $this->entityManager->persist($item);
                $this->entityManager->flush();

                $this->addFlash('success', $this->translator->trans('controller.item.created'));
                return $this->redirectToRoute('inventory_show', ['id' => $inventory->getId()]);

            } catch (\InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            } catch (\Doctrine\DBAL\Exception\DriverException $e) {
                $errors[] = $this->translator->trans('controller.item.database_error');
            } catch (\Exception $e) {
                $errorId = $this->errorHandler->logException($e, 'Item creation');
                $errors[] = $this->errorHandler->createUserFriendlyMessage($errorId);
            }
        }

        return $this->render('item/create.html.twig', [
            'inventory' => $inventory,
            'errors' => $errors,
            'formData' => $formData,
            'preview_service' => $this->previewService,
        ]);
    }

    /**
     * Обрабатывает редактирование элемента инвентаря через веб-форму
     *
     * @param Item $item Элемент для редактирования
     * @param Request $request HTTP запрос
     * @return Response
     */
    private function handleEdit(Item $item, Request $request): Response
    {
        $inventory = $item->getInventory();
        $errors = [];
        $formData = $request->request->all();

        if (!$this->isCsrfTokenValid('edit_item', $request->request->get('_token'))) {
            $errors[] = $this->translator->trans('controller.item.csrf_invalid');
        }

        if (empty($errors)) {
            try {
                if (isset($formData['version']) && $item->getVersion() !== (int)$formData['version']) {
                    $errors[] = $this->translator->trans('controller.item.concurrent_modification');
                } else {
                    $this->populateItemFields($item, $inventory, $formData);

                    $this->entityManager->flush();

                    $this->addFlash('success', $this->translator->trans('controller.item.updated'));
                    return $this->redirectToRoute('inventory_show', ['id' => $inventory->getId()]);
                }

            } catch (\InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            } catch (\Doctrine\DBAL\Exception\DriverException $e) {
                $errors[] = $this->translator->trans('controller.item.database_error');
            } catch (\Exception $e) {
                $errorId = $this->errorHandler->logException($e, 'Item update');
                $errors[] = $this->errorHandler->createUserFriendlyMessage($errorId);
            }
        }

        return $this->render('item/edit.html.twig', [
            'item' => $item,
            'inventory' => $inventory,
            'errors' => $errors,
            'formData' => $formData,
            'preview_service' => $this->previewService,
        ]);
    }

    /**
     * Заполняет кастомные поля элемента на основе данных формы
     *
     * @param Item $item Элемент
     * @param Inventory $inventory Инвентарь
     * @param array $data Данные формы
     * @return void
     */
    private function populateItemFields(Item $item, Inventory $inventory, array $data): void
    {
        for ($i = 1; $i <= 3; $i++) {
            if ($inventory->{"getCustomString{$i}State"}()) {
                $value = $data["custom_string{$i}_value"] ?? null;
                $this->validateStringField($value, $inventory, $i);
                $item->{"setCustomString{$i}Value"}($value);
            }

            if ($inventory->{"getCustomText{$i}State"}()) {
                $value = $data["custom_text{$i}_value"] ?? null;
                $item->{"setCustomText{$i}Value"}($value);
            }

            if ($inventory->{"getCustomInt{$i}State"}()) {
                $value = $data["custom_int{$i}_value"] ?? null;
                $value = $value !== null ? (int)$value : null;
                $this->validateIntField($value, $inventory, $i);
                $item->{"setCustomInt{$i}Value"}($value);
            }

            if ($inventory->{"getCustomBool{$i}State"}()) {
                $value = isset($data["custom_bool{$i}_value"]) && $data["custom_bool{$i}_value"] === 'on';
                $item->{"setCustomBool{$i}Value"}($value);
            }

            if ($inventory->{"getCustomLink{$i}State"}()) {
                $value = $data["custom_link{$i}_value"] ?? null;
                $item->{"setCustomLink{$i}Value"}($value);
            }
        }
    }

    /**
     * Форматирует данные элемента для API ответа
     *
     * @param Item $item Элемент
     * @param Inventory $inventory Инвентарь
     * @return array
     */
    private function formatItemData(Item $item, Inventory $inventory): array
    {
        $user = $this->getUser();
        $liked = false;

        if ($user) {
            $liked = $this->entityManager->getRepository(Like::class)->findOneBy([
                'user' => $user,
                'item' => $item,
            ]) !== null;
        }

        $data = [
            'id' => $item->getId(),
            'custom_id' => $item->getCustomId(),
            'created_at' => $item->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $item->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'created_by' => [
                'id' => $item->getCreatedBy()->getId(),
                'username' => $item->getCreatedBy()->getUsername(),
                'email' => $item->getCreatedBy()->getEmail(),
            ],
            'version' => $item->getVersion(),
            'likes_count' => $item->getLikes()->count(),
            'liked' => $liked,
        ];

        for ($i = 1; $i <= 3; $i++) {
            if ($inventory->{"getCustomString{$i}State"}()) {
                $data["custom_string{$i}_value"] = $item->{"getCustomString{$i}Value"}();
                $data["custom_string{$i}_name"] = $inventory->{"getCustomString{$i}Name"}();
            }

            if ($inventory->{"getCustomText{$i}State"}()) {
                $data["custom_text{$i}_value"] = $item->{"getCustomText{$i}Value"}();
                $data["custom_text{$i}_name"] = $inventory->{"getCustomText{$i}Name"}();
            }

            if ($inventory->{"getCustomInt{$i}State"}()) {
                $data["custom_int{$i}_value"] = $item->{"getCustomInt{$i}Value"}();
                $data["custom_int{$i}_name"] = $inventory->{"getCustomInt{$i}Name"}();
            }

            if ($inventory->{"getCustomBool{$i}State"}()) {
                $data["custom_bool{$i}_value"] = $item->{"getCustomBool{$i}Value"}();
                $data["custom_bool{$i}_name"] = $inventory->{"getCustomBool{$i}Name"}();
            }

            if ($inventory->{"getCustomLink{$i}State"}()) {
                $data["custom_link{$i}_value"] = $item->{"getCustomLink{$i}Value"}();
                $data["custom_link{$i}_name"] = $inventory->{"getCustomLink{$i}Name"}();
            }
        }

        return $data;
    }

    /**
     * Валидирует значение строкового поля
     *
     * @param string|null $value Значение поля
     * @param Inventory $inventory Инвентарь
     * @param int $fieldIndex Индекс поля (1-3)
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateStringField(?string $value, Inventory $inventory, int $fieldIndex): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $minLength = $inventory->{"getCustomString{$fieldIndex}MinLength"}();
        $maxLength = $inventory->{"getCustomString{$fieldIndex}MaxLength"}();
        $regex = $inventory->{"getCustomString{$fieldIndex}Regex"}();

        if ($minLength !== null && strlen($value) < $minLength) {
            throw new \InvalidArgumentException($this->translator->trans('controller.item.string_min_length', [
                '%field%' => $inventory->{"getCustomString{$fieldIndex}Name"}(),
                '%min%' => $minLength
            ]));
        }

        if ($maxLength !== null && strlen($value) > $maxLength) {
            throw new \InvalidArgumentException($this->translator->trans('controller.item.string_max_length', [
                '%field%' => $inventory->{"getCustomString{$fieldIndex}Name"}(),
                '%max%' => $maxLength
            ]));
        }

        if ($regex !== null && !preg_match($regex, $value)) {
            throw new \InvalidArgumentException($this->translator->trans('controller.item.string_regex', [
                '%field%' => $inventory->{"getCustomString{$fieldIndex}Name"}()
            ]));
        }
    }

    /**
     * Валидирует значение целочисленного поля
     *
     * @param int|null $value Значение поля
     * @param Inventory $inventory Инвентарь
     * @param int $fieldIndex Индекс поля (1-3)
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateIntField(?int $value, Inventory $inventory, int $fieldIndex): void
    {
        if ($value === null) {
            return;
        }

        $maxBigInt = 9223372036854775807;
        $minBigInt = -9223372036854775808;

        if ($value > $maxBigInt || $value < $minBigInt) {
            throw new \InvalidArgumentException($this->translator->trans('controller.item.int_value_out_of_range', [
                '%field%' => $inventory->{"getCustomInt{$fieldIndex}Name"}(),
            ]));
        }

        $minValue = $inventory->{"getCustomInt{$fieldIndex}MinValue"}();
        $maxValue = $inventory->{"getCustomInt{$fieldIndex}MaxValue"}();

        if ($minValue !== null && $value < $minValue) {
            throw new \InvalidArgumentException($this->translator->trans('controller.item.int_min_value', [
                '%field%' => $inventory->{"getCustomInt{$fieldIndex}Name"}(),
                '%min%' => $minValue
            ]));
        }

        if ($maxValue !== null && $value > $maxValue) {
            throw new \InvalidArgumentException($this->translator->trans('controller.item.int_max_value', [
                '%field%' => $inventory->{"getCustomInt{$fieldIndex}Name"}(),
                '%max%' => $maxValue
            ]));
        }
    }

    /**
     * Проверяет, имеет ли пользователь право на редактирование элементов инвентаря
     *
     * @param Inventory $inventory Инвентарь
     * @param mixed $user Пользователь (может быть null)
     * @return bool
     */
    private function hasItemAccess(Inventory $inventory, $user): bool
    {
        if ($inventory->getCreator() === $user) {
            return true;
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($inventory->isPublic()) {
            return true;
        }

        foreach ($inventory->getAccesses() as $access) {
            if ($access->getUser() === $user && $access->getAccessType() === 'write') {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверяет, имеет ли пользователь право на чтение элементов инвентаря
     *
     * @param Inventory $inventory Инвентарь
     * @param mixed $user Пользователь (может быть null)
     * @return bool
     */
    private function hasItemReadAccess(Inventory $inventory, $user): bool
    {
        if ($inventory->isPublic()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $this->hasItemAccess($inventory, $user);
    }

    /**
     * Добавляет или удаляет лайк к элементу
     *
     * @param Item $item Элемент
     * @return JsonResponse
     */
    #[Route('/api/{id}/like', name: 'item_api_like', methods: ['POST'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function apiLike(Item $item): JsonResponse
    {
        $user = $this->getUser();

        $existingLike = $this->entityManager->getRepository(Like::class)->findOneBy([
            'user' => $user,
            'item' => $item,
        ]);

        if ($existingLike) {
            $this->entityManager->remove($existingLike);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'liked' => false,
                'likes_count' => $item->getLikes()->count(),
                'message' => $this->translator->trans('controller.item.like_removed'),
            ]);
        } else {
            $like = new Like();
            $like->setUser($user);
            $like->setItem($item);

            $this->entityManager->persist($like);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'liked' => true,
                'likes_count' => $item->getLikes()->count(),
                'message' => $this->translator->trans('controller.item.like_added'),
            ]);
        }
    }

    /**
     * Получает статус лайка пользователя для элемента
     *
     * @param Item $item Элемент
     * @return JsonResponse
     */
    #[Route('/api/{id}/like/status', name: 'item_api_like_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function apiLikeStatus(Item $item): JsonResponse
    {
        $user = $this->getUser();

        $existingLike = $this->entityManager->getRepository(Like::class)->findOneBy([
            'user' => $user,
            'item' => $item,
        ]);

        return new JsonResponse([
            'liked' => $existingLike !== null,
            'likes_count' => $item->getLikes()->count(),
        ]);
    }
}