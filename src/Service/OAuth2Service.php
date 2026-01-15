<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\Facebook;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OAuth2Service
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UrlGeneratorInterface $urlGenerator,
        private string $googleClientId,
        private string $googleClientSecret,
        private string $facebookClientId,
        private string $facebookClientSecret,
        private string $appUrl
    ) {
    }

    public function getGoogleProvider(): Google
    {
        return new Google([
            'clientId' => $this->googleClientId,
            'clientSecret' => $this->googleClientSecret,
            'redirectUri' => $this->appUrl . $this->urlGenerator->generate('auth_google_callback'),
        ]);
    }

    public function getFacebookProvider(): Facebook
    {
        return new Facebook([
            'clientId' => $this->facebookClientId,
            'clientSecret' => $this->facebookClientSecret,
            'redirectUri' => $this->appUrl . $this->urlGenerator->generate('auth_facebook_callback'),
            'graphApiVersion' => 'v18.0',
        ]);
    }

    public function findOrCreateUserFromGoogle(array $userData): User
    {
        $googleId = $userData['sub'] ?? null;
        $email = $userData['email'] ?? null;
        $name = $userData['name'] ?? null;
        $picture = $userData['picture'] ?? null;

        if (!$googleId || !$email) {
            throw new \RuntimeException('Missing required Google user data');
        }

        // Ищем пользователя по googleId
        $user = $this->userRepository->findOneBy(['googleId' => $googleId]);

        if (!$user) {
            // Ищем по email
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if ($user) {
                // Пользователь существует, добавляем googleId
                $user->setGoogleId($googleId);
                // Автоматически подтверждаем почту для OAuth пользователей
                if (!$user->isEmailVerified()) {
                    $user->setEmailVerifiedAt(new \DateTimeImmutable());
                }
            } else {
                // Создаем нового пользователя
                $user = new User();
                $user->setEmail($email);
                $user->setGoogleId($googleId);
                $user->setUsername($name ?? explode('@', $email)[0]);
                $user->setRoles(['ROLE_USER']);
                $user->setAvatarUrl($picture);
                // Автоматически подтверждаем почту для OAuth пользователей
                $user->setEmailVerifiedAt(new \DateTimeImmutable());
            }
        } else {
            // Обновляем данные
            if ($picture && !$user->getAvatarUrl()) {
                $user->setAvatarUrl($picture);
            }
            if ($name && !$user->getUsername()) {
                $user->setUsername($name);
            }
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function findOrCreateUserFromFacebook(array $userData): User
    {
        $facebookId = $userData['id'] ?? null;
        $email = $userData['email'] ?? null;
        $name = $userData['name'] ?? null;
        $picture = $userData['picture']['data']['url'] ?? null;

        if (!$facebookId) {
            throw new \RuntimeException('Missing required Facebook user data');
        }

        // Ищем пользователя по facebookId
        $user = $this->userRepository->findOneBy(['facebookId' => $facebookId]);

        if (!$user) {
            // Ищем по email (если есть)
            if ($email) {
                $user = $this->userRepository->findOneBy(['email' => $email]);
            }

            if ($user) {
                // Пользователь существует, добавляем facebookId
                $user->setFacebookId($facebookId);
                // Автоматически подтверждаем почту для OAuth пользователей
                if (!$user->isEmailVerified()) {
                    $user->setEmailVerifiedAt(new \DateTimeImmutable());
                }
            } else {
                // Создаем нового пользователя
                $user = new User();
                if ($email) {
                    $user->setEmail($email);
                } else {
                    // Если email нет, создаем временный
                    $user->setEmail('facebook_' . $facebookId . '@temp.local');
                }
                $user->setFacebookId($facebookId);
                $user->setUsername($name ?? 'facebook_user_' . $facebookId);
                $user->setRoles(['ROLE_USER']);
                if ($picture) {
                    $user->setAvatarUrl($picture);
                }
                // Автоматически подтверждаем почту для OAuth пользователей
                $user->setEmailVerifiedAt(new \DateTimeImmutable());
            }
        } else {
            // Обновляем данные
            if ($picture && !$user->getAvatarUrl()) {
                $user->setAvatarUrl($picture);
            }
            if ($name && !$user->getUsername()) {
                $user->setUsername($name);
            }
            if ($email && $user->getEmail() === 'facebook_' . $facebookId . '@temp.local') {
                $user->setEmail($email);
            }
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}

