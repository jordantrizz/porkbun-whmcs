<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;
use PorkbunWhmcs\Registrar\DomainCache;

final class RefreshNameserversOperation
{
    /**
     * Fetches nameservers live from the registry and writes them through to cache.
     *
     * @return array{
     *   success: bool,
     *   nameservers?: array<int, string>,
     *   details?: string,
     *   context?: array<string, mixed>,
     *   request?: array<string, mixed>
     * }
     */
    public static function execute(ApiClient $client, string $domain, int $cacheTtlSeconds): array
    {
        $normalizedDomain = strtolower(trim($domain));
        $endpoint = '/domain/getNs/' . $normalizedDomain;
        $response = $client->request('RefreshNameservers', $endpoint, []);

        if (($response['success'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Nameserver refresh failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                ],
                'request' => [
                    'operation' => 'RefreshNameservers',
                    'endpoint' => $endpoint,
                    'payload' => [],
                ],
            ];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $nameservers = self::extractNameservers($data);

        if ($nameservers === []) {
            return [
                'success' => false,
                'details' => 'Nameserver refresh returned no nameservers.',
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => 'empty',
                    'statusCode' => 0,
                ],
                'request' => [
                    'operation' => 'RefreshNameservers',
                    'endpoint' => $endpoint,
                    'payload' => [],
                ],
            ];
        }

        $cached = DomainCache::put(
            $client->getCredentialFingerprint(),
            $normalizedDomain,
            'nameservers',
            $nameservers,
            $cacheTtlSeconds
        );

        if ($cached !== true) {
            return [
                'success' => false,
                'details' => 'Nameserver refresh succeeded but the cache write failed.',
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => 'cache_write',
                    'statusCode' => 0,
                ],
                'request' => [
                    'operation' => 'RefreshNameservers',
                    'endpoint' => $endpoint,
                    'payload' => [],
                ],
            ];
        }

        return [
            'success' => true,
            'nameservers' => $nameservers,
            'context' => [
                'request' => $response['context'] ?? [],
                'count' => count($nameservers),
                'source' => 'live',
            ],
            'request' => [
                'operation' => 'RefreshNameservers',
                'endpoint' => $endpoint,
                'payload' => [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    public static function extractNameservers(array $data): array
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

                $value = strtolower(trim($item));
                if ($value === '') {
                    continue;
                }

                $normalized[] = $value;
            }

            if ($normalized !== []) {
                return array_values(array_unique($normalized));
            }
        }

        return [];
    }
}
