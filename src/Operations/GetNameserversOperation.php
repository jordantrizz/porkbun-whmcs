<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;

final class GetNameserversOperation
{
    /**
     * @return array{
     *   success: bool,
     *   nameservers?: array<int, string>,
     *   details?: string,
     *   context?: array<string, mixed>,
     *   request?: array<string, mixed>
     * }
     */
    public static function execute(ApiClient $client, string $domain): array
    {
        $endpoint = '/domain/getNs/' . $domain;
        $response = $client->request('GetNameservers', $endpoint, []);

        if (($response['success'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];
            $apiResponse = is_array($response['data'] ?? null) ? $response['data'] : [];

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Get nameservers request failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                    'apiStatus' => (string) ($error['apiStatus'] ?? ''),
                    'apiMessage' => (string) ($error['apiMessage'] ?? ''),
                    'apiResponse' => $apiResponse,
                ],
                'request' => [
                    'operation' => 'GetNameservers',
                    'endpoint' => $endpoint,
                    'payload' => [],
                ],
            ];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $nameservers = self::extractNameservers($data);

        return [
            'success' => true,
            'nameservers' => $nameservers,
            'context' => [
                'request' => $response['context'] ?? [],
                'count' => count($nameservers),
            ],
            'request' => [
                'operation' => 'GetNameservers',
                'endpoint' => $endpoint,
                'payload' => [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private static function extractNameservers(array $data): array
    {
        $candidates = [
            $data['ns'] ?? null,
            $data['nameservers'] ?? null,
            $data['domain']['ns'] ?? null,
            $data['domain']['nameservers'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $normalized = [];
            foreach ($candidate as $item) {
                if (!is_string($item)) {
                    continue;
                }

                $value = trim($item);
                if ($value === '') {
                    continue;
                }

                $normalized[] = strtolower($value);
            }

            if ($normalized !== []) {
                return array_values(array_unique($normalized));
             }
         }

         return [];
     }
 }
