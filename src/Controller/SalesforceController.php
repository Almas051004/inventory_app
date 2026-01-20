<?php

namespace App\Controller;

use App\Service\Integration\Salesforce\SalesforceService;
use App\Repository\SalesforceIntegrationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
class SalesforceController extends AbstractController
{
    public function __construct(
        private SalesforceService $salesforceService,
        private SalesforceIntegrationRepository $salesforceIntegrationRepository,
        private TranslatorInterface $translator,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/auth/salesforce/connect', name: 'salesforce_connect')]
    public function connect(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->salesforceService->isEnabled()) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $authUrl = $this->salesforceService->getAuthorizationUrl($user);

        return $this->redirect($authUrl);
    }

    #[Route('/auth/salesforce/callback', name: 'salesforce_callback')]
    public function callback(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->salesforceService->isEnabled()) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $code = $request->query->get('code');
        if (!$code) {
            return $this->redirectToRoute('app_profile');
        }

        $this->salesforceService->exchangeCodeForTokens($user, $code);

        return $this->redirectToRoute('salesforce_sync');
    }

    #[Route('/auth/salesforce/sync', name: 'salesforce_sync', methods: ['GET', 'POST'])]
    public function sync(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->salesforceService->isEnabled()) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $integration = $this->salesforceIntegrationRepository->findOneBy(['user' => $user]);
        if (!$integration) {
            $this->addFlash('error', $this->translator->trans('salesforce.sync.integration_not_found'));
            return $this->redirectToRoute('app_profile');
        }

        if (!$request->isMethod('POST')) {
            $client = $this->salesforceService->getAuthorizedClient($integration);
            $instanceUrl = $integration->getInstanceUrl();
            if ($instanceUrl) {
                $existingContactId = $this->salesforceService->findExistingContact($client, $instanceUrl, $user->getEmail());
                if ($existingContactId) {
                    try {
                        $accountId = $this->salesforceService->createAccount($integration, 'Personal Account', null, null);
                        $this->salesforceService->updateContactAccount($client, $instanceUrl, $existingContactId, $accountId);

                        $integration->setSalesforceAccountId($accountId);
                        $integration->setSalesforceContactId($existingContactId);
                        $this->salesforceIntegrationRepository->save($integration, true);

                        $this->addFlash('success', $this->translator->trans('salesforce.sync.existing_contact_synced'));
                        return $this->redirectToRoute('app_profile');
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to sync existing contact', ['error' => $e->getMessage()]);
                        $this->addFlash('error', $this->translator->trans('salesforce.sync.error'));
                        return $this->redirectToRoute('app_profile');
                    }
                }
            }
        }

        if ($request->isMethod('POST')) {
            $companyName = $request->request->get('company_name');
            $phone = $request->request->get('phone');
            $address = $request->request->get('address');

            $this->logger->info('Creating new Salesforce company', [
                'companyName' => $companyName,
                'phone' => $phone,
                'address' => $address,
            ]);

            if (empty($companyName)) {
                return $this->json(['success' => false, 'error' => $this->translator->trans('salesforce.sync.company_name_required')], 400);
            }

            try {
                $accountId = $this->salesforceService->createAccount($integration, $companyName, $phone, $address);

                $contactId = $this->salesforceService->createContact($integration, $accountId, $user, $phone, $address);

                $integration->setSalesforceAccountId($accountId);
                $integration->setSalesforceContactId($contactId);
                $this->salesforceIntegrationRepository->save($integration, true);

                $this->logger->info('Salesforce integration saved', [
                    'accountId' => $accountId,
                    'contactId' => $contactId,
                    'existingContactUsed' => $this->salesforceService->wasExistingContactUsed(),
                ]);

                $message = $this->translator->trans('salesforce.sync.success');

                if ($this->salesforceService->wasExistingContactUsed()) {
                    $message .= ' ' . $this->translator->trans('salesforce.sync.duplicate_contact');
                }

                return $this->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'account_id' => $accountId,
                        'contact_id' => $contactId,
                    ]
                ]);

            } catch (\Exception $e) {
                return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        return $this->render('salesforce/sync.html.twig');
    }
}


