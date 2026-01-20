<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Inventory;
use App\Entity\InventoryAccess;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\InventoryAccessRepository;
use App\Repository\InventoryRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\CacheService;
use App\Service\ErrorHandlerService;
use App\Service\ExportService;
use App\Service\ImageUploadService;
use App\Service\MarkdownService;
use App\Service\StatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/inventories')]
class InventoryController extends AbstractController
{
    public function __construct(
        private InventoryRepository $inventoryRepository,
        private CategoryRepository $categoryRepository,
        private CommentRepository $commentRepository,
        private TagRepository $tagRepository,
        private UserRepository $userRepository,
        private InventoryAccessRepository $inventoryAccessRepository,
        private MarkdownService $markdownService,
        private StatisticsService $statisticsService,
        private ExportService $exportService,
        private CacheService $cacheService,
        private ImageUploadService $imageUploadService,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private ErrorHandlerService $errorHandler
    ) {
    }

    /**
     * Отображает форму создания нового инвентаря или обрабатывает создание
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/create', name: 'inventory_create')]
    #[IsGranted('EMAIL_VERIFIED')]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleCreate($request);
        }

        $categories = $this->categoryRepository->findAll();

        return $this->render('inventory/create.html.twig', [
            'categories' => $categories,
        ]);
    }

    /**
     * Обрабатывает создание нового инвентаря
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    private function handleCreate(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('create_inventory', $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('controller.create_inventory.invalid_csrf'));
            return $this->redirectToRoute('inventory_create');
        }

        try {
            $data = $request->request->all();

            if (empty($data['title'])) {
                $this->addFlash('error', $this->translator->trans('controller.create_inventory.title_required'));
                return $this->redirectToRoute('inventory_create');
            }

            if (empty($data['category_id'])) {
                $this->addFlash('error', $this->translator->trans('controller.create_inventory.category_required'));
                return $this->redirectToRoute('inventory_create');
            }

            $inventory = new Inventory();
            $inventory->setTitle(trim($data['title']));
            $inventory->setDescription($data['description'] ?? '');
            $inventory->setCreator($this->getUser());
            $inventory->setIsPublic(isset($data['is_public']) && $data['is_public'] === 'on');

            $category = $this->categoryRepository->find($data['category_id']);
            if (!$category) {
                $this->addFlash('error', $this->translator->trans('controller.create_inventory.category_not_found'));
                return $this->redirectToRoute('inventory_create');
            }
            $inventory->setCategory($category);

            $uploadedFile = $request->files->get('image');
            if ($uploadedFile) {
                $imageUrl = $this->imageUploadService->uploadImage($uploadedFile);
                if ($imageUrl) {
                    $inventory->setImageUrl($imageUrl);
                } else {
                    // Если изображение не загрузилось (например, дубликат), просто не устанавливаем его
                    $this->addFlash('warning', $this->translator->trans('controller.create_inventory.image_upload_warning'));
                }
            }

            if (!empty($data['tags'])) {
                $tagNames = array_map('trim', explode(',', $data['tags']));
                $tagNames = array_filter($tagNames);

                foreach ($tagNames as $tagName) {
                    if (empty($tagName)) continue;

                    $tag = $this->tagRepository->findOneBy(['name' => $tagName]);
                    if (!$tag) {
                        $tag = new Tag();
                        $tag->setName($tagName);
                        $this->entityManager->persist($tag);
                    }

                    $inventory->addTag($tag);
                }
            }

            $inventory->setVersion(1);

            $this->entityManager->persist($inventory);
            $this->entityManager->flush();

            $this->cacheService->invalidateLatestInventories();

            $this->addFlash('success', $this->translator->trans('controller.create_inventory.success'));

            return $this->redirectToRoute('inventory_show', ['id' => $inventory->getId()]);

        } catch (\Exception $e) {
            $errorId = $this->errorHandler->logException($e, 'Inventory creation');
            $this->addFlash('error', $this->translator->trans('controller.create_inventory.error', ['%error%' => 'Неизвестная ошибка. ID: ' . $errorId]));
            return $this->redirectToRoute('inventory_create');
        }
    }

    /**
     * API для получения списка тегов (для автодополнения)
     *
     * @param Request $request HTTP запрос
     * @return JsonResponse
     */
    #[Route('/api/tags', name: 'inventory_api_tags', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function apiTags(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 10);

        if (empty($query)) {
            $tags = $this->tagRepository->createQueryBuilder('t')
                ->leftJoin('t.inventories', 'i')
                ->groupBy('t.id')
                ->orderBy('COUNT(i.id)', 'DESC')
                ->addOrderBy('t.name', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } else {
            $tags = $this->tagRepository->createQueryBuilder('t')
                ->where('t.name LIKE :query')
                ->setParameter('query', '%' . $query . '%')
                ->orderBy('t.name', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }

        $tagNames = array_map(fn($tag) => $tag->getName(), $tags);

        return new JsonResponse($tagNames);
    }

    /**
     * Получает статистику инвентаря
     *
     * @param Inventory $inventory Инвентарь
     * @return JsonResponse
     */
    #[Route('/api/{id}/statistics', name: 'inventory_api_statistics', methods: ['GET'])]
    public function getStatistics(Inventory $inventory): JsonResponse
    {
        $user = $this->getUser();

        if (!$inventory->isPublic()) {
            $hasAccess = $inventory->getCreator() === $user ||
                        $this->isGranted('ROLE_ADMIN');

            if (!$hasAccess && $user !== null) {
                $hasAccess = $inventory->getAccesses()->exists(function($key, $access) use ($user) {
                    return $access->getUser() === $user;
                });
            }

            if (!$hasAccess) {
                return new JsonResponse(['error' => 'Access denied'], 403);
            }
        }

        $statistics = $this->cacheService->getCachedInventoryStats(
            $inventory->getId(),
            fn() => $this->statisticsService->getInventoryStatistics($inventory)
        );

        return new JsonResponse($statistics);
    }

    /**
     * Отображает страницу инвентаря
     *
     * @param Inventory $inventory Инвентарь
     * @return Response
     */
    #[Route('/{id}', name: 'inventory_show', requirements: ['id' => '\d+'])]
    public function show(Inventory $inventory): Response
    {
        $user = $this->getUser();

        if (!$inventory->isPublic()) {
            $hasAccess = false;

            if ($user !== null) {
                $hasAccess = $inventory->getCreator() === $user ||
                            $this->isGranted('ROLE_ADMIN');

                if (!$hasAccess) {
                    $hasAccess = $inventory->getAccesses()->exists(function($key, $access) use ($user) {
                        return $access->getUser() === $user && $access->getAccessType() === 'write';
                    });
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException($this->translator->trans('controller.access_denied'));
            }
        }

        $categories = $this->categoryRepository->findAll();

        return $this->render('inventory/show.html.twig', [
            'inventory' => $inventory,
            'categories' => $categories,
        ]);
    }

    /**
     * Экспортирует элементы инвентаря в CSV формате
     *
     * @param Inventory $inventory Инвентарь
     * @return Response
     */
    #[Route('/{id}/export/csv', name: 'inventory_export_csv', requirements: ['id' => '\d+'])]
    public function exportCsv(Inventory $inventory): Response
    {
        $user = $this->getUser();

        if (!$inventory->isPublic()) {
            $hasAccess = false;

            if ($user !== null) {
                $hasAccess = $inventory->getCreator() === $user ||
                            $this->isGranted('ROLE_ADMIN');

                if (!$hasAccess) {
                    $hasAccess = $inventory->getAccesses()->exists(function($key, $access) use ($user) {
                        return $access->getUser() === $user && $access->getAccessType() === 'write';
                    });
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException($this->translator->trans('controller.access_denied'));
            }
        }

        $csvContent = $this->exportService->exportItemsToCsv($inventory);

        $filename = sprintf('%s_items_%s.csv',
            preg_replace('/[^a-zA-Z0-9_-]/', '_', $inventory->getTitle()),
            date('Y-m-d_H-i-s')
        );

        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Экспортирует элементы инвентаря в Excel формате
     *
     * @param Inventory $inventory Инвентарь
     * @return Response
     */
    #[Route('/{id}/export/excel', name: 'inventory_export_excel', requirements: ['id' => '\d+'])]
    public function exportExcel(Inventory $inventory): Response
    {
        $user = $this->getUser();

        if (!$inventory->isPublic()) {
            $hasAccess = false;

            if ($user !== null) {
                $hasAccess = $inventory->getCreator() === $user ||
                            $this->isGranted('ROLE_ADMIN');

                if (!$hasAccess) {
                    $hasAccess = $inventory->getAccesses()->exists(function($key, $access) use ($user) {
                        return $access->getUser() === $user && $access->getAccessType() === 'write';
                    });
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException($this->translator->trans('controller.access_denied'));
            }
        }

        $excelContent = $this->exportService->exportItemsToExcel($inventory);

        $filename = sprintf('%s_items_%s.xls',
            preg_replace('/[^a-zA-Z0-9_-]/', '_', $inventory->getTitle()),
            date('Y-m-d_H-i-s')
        );

        return new Response($excelContent, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Обрабатывает настройку полей инвентаря
     *
     * @param Inventory $inventory Инвентарь
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/{id}/fields', name: 'inventory_fields', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function fields(Inventory $inventory, Request $request): Response
    {
        if ($inventory->getCreator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException($this->translator->trans('controller.fields_access_denied'));
        }

        if ($request->isMethod('POST')) {
            $this->handleFieldsSave($inventory, $request);
            return $this->redirectToRoute('inventory_show', ['id' => $inventory->getId(), '_fragment' => 'fields']);
        }

        return $this->redirectToRoute('inventory_show', ['id' => $inventory->getId(), '_fragment' => 'fields']);
    }

    /**
     * Обрабатывает настройку формата пользовательских ID инвентаря
     *
     * @param Inventory $inventory Инвентарь
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/{id}/custom-ids', name: 'inventory_custom_ids', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function customIds(Inventory $inventory, Request $request): Response
    {
        if ($inventory->getCreator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException($this->translator->trans('controller.custom_ids_access_denied'));
        }

        if ($request->isMethod('POST')) {
            $this->handleCustomIdsSave($inventory, $request);
            return $this->redirectToRoute('inventory_show', ['id' => $inventory->getId(), '_fragment' => 'custom-ids']);
        }

        return $this->redirectToRoute('inventory_show', ['id' => $inventory->getId(), '_fragment' => 'custom-ids']);
    }

    /**
     * Обрабатывает общие настройки инвентаря
     *
     * @param Inventory $inventory Инвентарь
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/{id}/general-settings', name: 'inventory_general_settings', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function generalSettings(Inventory $inventory, Request $request): Response
    {
        if ($inventory->getCreator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException($this->translator->trans('controller.settings_access_denied'));
        }

        if ($request->isMethod('POST')) {
            return $this->handleGeneralSettingsSave($inventory, $request);
        }

        $categories = $this->categoryRepository->findAll();

        return $this->redirectToRoute('inventory_show', ['id' => $inventory->getId(), '_fragment' => 'general-settings']);
    }

    /**
     * Обрабатывает сохранение общих настроек инвентаря
     *
     * @param Inventory $inventory Инвентарь
     * @param Request $request HTTP запрос
     * @return Response
     */
    private function handleGeneralSettingsSave(Inventory $inventory, Request $request): Response
    {
        $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if (!$isAjax && !$this->isCsrfTokenValid('save_general_settings', $request->request->get('_token'))) {
            if ($isAjax) {
                return new JsonResponse(['error' => $this->translator->trans('controller.create_inventory.invalid_csrf')], 400);
            }
            $this->addFlash('error', $this->translator->trans('controller.create_inventory.invalid_csrf'));
            return $this->redirectToRoute('inventory_general_settings', ['id' => $inventory->getId()]);
        }

        try {
            if ($isAjax) {
                $data = json_decode($request->getContent(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new JsonResponse(['error' => $this->translator->trans('controller.invalid_json')], 400);
                }
            } else {
                $data = $request->request->all();
            }

            if (empty($data['title'])) {
                $this->addFlash('error', $this->translator->trans('controller.create_inventory.title_required'));
                return $this->redirectToRoute('inventory_general_settings', ['id' => $inventory->getId()]);
            }

            if ($isAjax && isset($data['version'])) {
                if ($inventory->getVersion() !== (int)$data['version']) {
                    if ($isAjax) {
                        return new JsonResponse([
                            'error' => $this->translator->trans('controller.concurrent_modification')
                        ], 409);
                    }
                    $this->addFlash('error', $this->translator->trans('controller.concurrent_modification'));
                    return $this->redirectToRoute('inventory_general_settings', ['id' => $inventory->getId()]);
                }
            }

            $inventory->setTitle(trim($data['title']));
            $inventory->setDescription($data['description'] ?? '');
            $inventory->setIsPublic(isset($data['is_public']) && $data['is_public']);

            if (!empty($data['category_id'])) {
                $category = $this->categoryRepository->find($data['category_id']);
                if (!$category) {
                    $this->addFlash('error', $this->translator->trans('controller.create_inventory.category_not_found'));
                    return $this->redirectToRoute('inventory_general_settings', ['id' => $inventory->getId()]);
                }
                $inventory->setCategory($category);
            }

            $uploadedFile = $request->files->get('image');
            if ($uploadedFile) {
                $imageUrl = $this->imageUploadService->uploadImage($uploadedFile);
                if ($imageUrl) {
                    $inventory->setImageUrl($imageUrl);
                } else {
                    // Если изображение не загрузилось (например, дубликат), просто не устанавливаем его
                    $this->addFlash('warning', $this->translator->trans('controller.create_inventory.image_upload_warning'));
                }
            }

            foreach ($inventory->getTags() as $tag) {
                $inventory->removeTag($tag);
            }

            if (!empty($data['tags'])) {
                $tagNames = array_map('trim', explode(',', $data['tags']));
                $tagNames = array_filter($tagNames);

                foreach ($tagNames as $tagName) {
                    if (empty($tagName)) continue;

                    $tag = $this->tagRepository->findOneBy(['name' => $tagName]);
                    if (!$tag) {
                        $tag = new Tag();
                        $tag->setName($tagName);
                        $this->entityManager->persist($tag);
                    }

                    $inventory->addTag($tag);
                }
            }

            $inventory->setVersion($inventory->getVersion() + 1);

            $this->entityManager->flush();

            $this->cacheService->invalidatePopularTags();

            if ($isAjax) {
                return new JsonResponse([
                    'success' => true,
                    'version' => $inventory->getVersion(),
                    'message' => $this->translator->trans('controller.settings_saved')
                ]);
            }

            $this->addFlash('success', $this->translator->trans('controller.settings_saved'));

        } catch (\Exception $e) {
            $errorId = $this->errorHandler->logException($e, 'Inventory general settings save');
            $errorMessage = $this->errorHandler->createUserFriendlyMessage($errorId, 'Ошибка сохранения настроек');
            if ($isAjax) {
                return new JsonResponse(['error' => $errorMessage], 500);
            }
            $this->addFlash('error', $errorMessage);
        }

        if ($isAjax) {
            return new JsonResponse(['error' => $this->translator->trans('controller.unknown_error')], 500);
        }

        return $this->redirectToRoute('inventory_general_settings', ['id' => $inventory->getId()]);
    }

    /**
     * Обрабатывает сохранение формата пользовательских ID инвентаря
     *
     * @param Inventory $inventory Инвентарь
     * @param Request $request HTTP запрос
     * @return Response
     */
    private function handleCustomIdsSave(Inventory $inventory, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('save_custom_ids', $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('controller.create_inventory.invalid_csrf'));
            return $this->redirectToRoute('inventory_custom_ids', ['id' => $inventory->getId()]);
        }

        try {
            $formatData = $request->request->get('custom_id_format', '{}');
            $format = json_decode($formatData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addFlash('error', $this->translator->trans('controller.custom_ids_invalid_format'));
                return $this->redirectToRoute('inventory_custom_ids', ['id' => $inventory->getId()]);
            }

            $version = $request->request->get('version');
            if ($version && $inventory->getVersion() !== (int)$version) {
                $this->addFlash('error', $this->translator->trans('controller.concurrent_modification'));
                return $this->redirectToRoute('inventory_custom_ids', ['id' => $inventory->getId()]);
            }

            if (!isset($format['parts']) || !is_array($format['parts'])) {
                $format = ['parts' => []];
            }

            if (count($format['parts']) > 20) {
                $this->addFlash('error', $this->translator->trans('controller.custom_ids_too_many_elements'));
                return $this->redirectToRoute('inventory_custom_ids', ['id' => $inventory->getId()]);
            }

            $inventory->setCustomIdFormat($format);

            $inventory->setVersion($inventory->getVersion() + 1);

            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('controller.custom_ids_saved'));

        } catch (\Exception $e) {
            $errorId = $this->errorHandler->logException($e, 'Custom IDs save');
            $this->addFlash('error', $this->errorHandler->createUserFriendlyMessage($errorId, 'Ошибка сохранения формата ID'));
        }

        return $this->redirectToRoute('inventory_custom_ids', ['id' => $inventory->getId()]);
    }

    /**
     * Обрабатывает сохранение полей инвентаря
     *
     * @param Inventory $inventory Инвентарь
     * @param Request $request HTTP запрос
     * @return Response
     */
    private function handleFieldsSave(Inventory $inventory, Request $request): Response
    {
        $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if (!$isAjax && !$this->isCsrfTokenValid('save_fields', $request->request->get('_token'))) {
            if ($isAjax) {
                return new JsonResponse(['error' => $this->translator->trans('controller.create_inventory.invalid_csrf')], 400);
            }
            $this->addFlash('error', $this->translator->trans('controller.create_inventory.invalid_csrf'));
            return $this->redirectToRoute('inventory_fields', ['id' => $inventory->getId()]);
        }

        try {
            if ($isAjax) {
                $fieldData = json_decode($request->getContent(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new JsonResponse(['error' => $this->translator->trans('controller.invalid_json')], 400);
                }
            } else {
                $fieldData = $request->request->all();
            }

            if ($isAjax && isset($fieldData['version'])) {
                if ($inventory->getVersion() !== (int)$fieldData['version']) {
                    if ($isAjax) {
                        return new JsonResponse([
                            'error' => $this->translator->trans('controller.concurrent_modification')
                        ], 409);
                    }
                    $this->addFlash('error', $this->translator->trans('controller.concurrent_modification'));
                    return $this->redirectToRoute('inventory_fields', ['id' => $inventory->getId()]);
                }
            }

            $fieldCounts = [
                'string' => 0,
                'text' => 0,
                'int' => 0,
                'bool' => 0,
                'link' => 0,
            ];

            for ($i = 1; $i <= 3; $i++) {
                foreach (['string', 'text', 'int', 'bool', 'link'] as $type) {
                    $stateKey = "custom_{$type}{$i}_state";
                    $nameKey = "custom_{$type}{$i}_name";
                    $descriptionKey = "custom_{$type}{$i}_description";
                    $showInTableKey = "custom_{$type}{$i}_show_in_table";

                    $isEnabled = isset($fieldData[$stateKey]) && $fieldData[$stateKey] === 'on';

                    if ($isEnabled) {
                        $fieldCounts[$type]++;
                    }

                    $inventory->{"setCustom{$type}{$i}State"}($isEnabled);
                    $inventory->{"setCustom{$type}{$i}Name"}($isEnabled ? ($fieldData[$nameKey] ?? '') : null);
                    $inventory->{"setCustom{$type}{$i}Description"}($isEnabled ? ($fieldData[$descriptionKey] ?? '') : null);
                    $inventory->{"setCustom{$type}{$i}ShowInTable"}($isEnabled ? (isset($fieldData[$showInTableKey]) && $fieldData[$showInTableKey] === 'on') : false);

                    if ($type === 'string') {
                        $minLengthKey = "custom_{$type}{$i}_min_length";
                        $maxLengthKey = "custom_{$type}{$i}_max_length";
                        $regexKey = "custom_{$type}{$i}_regex";

                        $minLength = $isEnabled && isset($fieldData[$minLengthKey]) && !empty($fieldData[$minLengthKey]) ? (int)$fieldData[$minLengthKey] : null;
                        $maxLength = $isEnabled && isset($fieldData[$maxLengthKey]) && !empty($fieldData[$maxLengthKey]) ? (int)$fieldData[$maxLengthKey] : null;
                        $regex = $isEnabled && isset($fieldData[$regexKey]) && !empty($fieldData[$regexKey]) ? $fieldData[$regexKey] : null;

                        $inventory->{"setCustom{$type}{$i}MinLength"}($minLength);
                        $inventory->{"setCustom{$type}{$i}MaxLength"}($maxLength);
                        $inventory->{"setCustom{$type}{$i}Regex"}($regex);
                    } elseif ($type === 'int') {
                        $minValueKey = "custom_{$type}{$i}_min_value";
                        $maxValueKey = "custom_{$type}{$i}_max_value";

                        $minValue = $isEnabled && isset($fieldData[$minValueKey]) && !empty($fieldData[$minValueKey]) ? (int)$fieldData[$minValueKey] : null;
                        $maxValue = $isEnabled && isset($fieldData[$maxValueKey]) && !empty($fieldData[$maxValueKey]) ? (int)$fieldData[$maxValueKey] : null;

                        $inventory->{"setCustom{$type}{$i}MinValue"}($minValue);
                        $inventory->{"setCustom{$type}{$i}MaxValue"}($maxValue);
                    }
                }
            }

            foreach ($fieldCounts as $type => $count) {
                if ($count > 3) {
                    $this->addFlash('error', $this->translator->trans('controller.fields_max_limit', ['%type%' => $type]));
                    return $this->redirectToRoute('inventory_fields', ['id' => $inventory->getId()]);
                }
            }

            $inventory->setVersion($inventory->getVersion() + 1);

            $this->entityManager->flush();

            if ($isAjax) {
                return new JsonResponse([
                    'success' => true,
                    'version' => $inventory->getVersion(),
                    'message' => $this->translator->trans('controller.fields_saved')
                ]);
            }

            $this->addFlash('success', $this->translator->trans('controller.fields_saved'));

        } catch (\Exception $e) {
            $errorId = $this->errorHandler->logException($e, 'Inventory fields save');
            $errorMessage = $this->errorHandler->createUserFriendlyMessage($errorId, 'Ошибка сохранения полей');
            if ($isAjax) {
                return new JsonResponse(['error' => $errorMessage], 500);
            }
            $this->addFlash('error', $errorMessage);
        }

        if ($isAjax) {
            return new JsonResponse(['error' => $this->translator->trans('controller.unknown_error')], 500);
        }

        return $this->redirectToRoute('inventory_fields', ['id' => $inventory->getId()]);
    }

    /**
     * API для получения списка пользователей для предоставления доступа к инвентарю
     *
     * @param Request $request HTTP запрос
     * @param Inventory $inventory Инвентарь
     * @return JsonResponse
     */
    #[Route('/api/{id}/users', name: 'inventory_api_users', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function apiUsers(Request $request, Inventory $inventory): JsonResponse
    {
        if ($inventory->getCreator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => $this->translator->trans('ajax.error_access_denied')], 403);
        }

        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 10);

        $qb = $this->userRepository->createQueryBuilder('u');

        if (!empty($query)) {
            $qb->where('u.username LIKE :query OR u.email LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        $qb->andWhere('u.id != :currentUser')
           ->setParameter('currentUser', $this->getUser()->getId());

        $existingUserIds = $inventory->getAccesses()->map(fn($access) => $access->getUser()->getId())->toArray();
        if (!empty($existingUserIds)) {
            $qb->andWhere('u.id NOT IN (:existingUsers)')
               ->setParameter('existingUsers', $existingUserIds);
        }

        $users = $qb->setMaxResults($limit)
                   ->getQuery()
                   ->getResult();

        $userData = array_map(function($user) {
            return [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'display_name' => $user->getUsername() ?: $user->getEmail(),
            ];
        }, $users);

        return new JsonResponse($userData);
    }

    /**
     * Получает список пользователей, имеющих доступ к инвентарю
     *
     * @param Inventory $inventory Инвентарь
     * @return JsonResponse
     */
    #[Route('/api/{id}/access-list', name: 'inventory_api_access_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAccessList(Inventory $inventory): JsonResponse
    {
        if ($inventory->getCreator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => $this->translator->trans('ajax.error_access_denied')], 403);
        }

        $accesses = $inventory->getAccesses()->map(function($access) {
            $user = $access->getUser();
            return [
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'display_name' => $user->getUsername() ?: $user->getEmail(),
                ],
                'access_type' => $access->getAccessType(),
            ];
        })->toArray();

        return new JsonResponse([
            'success' => true,
            'accesses' => $accesses,
        ]);
    }

    /**
     * Предоставляет доступ к инвентарю для пользователя
     *
     * @param Request $request HTTP запрос
     * @param Inventory $inventory Инвентарь
     * @return JsonResponse
     */
    #[Route('/api/{id}/access', name: 'inventory_api_access', methods: ['POST'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function addAccess(Request $request, Inventory $inventory): JsonResponse
    {
        if ($inventory->getCreator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => $this->translator->trans('ajax.error_access_denied')], 403);
        }

        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $accessType = $data['access_type'] ?? 'write';

        if (!$userId) {
            return new JsonResponse(['error' => $this->translator->trans('controller.user_id_required')], 400);
        }

        if (!in_array($accessType, ['read', 'write'])) {
            $accessType = 'write';
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return new JsonResponse(['error' => $this->translator->trans('controller.user_not_found')], 404);
        }

        $existingAccess = $this->inventoryAccessRepository->findOneBy([
            'inventory' => $inventory,
            'user' => $user,
        ]);

        if ($existingAccess) {
            return new JsonResponse(['error' => $this->translator->trans('controller.user_already_has_access')], 400);
        }

        $access = new InventoryAccess();
        $access->setInventory($inventory);
        $access->setUser($user);
        $access->setAccessType($accessType);

        $this->entityManager->persist($access);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'display_name' => $user->getUsername() ?: $user->getEmail(),
            ],
        ]);
    }

    /**
     * Удаляет доступ к инвентарю для пользователя
     *
     * @param Request $request HTTP запрос
     * @param Inventory $inventory Инвентарь
     * @return JsonResponse
     */
    #[Route('/api/{id}/access', name: 'inventory_api_remove_access', methods: ['DELETE'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function removeAccess(Request $request, Inventory $inventory): JsonResponse
    {
        if ($inventory->getCreator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => $this->translator->trans('ajax.error_access_denied')], 403);
        }

        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            return new JsonResponse(['error' => $this->translator->trans('controller.user_id_required')], 400);
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $access = $this->inventoryAccessRepository->findOneBy([
            'inventory' => $inventory,
            'user' => $user,
        ]);

        if (!$access) {
            return new JsonResponse(['error' => $this->translator->trans('controller.access_not_found')], 404);
        }

        $this->entityManager->remove($access);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Получает список комментариев к инвентарю
     *
     * @param Inventory $inventory Инвентарь
     * @return JsonResponse
     */
    #[Route('/api/{id}/comments', name: 'inventory_api_comments', methods: ['GET'])]
    public function getComments(Inventory $inventory): JsonResponse
    {
        if (!$inventory->isPublic() &&
            $inventory->getCreator() !== $this->getUser() &&
            !$this->inventoryAccessRepository->hasAccess($inventory, $this->getUser()) &&
            !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => $this->translator->trans('ajax.error_access_denied')], 403);
        }

        $comments = $this->commentRepository->findBy(
            ['inventory' => $inventory],
            ['createdAt' => 'ASC']
        );

        $commentsData = [];
        foreach ($comments as $comment) {
            $commentsData[] = [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'content_html' => $this->markdownService->parseSafe($comment->getContent()),
                'created_at' => $comment->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $comment->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'user' => [
                    'id' => $comment->getUser()->getId(),
                    'username' => $comment->getUser()->getUsername(),
                    'email' => $comment->getUser()->getEmail(),
                    'display_name' => $comment->getUser()->getUsername() ?: $comment->getUser()->getEmail(),
                ],
                'can_delete' => $comment->getUser() === $this->getUser() || $this->isGranted('ROLE_ADMIN'),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'comments' => $commentsData,
        ]);
    }

    /**
     * Добавляет комментарий к инвентарю
     *
     * @param Request $request HTTP запрос
     * @param Inventory $inventory Инвентарь
     * @return JsonResponse
     */
    #[Route('/api/{id}/comments', name: 'inventory_api_add_comment', methods: ['POST'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function addComment(Request $request, Inventory $inventory): JsonResponse
    {
        if (!$inventory->isPublic() &&
            $inventory->getCreator() !== $this->getUser() &&
            !$this->inventoryAccessRepository->hasAccess($inventory, $this->getUser()) &&
            !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => $this->translator->trans('ajax.error_access_denied')], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = trim($data['content'] ?? '');

        if (empty($content)) {
            return new JsonResponse(['error' => $this->translator->trans('controller.comment_empty')], 400);
        }

        if (strlen($content) > 10000) {
            return new JsonResponse(['error' => $this->translator->trans('controller.comment_too_long')], 400);
        }

        $comment = new Comment();
        $comment->setInventory($inventory);
        $comment->setUser($this->getUser());
        $comment->setContent($content);

        try {
            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'comment' => [
                    'id' => $comment->getId(),
                    'content' => $comment->getContent(),
                    'content_html' => $this->markdownService->parseSafe($comment->getContent()),
                    'created_at' => $comment->getCreatedAt()->format('Y-m-d H:i:s'),
                    'user' => [
                        'id' => $comment->getUser()->getId(),
                        'username' => $comment->getUser()->getUsername(),
                        'email' => $comment->getUser()->getEmail(),
                        'display_name' => $comment->getUser()->getUsername() ?: $comment->getUser()->getEmail(),
                    ],
                    'can_delete' => true,
                ],
            ]);
        } catch (\Exception $e) {
            $errorId = $this->errorHandler->logException($e, 'Comment save');
            $errorMessage = $this->errorHandler->createUserFriendlyMessage($errorId, 'Ошибка сохранения комментария');
            return new JsonResponse(['error' => $errorMessage], 500);
        }
    }

    /**
     * Удаляет комментарий
     *
     * @param Comment $comment Комментарий
     * @return JsonResponse
     */
    #[Route('/api/comments/{id}', name: 'inventory_api_delete_comment', methods: ['DELETE'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function deleteComment(Comment $comment): JsonResponse
    {
        if ($comment->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => $this->translator->trans('ajax.error_access_denied')], 403);
        }

        try {
            $this->entityManager->remove($comment);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $errorId = $this->errorHandler->logException($e, 'Comment delete');
            $errorMessage = $this->errorHandler->createUserFriendlyMessage($errorId, 'Ошибка удаления комментария');
            return new JsonResponse(['error' => $errorMessage], 500);
        }
    }

    /**
     * Обновляет статус публичного доступа к инвентарю
     *
     * @param Request $request HTTP запрос
     * @param Inventory $inventory Инвентарь
     * @return JsonResponse
     */
    #[Route('/api/{id}/public-access', name: 'inventory_api_public_access', methods: ['POST'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function updatePublicAccess(Request $request, Inventory $inventory): JsonResponse
    {
        if ($inventory->getCreator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => $this->translator->trans('ajax.error_access_denied')], 403);
        }

        $data = json_decode($request->getContent(), true);
        $isPublic = $data['is_public'] ?? false;

        if (isset($data['version']) && $inventory->getVersion() !== (int)$data['version']) {
            return new JsonResponse([
                'error' => $this->translator->trans('controller.concurrent_modification')
            ], 409);
        }

        try {
            $inventory->setIsPublic((bool) $isPublic);

            $inventory->setVersion($inventory->getVersion() + 1);

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'is_public' => $inventory->isPublic(),
                'version' => $inventory->getVersion(),
            ]);
        } catch (\Exception $e) {
            $errorId = $this->errorHandler->logException($e, 'Public access update');
            $errorMessage = $this->errorHandler->createUserFriendlyMessage($errorId, 'Ошибка обновления публичного доступа');
            return new JsonResponse(['error' => $errorMessage], 500);
        }
    }

    /**
     * Удаляет инвентарь и все связанные данные
     *
     * @param Inventory $inventory Инвентарь
     * @param Request $request HTTP запрос
     * @return JsonResponse
     */
    #[Route('/api/{id}', name: 'inventory_api_delete', methods: ['DELETE'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function delete(Inventory $inventory, Request $request): JsonResponse
    {
        if ($inventory->getCreator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => $this->translator->trans('ajax.error_access_denied')], 403);
        }

        try {
            foreach ($inventory->getComments() as $comment) {
                $this->entityManager->remove($comment);
            }

            foreach ($inventory->getItems() as $item) {
                foreach ($item->getLikes() as $like) {
                    $this->entityManager->remove($like);
                }
                $this->entityManager->remove($item);
            }

            foreach ($inventory->getAccesses() as $access) {
                $this->entityManager->remove($access);
            }

            $inventory->getTags()->clear();

            // Удаляем изображение только если оно хранится локально (начинается с '/')
            if ($inventory->getImageUrl() && str_starts_with($inventory->getImageUrl(), '/')) {
                $imagePath = $this->getParameter('kernel.project_dir') . '/public' . $inventory->getImageUrl();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $this->entityManager->remove($inventory);
            $this->entityManager->flush();

            $this->cacheService->invalidateLatestInventories();
            $this->cacheService->invalidatePopularTags();

            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('controller.inventory_deleted')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $this->translator->trans('controller.inventory_delete_error', ['%error%' => $e->getMessage()])
            ], 500);
        }
    }

    /**
     * Удаляет несколько инвентарей одновременно
     *
     * @param Request $request HTTP запрос
     * @return JsonResponse
     */
    #[Route('/api/batch-delete', name: 'inventory_api_batch_delete', methods: ['DELETE'])]
    #[IsGranted('EMAIL_VERIFIED')]
    public function batchDelete(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $inventoryIds = $data['inventory_ids'] ?? [];

        if (empty($inventoryIds) || !is_array($inventoryIds)) {
            return new JsonResponse(['error' => $this->translator->trans('controller.inventory_ids_required')], 400);
        }

        $user = $this->getUser();
        $deletedCount = 0;
        $errors = [];

        try {
            foreach ($inventoryIds as $inventoryId) {
                $inventory = $this->inventoryRepository->find($inventoryId);

                if (!$inventory) {
                    $errors[] = $this->translator->trans('controller.inventory_not_found', ['%id%' => $inventoryId]);
                    continue;
                }

                if ($inventory->getCreator() !== $user && !$this->isGranted('ROLE_ADMIN')) {
                    $errors[] = $this->translator->trans('controller.inventory_delete_access_denied', ['%title%' => $inventory->getTitle()]);
                    continue;
                }

                foreach ($inventory->getComments() as $comment) {
                    $this->entityManager->remove($comment);
                }

                foreach ($inventory->getItems() as $item) {
                    foreach ($item->getLikes() as $like) {
                        $this->entityManager->remove($like);
                    }
                    $this->entityManager->remove($item);
                }

                foreach ($inventory->getAccesses() as $access) {
                    $this->entityManager->remove($access);
                }

                $inventory->getTags()->clear();

                // Удаляем изображение только если оно хранится локально (начинается с '/')
                if ($inventory->getImageUrl() && str_starts_with($inventory->getImageUrl(), '/')) {
                    $imagePath = $this->getParameter('kernel.project_dir') . '/public' . $inventory->getImageUrl();
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }

                $this->entityManager->remove($inventory);
                $deletedCount++;
            }

            $this->entityManager->flush();

            $this->cacheService->invalidateLatestInventories();
            $this->cacheService->invalidatePopularTags();

            $response = [
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => $this->translator->trans('controller.inventories_deleted', ['%count%' => $deletedCount])
            ];

            if (!empty($errors)) {
                $response['warnings'] = $errors;
            }

            return new JsonResponse($response);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $this->translator->trans('controller.inventories_delete_error', ['%error%' => $e->getMessage()])
            ], 500);
        }
    }
}
