<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;

final class SaveNameserversOperation
{
    /**
     * @param array<int, string> $nameservers
     * @return array{
     *   success: bool,
     *   details?: string,
     *   context?: array<string, mixed>,
     *   request?: array<string, mixed>
     * }
     */
    public static function execute(ApiClient $client, string $domain, array $nameservers): array
    {
        $endpoint = '/domain/updateNs/' . $domain;
        $payload = ['ns' => $nameservers];
        $response = $client->request('SaveNameservers', $endpoint, $payload);

        if (($response['success'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Save nameservers request failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                ],
                'request' => [
                    'operation' => 'SaveNameservers',
                    'endpoint' => $endpoint,
                    'payload' => $payload,
                ],
            ];
        }

        return [
            'success' => true,
            'context' => [
                'request' => $response['context'] ?? [],
                'count' => count($nameservers),
            ],
            'request' => [
                'operation' => 'SaveNameservers',
                'endpoint' => $endpoint,
                'payload' => $payload,
            ],
        ];
    }
}
