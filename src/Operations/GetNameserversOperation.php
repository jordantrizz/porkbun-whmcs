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
      *   warning?: string,
      *   warningCode?: string,
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
            $apiCode = self::extractApiCode($apiResponse);
            $isApiOptInWarning = $apiCode === 'DOMAIN_IS_NOT_OPTED_IN_TO_API_ACCESS';
            $warningMessage = 'Nameserver sync warning for ' . $domain . ': domain is not opted in to API access at Porkbun. '
                . 'Using existing WHMCS nameserver values.';

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Get nameservers request failed.'),
                'warning' => $isApiOptInWarning ? $warningMessage : '',
                'warningCode' => $isApiOptInWarning ? $apiCode : '',
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                    'apiStatus' => (string) ($error['apiStatus'] ?? ''),
                    'apiMessage' => (string) ($error['apiMessage'] ?? ''),
                    'apiCode' => $apiCode,
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

    /**
     * @param array<string, mixed> $data
     */
    private static function extractApiCode(array $data): string
    {
        if (!isset($data['code']) || !is_string($data['code'])) {
            return '';
        }

        return strtoupper(trim($data['code']));
    }
 }
