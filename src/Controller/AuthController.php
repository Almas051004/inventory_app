<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\OAuth2Service;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private OAuth2Service $oauth2Service,
        private TokenStorageInterface $tokenStorage,
        private TranslatorInterface $translator,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private EmailService $emailService,
        private ValidatorInterface $validator,
        private AuthenticationUtils $authenticationUtils
    ) {
    }

    /**
     * Инициирует аутентификацию через Google OAuth2
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/auth/google', name: 'auth_google')]
    public function google(Request $request): Response
    {
        $provider = $this->oauth2Service->getGoogleProvider();
        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['email', 'profile'],
        ]);

        $request->getSession()->set('oauth2state', $provider->getState());

        return $this->redirect($authUrl);
    }

    /**
     * Обрабатывает callback от Google OAuth2 после аутентификации
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/auth/google/callback', name: 'auth_google_callback')]
    public function googleCallback(Request $request): Response
    {
        $state = $request->query->get('state');
        $code = $request->query->get('code');

        if (!$state || $state !== $request->getSession()->get('oauth2state')) {
            $request->getSession()->remove('oauth2state');
            $this->addFlash('error', $this->translator->trans('auth.invalid_state'));
            return $this->redirectToRoute('app_home');
        }

        $request->getSession()->remove('oauth2state');

        if (!$code) {
            $this->addFlash('error', $this->translator->trans('auth.authorization_failed'));
            return $this->redirectToRoute('app_home');
        }

        try {
            $provider = $this->oauth2Service->getGoogleProvider();
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            $userData = $provider->getResourceOwner($token)->toArray();

            $user = $this->oauth2Service->findOrCreateUserFromGoogle($userData);

            $token = new UsernamePasswordToken(
                $user,
                'main',
                $user->getRoles()
            );
            $this->tokenStorage->setToken($token);
            $request->getSession()->set('_security_main', serialize($token));

            $this->addFlash('success', $this->translator->trans('auth.google_login_success'));
            return $this->redirectToRoute('app_home');

        } catch (IdentityProviderException $e) {
            $this->addFlash('error', $this->translator->trans('auth.authentication_failed') . ' ' . $e->getMessage());
            return $this->redirectToRoute('app_home');
        }
    }

    /**
     * Инициирует аутентификацию через Facebook OAuth2
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/auth/facebook', name: 'auth_facebook')]
    public function facebook(Request $request): Response
    {
        $provider = $this->oauth2Service->getFacebookProvider();
        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['public_profile'],
        ]);

        $request->getSession()->set('oauth2state', $provider->getState());

        return $this->redirect($authUrl);
    }

    /**
     * Обрабатывает callback от Facebook OAuth2 после аутентификации
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/auth/facebook/callback', name: 'auth_facebook_callback')]
    public function facebookCallback(Request $request): Response
    {
        $state = $request->query->get('state');
        $code = $request->query->get('code');

        if (!$state || $state !== $request->getSession()->get('oauth2state')) {
            $request->getSession()->remove('oauth2state');
            $this->addFlash('error', $this->translator->trans('auth.invalid_state'));
            return $this->redirectToRoute('app_home');
        }

        $request->getSession()->remove('oauth2state');

        if (!$code) {
            $this->addFlash('error', $this->translator->trans('auth.authorization_failed'));
            return $this->redirectToRoute('app_home');
        }

        try {
            $provider = $this->oauth2Service->getFacebookProvider();
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            $userData = $provider->getResourceOwner($token)->toArray();

            $user = $this->oauth2Service->findOrCreateUserFromFacebook($userData);

            $token = new UsernamePasswordToken(
                $user,
                'main',
                $user->getRoles()
            );
            $this->tokenStorage->setToken($token);
            $request->getSession()->set('_security_main', serialize($token));

            $this->addFlash('success', $this->translator->trans('auth.facebook_login_success'));
            return $this->redirectToRoute('app_home');

        } catch (IdentityProviderException $e) {
            $this->addFlash('error', $this->translator->trans('auth.authentication_failed') . ' ' . $e->getMessage());
            return $this->redirectToRoute('app_home');
        }
    }

    /**
     * Отображает страницу входа в систему
     *
     * @return Response
     */
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = $this->authenticationUtils->getLastAuthenticationError();
        $lastUsername = $this->authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'error' => $error,
            'last_username' => $lastUsername,
        ]);
    }

    /**
     * Отображает страницу регистрации или обрабатывает регистрацию пользователя
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/register', name: 'auth_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            return $this->handleRegistration($request);
        }

        return $this->render('auth/register.html.twig');
    }

    /**
     * Подтверждает email пользователя по токену верификации
     *
     * @param string $token Токен верификации email
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/verify-email/{token}', name: 'auth_verify_email')]
    public function verifyEmail(string $token, Request $request): Response
    {
        $user = $this->userRepository->findOneBy(['emailVerificationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', $this->translator->trans('auth.verification.invalid_token'));
            return $this->redirectToRoute('app_home');
        }

        if ($user->isEmailVerified()) {
            $this->addFlash('info', $this->translator->trans('auth.verification.already_verified'));
            return $this->redirectToRoute('app_home');
        }

        $user->setEmailVerifiedAt(new \DateTimeImmutable());
        $user->setEmailVerificationToken(null);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('auth.verification.success'));

        $token = new UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles()
        );
        $this->tokenStorage->setToken($token);
        $request->getSession()->set('_security_main', serialize($token));

        return $this->redirectToRoute('app_home');
    }

    /**
     * Повторно отправляет письмо для подтверждения email
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/resend-verification', name: 'auth_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('resend_verification', $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('auth.csrf_invalid'));
            return $this->redirectToRoute('app_home');
        }

        if ($user->isEmailVerified()) {
            $this->addFlash('info', $this->translator->trans('auth.verification.already_verified'));
            return $this->redirectToRoute('app_home');
        }

        $lastSent = $user->getUpdatedAt();
        if ($lastSent && $lastSent->getTimestamp() > (time() - 60)) {
            $this->addFlash('warning', $this->translator->trans('auth.resend.too_frequent'));
            return $this->redirectToRoute('app_home');
        }

        $this->emailService->sendEmailConfirmation($user);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('auth.resend.success'));
        return $this->redirectToRoute('app_home');
    }

    /**
     * Отображает форму восстановления пароля или отправляет письмо со ссылкой сброса
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/forgot-password', name: 'auth_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if ($user) {
                $this->emailService->sendPasswordReset($user);
                $this->entityManager->flush();
            }

            $this->addFlash('success', $this->translator->trans('auth.reset.sent'));
            return $this->redirectToRoute('auth_forgot_password');
        }

        return $this->render('auth/forgot_password.html.twig');
    }

    /**
     * Отображает форму сброса пароля или обрабатывает сброс пароля
     *
     * @param string $token Токен сброса пароля
     * @param Request $request HTTP запрос
     * @return Response
     */
    #[Route('/reset-password/{token}', name: 'auth_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(string $token, Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = $this->userRepository->findOneBy(['passwordResetToken' => $token]);

        if (!$user || !$user->isPasswordResetTokenValid()) {
            $this->addFlash('error', $this->translator->trans('auth.reset.invalid_token'));
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            return $this->handlePasswordReset($user, $request);
        }

        return $this->render('auth/reset_password.html.twig', [
            'token' => $token
        ]);
    }

    /**
     * Обрабатывает регистрацию нового пользователя
     *
     * @param Request $request HTTP запрос
     * @return Response
     */
    private function handleRegistration(Request $request): Response
    {
        $email = trim($request->request->get('email', ''));
        $username = trim($request->request->get('username', ''));
        $password = $request->request->get('password', '');
        $passwordConfirm = $request->request->get('password_confirm', '');

        if (empty($email)) {
            $this->addFlash('error', $this->translator->trans('auth.register.email_required'));
            return $this->redirectToRoute('auth_register');
        }

        if (empty($username)) {
            $this->addFlash('error', $this->translator->trans('auth.register.username_required'));
            return $this->redirectToRoute('auth_register');
        }

        if (empty($password)) {
            $this->addFlash('error', $this->translator->trans('auth.register.password_required'));
            return $this->redirectToRoute('auth_register');
        }

        if (strlen($password) < 8) {
            $this->addFlash('error', $this->translator->trans('auth.register.password_too_short'));
            return $this->redirectToRoute('auth_register');
        }

        if ($password !== $passwordConfirm) {
            $this->addFlash('error', $this->translator->trans('auth.register.passwords_dont_match'));
            return $this->redirectToRoute('auth_register');
        }

        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser) {
            $this->addFlash('error', $this->translator->trans('auth.register.email_exists'));
            return $this->redirectToRoute('auth_register');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->redirectToRoute('auth_register');
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->emailService->sendEmailConfirmation($user);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('auth.register.success'));
        return $this->redirectToRoute('app_home');
    }

    /**
     * Обрабатывает сброс пароля пользователя
     *
     * @param User $user Пользователь
     * @param Request $request HTTP запрос
     * @return Response
     */
    private function handlePasswordReset(User $user, Request $request): Response
    {
        $password = $request->request->get('password', '');
        $passwordConfirm = $request->request->get('password_confirm', '');

        if (empty($password)) {
            $this->addFlash('error', $this->translator->trans('auth.reset.password_required'));
            return $this->redirectToRoute('auth_reset_password', ['token' => $request->request->get('token')]);
        }

        if (strlen($password) < 8) {
            $this->addFlash('error', $this->translator->trans('auth.reset.password_too_short'));
            return $this->redirectToRoute('auth_reset_password', ['token' => $request->request->get('token')]);
        }

        if ($password !== $passwordConfirm) {
            $this->addFlash('error', $this->translator->trans('auth.reset.passwords_dont_match'));
            return $this->redirectToRoute('auth_reset_password', ['token' => $request->request->get('token')]);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setPasswordResetToken(null);
        $user->setPasswordResetTokenExpiresAt(null);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('auth.reset.success'));

        $token = new UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles()
        );
        $this->tokenStorage->setToken($token);
        $request->getSession()->set('_security_main', serialize($token));

        return $this->redirectToRoute('app_home');
    }

    /**
     * Выход из системы (перехватывается firewall)
     *
     * @throws \LogicException
     */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
