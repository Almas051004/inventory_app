<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: KernelEvents::REQUEST)]
class UserBlockCheckListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private RequestStack $requestStack
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Пропускаем проверку для маршрутов, не требующих аутентификации
        if (!$this->isProtectedRoute($request)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        if ($user->isBlocked()) {
            // Очищаем токен
            $this->tokenStorage->setToken(null);

            // Очищаем сессию
            $session = $request->getSession();
            if ($session) {
                $session->invalidate();
            }

            // Добавляем flash сообщение
            $session = $this->requestStack->getSession();
            if ($session) {
                $session->getFlashBag()->add('error', $this->translator->trans('auth.account_blocked_during_session'));
            }

            // Редиректим на главную
            $response = new RedirectResponse($this->urlGenerator->generate('app_home'));
            $event->setResponse($response);
        }
    }

    private function isProtectedRoute(Request $request): bool
    {
        $route = $request->attributes->get('_route');

        // Список маршрутов, которые не требуют аутентификации
        $publicRoutes = [
            'app_home',
            'app_login',
            'auth_register',
            'auth_forgot_password',
            'auth_reset_password',
            'auth_google',
            'auth_google_callback',
            'auth_facebook',
            'auth_facebook_callback',
            'auth_verify_email',
            'auth_resend_verification',
        ];

        if (str_starts_with($route, 'api_')) {
            return false;
        }

        return !in_array($route, $publicRoutes);
    }
}
