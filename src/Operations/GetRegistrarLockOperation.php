<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;
use PorkbunWhmcs\Registrar\DomainCache;
use PorkbunWhmcs\Registrar\DomainRefreshQueue;

final class GetRegistrarLockOperation
{
    /**
     * @return array{
     *   success: bool,
     *   lockEnabled?: bool,
     *   details?: string,
     *   context?: array<string, mixed>,
     *   request?: array<string, mixed>
     * }
     */
    public static function execute(
        ApiClient $client,
        string $domain,
        ?int $cacheTtlSeconds = null,
        ?int $refreshCooldownSeconds = null
    ): array
    {
        $ttl = $cacheTtlSeconds ?? DomainCache::defaultTtlSeconds();
        $accountHash = $client->getCredentialFingerprint();
        $normalizedDomain = strtolower(trim($domain));

        $cached = DomainCache::get($accountHash, $normalizedDomain, 'lock');
        if (is_array($cached) && is_bool($cached['value'] ?? null)) {
            $isStale = (string) ($cached['freshness'] ?? '') === 'stale';
            $queued = false;
            if ($isStale) {
                $queued = DomainRefreshQueue::enqueue($accountHash, 'lock', $refreshCooldownSeconds);
            }

            return [
                'success' => true,
                'lockEnabled' => (bool) $cached['value'],
                'context' => [
                    'request' => [
                        'operation' => 'GetRegistrarLock',
                        'endpoint' => '/domain/listAll',
                    ],
                    'status' => 'success',
                    'source' => $isStale ? 'cache-stale' : 'cache',
                    'refreshQueued' => $queued,
                ],
                'request' => [
                    'operation' => 'GetRegistrarLock',
                    'endpoint' => '/domain/listAll',
                    'payload' => [],
                ],
            ];
        }

        $queued = DomainRefreshQueue::enqueue($accountHash, 'lock', $refreshCooldownSeconds);

        return [
            'success' => false,
            'details' => 'Lock status is not available in cache yet. Refresh has been queued.',
            'context' => [
                'request' => [
                    'operation' => 'GetRegistrarLock',
                    'endpoint' => '/domain/listAll',
                ],
                'status' => 'cache_miss',
                'refreshQueued' => $queued,
                'ttlSeconds' => $ttl,
            ],
            'request' => [
                'operation' => 'GetRegistrarLock',
                'endpoint' => '/domain/listAll',
                'payload' => [],
            ],
        ];
    }
}
