<?php

namespace App\Controller;

use App\Repository\InventoryRepository;
use App\Repository\TagRepository;
use App\Service\CacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private CacheService $cacheService
    ) {
    }

    /**
     * Отображает главную страницу с последними и популярными инвентарями
     *
     * @param InventoryRepository $inventoryRepository Репозиторий для работы с инвентарями
     * @param TagRepository $tagRepository Репозиторий для работы с тегами
     * @return Response
     */
    #[Route('/', name: 'app_home')]
    public function index(InventoryRepository $inventoryRepository, TagRepository $tagRepository): Response
    {
        $latestInventories = $this->cacheService->getCachedLatestInventories(
            fn() => $inventoryRepository->getLatestInventories(10)
        );

        $popularInventories = $this->cacheService->getCachedPopularInventories(
            fn() => $inventoryRepository->getPopularInventories(5)
        );

        $popularTags = $this->cacheService->getCachedPopularTags(
            fn() => $tagRepository->createQueryBuilder('t')
                ->leftJoin('t.inventories', 'i')
                ->addSelect('COUNT(i.id) as inventory_count')
                ->groupBy('t.id')
                ->orderBy('inventory_count', 'DESC')
                ->setMaxResults(30)
                ->getQuery()
                ->getResult()
        );

        return $this->render('home/index.html.twig', [
            'latestInventories' => $latestInventories,
            'popularInventories' => $popularInventories,
            'popularTags' => $popularTags,
        ]);
    }

    /**
     * Устанавливает локаль приложения для текущего пользователя
     *
     * @param Request $request HTTP запрос
     * @param string $locale Код локали (en или ru)
     * @return Response
     */
    #[Route('/set-locale/{locale}', name: 'app_set_locale')]
    public function setLocale(Request $request, string $locale): Response
    {
        if (!in_array($locale, ['en', 'ru'])) {
            $locale = 'ru';
        }

        $request->getSession()->set('_locale', $locale);

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_home');
    }

    /**
     * Отображает страницу ошибки 401 (Не авторизован)
     *
     * @return Response
     */
    #[Route('/error/401', name: 'app_error_401')]
    public function error401(): Response
    {
        $response = $this->render('bundles/TwigBundle/Exception/error401.html.twig');
        $response->setStatusCode(401);
        return $response;
    }

    /**
     * Отображает страницу ошибки 403 (Доступ запрещен)
     *
     * @return Response
     */
    #[Route('/error/403', name: 'app_error_403')]
    public function error403(): Response
    {
        $response = $this->render('bundles/TwigBundle/Exception/error403.html.twig');
        $response->setStatusCode(403);
        return $response;
    }
}
