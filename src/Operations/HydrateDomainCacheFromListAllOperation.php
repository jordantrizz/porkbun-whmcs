<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;
use PorkbunWhmcs\Registrar\DomainCache;

final class HydrateDomainCacheFromListAllOperation
{
    /**
     * @return array{success: bool, details?: string, domainsHydrated?: int, pagesFetched?: int, context?: array<string, mixed>}
     */
    public static function execute(ApiClient $client, string $accountHash, int $cacheTtlSeconds): array
    {
        $start = 0;
        $pageSize = 1000;
        $pagesFetched = 0;
        $domainsHydrated = 0;

        while (true) {
            $response = $client->request('HydrateDomainCacheListAll', '/domain/listAll', ['start' => $start]);
            if (($response['success'] ?? false) !== true) {
                $error = is_array($response['error'] ?? null) ? $response['error'] : [];

                return [
                    'success' => false,
                    'details' => (string) ($error['message'] ?? 'List all domains request failed.'),
                    'context' => [
                        'request' => $response['context'] ?? [],
                        'errorType' => (string) ($error['type'] ?? 'unknown'),
                        'statusCode' => (int) ($error['statusCode'] ?? 0),
                        'pagesFetched' => $pagesFetched,
                    ],
                ];
            }

            $pagesFetched++;
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $entryCount = self::countDomainEntries($data);
            $typedData = self::extractDomainData($data);

            if ($typedData !== []) {
                $domainsHydrated += DomainCache::putMany($accountHash, $typedData, $cacheTtlSeconds);
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
                    'operation' => 'HydrateDomainCacheListAll',
                    'endpoint' => '/domain/listAll',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array<string, mixed>>
     */
    private static function extractDomainData(array $data): array
    {
        $containers = self::extractDomainContainers($data);
        $typedData = [];

        foreach ($containers as $container) {
            foreach ($container as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $domain = self::extractDomainName($item, $index);
                if ($domain === null) {
                    continue;
                }

                $lockEnabled = self::extractLockState($item);
                if ($lockEnabled !== null) {
                    $typedData[$domain]['lock'] = $lockEnabled;
                }

                $nameservers = self::extractNameservers($item);
                if ($nameservers !== []) {
                    $typedData[$domain]['nameservers'] = $nameservers;
                }

                $syncData = self::extractSyncData($item);
                if ($syncData !== null) {
                    $typedData[$domain]['sync'] = $syncData;
                }
            }
        }

        return $typedData;
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>|null
     */
    private static function extractSyncData(array $data): ?array
    {
        $expiryDate = self::extractFirstString($data, [
            ['expiryDate'],
            ['expirationDate'],
            ['expireDate'],
            ['expiresAt'],
            ['expires'],
            ['domain', 'expiryDate'],
            ['domain', 'expirationDate'],
            ['domain', 'expireDate'],
            ['domain', 'expiresAt'],
            ['domain', 'expires'],
        ]);

        $status = self::extractFirstString($data, [
            ['status'],
            ['domainStatus'],
            ['domain', 'status'],
            ['domain', 'domainStatus'],
        ]);

        if ($expiryDate === null && $status === null) {
            return null;
        }

        return [
            'expiryDate' => $expiryDate ?? '',
            'status' => $status ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<int, string>> $candidatePaths
     */
    private static function extractFirstString(array $data, array $candidatePaths): ?string
    {
        foreach ($candidatePaths as $path) {
            $value = self::getNestedValue($data, $path);
            if (!is_string($value)) {
                continue;
            }

            $normalized = trim($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $path
     * @return mixed
     */
    private static function getNestedValue(array $data, array $path)
    {
        $cursor = $data;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
