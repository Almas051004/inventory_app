<?php

namespace App\Controller;

use App\Repository\InventoryRepository;
use App\Repository\SalesforceIntegrationRepository;
use App\Service\Integration\Salesforce\SalesforceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private InventoryRepository $inventoryRepository,
        private SalesforceIntegrationRepository $salesforceIntegrationRepository,
        private SalesforceService $salesforceService
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

        $salesforceIntegration = $this->salesforceIntegrationRepository->findOneBy(['user' => $user]);

        return $this->render('profile/index.html.twig', [
            'owned_inventories' => $ownedInventories,
            'accessible_inventories' => $accessibleInventories,
            'salesforce_integration' => $salesforceIntegration,
        ]);
    }

    /**
     * Повторная синхронизация с Salesforce
     */
    #[Route('/salesforce/resync', name: 'salesforce_resync', methods: ['POST'])]
    public function resyncSalesforce(): JsonResponse
    {
        try {
            $user = $this->getUser();
            $integration = $this->salesforceIntegrationRepository->findOneBy(['user' => $user]);

            if (!$integration) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Salesforce integration not found'
                ], 404);
            }

            $accountId = $integration->getSalesforceAccountId();
            $contactId = $integration->getSalesforceContactId();
            $user = $this->getUser();

            $client = $this->salesforceService->getAuthorizedClient($integration);

            $accountExists = false;
            $contactExists = false;

            if ($accountId) {
                $accountDetails = $this->salesforceService->getAccountDetails($integration, $accountId);
                $accountExists = $accountDetails !== null;
            }

            if ($contactId) {
                $contactDetails = $this->salesforceService->getContactDetails($integration, $contactId);
                $contactExists = $contactDetails !== null;
            }

            if (!$accountExists) {
                $accountId = $this->salesforceService->createAccount($integration, 'Personal Account', null, null);
                $integration->setSalesforceAccountId($accountId);
            }

            if (!$contactExists) {
                $contactId = $this->salesforceService->createContact($integration, $accountId, $user, null, null);
                $integration->setSalesforceContactId($contactId);
            } elseif ($accountId && isset($contactDetails) && ($contactDetails['AccountId'] ?? null) !== $accountId) {
                $instanceUrl = $integration->getInstanceUrl();
                if ($instanceUrl) {
                    $this->salesforceService->updateContactAccount($client, $instanceUrl, $contactId, $accountId);
                }
            }

            $integration->setUpdatedAt(new \DateTimeImmutable());
            $this->salesforceIntegrationRepository->save($integration, true);

            return new JsonResponse([
                'success' => true,
                'message' => 'Salesforce resync completed successfully'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to resync with Salesforce'
            ], 500);
        }
    }

    /**
     * Удаление Salesforce интеграции для повторного подключения
     */
    #[Route('/salesforce/disconnect', name: 'salesforce_disconnect', methods: ['POST'])]
    public function disconnectSalesforce(): JsonResponse
    {
        try {
            $user = $this->getUser();
            $integration = $this->salesforceIntegrationRepository->findOneBy(['user' => $user]);

            if ($integration) {
                $this->salesforceIntegrationRepository->remove($integration, true);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Salesforce integration disconnected'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to disconnect Salesforce integration'
            ], 500);
        }
    }
}
