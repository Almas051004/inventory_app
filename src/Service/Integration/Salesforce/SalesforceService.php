<?php

namespace App\Service\Integration\Salesforce;

use App\Entity\SalesforceIntegration;
use App\Entity\User;
use App\Repository\SalesforceIntegrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SalesforceService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private SalesforceIntegrationRepository $integrationRepository,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private bool $enabled,
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private string $authUrl,
        private string $tokenUrl,
        private string $apiBaseUrl,
        private bool $existingContactUsed = false
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getAuthorizationUrl(User $user): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'api refresh_token',
            'state' => $user->getId(),
        ];

        return $this->authUrl . '?' . http_build_query($params);
    }

    public function exchangeCodeForTokens(User $user, string $code): SalesforceIntegration
    {
        $response = $this->httpClient->request('POST', $this->tokenUrl, [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
            ],
        ]);

        $data = $response->toArray(false);

        $accessToken = $data['access_token'] ?? null;
        $refreshToken = $data['refresh_token'] ?? null;
        $instanceUrl = $data['instance_url'] ?? null;
        $expiresIn = $data['expires_in'] ?? null;

        if (!$accessToken) {
            throw new \RuntimeException('Salesforce access token is missing in response');
        }

        $integration = $this->integrationRepository->findOneByUser($user) ?? new SalesforceIntegration();
        $integration->setUser($user);
        $integration->setAccessToken($accessToken);
        $integration->setRefreshToken($refreshToken);
        $integration->setInstanceUrl($instanceUrl);

        if ($expiresIn !== null) {
            $integration->setExpiresAt((new \DateTimeImmutable())->modify('+' . (int) $expiresIn . ' seconds'));
        }

        $integration->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($integration);
        $this->entityManager->flush();

        return $integration;
    }

    public function refreshAccessToken(SalesforceIntegration $integration): SalesforceIntegration
    {
        $refreshToken = $integration->getRefreshToken();
        if (!$refreshToken) {
            throw new \RuntimeException('Salesforce refresh token is missing');
        }

        $response = $this->httpClient->request('POST', $this->tokenUrl, [
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);

        $data = $response->toArray(false);

        $accessToken = $data['access_token'] ?? null;
        $instanceUrl = $data['instance_url'] ?? $integration->getInstanceUrl();
        $expiresIn = $data['expires_in'] ?? null;

        if (!$accessToken) {
            throw new \RuntimeException('Salesforce access token is missing in refresh response');
        }

        $integration->setAccessToken($accessToken);
        $integration->setInstanceUrl($instanceUrl);

        if ($expiresIn !== null) {
            $integration->setExpiresAt((new \DateTimeImmutable())->modify('+' . (int) $expiresIn . ' seconds'));
        }

        $integration->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($integration);
        $this->entityManager->flush();

        return $integration;
    }

    public function getAuthorizedClient(SalesforceIntegration $integration): HttpClientInterface
    {
        $now = new \DateTimeImmutable();

        if ($integration->getExpiresAt() !== null && $integration->getExpiresAt() <= $now) {
            $integration = $this->refreshAccessToken($integration);
        }

        $accessToken = $integration->getAccessToken();

        if (!$accessToken) {
            throw new \RuntimeException('Salesforce access token is missing');
        }

        return $this->httpClient->withOptions([
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);
    }

    public function wasExistingContactUsed(): bool
    {
        return $this->existingContactUsed;
    }

    public function resetExistingContactFlag(): void
    {
        $this->existingContactUsed = false;
    }

    public function createAccount(SalesforceIntegration $integration, string $companyName, ?string $phone, ?string $address): string
    {
        $client = $this->getAuthorizedClient($integration);

        $accountData = [
            'Name' => $companyName,
            'Type' => 'Customer',
        ];

        if ($phone) {
            $accountData['Phone'] = $phone;
        }

        if ($address) {
            $accountData['BillingStreet'] = $address;
        }

        $instanceUrl = $integration->getInstanceUrl();
        if (!$instanceUrl) {
            throw new \RuntimeException('Salesforce instance URL is missing. Please reconnect.');
        }

        $this->logger->info('Creating Salesforce Account', [
            'companyName' => $companyName,
            'phone' => $phone,
            'address' => $address,
            'instanceUrl' => $instanceUrl,
        ]);

        $response = $client->request('POST', $instanceUrl . '/services/data/v57.0/sobjects/Account', [
            'json' => $accountData,
        ]);

        $data = $response->toArray(false);

        if (!isset($data['id'])) {
            $this->logger->error('Failed to create Salesforce Account', ['response' => $data]);
            throw new \RuntimeException('Failed to create Salesforce Account: ' . json_encode($data));
        }

        $this->logger->info('Successfully created Salesforce Account', [
            'accountId' => $data['id'],
            'accountData' => $accountData,
            'response' => $data
        ]);

        return $data['id'];
    }

    public function createContact(SalesforceIntegration $integration, string $accountId, User $user, ?string $phone, ?string $address): string
    {
        $this->resetExistingContactFlag();
        $client = $this->getAuthorizedClient($integration);

        $contactData = [
            'AccountId' => $accountId,
            'FirstName' => $user->getUsername() ? explode(' ', $user->getUsername())[0] : 'User',
            'LastName' => $user->getUsername() ? (explode(' ', $user->getUsername())[1] ?? 'User') : 'User',
            'Email' => $user->getEmail(),
        ];

        if ($phone) {
            $contactData['Phone'] = $phone;
        }

        if ($address) {
            $contactData['MailingStreet'] = $address;
        }

        $instanceUrl = $integration->getInstanceUrl();
        if (!$instanceUrl) {
            throw new \RuntimeException('Salesforce instance URL is missing. Please reconnect.');
        }

        $this->logger->info('Creating Salesforce Contact', [
            'accountId' => $accountId,
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'phone' => $phone,
            'address' => $address,
            'instanceUrl' => $instanceUrl,
        ]);

        $response = $client->request('POST', $instanceUrl . '/services/data/v57.0/sobjects/Contact', [
            'json' => $contactData,
        ]);

        $data = $response->toArray(false);

        if (isset($data['id'])) {
            $this->logger->info('Successfully created Salesforce Contact', [
                'contactId' => $data['id'],
                'contactData' => $contactData,
                'response' => $data
            ]);
            $this->existingContactUsed = false;
            return $data['id'];
        }

        if (isset($data[0]['errorCode']) && $data[0]['errorCode'] === 'DUPLICATES_DETECTED') {
            $this->logger->info('Contact duplicates detected, finding existing contact');
            $existingContactId = $this->findExistingContact($client, $instanceUrl, $user->getEmail());
            if ($existingContactId) {
                $this->updateContactAccount($client, $instanceUrl, $existingContactId, $accountId);
                $this->existingContactUsed = true;
                $this->logger->info('Using existing Salesforce Contact', ['contactId' => $existingContactId]);
                return $existingContactId;
            }
        }

        $this->logger->error('Failed to create Salesforce Contact', ['response' => $data]);
        throw new \RuntimeException('Failed to create Salesforce Contact: ' . json_encode($data));
    }

    public function getAccountDetails(SalesforceIntegration $integration, string $accountId): ?array
    {
        $client = $this->getAuthorizedClient($integration);
        $instanceUrl = $integration->getInstanceUrl();

        if (!$instanceUrl) {
            return null;
        }

        try {
            $response = $client->request('GET', $instanceUrl . '/services/data/v57.0/sobjects/Account/' . $accountId);
            return $response->toArray(false);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get Account details', ['accountId' => $accountId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function getContactDetails(SalesforceIntegration $integration, string $contactId): ?array
    {
        $client = $this->getAuthorizedClient($integration);
        $instanceUrl = $integration->getInstanceUrl();

        if (!$instanceUrl) {
            return null;
        }

        try {
            $response = $client->request('GET', $instanceUrl . '/services/data/v57.0/sobjects/Contact/' . $contactId);
            return $response->toArray(false);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get Contact details', ['contactId' => $contactId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function findExistingContact(\Symfony\Contracts\HttpClient\HttpClientInterface $client, string $instanceUrl, string $email): ?string
    {
        try {
            $query = "SELECT Id FROM Contact WHERE Email = '" . addslashes($email) . "' LIMIT 1";
            $response = $client->request('GET', $instanceUrl . '/services/data/v57.0/query', [
                'query' => ['q' => $query],
            ]);

            $data = $response->toArray(false);

            if (isset($data['records'][0]['Id'])) {
                return $data['records'][0]['Id'];
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    public function updateContactAccount(\Symfony\Contracts\HttpClient\HttpClientInterface $client, string $instanceUrl, string $contactId, string $accountId): void
    {
        try {
            $client->request('PATCH', $instanceUrl . '/services/data/v57.0/sobjects/Contact/' . $contactId, [
                'json' => ['AccountId' => $accountId],
            ]);
        } catch (\Exception $e) {
        }
    }
}



