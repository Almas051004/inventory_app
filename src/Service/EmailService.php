<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailService
{
    private string $fromAddress;
    private string $fromName;

    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        string $fromAddress,
        string $fromName
    ) {
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
    }

    public function sendEmailConfirmation(User $user): void
    {
        // Генерируем токен подтверждения
        $token = bin2hex(random_bytes(32));
        $user->setEmailVerificationToken($token);

        $confirmationUrl = $this->urlGenerator->generate('auth_verify_email', [
            'token' => $token
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from(new \Symfony\Component\Mime\Address($this->fromAddress, $this->fromName))
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.confirmation.subject'))
            ->html($this->getConfirmationEmailTemplate($user, $confirmationUrl));

        $this->mailer->send($email);
    }

    public function sendPasswordReset(User $user): void
    {
        // Генерируем токен для сброса пароля
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour'); // Токен действителен 1 час

        $user->setPasswordResetToken($token);
        $user->setPasswordResetTokenExpiresAt($expiresAt);

        $resetUrl = $this->urlGenerator->generate('auth_reset_password', [
            'token' => $token
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from(new \Symfony\Component\Mime\Address($this->fromAddress, $this->fromName))
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.reset_password.subject'))
            ->html($this->getPasswordResetEmailTemplate($user, $resetUrl));

        $this->mailer->send($email);
    }

    private function getConfirmationEmailTemplate(User $user, string $confirmationUrl): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . $this->translator->trans('email.confirmation.subject') . '</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #007bff;">' . $this->translator->trans('email.confirmation.subject') . '</h2>

                <p>' . sprintf($this->translator->trans('email.confirmation.hello'), htmlspecialchars($user->getUsername() ?: $user->getEmail())) . '</p>

                <p>' . $this->translator->trans('email.confirmation.message') . '</p>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $confirmationUrl . '" style="background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
                        ' . $this->translator->trans('email.confirmation.button') . '
                    </a>
                </div>

                <p>' . $this->translator->trans('email.confirmation.alternative') . '</p>

                <p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 3px;">
                    <a href="' . $confirmationUrl . '">' . $confirmationUrl . '</a>
                </p>

                <p style="color: #666; font-size: 12px;">
                    ' . $this->translator->trans('email.confirmation.footer') . '
                </p>
            </div>
        </body>
        </html>';
    }

    private function getPasswordResetEmailTemplate(User $user, string $resetUrl): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . $this->translator->trans('email.reset_password.subject') . '</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #dc3545;">' . $this->translator->trans('email.reset_password.subject') . '</h2>

                <p>' . sprintf($this->translator->trans('email.reset_password.hello'), htmlspecialchars($user->getUsername() ?: $user->getEmail())) . '</p>

                <p>' . $this->translator->trans('email.reset_password.message') . '</p>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $resetUrl . '" style="background-color: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
                        ' . $this->translator->trans('email.reset_password.button') . '
                    </a>
                </div>

                <p>' . $this->translator->trans('email.reset_password.alternative') . '</p>

                <p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 3px;">
                    <a href="' . $resetUrl . '">' . $resetUrl . '</a>
                </p>

                <p style="color: #666; font-size: 12px;">
                    ' . $this->translator->trans('email.reset_password.footer') . '
                </p>
            </div>
        </body>
        </html>';
    }
}
