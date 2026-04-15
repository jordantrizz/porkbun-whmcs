<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;
use PorkbunWhmcs\Registrar\LockStatusCache;

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
    public static function execute(ApiClient $client, string $domain, ?int $cacheTtlSeconds = null): array
    {
        $ttl = $cacheTtlSeconds ?? LockStatusCache::defaultTtlSeconds();
        $accountHash = $client->getCredentialFingerprint();
        $normalizedDomain = strtolower(trim($domain));

        $cached = LockStatusCache::getFresh($accountHash, $normalizedDomain);
        if (is_bool($cached)) {
            return [
                'success' => true,
                'lockEnabled' => $cached,
                'context' => [
                    'request' => [
                        'operation' => 'GetRegistrarLock',
                        'endpoint' => '/domain/listAll',
                    ],
                    'status' => 'success',
                    'source' => 'cache',
                ],
                'request' => [
                    'operation' => 'GetRegistrarLock',
                    'endpoint' => '/domain/listAll',
                    'payload' => [],
                ],
            ];
        }

        $hydrated = self::hydrateFromListAll($client, $accountHash, $ttl);
        if (($hydrated['success'] ?? false) === true) {
            $fresh = LockStatusCache::getFresh($accountHash, $normalizedDomain);
            if (is_bool($fresh)) {
                return [
                    'success' => true,
                    'lockEnabled' => $fresh,
                    'context' => [
                        'request' => $hydrated['context']['request'] ?? [],
                        'status' => 'success',
                        'source' => 'listAll-cache',
                        'domainsHydrated' => (int) ($hydrated['domainsHydrated'] ?? 0),
                        'pagesFetched' => (int) ($hydrated['pagesFetched'] ?? 0),
                    ],
                    'request' => [
                        'operation' => 'GetRegistrarLock',
                        'endpoint' => '/domain/listAll',
                        'payload' => [],
                    ],
                ];
            }
        }

        $fallback = self::fetchDirectLock($client, $normalizedDomain);
        if (($fallback['success'] ?? false) === true) {
            LockStatusCache::put($accountHash, $normalizedDomain, (bool) ($fallback['lockEnabled'] ?? false), $ttl);

            return $fallback;
        }

        $hydrationDetails = (string) ($hydrated['details'] ?? 'List all domains request failed.');
        $fallbackDetails = (string) ($fallback['details'] ?? 'Direct lock lookup failed.');

        return [
            'success' => false,
            'details' => $hydrationDetails . ' Fallback reason: ' . $fallbackDetails,
            'context' => [
                'request' => $fallback['context']['request'] ?? [],
                'hydration' => $hydrated['context'] ?? [],
            ],
            'request' => [
                'operation' => 'GetRegistrarLock',
                'endpoint' => '/domain/listAll',
                'payload' => [],
            ],
        ];
    }

    /**
     * @return array{success: bool, details?: string, domainsHydrated?: int, pagesFetched?: int, context?: array<string, mixed>}
     */
    private static function hydrateFromListAll(ApiClient $client, string $accountHash, int $cacheTtlSeconds): array
    {
        $start = 0;
        $pageSize = 1000;
        $pagesFetched = 0;
        $domainsHydrated = 0;

        while (true) {
            $response = $client->request('GetRegistrarLockListAll', '/domain/listAll', ['start' => $start]);
            if (($response['success'] ?? false) !== true) {
                $error = is_array($response['error'] ?? null) ? $response['error'] : [];

                return [
                    'success' => false,
                    'details' => (string) ($error['message'] ?? 'List all domains request failed.'),
                    'context' => [
                        'request' => $response['context'] ?? [],
                        'errorType' => (string) ($error['type'] ?? 'unknown'),
                        'statusCode' => (int) ($error['statusCode'] ?? 0),
                    ],
                ];
            }

            $pagesFetched++;
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $entryCount = self::countDomainEntries($data);
            $domainLocks = self::extractDomainLocks($data);
            if ($domainLocks !== []) {
                $domainsHydrated += LockStatusCache::putMany($accountHash, $domainLocks, $cacheTtlSeconds);
            }

            if ($entryCount < $pageSize) {
                break;
            }

            $start += $pageSize;
        }

        return [
            'success' => true,
            'domainsHydrated' => $domainsHydrated,
            'pagesFetched' => $pagesFetched,
            'context' => [
                'request' => [
                    'operation' => 'GetRegistrarLockListAll',
                    'endpoint' => '/domain/listAll',
                ],
            ],
        ];
    }

    /**
     * @return array{success: bool, lockEnabled?: bool, details?: string, context?: array<string, mixed>, request?: array<string, mixed>}
     */
    private static function fetchDirectLock(ApiClient $client, string $domain): array
    {
        $endpoint = '/domain/getLock/' . $domain;
        $response = $client->request('GetRegistrarLock', $endpoint, []);

        if (($response['success'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Get registrar lock request failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                ],
                'request' => [
                    'operation' => 'GetRegistrarLock',
                    'endpoint' => $endpoint,
                    'payload' => [],
                ],
            ];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $lockState = self::extractLockState($data);
        if ($lockState === null) {
            return [
                'success' => false,
                'details' => 'Registry did not return lock state for this domain.',
                'context' => [
                    'request' => $response['context'] ?? [],
                ],
                'request' => [
                    'operation' => 'GetRegistrarLock',
                    'endpoint' => $endpoint,
                    'payload' => [],
                ],
            ];
        }

        return [
            'success' => true,
            'lockEnabled' => $lockState,
            'context' => [
                'request' => $response['context'] ?? [],
                'status' => 'success',
            ],
            'request' => [
                'operation' => 'GetRegistrarLock',
                'endpoint' => $endpoint,
                'payload' => [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, bool>
     */
    private static function extractDomainLocks(array $data): array
    {
        $containers = self::extractDomainContainers($data);

        $domainLocks = [];

        foreach ($containers as $container) {
            foreach ($container as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $domain = self::extractDomainName($item, $index);
                $lockEnabled = self::extractLockState($item);
                if ($domain === null || $lockEnabled === null) {
                    continue;
                }

                $domainLocks[$domain] = $lockEnabled;
            }
        }

        return $domainLocks;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function countDomainEntries(array $data): int
    {
        $count = 0;
        $containers = self::extractDomainContainers($data);
        foreach ($containers as $container) {
            $count += count($container);
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<int|string, mixed>>
     */
    private static function extractDomainContainers(array $data): array
    {
        $containers = [];
        foreach (['domains', 'results', 'domainList', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $containers[] = $data[$key];
            }
        }

        if ($containers === [] && self::looksLikeDomainCollection($data)) {
            $containers[] = $data;
        }

        return $containers;
    }

    /**
     * @param mixed $index
     */
    private static function extractDomainName(array $item, $index): ?string
    {
        $candidates = [
            $item['domain'] ?? null,
            $item['name'] ?? null,
            $item['domainName'] ?? null,
            $item['fqdn'] ?? null,
            $item['domain']['domain'] ?? null,
            $item['domain']['name'] ?? null,
        ];

        if (is_string($index) && trim($index) !== '') {
            $candidates[] = $index;
        }

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $domain = strtolower(trim($candidate));
            if ($domain !== '' && str_contains($domain, '.')) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function looksLikeDomainCollection(array $data): bool
    {
        foreach ($data as $value) {
            if (is_array($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractLockState(array $data): ?bool
    {
        $candidates = [
            $data['locked'] ?? null,
            $data['lock'] ?? null,
            $data['isLocked'] ?? null,
            $data['registrarLock'] ?? null,
            $data['domain']['locked'] ?? null,
            $data['domain']['lock'] ?? null,
            $data['domain']['isLocked'] ?? null,
            $data['domain']['registrarLock'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_bool($candidate)) {
                return $candidate;
            }

            if (is_int($candidate)) {
                return $candidate === 1;
            }

            if (!is_string($candidate)) {
                continue;
            }

            $value = strtolower(trim($candidate));
            if (in_array($value, ['1', 'true', 'on', 'yes', 'locked', 'enabled'], true)) {
                return true;
            }

            if (in_array($value, ['0', 'false', 'off', 'no', 'unlocked', 'disabled'], true)) {
                return false;
            }
        }

        return null;
    }
}
