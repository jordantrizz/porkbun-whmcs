<?php

namespace PorkbunWhmcs\Registrar;

final class ApiClient
{
    /** @var array<string, mixed> */
    private static array $metrics = [
        'requestsTotal' => 0,
        'successTotal' => 0,
        'failureTotal' => 0,
        'operations' => [],
    ];

    private string $apiKey;
    private string $secretApiKey;
    private int $timeout;
    private string $baseUrl;

    public function __construct(string $apiKey, string $secretApiKey, int $timeout = 20, ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey;
        $this->secretApiKey = $secretApiKey;
        $this->timeout = $timeout;
        $this->baseUrl = $baseUrl ?? 'https://api.porkbun.com/api/json/v3';
    }

    /**
     * Builds a request payload with authentication fields.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function withAuth(array $payload = []): array
    {
        return $payload + [
            'apikey' => $this->apiKey,
            'secretapikey' => $this->secretApiKey,
        ];
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   success: bool,
     *   data?: array<string, mixed>,
        *   error?: array{type: string, message: string, statusCode?: int, apiStatus?: string, apiMessage?: string},
        *   context: array{operation: string, endpoint: string, durationMs: int, statusCode: int, correlationId: string, fullUrl?: string}
     * }
     */
    public function request(string $operation, string $endpoint, array $payload = []): array
    {
        $startedAt = microtime(true);
        $statusCode = 0;
        $correlationId = $this->newCorrelationId();

        if (stripos($this->baseUrl, 'https://') !== 0) {
            $context = $this->buildContext($operation, $endpoint, $startedAt, $statusCode, $correlationId);
            self::recordMetric($operation, $context['durationMs'], false, 'configuration');

            return [
                'success' => false,
                'error' => [
                    'type' => 'configuration',
                    'message' => 'TLS is required for API requests.',
                ],
                'context' => $context,
            ];
        }

        if (!function_exists('curl_init')) {
            $context = $this->buildContext($operation, $endpoint, $startedAt, $statusCode, $correlationId);
            self::recordMetric($operation, $context['durationMs'], false, 'configuration');

            return [
                'success' => false,
                'error' => [
                    'type' => 'configuration',
                    'message' => 'cURL extension is required.',
                ],
                'context' => $context,
            ];
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $body = json_encode($this->withAuth($payload));

        if ($body === false) {
            $context = $this->buildContext($operation, $endpoint, $startedAt, $statusCode, $correlationId);
            self::recordMetric($operation, $context['durationMs'], false, 'request');

            return [
                'success' => false,
                'error' => [
                    'type' => 'request',
                    'message' => 'Failed to encode request payload.',
                ],
                'context' => $context,
            ];
        }

        $curl = curl_init($url);
        if ($curl === false) {
            $context = $this->buildContext($operation, $endpoint, $startedAt, $statusCode, $correlationId);
            self::recordMetric($operation, $context['durationMs'], false, 'network');

            return [
                'success' => false,
                'error' => [
                    'type' => 'network',
                    'message' => 'Failed to initialize HTTP client.',
                ],
                'context' => $context,
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($responseBody === false) {
            $context = $this->buildContext($operation, $endpoint, $startedAt, $statusCode, $correlationId);
            self::recordMetric($operation, $context['durationMs'], false, 'network');

            return [
                'success' => false,
                'error' => [
                    'type' => 'network',
                    'message' => $curlError !== '' ? $curlError : 'HTTP request failed.',
                    'statusCode' => $statusCode,
                ],
                'context' => $context,
            ];
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            $context = $this->buildContext($operation, $endpoint, $startedAt, $statusCode, $correlationId);
            self::recordMetric($operation, $context['durationMs'], false, 'parse');

            return [
                'success' => false,
                'error' => [
                    'type' => 'parse',
                    'message' => 'Invalid JSON response from API.',
                    'statusCode' => $statusCode,
                ],
                'context' => $context,
            ];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $context = $this->buildContext($operation, $endpoint, $startedAt, $statusCode, $correlationId, $url);
            $apiStatus = isset($decoded['status']) ? strtolower((string) $decoded['status']) : '';
            $apiMessage = $this->extractApiMessage($decoded);
            $httpMessage = 'API returned non-success HTTP status (' . $statusCode . ').';
            if ($apiMessage !== null) {
                $httpMessage .= ' API message: ' . $apiMessage;
            }
            self::recordMetric($operation, $context['durationMs'], false, 'http');

            return [
                'success' => false,
                'error' => [
                    'type' => 'http',
                    'message' => $httpMessage,
                    'statusCode' => $statusCode,
                    'apiStatus' => $apiStatus,
                    'apiMessage' => $apiMessage ?? '',
                ],
                'data' => $decoded,
                'context' => $context,
            ];
        }

        $apiStatus = isset($decoded['status']) ? strtolower((string) $decoded['status']) : '';
        if ($apiStatus !== '' && $apiStatus !== 'success') {
            $apiMessage = isset($decoded['message']) ? (string) $decoded['message'] : 'Porkbun API returned an error status.';
            $context = $this->buildContext($operation, $endpoint, $startedAt, $statusCode, $correlationId, $url);
            self::recordMetric($operation, $context['durationMs'], false, 'api');

            return [
                'success' => false,
                'error' => [
                    'type' => 'api',
                    'message' => $apiMessage,
                    'statusCode' => $statusCode,
                ],
                'data' => $decoded,
                'context' => $context,
            ];
        }

        $context = $this->buildContext($operation, $endpoint, $startedAt, $statusCode, $correlationId, $url);
        self::recordMetric($operation, $context['durationMs'], true, null);

        return [
            'success' => true,
            'data' => $decoded,
            'context' => $context,
        ];
    }

    /**
     * @return array{success: bool, error?: string, details?: string, context?: array<string, mixed>}
     */
    public function validateCredentials(): array
    {
        $response = $this->request('TestConnection', '/ping');
        if (!$response['success']) {
            $error = $response['error'] ?? ['message' => 'Credential test failed.'];

            return [
                'success' => false,
                'error' => 'Credential test failed.',
                'details' => (string) ($error['message'] ?? 'Credential test failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                ],
            ];
        }

        return [
            'success' => true,
            'context' => [
                'request' => $response['context'] ?? [],
                'status' => 'success',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function redactContext(array $context): array
    {
        $sensitiveKeys = ['apikey', 'apiKey', 'secretapikey', 'secretApiKey', 'password', 'token', 'authorization'];
        $redacted = [];

        foreach ($context as $key => $value) {
            if (in_array((string) $key, $sensitiveKeys, true)) {
                $redacted[$key] = '***redacted***';
                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = self::redactContext($value);
                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getMetricsSnapshot(): array
    {
        return self::$metrics;
    }

    public static function resetMetrics(): void
    {
        self::$metrics = [
            'requestsTotal' => 0,
            'successTotal' => 0,
            'failureTotal' => 0,
            'operations' => [],
        ];
    }

    /**
     * @return array{operation: string, endpoint: string, durationMs: int, statusCode: int, correlationId: string, fullUrl?: string}
     */
    private function buildContext(
        string $operation,
        string $endpoint,
        float $startedAt,
        int $statusCode,
        string $correlationId,
        ?string $fullUrl = null
    ): array
    {
        $context = [
            'operation' => $operation,
            'endpoint' => $endpoint,
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
            'statusCode' => $statusCode,
            'correlationId' => $correlationId,
        ];

        if ($fullUrl !== null && $fullUrl !== '') {
            $context['fullUrl'] = $fullUrl;
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractApiMessage(array $decoded): ?string
    {
        if (isset($decoded['message']) && is_string($decoded['message'])) {
            $message = trim($decoded['message']);
            if ($message !== '') {
                return $message;
            }
        }

        if (isset($decoded['error']) && is_string($decoded['error'])) {
            $message = trim($decoded['error']);
            if ($message !== '') {
                return $message;
            }
        }

        return null;
    }

    private function newCorrelationId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable $exception) {
            return uniqid('pb', true);
        }
    }

    private static function recordMetric(string $operation, int $durationMs, bool $success, ?string $errorType): void
    {
        self::$metrics['requestsTotal']++;
        if ($success) {
            self::$metrics['successTotal']++;
        } else {
            self::$metrics['failureTotal']++;
        }

        if (!isset(self::$metrics['operations'][$operation])) {
            self::$metrics['operations'][$operation] = [
                'requests' => 0,
                'success' => 0,
                'failure' => 0,
                'totalDurationMs' => 0,
                'avgDurationMs' => 0,
                'lastErrorType' => null,
            ];
        }

        self::$metrics['operations'][$operation]['requests']++;
        self::$metrics['operations'][$operation]['totalDurationMs'] += $durationMs;
        $requests = (int) self::$metrics['operations'][$operation]['requests'];
        $totalDuration = (int) self::$metrics['operations'][$operation]['totalDurationMs'];
        self::$metrics['operations'][$operation]['avgDurationMs'] = $requests > 0
            ? (int) round($totalDuration / $requests)
            : 0;

        if ($success) {
            self::$metrics['operations'][$operation]['success']++;
        } else {
            self::$metrics['operations'][$operation]['failure']++;
            self::$metrics['operations'][$operation]['lastErrorType'] = $errorType;
        }
    }
}
