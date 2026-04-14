<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;

final class TransferDomainOperation
{
    /**
     * @return array{
     *   success: bool,
     *   details?: string,
     *   context?: array<string, mixed>,
     *   request?: array<string, mixed>
     * }
     */
    public static function execute(ApiClient $client, string $domain, string $authCode): array
    {
        $endpoint = '/domain/transfer/create/' . $domain;
        $payload = ['authCode' => $authCode];

        $response = $client->request('TransferDomain', $endpoint, $payload);
        if (($response['success'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Transfer request failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                ],
                'request' => [
                    'operation' => 'TransferDomain',
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
                'operation' => 'TransferDomain',
                'endpoint' => $endpoint,
                'payload' => $payload,
            ],
        ];
    }
}
