<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;
use PorkbunWhmcs\Registrar\DomainCache;
use PorkbunWhmcs\Registrar\DomainRefreshQueue;

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
    public static function execute(ApiClient $client, string $domain, ?int $refreshCooldownSeconds = null): array
    {
        $normalizedDomain = strtolower(trim($domain));
        $accountHash = $client->getCredentialFingerprint();
        $cached = DomainCache::get($accountHash, $normalizedDomain, 'nameservers');

        if (is_array($cached)) {
            $nameservers = self::normalizeNameservers($cached['value'] ?? null);
            if ($nameservers !== []) {
                $isStale = (string) ($cached['freshness'] ?? '') === 'stale';
                $queued = false;
                if ($isStale) {
                    $queued = DomainRefreshQueue::enqueue($accountHash, 'nameservers', $refreshCooldownSeconds);
                }

                return [
                    'success' => true,
                    'nameservers' => $nameservers,
                    'context' => [
                        'request' => [
                            'operation' => 'GetNameservers',
                            'endpoint' => '/domain/listAll',
                        ],
                        'count' => count($nameservers),
                        'source' => $isStale ? 'cache-stale' : 'cache',
                        'refreshQueued' => $queued,
                    ],
                    'request' => [
                        'operation' => 'GetNameservers',
                        'endpoint' => '/domain/listAll',
                        'payload' => [],
                    ],
                ];
            }
        }

        $queued = DomainRefreshQueue::enqueue($accountHash, 'nameservers', $refreshCooldownSeconds);

        return [
            'success' => false,
            'details' => 'Nameserver cache is not populated yet. Refresh has been queued.',
            'warning' => 'Nameserver cache refresh has been queued. Using existing WHMCS nameserver values.',
            'warningCode' => 'CACHE_REFRESH_QUEUED',
            'context' => [
                'request' => [
                    'operation' => 'GetNameservers',
                    'endpoint' => '/domain/listAll',
                ],
                'source' => 'cache-miss',
                'refreshQueued' => $queued,
            ],
            'request' => [
                'operation' => 'GetNameservers',
                'endpoint' => '/domain/listAll',
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
    private static function normalizeNameservers($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $candidate = strtolower(trim($item));
            if ($candidate === '') {
                continue;
            }

            $normalized[] = $candidate;
        }

        return array_values(array_unique($normalized));
    }
 }
