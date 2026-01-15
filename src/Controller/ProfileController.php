<?php

namespace App\Controller;

use App\Repository\InventoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private InventoryRepository $inventoryRepository
    ) {
    }

    /**
     * Отображает профиль пользователя с его инвентарями
     *
     * @return Response
     */
    #[Route('', name: 'app_profile')]
    public function index(): Response
    {
        $user = $this->getUser();

        $ownedInventories = $this->inventoryRepository->findByCreator($user->getId());

        $accessibleInventories = $this->inventoryRepository->findByWriteAccess($user->getId());

        return $this->render('profile/index.html.twig', [
            'owned_inventories' => $ownedInventories,
            'accessible_inventories' => $accessibleInventories,
        ]);
    }
}
