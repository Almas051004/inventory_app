<?php

namespace App\Controller;

use App\Service\CloudStorageService;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for Dropbox OAuth2 authentication
 */
class DropboxAuthController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
        private string $dropboxAppKey,
        private string $dropboxAppSecret,
        private string $appUrl,
        private CloudStorageService $cloudStorageService
    ) {
    }

    /**
     * Initiates Dropbox OAuth2 authentication
     *
     * @param Request $request HTTP request
     * @return Response
     */
    #[Route('/auth/dropbox', name: 'auth_dropbox')]
    #[IsGranted('ROLE_ADMIN')]
    public function dropbox(Request $request): Response
    {
        $authUrl = 'https://www.dropbox.com/oauth2/authorize?' . http_build_query([
            'client_id' => $this->dropboxAppKey,
            'response_type' => 'code',
            'token_access_type' => 'offline',
            'redirect_uri' => $this->appUrl . $this->generateUrl('auth_dropbox_callback'),
        ]);

        $request->getSession()->set('dropbox_oauth_state', bin2hex(random_bytes(16)));

        return $this->redirect($authUrl);
    }

    /**
     * Handles Dropbox OAuth2 callback and exchanges code for tokens
     *
     * @param Request $request HTTP request
     * @return Response
     */
    #[Route('/auth/dropbox/callback', name: 'auth_dropbox_callback')]
    public function dropboxCallback(Request $request): Response
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('error', $this->translator->trans('dropbox.auth.error', ['error' => $error]));
            return $this->redirectToRoute('app_home');
        }

        if (!$code) {
            $this->addFlash('error', $this->translator->trans('dropbox.auth.no_code'));
            return $this->redirectToRoute('app_home');
        }

        try {
            $httpClient = new Client();
            $response = $httpClient->post('https://api.dropbox.com/oauth2/token', [
                'auth' => [$this->dropboxAppKey, $this->dropboxAppSecret],
                'form_params' => [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->appUrl . $this->generateUrl('auth_dropbox_callback'),
                ],
            ]);

            $tokenData = json_decode($response->getBody()->getContents(), true);

            if (!isset($tokenData['access_token'])) {
                throw new \Exception('No access token received from Dropbox');
            }

            $refreshToken = $tokenData['refresh_token'] ?? null;

            $this->addFlash('success', $this->translator->trans('dropbox.auth.success'));

            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->render('dropbox/setup.html.twig', [
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $refreshToken,
                    'expires_in' => $tokenData['expires_in'] ?? null,
                ]);
            } else {
                return $this->render('dropbox/setup_public.html.twig', [
                    'message' => $this->translator->trans('dropbox.auth.success_public')
                ]);
            }

        } catch (\Exception $e) {
            $this->addFlash('error', $this->translator->trans('dropbox.auth.token_exchange_failed', [
                'error' => $e->getMessage()
            ]));
            return $this->redirectToRoute('app_home');
        }
    }

    /**
     * Shows Dropbox configuration status
     *
     * @return Response
     */
    #[Route('/dropbox/status', name: 'dropbox_status')]
    #[IsGranted('ROLE_ADMIN')]
    public function status(): Response
    {
        $isConfigured = $this->cloudStorageService->isConfigured();
        $accountInfo = null;

        if ($isConfigured) {
            $accountInfo = $this->cloudStorageService->getAccountInfo();
        }

        return $this->render('dropbox/status.html.twig', [
            'is_configured' => $isConfigured,
            'account_info' => $accountInfo,
        ]);
    }
}
