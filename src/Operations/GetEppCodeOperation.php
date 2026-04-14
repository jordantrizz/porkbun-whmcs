<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;

final class GetEppCodeOperation
{
    /**
     * @return array{
     *   success: bool,
     *   eppCode?: string,
     *   details?: string,
     *   context?: array<string, mixed>,
     *   request?: array<string, mixed>
     * }
     */
    public static function execute(ApiClient $client, string $domain): array
    {
        $endpoint = '/domain/getAuthCode/' . $domain;
        $response = $client->request('GetEPPCode', $endpoint, []);

        if (($response['success'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Get EPP code request failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                ],
                'request' => [
                    'operation' => 'GetEPPCode',
                    'endpoint' => $endpoint,
                    'payload' => [],
                ],
            ];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $eppCode = self::extractEppCode($data);

        if ($eppCode === null) {
            return [
                'success' => false,
                'details' => 'Registry did not return an EPP/Auth code for this domain.',
                'context' => [
                    'request' => $response['context'] ?? [],
                ],
                'request' => [
                    'operation' => 'GetEPPCode',
                    'endpoint' => $endpoint,
                    'payload' => [],
                ],
            ];
        }

        return [
            'success' => true,
            'eppCode' => $eppCode,
            'context' => [
                'request' => $response['context'] ?? [],
                'status' => 'success',
            ],
            'request' => [
                'operation' => 'GetEPPCode',
                'endpoint' => $endpoint,
                'payload' => [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractEppCode(array $data): ?string
    {
        $candidates = [
            $data['authCode'] ?? null,
            $data['eppCode'] ?? null,
            $data['authcode'] ?? null,
            $data['eppcode'] ?? null,
            $data['domain']['authCode'] ?? null,
            $data['domain']['eppCode'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $value = trim($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
