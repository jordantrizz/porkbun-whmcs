<?php

namespace PorkbunWhmcs\Registrar;

final class LockStatusCache
{
    private const TABLE_NAME = 'mod_porkbun_lock_cache';
    private const DEFAULT_TTL_SECONDS = 3600;

    public static function defaultTtlSeconds(): int
    {
        $configured = getenv('PORKBUN_LOCK_CACHE_TTL');
        if (!is_string($configured)) {
            return self::DEFAULT_TTL_SECONDS;
        }

        $ttl = (int) trim($configured);

        return $ttl > 0 ? $ttl : self::DEFAULT_TTL_SECONDS;
    }

    public static function getFresh(string $accountHash, string $domain, ?int $now = null): ?bool
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

        return isset($row->lock_enabled) && (int) $row->lock_enabled === 1;
    }

    public static function put(string $accountHash, string $domain, bool $lockEnabled, int $ttlSeconds, ?int $now = null): bool
    {
        return self::putMany(
            $accountHash,
            [self::normalizeDomain($domain) => $lockEnabled],
            $ttlSeconds,
            $now
        ) > 0;
    }

    /**
     * @return array{totalRecords: int, lastFetchedAt: int|null}
     */
    public static function getStats(): array
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return [
                'totalRecords' => 0,
                'lastFetchedAt' => null,
            ];
        }

        try {
            $capsule = self::capsuleClass();
            $totalRecords = (int) $capsule::table(self::TABLE_NAME)->count();
            $lastFetchedAt = $capsule::table(self::TABLE_NAME)->max('fetched_at');

            return [
                'totalRecords' => $totalRecords,
                'lastFetchedAt' => is_numeric($lastFetchedAt) && (int) $lastFetchedAt > 0 ? (int) $lastFetchedAt : null,
            ];
        } catch (\Throwable $exception) {
            return [
                'totalRecords' => 0,
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

    /**
     * @param array<string, bool> $domainLocks
     */
    public static function putMany(string $accountHash, array $domainLocks, int $ttlSeconds, ?int $now = null): int
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return 0;
        }

        $currentTime = $now ?? time();
        $boundedTtl = max(60, $ttlSeconds);
        $expiresAt = $currentTime + $boundedTtl;
        $upserts = 0;

        foreach ($domainLocks as $domain => $lockEnabled) {
            $normalizedDomain = self::normalizeDomain((string) $domain);
            if ($normalizedDomain === '') {
                continue;
            }

            $capsule = self::capsuleClass();

            $payload = [
                'lock_enabled' => $lockEnabled ? 1 : 0,
                'fetched_at' => $currentTime,
                'expires_at' => $expiresAt,
                'updated_at' => date('Y-m-d H:i:s', $currentTime),
            ];

            try {
                $existing = $capsule::table(self::TABLE_NAME)
                    ->where('account_hash', $accountHash)
                    ->where('domain', $normalizedDomain)
                    ->first();

                if (is_object($existing) && isset($existing->id)) {
                    $capsule::table(self::TABLE_NAME)
                        ->where('id', (int) $existing->id)
                        ->update($payload);
                } else {
                    $capsule::table(self::TABLE_NAME)->insert($payload + [
                        'account_hash' => $accountHash,
                        'domain' => $normalizedDomain,
                        'created_at' => date('Y-m-d H:i:s', $currentTime),
                    ]);
                }

                $upserts++;
            } catch (\Throwable $exception) {
                continue;
            }
        }

        self::cleanupExpired($currentTime);

        return $upserts;
    }

    private static function normalizeDomain(string $domain): string
    {
        return strtolower(trim($domain));
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
                    $table->tinyInteger('lock_enabled')->default(0);
                    $table->unsignedInteger('fetched_at')->default(0);
                    $table->unsignedInteger('expires_at')->default(0);
                    $table->dateTime('created_at');
                    $table->dateTime('updated_at');
                    $table->unique(['account_hash', 'domain'], 'pb_lock_cache_account_domain_unique');
                    $table->index('expires_at', 'pb_lock_cache_expires_idx');
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
