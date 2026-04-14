<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;

final class SaveRegistrarLockOperation
{
    /**
     * @return array{
     *   success: bool,
     *   details?: string,
     *   context?: array<string, mixed>,
     *   request?: array<string, mixed>
     * }
     */
    public static function execute(ApiClient $client, string $domain, bool $lockEnabled): array
    {
        $endpoint = '/domain/updateLock/' . $domain;
        $payload = [
            'lock' => $lockEnabled ? 'on' : 'off',
        ];

        $response = $client->request('SaveRegistrarLock', $endpoint, $payload);
        if (($response['success'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Save registrar lock request failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                ],
                'request' => [
                    'operation' => 'SaveRegistrarLock',
                    'endpoint' => $endpoint,
                    'payload' => $payload,
                ],
            ];
        }

        return [
            'success' => true,
            'context' => [
                'request' => $response['context'] ?? [],
                'status' => 'success',
                'lockEnabled' => $lockEnabled,
            ],
            'request' => [
                'operation' => 'SaveRegistrarLock',
                'endpoint' => $endpoint,
                'payload' => $payload,
            ],
        ];
    }
}
