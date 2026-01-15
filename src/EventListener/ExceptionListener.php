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

        // Не логируем ошибки маршрутизации (404, 405), AccessDeniedException и AuthenticationCredentialsNotFoundException
        if ($this->isRoutingException($exception) ||
            $exception instanceof AccessDeniedException ||
            $exception instanceof AuthenticationCredentialsNotFoundException) {
            // Если отключено отображение кастомных шаблонов ошибок, позволяем Symfony показать стандартные страницы
            if (!$this->showCustomErrorTemplates) {
                return;
            }
        } else {
            // Логируем другие исключения
            $this->errorHandler->logException($exception, 'Unhandled exception');
        }

        // Если отключено отображение кастомных шаблонов ошибок, позволяем Symfony показать стандартные страницы
        if (!$this->showCustomErrorTemplates) {
            return;
        }

        $exception = $event->getThrowable();
        $request = $event->getRequest();
        $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        // AccessDeniedException (403 Forbidden)
        if ($exception instanceof AccessDeniedException) {
            // Если отключено отображение кастомных шаблонов ошибок, позволяем Symfony показать стандартные страницы
            if (!$this->showCustomErrorTemplates) {
                return;
            }

            if ($isAjax) {
                // Для AJAX запросов возвращаем JSON
                $message = $this->translator->trans('controller.access_denied');
                $response = new \Symfony\Component\HttpFoundation\JsonResponse([
                    'error' => $message
                ], 403);
            } else {
                // Для обычных запросов перенаправляем на страницу ошибки
                $response = new \Symfony\Component\HttpFoundation\RedirectResponse(
                    $this->router->generate('app_error_403')
                );
            }
            $event->setResponse($response);
            return;
        }

        // HttpException (может содержать сообщения об аутентификации)
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            $message = $exception->getMessage();

            // Проверяем, является ли это ошибкой аутентификации
            if (str_contains($message, 'Full authentication is required') ||
                str_contains($message, 'authentication')) {

                // Если отключено отображение кастомных шаблонов ошибок, позволяем Symfony показать стандартные страницы
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

            // Для других HttpException возвращаем 403 если статус >= 400
            if ($exception->getStatusCode() >= 400) {
                // Если отключено отображение кастомных шаблонов ошибок, позволяем Symfony показать стандартные страницы
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

        // AuthenticationCredentialsNotFoundException (401 Unauthorized)
        if ($exception instanceof AuthenticationCredentialsNotFoundException) {
            // Если отключено отображение кастомных шаблонов ошибок, позволяем Symfony показать стандартные страницы
            if (!$this->showCustomErrorTemplates) {
                return;
            }

            if ($isAjax) {
                // Для AJAX запросов возвращаем JSON
                $message = $this->translator->trans('controller.authentication_required');
                $response = new \Symfony\Component\HttpFoundation\JsonResponse([
                    'error' => $message
                ], 401);
            } else {
                // Для обычных запросов перенаправляем на страницу ошибки
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
