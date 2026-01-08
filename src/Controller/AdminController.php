<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\ErrorHandlerService;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private ErrorHandlerService $errorHandler
    ) {
    }

    /**
     * Подсчитывает количество пользователей с ролью администратора
     *
     * @return int
     */
    private function countAdminUsers(): int
    {
        $users = $this->userRepository->findAll();
        $count = 0;
        foreach ($users as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Отображает панель администратора с основной статистикой
     *
     * @return Response
     */
    #[Route('', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $stats = [
            'total_users' => $this->userRepository->count([]),
            'blocked_users' => $this->userRepository->count(['isBlocked' => true]),
            'admin_users' => $this->countAdminUsers(),
            'recent_users' => $this->userRepository->findBy([], ['createdAt' => 'DESC'], 5),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    /**
     * Отображает список пользователей с возможностью фильтрации и поиска
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/users', name: 'admin_users')]
    public function users(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', 'all');
        $sortBy = $request->query->get('sort', 'created_at');
        $sortOrder = $request->query->get('order', 'desc');

        if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $queryBuilder = $this->userRepository->createQueryBuilder('u');

        if ($search) {
            $queryBuilder->andWhere('u.username LIKE :search OR u.email LIKE :search')
                        ->setParameter('search', '%' . $search . '%');
        }

        if ($status === 'active') {
            $queryBuilder->andWhere('u.isBlocked = false');
        } elseif ($status === 'blocked') {
            $queryBuilder->andWhere('u.isBlocked = true');
        }

        $orderBy = match ($sortBy) {
            'username' => 'u.username',
            'email' => 'u.email',
            default => 'u.createdAt'
        };

        $queryBuilder->orderBy($orderBy, strtoupper($sortOrder));

        $users = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'search' => $search,
            'status' => $status,
            'sort' => $sortBy,
            'order' => $sortOrder,
        ]);
    }

    /**
     * Блокирует или разблокирует пользователя
     *
     * @param User $user Пользователь
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/users/{id}/toggle-block', name: 'admin_user_toggle_block', methods: ['POST'])]
    public function toggleBlock(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle-block-' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('admin.csrf_invalid'));
            return $this->redirectToRoute('admin_users');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', $this->translator->trans('admin.self_block_error'));
            return $this->redirectToRoute('admin_users');
        }

        $user->setIsBlocked(!$user->isBlocked());
        $this->entityManager->flush();

        $action = $user->isBlocked() ? $this->translator->trans('admin.user_blocked') : $this->translator->trans('admin.user_unblocked');
        $this->addFlash('success', $this->translator->trans('admin.user_status_changed', [
            '%s' => $user->getUsername() ?? $user->getEmail(),
            '%s' => $action
        ]));

        return $this->redirectToRoute('admin_users');
    }

    /**
     * Назначает или снимает роль администратора у пользователя
     *
     * @param User $user Пользователь
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/users/{id}/toggle-admin', name: 'admin_user_toggle_admin', methods: ['POST'])]
    public function toggleAdmin(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle-admin-' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('admin.csrf_invalid'));
            return $this->redirectToRoute('admin_users');
        }

        $currentRoles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $currentRoles)) {
            $user->setRoles(array_diff($currentRoles, ['ROLE_ADMIN']));
            $action = $this->translator->trans('admin.admin_role_removed');
        } else {
            $user->setRoles(array_merge($currentRoles, ['ROLE_ADMIN']));
            $action = $this->translator->trans('admin.admin_role_added');
        }

        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('admin.user_admin_status_changed', [
            '%s' => $user->getUsername() ?? $user->getEmail(),
            '%s' => $action
        ]));

        return $this->redirectToRoute('admin_users');
    }

    /**
     * Удаляет пользователя
     *
     * @param User $user Пользователь
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-user-' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('admin.csrf_invalid'));
            return $this->redirectToRoute('admin_users');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', $this->translator->trans('admin.self_delete_error'));
            return $this->redirectToRoute('admin_users');
        }

        $username = $user->getUsername() ?? $user->getEmail();
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('admin.user_deleted', ['%s' => $username]));

        return $this->redirectToRoute('admin_users');
    }

    /**
     * Выполняет массовые действия над пользователями
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/users/bulk-action', name: 'admin_users_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('bulk_action', $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('admin.csrf_invalid'));
            return $this->redirectToRoute('admin_users');
        }

        $action = $request->request->get('action');
        $userIds = explode(',', $request->request->get('user_ids', ''));

        if (empty($userIds)) {
            $this->addFlash('error', $this->translator->trans('admin.no_users_selected'));
            return $this->redirectToRoute('admin_users');
        }

        $users = $this->userRepository->findBy(['id' => $userIds]);
        $currentUser = $this->getUser();
        $processed = 0;
        $errors = [];

        foreach ($users as $user) {
            try {
                switch ($action) {
                    case 'block':
                        $user->setIsBlocked(true);
                        $processed++;
                        break;
                    case 'unblock':
                        $user->setIsBlocked(false);
                        $processed++;
                        break;
                    case 'make_admin':
                        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
                            $roles = $user->getRoles();
                            $roles[] = 'ROLE_ADMIN';
                            $user->setRoles($roles);
                            $processed++;
                        }
                        break;
                    case 'remove_admin':
                        if (in_array('ROLE_ADMIN', $user->getRoles())) {
                            $roles = array_diff($user->getRoles(), ['ROLE_ADMIN']);
                            $user->setRoles($roles);
                            $processed++;
                        }
                        break;
                    case 'delete':
                        $this->entityManager->remove($user);
                        $processed++;
                        break;
                }
            } catch (\Exception $e) {
                $errors[] = $this->translator->trans('admin.user_processing_error', [
                    '%s' => $user->getUsername() ?? $user->getEmail(),
                    '%s' => $e->getMessage()
                ]);
            }
        }

        $this->entityManager->flush();

        if ($processed > 0) {
            $actionMessages = [
                'block' => $this->translator->trans('admin.bulk.blocked'),
                'unblock' => $this->translator->trans('admin.bulk.unblocked'),
                'make_admin' => $this->translator->trans('admin.bulk.made_admin'),
                'remove_admin' => $this->translator->trans('admin.bulk.removed_admin'),
                'delete' => $this->translator->trans('admin.bulk.deleted')
            ];

            $message = $this->translator->trans('admin.bulk.success', [
                '%d' => $processed,
                '%s' => $actionMessages[$action] ?? $this->translator->trans('admin.bulk.processed')
            ]);
            $this->addFlash('success', $message);
        }

        foreach ($errors as $error) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('admin_users');
    }

    /**
     * Отображает детальную информацию об ошибке
     *
     * @param string $errorId ID ошибки
     * @return Response
     */
    #[Route('/errors/{errorId}', name: 'admin_error_details', requirements: ['errorId' => '[a-f0-9\-]+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function errorDetails(string $errorId): Response
    {
        $error = $this->errorHandler->findErrorById($errorId);

        if (!$error) {
            $this->addFlash('error', 'Ошибка с таким ID не найдена');
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/error_details.html.twig', [
            'error' => $error,
        ]);
    }

    /**
     * Отображает список ошибок системы
     *
     * @return Response
     */
    #[Route('/errors', name: 'admin_errors')]
    #[IsGranted('ROLE_ADMIN')]
    public function errors(): Response
    {
        $errorRepository = $this->entityManager->getRepository(\App\Entity\ErrorLog::class);

        $errors = $errorRepository->findBy([], ['createdAt' => 'DESC'], 50);

        return $this->render('admin/errors.html.twig', [
            'errors' => $errors,
        ]);
    }
}
