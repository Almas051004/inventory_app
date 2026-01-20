<?php

namespace App\Controller;

use App\Entity\SupportTicket;
use App\Repository\SupportTicketRepository;
use App\Service\CloudStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/support')]
class SupportController extends AbstractController
{
    public function __construct(
        private SupportTicketRepository $supportTicketRepository,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private CloudStorageService $cloudStorageService,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/create', name: 'support_create', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleCreate($request);
        }

        $pageUrl = $request->headers->get('referer', $request->getUri());

        return $this->render('support/create.html.twig', [
            'page_url' => $pageUrl,
            'priorities' => SupportTicket::getPriorityChoices(),
            'statuses' => SupportTicket::getStatusChoices(),
        ]);
    }

    #[Route('/create-ajax', name: 'support_create_ajax', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function createAjax(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                $this->logger->warning('Support ticket creation failed: invalid JSON data', [
                    'user_id' => $this->getUser()->getId(),
                    'user_email' => $this->getUser()->getEmail(),
                    'request_content' => $request->getContent()
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => $this->translator->trans('support.invalid_data')
                ], 400);
            }

            $supportTicket = new SupportTicket();
            $supportTicket->setUser($this->getUser());
            $supportTicket->setSummary(trim($data['summary'] ?? ''));
            $supportTicket->setPageUrl($data['page_url'] ?? '');
            $supportTicket->setPriority($data['priority'] ?? SupportTicket::PRIORITY_MEDIUM);
            $supportTicket->setStatus(SupportTicket::STATUS_NEW);

            $this->supportTicketRepository->save($supportTicket, true);

            $ticketData = [
                'summary' => $supportTicket->getSummary(),
                'priority' => $supportTicket->getPriority(),
                'page_url' => $supportTicket->getPageUrl(),
                'user_email' => $this->getUser()->getEmail(),
                'user_username' => $this->getUser()->getUsername(),
                'created_at' => $supportTicket->getCreatedAt()?->format('Y-m-d H:i:s'),
                'admin_emails' => $this->getAdminEmails(),
            ];

            $supportTicket->setDataJson($ticketData);

            $this->supportTicketRepository->save($supportTicket, true);

            $cloudFilePath = $this->cloudStorageService->uploadJsonData(
                $ticketData,
                'support_ticket_' . $supportTicket->getId()
            );

            if ($cloudFilePath) {
                $supportTicket->setFilePath($cloudFilePath);
                $this->supportTicketRepository->save($supportTicket, true);

                $this->logger->info('Support ticket created and uploaded to cloud', [
                    'ticket_id' => $supportTicket->getId(),
                    'user_id' => $this->getUser()->getId(),
                    'cloud_file_path' => $cloudFilePath
                ]);
            } else {
                $this->logger->warning('Support ticket created but cloud upload failed', [
                    'ticket_id' => $supportTicket->getId(),
                    'user_id' => $this->getUser()->getId()
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('support.ticket_created'),
                'ticket_id' => $supportTicket->getId(),
                'cloud_upload' => $cloudFilePath ? 'success' : 'failed'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Support ticket creation failed with exception', [
                'user_id' => $this->getUser()->getId(),
                'user_email' => $this->getUser()->getEmail(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => $this->translator->trans('support.creation_error')
            ], 500);
        }
    }

    private function handleCreate(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('create_support_ticket', $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('support.invalid_csrf'));
            return $this->redirectToRoute('support_create');
        }

        try {
            $data = $request->request->all();

            if (empty($data['summary'])) {
                $this->addFlash('error', $this->translator->trans('support.summary_required'));
                return $this->redirectToRoute('support_create');
            }

            $supportTicket = new SupportTicket();
            $supportTicket->setUser($this->getUser());
            $supportTicket->setSummary(trim($data['summary']));
            $supportTicket->setPageUrl($data['page_url'] ?? '');
            $supportTicket->setPriority($data['priority'] ?? SupportTicket::PRIORITY_MEDIUM);
            $supportTicket->setStatus(SupportTicket::STATUS_NEW);

            $this->supportTicketRepository->save($supportTicket, true);

            $ticketData = [
                'summary' => $supportTicket->getSummary(),
                'priority' => $supportTicket->getPriority(),
                'page_url' => $supportTicket->getPageUrl(),
                'user_email' => $this->getUser()->getEmail(),
                'user_username' => $this->getUser()->getUsername(),
                'created_at' => $supportTicket->getCreatedAt()?->format('Y-m-d H:i:s'),
                'admin_emails' => $this->getAdminEmails(),
            ];

            $supportTicket->setDataJson($ticketData);

            $this->supportTicketRepository->save($supportTicket, true);

            $this->logger->info('Attempting to upload ticket to cloud storage', [
                'ticket_id' => $supportTicket->getId(),
                'user_id' => $this->getUser()->getId()
            ]);

            $cloudFilePath = $this->cloudStorageService->uploadJsonData(
                $ticketData,
                'support_ticket_' . $supportTicket->getId()
            );

            if ($cloudFilePath) {
                $supportTicket->setFilePath($cloudFilePath);
                $this->supportTicketRepository->save($supportTicket, true);

                $this->logger->info('Support ticket created and uploaded to cloud', [
                    'ticket_id' => $supportTicket->getId(),
                    'user_id' => $this->getUser()->getId(),
                    'cloud_file_path' => $cloudFilePath
                ]);
            } else {
                $this->logger->warning('Support ticket created but cloud upload failed', [
                    'ticket_id' => $supportTicket->getId(),
                    'user_id' => $this->getUser()->getId()
                ]);
            }

            $this->addFlash('success', $this->translator->trans('support.ticket_created'));
            return $this->redirectToRoute('app_home');

        } catch (\Exception $e) {
            $this->addFlash('error', $this->translator->trans('support.creation_error'));
            return $this->redirectToRoute('support_create');
        }
    }

    private function getAdminEmails(): array
    {
        $adminUsers = $this->entityManager->getRepository(\App\Entity\User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->andWhere('u.isBlocked = :isBlocked')
            ->setParameter('role', '%"ROLE_ADMIN"%')
            ->setParameter('isBlocked', false)
            ->getQuery()
            ->getResult();

        return array_map(fn($user) => $user->getEmail(), $adminUsers);
    }
}
