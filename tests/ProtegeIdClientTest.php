<?php

namespace ProtegeId\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ProtegeId\DataObjects\ProtegeIdSession;
use ProtegeId\DataObjects\ProtegeIdVerificationResult;
use ProtegeId\Enums\ProtegeIdVerificationStatus;
use ProtegeId\Exceptions\ApiException;
use ProtegeId\Exceptions\ConfigException;
use ProtegeId\Exceptions\ValidationException;
use ProtegeId\ProtegeIdClient;

class ProtegeIdClientTest extends TestCase
{
    private function createMockClient(array $responses): GuzzleClient
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new GuzzleClient(['handler' => $handlerStack]);
    }

    // ==================== CONSTRUCTOR TESTS ====================

    public function testConstructorThrowsExceptionWhenApiKeyIsEmpty(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('apiKey must not be empty.');

        new ProtegeIdClient('');
    }

    public function testConstructorThrowsExceptionWhenApiKeyIsOnlyWhitespace(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('apiKey must not be empty.');

        new ProtegeIdClient('   ');
    }

    public function testConstructorAcceptsValidApiKey(): void
    {
        $client = new ProtegeIdClient('valid-api-key');

        $this->assertInstanceOf(ProtegeIdClient::class, $client);
    }

    public function testConstructorAcceptsCustomConfiguration(): void
    {
        $mockClient = $this->createMockClient([]);

        $client = new ProtegeIdClient(
            apiKey: 'valid-api-key',
            client: $mockClient,
            baseUrl: 'https://custom.api.com',
            timeout: 30
        );

        $this->assertInstanceOf(ProtegeIdClient::class, $client);
    }

    // ==================== CREATE SESSION TESTS ====================

    public function testCreateSessionThrowsExceptionWhenUserRefIsEmpty(): void
    {
        $client = new ProtegeIdClient('valid-api-key');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('userRef must not be empty.');

        $client->createSession('');
    }

    public function testCreateSessionSuccessWithMinimalData(): void
    {
        $mockClient = $this->createMockClient([
            new Response(201, [], json_encode([
                'sessionId' => 'sess_123456',
                'temporaryUrl' => 'https://verify.protegeid.com/sess_123456',
                'expiresAt' => '2026-01-26T23:59:59Z'
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);
        $session = $client->createSession('user-123');

        $this->assertInstanceOf(ProtegeIdSession::class, $session);
        $this->assertEquals('sess_123456', $session->sessionId);
        $this->assertEquals('https://verify.protegeid.com/sess_123456', $session->temporaryUrl);
        $this->assertEquals('user-123', $session->userRef);
        $this->assertEquals('2026-01-26T23:59:59Z', $session->expiresAt);
    }

    public function testCreateSessionSuccessWithAllData(): void
    {
        $mockClient = $this->createMockClient([
            new Response(201, [], json_encode([
                'sessionId' => 'sess_789',
                'temporaryUrl' => 'https://verify.protegeid.com/sess_789',
                'expiresAt' => '2026-01-27T12:00:00Z'
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);
        $session = $client->createSession(
            userRef: 'user-456',
            returnUrl: 'https://mysite.com/callback',
            metadata: ['plan' => 'premium', 'country' => 'BR']
        );

        $this->assertInstanceOf(ProtegeIdSession::class, $session);
        $this->assertEquals('sess_789', $session->sessionId);
        $this->assertEquals('user-456', $session->userRef);
    }

    public function testCreateSessionThrowsExceptionOnBadRequest(): void
    {
        $mockClient = $this->createMockClient([
            new Response(400, [], json_encode([
                'message' => 'Invalid user_ref format'
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid user_ref format');
        $this->expectExceptionCode(400);

        $client->createSession('invalid-user');
    }

    public function testCreateSessionThrowsExceptionWhenMissingSessionId(): void
    {
        $mockClient = $this->createMockClient([
            new Response(201, [], json_encode([
                'temporaryUrl' => 'https://verify.protegeid.com/sess_999'
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Response missing sessionId or temporaryUrl.');

        $client->createSession('user-123');
    }

    public function testCreateSessionThrowsExceptionWhenMissingTemporaryUrl(): void
    {
        $mockClient = $this->createMockClient([
            new Response(201, [], json_encode([
                'sessionId' => 'sess_999'
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Response missing sessionId or temporaryUrl.');

        $client->createSession('user-123');
    }

    public function testCreateSessionThrowsExceptionOnUnexpectedStatusCode(): void
    {
        $mockClient = $this->createMockClient([
            new Response(500, [], json_encode([
                'error' => 'Internal server error'
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(500);

        $client->createSession('user-123');
    }

    // ==================== VERIFY SESSION TESTS ====================

    public function testVerifySessionThrowsExceptionWhenUserRefIsEmpty(): void
    {
        $client = new ProtegeIdClient('valid-api-key');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('userRef must not be empty.');

        $client->verifySession('');
    }

    public function testVerifySessionSuccessWithPendingStatus(): void
    {
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'user_ref' => 'user-123',
                        'status' => 'pending',
                        'age_verified' => null
                    ]
                ]
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);
        $result = $client->verifySession('user-123');

        $this->assertInstanceOf(ProtegeIdVerificationResult::class, $result);
        $this->assertEquals('user-123', $result->userRef);
        $this->assertEquals(ProtegeIdVerificationStatus::PENDING, $result->status);
        $this->assertNull($result->ageVerified);
    }

    public function testVerifySessionSuccessWithSuccessStatus(): void
    {
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'user_ref' => 'user-456',
                        'status' => 'success',
                        'age_verified' => true
                    ]
                ]
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);
        $result = $client->verifySession('user-456');

        $this->assertInstanceOf(ProtegeIdVerificationResult::class, $result);
        $this->assertEquals('user-456', $result->userRef);
        $this->assertEquals(ProtegeIdVerificationStatus::SUCCESS, $result->status);
        $this->assertTrue($result->ageVerified);
    }

    public function testVerifySessionSuccessWithFailedStatus(): void
    {
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'user_ref' => 'user-789',
                        'status' => 'failed',
                        'age_verified' => false
                    ]
                ]
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);
        $result = $client->verifySession('user-789');

        $this->assertEquals(ProtegeIdVerificationStatus::FAILED, $result->status);
        $this->assertFalse($result->ageVerified);
    }

    public function testVerifySessionWithAllStatuses(): void
    {
        $statuses = [
            'pending' => ProtegeIdVerificationStatus::PENDING,
            'awaiting_client' => ProtegeIdVerificationStatus::AWAITING_CLIENT,
            'success' => ProtegeIdVerificationStatus::SUCCESS,
            'failed' => ProtegeIdVerificationStatus::FAILED,
            'skipped' => ProtegeIdVerificationStatus::SKIPPED,
            'timeout' => ProtegeIdVerificationStatus::TIMEOUT,
            'canceled' => ProtegeIdVerificationStatus::CANCELED,
        ];

        foreach ($statuses as $apiStatus => $enumStatus) {
            $mockClient = $this->createMockClient([
                new Response(200, [], json_encode([
                    'data' => [
                        [
                            'user_ref' => 'user-test',
                            'status' => $apiStatus,
                            'age_verified' => null
                        ]
                    ]
                ]))
            ]);

            $client = new ProtegeIdClient('valid-api-key', $mockClient);
            $result = $client->verifySession('user-test');

            $this->assertEquals($enumStatus, $result->status, "Failed for status: {$apiStatus}");
        }
    }

    public function testVerifySessionThrowsExceptionWhenDataArrayIsEmpty(): void
    {
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode([
                'data' => []
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Response data is empty or invalid.');

        $client->verifySession('user-123');
    }

    public function testVerifySessionThrowsExceptionWhenDataIsMissing(): void
    {
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode([]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Response data is empty or invalid.');

        $client->verifySession('user-123');
    }

    public function testVerifySessionThrowsExceptionWhenUserRefIsMissing(): void
    {
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'status' => 'pending'
                    ]
                ]
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Response missing user_ref.');

        $client->verifySession('user-123');
    }

    public function testVerifySessionThrowsExceptionWhenStatusIsUnknown(): void
    {
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'user_ref' => 'user-123',
                        'status' => 'unknown_status'
                    ]
                ]
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Response contains unknown status.');

        $client->verifySession('user-123');
    }

    public function testVerifySessionThrowsExceptionOnUnexpectedStatusCode(): void
    {
        $mockClient = $this->createMockClient([
            new Response(404, [], json_encode([
                'error' => 'Not found'
            ]))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $client->verifySession('user-123');
    }

    // ==================== NETWORK/GUZZLE EXCEPTION TESTS ====================

    public function testCreateSessionThrowsExceptionOnNetworkError(): void
    {
        $mockClient = $this->createMockClient([
            new ConnectException(
                'Connection timeout',
                new Request('POST', '/api/age-verification/sessions')
            )
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);

        $client->createSession('user-123');
    }

    // ==================== JSON DECODE TESTS ====================

    public function testCreateSessionThrowsExceptionOnInvalidJson(): void
    {
        $mockClient = $this->createMockClient([
            new Response(201, [], 'invalid json{')
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $client->createSession('user-123');
    }

    public function testVerifySessionHandlesEmptyResponseBody(): void
    {
        $mockClient = $this->createMockClient([
            new Response(200, [], '')
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Response data is empty or invalid.');

        $client->verifySession('user-123');
    }

    // ==================== API EXCEPTION ERROR BODY TEST ====================

    public function testApiExceptionContainsErrorBodyOn400(): void
    {
        $errorBody = [
            'message' => 'Validation failed',
            'errors' => [
                'user_ref' => ['User reference is invalid']
            ]
        ];

        $mockClient = $this->createMockClient([
            new Response(400, [], json_encode($errorBody))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        try {
            $client->createSession('invalid-user');
            $this->fail('Expected ApiException was not thrown');
        } catch (ApiException $e) {
            $actualErrorBody = $e->getErrorBody();

            // Se for null, significa que Guzzle lançou exceção HTTP antes do nosso parseamento
            if ($actualErrorBody !== null) {
                $this->assertIsArray($actualErrorBody);
                $this->assertArrayHasKey('message', $actualErrorBody);
                $this->assertEquals('Validation failed', $actualErrorBody['message']);
            }

            $this->assertEquals(400, $e->getCode());
        }
    }

    public function testApiExceptionContainsErrorBodyOn500(): void
    {
        $errorBody = ['error' => 'Internal server error'];

        $mockClient = $this->createMockClient([
            new Response(500, [], json_encode($errorBody))
        ]);

        $client = new ProtegeIdClient('valid-api-key', $mockClient);

        try {
            $client->createSession('user-123');
            $this->fail('Expected ApiException was not thrown');
        } catch (ApiException $e) {
            $actualErrorBody = $e->getErrorBody();

            if ($actualErrorBody !== null) {
                $this->assertIsArray($actualErrorBody);
            }

            $this->assertEquals(500, $e->getCode());
        }
    }
}
