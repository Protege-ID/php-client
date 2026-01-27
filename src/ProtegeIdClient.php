<?php

namespace ProtegeId;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use ProtegeId\DataObjects\ProtegeIdSession;
use ProtegeId\DataObjects\ProtegeIdVerificationResult;
use ProtegeId\Enums\ProtegeIdVerificationStatus;
use ProtegeId\Http\HttpStatusCodes;
use ProtegeId\Exceptions\ApiException;
use ProtegeId\Exceptions\ConfigException;
use ProtegeId\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * ProtegeId PHP SDK Client
 *
 * Client for integrating with the ProtegeId Age Verification API.
 * Provides methods to create verification sessions and check verification status.
 *
 * @package ProtegeId
 */
final class ProtegeIdClient
{
    private const BASE_URL = 'https://api.protegeid.com.br';
    private const SESSIONS_ENDPOINT = '/api/age-verification/sessions';
    private const CLIENT_TIMEOUT = 10;

    private string $apiKey;
    private GuzzleClient $client;

    /**
     * Initialize ProtegeId client
     *
     * @param string $apiKey Your ProtegeId API key
     * @param GuzzleClient|null $client Optional Guzzle client for testing/custom configuration
     * @param string $baseUrl Optional base URL (defaults to production API)
     * @param int $timeout Request timeout in seconds (default: 10)
     *
     * @throws ConfigException If API key is empty
     */
    public function __construct(
        string $apiKey,
        ?GuzzleClient $client = null,
        string $baseUrl = self::BASE_URL,
        int $timeout = self::CLIENT_TIMEOUT
    ) {
        $apiKey = trim($apiKey);
        if (empty($apiKey)) {
            throw new ConfigException('apiKey must not be empty.');
        }

        $this->apiKey = $apiKey;
        $this->client = $client ?? new GuzzleClient([
            'base_uri' => $baseUrl,
            'timeout' => $timeout,
            'http_errors' => false,
        ]);
    }

    /**
     * Creates a session and returns a temporary URL where the user can complete
     *
     * @param string $userRef Unique identifier for the user in your system
     * @param string $returnUrl Optional callback URL to redirect user after verification completes
     * @param array<string, mixed> $metadata Optional metadata to associate with this session
     *
     * @return ProtegeIdSession The created session containing sessionId, temporaryUrl, and expiration
     *
     * @throws ValidationException If userRef is empty
     * @throws ApiException If API returns an error or unexpected response
     */
    public function createSession(string $userRef, string $returnUrl = '', array $metadata = []): ProtegeIdSession
    {
        if (empty($userRef)) {
            throw new ValidationException('userRef must not be empty.');
        }

        $payload = array_filter([
            'user_ref' => $userRef,
            'return_url' => $returnUrl,
            'partner_metadata' => $metadata,
        ]);

        $response = $this->callApi('POST', self::SESSIONS_ENDPOINT, $payload);
        return $this->parseCreateSessionResponse($response, $userRef);
    }

    /**
     * Retrieves the most recent verification result for a given user reference.
     *
     * @param string $userRef Unique identifier for the user in your system
     * @param int $limit Maximum number of results to return (default: 1, returns most recent)
     *
     * @return ProtegeIdVerificationResult The verification result with status and age verification flag
     *
     * @throws ValidationException If userRef is empty
     * @throws ApiException If API returns an error, empty data, or unexpected response
     */
    public function verifySession(string $userRef, int $limit = 1): ProtegeIdVerificationResult
    {
        if (empty($userRef)) {
            throw new ValidationException('userRef must not be empty.');
        }

        $response = $this->callApi(
            method: 'GET',
            uri: self::SESSIONS_ENDPOINT,
            queryParams: [
                'user_ref' => $userRef,
                'limit' => $limit,
            ]
        );

        return $this->parseVerificationResponse($response);
    }

    private function callApi(
        string $method,
        string $uri,
        array $data = [],
        array $queryParams = []
    ): ResponseInterface {
        $options = array_filter([
            'json' => $data,
            'query' => $queryParams,
            'headers' => $this->getRequestHeaders(),
        ]);

        try {
            return $this->client->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            throw new ApiException($e->getMessage(), $e->getCode(), previous: $e);
        }
    }

    private function parseCreateSessionResponse(ResponseInterface $response, string $userRef): ProtegeIdSession
    {
        $statusCode = $response->getStatusCode();
        $responseData = $this->decodeResponse($response);

        if ($statusCode === HttpStatusCodes::BAD_REQUEST) {
            $message = $responseData['message'] ?? 'Validation error.';
            throw new ApiException($message, $statusCode, $responseData);
        }

        if ($statusCode !== HttpStatusCodes::CREATED) {
            throw new ApiException('Unexpected response status while creating session.', $statusCode, $responseData);
        }

        $sessionId = $responseData['sessionId'] ?? null;
        $temporaryUrl = $responseData['temporaryUrl'] ?? null;
        if (empty($sessionId) || empty($temporaryUrl)) {
            throw new ApiException('Response missing sessionId or temporaryUrl.', $statusCode, $responseData);
        }

        return new ProtegeIdSession(
            sessionId: $sessionId,
            temporaryUrl: $temporaryUrl,
            userRef: $userRef,
            expiresAt: $responseData['expiresAt'] ?? null
        );
    }

    private function parseVerificationResponse(ResponseInterface $response): ProtegeIdVerificationResult
    {
        $statusCode = $response->getStatusCode();
        $responseData = $this->decodeResponse($response);

        if ($statusCode !== HttpStatusCodes::OK) {
            throw new ApiException(
                'Unexpected response status while fetching verification.',
                $statusCode,
                $responseData
            );
        }

        if (empty($responseData['data']) || !is_array($responseData['data'])) {
            throw new ApiException(
                'Response data is empty or invalid.',
                $statusCode,
                $responseData
            );
        }

        $verificationData = $responseData['data'][0];
        $userRef = $verificationData['user_ref'] ?? null;
        if (empty($userRef)) {
            throw new ApiException(
                'Response missing user_ref.',
                $statusCode,
                $responseData
            );
        }

        $status = ProtegeIdVerificationStatus::tryFrom($verificationData['status'] ?? '');
        if ($status === null) {
            throw new ApiException(
                'Response contains unknown status.',
                $statusCode,
                $responseData
            );
        }

        return new ProtegeIdVerificationResult(
            userRef: $userRef,
            status: $status,
            ageVerified: $verificationData['age_verified'] ?? null
        );
    }

    private function decodeResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('Invalid JSON response: ' . json_last_error_msg());
        }

        return $data;
    }

    private function getRequestHeaders(): array
    {
        return [
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}
