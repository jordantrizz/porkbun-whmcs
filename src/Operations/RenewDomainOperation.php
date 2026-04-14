<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;

final class RenewDomainOperation
{
    /**
     * @return array{
     *   success: bool,
     *   details?: string,
     *   context?: array<string, mixed>,
     *   request?: array<string, mixed>
     * }
     */
    public static function execute(ApiClient $client, string $domain, int $years): array
    {
        $years = $years > 0 ? $years : 1;
        $endpoint = '/domain/renew/' . $domain;
        $payload = ['years' => $years];

        $response = $client->request('RenewDomain', $endpoint, $payload);
        if (($response['success'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Renew request failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                ],
                'request' => [
                    'operation' => 'RenewDomain',
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
            ],
            'request' => [
                'operation' => 'RenewDomain',
                'endpoint' => $endpoint,
                'payload' => $payload,
            ],
        ];
    }
}
