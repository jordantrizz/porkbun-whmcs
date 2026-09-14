<?php

namespace PorkbunWhmcs\Registrar;

final class DomainCache
{
    private const TABLE_NAME = 'mod_porkbun_domain_cache';
    private const DEFAULT_TTL_SECONDS = 3600;

    public static function defaultTtlSeconds(): int
    {
        $configured = getenv('PORKBUN_DOMAIN_CACHE_TTL');
        if (!is_string($configured)) {
            return self::DEFAULT_TTL_SECONDS;
        }

        $ttl = (int) trim($configured);

        return $ttl > 0 ? $ttl : self::DEFAULT_TTL_SECONDS;
    }

    /**
     * @return array{value: mixed, freshness: string, fetchedAt: int, staleAt: int, expiresAt: int}|null
     */
    public static function get(string $accountHash, string $domain, string $dataType, ?int $now = null): ?array
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return null;
        }

        $currentTime = $now ?? time();

        try {
            $capsule = self::capsuleClass();
            /** @var object|null $row */
            $row = $capsule::table(self::TABLE_NAME)
                ->where('account_hash', $accountHash)
                ->where('domain', self::normalizeDomain($domain))
                ->where('data_type', self::normalizeDataType($dataType))
                ->first();
        } catch (\Throwable $exception) {
            return null;
        }

        if (!is_object($row)) {
            return null;
        }

        $expiresAt = isset($row->expires_at) ? (int) $row->expires_at : 0;
        if ($expiresAt <= 0 || $expiresAt < $currentTime) {
            return null;
        }

        $staleAt = isset($row->stale_at) ? (int) $row->stale_at : 0;
        if ($staleAt <= 0) {
            $staleAt = $expiresAt;
        }

        $rawValue = isset($row->data_json) && is_string($row->data_json) ? $row->data_json : '';
        if ($rawValue === '') {
            return null;
        }

        $decoded = json_decode($rawValue, true);
        if (!is_array($decoded) || !array_key_exists('value', $decoded)) {
            return null;
        }

        return [
            'value' => $decoded['value'],
            'freshness' => $staleAt > $currentTime ? 'fresh' : 'stale',
            'fetchedAt' => isset($row->fetched_at) ? (int) $row->fetched_at : 0,
            'staleAt' => $staleAt,
            'expiresAt' => $expiresAt,
        ];
    }

    public static function put(
        string $accountHash,
        string $domain,
        string $dataType,
        $value,
        int $ttlSeconds,
        ?int $now = null
    ): bool {
        return self::putMany(
            $accountHash,
            [
                self::normalizeDomain($domain) => [
                    self::normalizeDataType($dataType) => $value,
                ],
            ],
            $ttlSeconds,
            $now
        ) > 0;
    }

    /**
     * @param array<string, array<string, mixed>> $domainData
     */
    public static function putMany(string $accountHash, array $domainData, int $ttlSeconds, ?int $now = null): int
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return 0;
        }

        $currentTime = $now ?? time();
        $boundedTtl = max(60, $ttlSeconds);
        $staleAt = $currentTime + $boundedTtl;
        $expiresAt = $staleAt + $boundedTtl;
        $upserts = 0;

        foreach ($domainData as $domain => $typedValues) {
            $normalizedDomain = self::normalizeDomain((string) $domain);
            if ($normalizedDomain === '' || !is_array($typedValues)) {
                continue;
            }

            foreach ($typedValues as $dataType => $value) {
                $normalizedType = self::normalizeDataType((string) $dataType);
                if ($normalizedType === '') {
                    continue;
                }

                $encoded = json_encode(['value' => $value]);
                if (!is_string($encoded)) {
                    continue;
                }

                $payload = [
                    'data_json' => $encoded,
                    'fetched_at' => $currentTime,
                    'stale_at' => $staleAt,
                    'expires_at' => $expiresAt,
                    'updated_at' => date('Y-m-d H:i:s', $currentTime),
                ];

                try {
                    $capsule = self::capsuleClass();
                    $existing = $capsule::table(self::TABLE_NAME)
                        ->where('account_hash', $accountHash)
                        ->where('domain', $normalizedDomain)
                        ->where('data_type', $normalizedType)
                        ->first();

                    if (is_object($existing) && isset($existing->id)) {
                        $capsule::table(self::TABLE_NAME)
                            ->where('id', (int) $existing->id)
                            ->update($payload);
                    } else {
                        $capsule::table(self::TABLE_NAME)->insert($payload + [
                            'account_hash' => $accountHash,
                            'domain' => $normalizedDomain,
                            'data_type' => $normalizedType,
                            'created_at' => date('Y-m-d H:i:s', $currentTime),
                        ]);
                    }

                    $upserts++;
                } catch (\Throwable $exception) {
                    continue;
                }
            }
        }

        self::cleanupExpired($currentTime);

        return $upserts;
    }

    /**
     * @return array{totalRecords: int, totalDomains: int, lastFetchedAt: int|null}
     */
    public static function getStats(): array
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return [
                'totalRecords' => 0,
                'totalDomains' => 0,
                'lastFetchedAt' => null,
            ];
        }

        try {
            $capsule = self::capsuleClass();
            $totalRecords = (int) $capsule::table(self::TABLE_NAME)->count();
            $totalDomains = (int) $capsule::table(self::TABLE_NAME)
                ->distinct()
                ->count('domain');
            $lastFetchedAt = $capsule::table(self::TABLE_NAME)->max('fetched_at');

            return [
                'totalRecords' => $totalRecords,
                'totalDomains' => $totalDomains,
                'lastFetchedAt' => is_numeric($lastFetchedAt) && (int) $lastFetchedAt > 0 ? (int) $lastFetchedAt : null,
            ];
        } catch (\Throwable $exception) {
            return [
                'totalRecords' => 0,
                'totalDomains' => 0,
                'lastFetchedAt' => null,
            ];
        }
    }

    public static function clearAll(): int
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return 0;
        }

        try {
            $capsule = self::capsuleClass();

            return (int) $capsule::table(self::TABLE_NAME)->delete();
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    private static function normalizeDomain(string $domain): string
    {
        return strtolower(trim($domain));
    }

    private static function normalizeDataType(string $dataType): string
    {
        return strtolower(trim($dataType));
    }

    private static function isStorageAvailable(): bool
    {
        return class_exists('\\WHMCS\\Database\\Capsule');
    }

    private static function ensureTable(): bool
    {
        static $tableReady = false;

        if ($tableReady) {
            return true;
        }

        try {
            $capsule = self::capsuleClass();
            $schema = $capsule::schema();
            if (!$schema->hasTable(self::TABLE_NAME)) {
                $schema->create(self::TABLE_NAME, function ($table): void {
                    $table->increments('id');
                    $table->string('account_hash', 64);
                    $table->string('domain', 255);
                    $table->string('data_type', 64);
                    $table->text('data_json');
                    $table->unsignedInteger('fetched_at')->default(0);
                    $table->unsignedInteger('stale_at')->default(0);
                    $table->unsignedInteger('expires_at')->default(0);
                    $table->dateTime('created_at');
                    $table->dateTime('updated_at');
                    $table->unique(['account_hash', 'domain', 'data_type'], 'pb_domain_cache_account_domain_type_unique');
                    $table->index('expires_at', 'pb_domain_cache_expires_idx');
                    $table->index(['account_hash', 'data_type'], 'pb_domain_cache_account_type_idx');
                });
            }
        } catch (\Throwable $exception) {
            return false;
        }

        $tableReady = true;

        return true;
    }

    private static function cleanupExpired(int $currentTime): void
    {
        if (random_int(1, 100) !== 1) {
            return;
        }

        try {
            $capsule = self::capsuleClass();
            $capsule::table(self::TABLE_NAME)
                ->where('expires_at', '<', $currentTime - 86400)
                ->delete();
        } catch (\Throwable $exception) {
            return;
        }
    }

    private static function capsuleClass(): string
    {
        return '\\WHMCS\\Database\\Capsule';
    }
}
