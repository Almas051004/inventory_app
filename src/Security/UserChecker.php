<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isBlocked()) {
            throw new CustomUserMessageAuthenticationException('Ваш аккаунт заблокирован. Обратитесь к администратору.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token = null): void
    {
        // Можно добавить дополнительные проверки после аутентификации
    }
}
