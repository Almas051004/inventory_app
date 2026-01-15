<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class EmailVerificationVoter extends Voter
{
    public const EMAIL_VERIFIED = 'EMAIL_VERIFIED';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::EMAIL_VERIFIED;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        if (!$user instanceof User) {
            return false;
        }

        // Если почта подтверждена, разрешаем доступ
        if ($user->isEmailVerified()) {
            return true;
        }

        // Если почта не подтверждена, запрещаем доступ к действиям создания/редактирования
        return false;
    }
}
