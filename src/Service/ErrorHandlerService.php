<?php

namespace App\Service;

use App\Entity\ErrorLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Сервис для обработки и логирования ошибок
 */
class ErrorHandlerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage
    ) {}

    /**
     * Логирует исключение и возвращает ID ошибки для пользователя
     */
    public function logException(\Throwable $exception, ?string $context = null): string
    {
        $errorLog = new ErrorLog();

        // Получаем текущий запрос
        $request = $this->requestStack->getCurrentRequest();

        // Получаем текущего пользователя
        $user = null;
        $token = $this->tokenStorage->getToken();
        if ($token && $token->getUser() instanceof User) {
            $user = $token->getUser();
        }

        // Формируем сообщение об ошибке
        $message = $exception->getMessage();
        if ($context) {
            $message = "[$context] " . $message;
        }

        // Заполняем данные ошибки
        $errorLog->setMessage($message);
        $errorLog->setTrace($exception->getTraceAsString());

        if ($request) {
            $errorLog->setUrl($request->getUri());
            $errorLog->setIpAddress($request->getClientIp());
            $errorLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $errorLog->setUser($user);

        // Сохраняем ошибку в базу данных
        try {
            $this->entityManager->persist($errorLog);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Если не удалось сохранить в БД, логируем в файл как fallback
            error_log("Failed to save error log to database: " . $e->getMessage());
            error_log("Original error: " . $exception->getMessage());
        }

        return $errorLog->getErrorId();
    }

    /**
     * Создает пользовательское сообщение об ошибке с ID для обратной связи
     */
    public function createUserFriendlyMessage(string $errorId, ?string $customMessage = null): string
    {
        $baseMessage = $customMessage ?: 'Произошла неожиданная ошибка. Пожалуйста, свяжитесь с администратором.';

        return sprintf(
            '%s\n\nID ошибки: %s\n\nИспользуйте этот ID при обращении в поддержку.',
            $baseMessage,
            $errorId
        );
    }

    /**
     * Находит ошибку по ID (для администраторов)
     */
    public function findErrorById(string $errorId): ?ErrorLog
    {
        return $this->entityManager->getRepository(ErrorLog::class)->findOneBy([
            'errorId' => $errorId
        ]);
    }
}
