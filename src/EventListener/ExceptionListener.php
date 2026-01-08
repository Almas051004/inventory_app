<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ExceptionListener
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator,
        private bool $showCustomErrorTemplates,
        private \App\Service\ErrorHandlerService $errorHandler
    ) {}

    #[AsEventListener]
    public function onExceptionEvent(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($this->isRoutingException($exception) ||
            $exception instanceof AccessDeniedException ||
            $exception instanceof AuthenticationCredentialsNotFoundException) {
            if (!$this->showCustomErrorTemplates) {
                return;
            }
        } else {
            $this->errorHandler->logException($exception, 'Unhandled exception');
        }

        if (!$this->showCustomErrorTemplates) {
            return;
        }

        $exception = $event->getThrowable();
        $request = $event->getRequest();
        $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if ($exception instanceof AccessDeniedException) {
            if (!$this->showCustomErrorTemplates) {
                return;
            }

            if ($isAjax) {
                $message = $this->translator->trans('controller.access_denied');
                $response = new \Symfony\Component\HttpFoundation\JsonResponse([
                    'error' => $message
                ], 403);
            } else {
                $response = new \Symfony\Component\HttpFoundation\RedirectResponse(
                    $this->router->generate('app_error_403')
                );
            }
            $event->setResponse($response);
            return;
        }

        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            $message = $exception->getMessage();

            if (str_contains($message, 'Full authentication is required') ||
                str_contains($message, 'authentication')) {

                if (!$this->showCustomErrorTemplates) {
                    return;
                }

                if ($isAjax) {
                    $response = new \Symfony\Component\HttpFoundation\JsonResponse([
                        'error' => $this->translator->trans('controller.authentication_required')
                    ], 401);
                } else {
                    $response = new \Symfony\Component\HttpFoundation\RedirectResponse(
                        $this->router->generate('app_error_401')
                    );
                }
                $event->setResponse($response);
                return;
            }

            if ($exception->getStatusCode() >= 400) {
                if (!$this->showCustomErrorTemplates) {
                    return;
                }

                if ($isAjax) {
                    $response = new \Symfony\Component\HttpFoundation\JsonResponse([
                        'error' => $message
                    ], $exception->getStatusCode());
                } else {
                    $response = new \Symfony\Component\HttpFoundation\RedirectResponse(
                        $this->router->generate('app_error_403')
                    );
                }
                $event->setResponse($response);
                return;
            }
        }

        if ($exception instanceof AuthenticationCredentialsNotFoundException) {
            if (!$this->showCustomErrorTemplates) {
                return;
            }

            if ($isAjax) {
                $message = $this->translator->trans('controller.authentication_required');
                $response = new \Symfony\Component\HttpFoundation\JsonResponse([
                    'error' => $message
                ], 401);
            } else {
                $response = new \Symfony\Component\HttpFoundation\RedirectResponse(
                    $this->router->generate('app_error_401')
                );
            }
            $event->setResponse($response);
            return;
        }
    }

    /**
     * Проверяет, является ли исключение ошибкой маршрутизации (404, 405 и т.п.)
     */
    private function isRoutingException(\Throwable $exception): bool
    {
        return $exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
            || $exception instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
            || $exception instanceof \Symfony\Component\Routing\Exception\RouteNotFoundException
            || $exception instanceof \Symfony\Component\Routing\Exception\ResourceNotFoundException;
    }
}
