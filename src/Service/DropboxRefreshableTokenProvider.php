<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Spatie\Dropbox\RefreshableTokenProvider;

/**
 * Dropbox token provider that automatically refreshes access tokens using refresh tokens
 */
class DropboxRefreshableTokenProvider implements RefreshableTokenProvider
{
    private string $accessToken;
    private ?string $refreshToken;
    private string $appKey;
    private string $appSecret;
    private Client $httpClient;

    public function __construct(
        string $accessToken,
        ?string $refreshToken,
        string $appKey,
        string $appSecret
    ) {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        $this->httpClient = new Client();
    }

    public function getToken(): string
    {
        return $this->accessToken;
    }

    public function refresh(ClientException $exception): bool
    {
        if (!$this->refreshToken || $this->refreshToken === 'null') {
            return false;
        }

        if ($exception->getResponse()->getStatusCode() !== 401) {
            return false;
        }

        try {
            $response = $this->httpClient->post('https://api.dropbox.com/oauth2/token', [
                'auth' => [$this->appKey, $this->appSecret],
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the current refresh token
     */
    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /**
     * Update the access token (useful for manual token updates)
     */
    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    /**
     * Update the refresh token
     */
    public function setRefreshToken(?string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }
}

