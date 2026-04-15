<?php

namespace PorkbunWhmcs\Registrar;

final class DomainRefreshQueue
{
    private const TABLE_NAME = 'mod_porkbun_domain_refresh_queue';
    private const DEFAULT_COOLDOWN_SECONDS = 300;
    private const DEFAULT_LEASE_SECONDS = 120;

    public static function enqueue(string $accountHash, string $dataType, ?int $cooldownSeconds = null, ?int $now = null): bool
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return false;
        }

        $currentTime = $now ?? time();
        $cooldown = max(5, (int) ($cooldownSeconds ?? self::DEFAULT_COOLDOWN_SECONDS));
        $normalizedType = self::normalizeDataType($dataType);

        try {
            $capsule = self::capsuleClass();
            /** @var object|null $existing */
            $existing = $capsule::table(self::TABLE_NAME)
                ->where('account_hash', $accountHash)
                ->where('data_type', $normalizedType)
                ->first();

            if (!is_object($existing)) {
                $capsule::table(self::TABLE_NAME)->insert([
                    'account_hash' => $accountHash,
                    'data_type' => $normalizedType,
                    'status' => 'pending',
                    'attempts' => 0,
                    'available_at' => $currentTime,
                    'lease_until' => 0,
                    'last_enqueued_at' => $currentTime,
                    'last_error' => '',
                    'created_at' => date('Y-m-d H:i:s', $currentTime),
                    'updated_at' => date('Y-m-d H:i:s', $currentTime),
                ]);

                return true;
            }

            $lastEnqueuedAt = isset($existing->last_enqueued_at) ? (int) $existing->last_enqueued_at : 0;
            if ($lastEnqueuedAt > 0 && ($currentTime - $lastEnqueuedAt) < $cooldown) {
                return false;
            }

            $capsule::table(self::TABLE_NAME)
                ->where('id', (int) $existing->id)
                ->update([
                    'status' => 'pending',
                    'available_at' => $currentTime,
                    'last_enqueued_at' => $currentTime,
                    'lease_until' => 0,
                    'updated_at' => date('Y-m-d H:i:s', $currentTime),
                ]);

            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    /**
     * @return array<int, array{id: int, accountHash: string, dataType: string, attempts: int}>
     */
    public static function claimBatch(int $limit = 10, ?int $leaseSeconds = null, ?int $now = null): array
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return [];
        }

        $currentTime = $now ?? time();
        $lease = max(30, (int) ($leaseSeconds ?? self::DEFAULT_LEASE_SECONDS));
        $batchSize = max(1, min($limit, 100));
        $claimed = [];

        try {
            $capsule = self::capsuleClass();
            /** @var iterable<object> $rows */
            $rows = $capsule::table(self::TABLE_NAME)
                ->where('status', 'pending')
                ->where('available_at', '<=', $currentTime)
                ->orderBy('updated_at', 'asc')
                ->limit($batchSize)
                ->get();

            foreach ($rows as $row) {
                if (!is_object($row) || !isset($row->id)) {
                    continue;
                }

                $updated = $capsule::table(self::TABLE_NAME)
                    ->where('id', (int) $row->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'processing',
                        'lease_until' => $currentTime + $lease,
                        'updated_at' => date('Y-m-d H:i:s', $currentTime),
                    ]);

                if ((int) $updated < 1) {
                    continue;
                }

                $claimed[] = [
                    'id' => (int) $row->id,
                    'accountHash' => (string) ($row->account_hash ?? ''),
                    'dataType' => (string) ($row->data_type ?? ''),
                    'attempts' => (int) ($row->attempts ?? 0),
                ];
            }
        } catch (\Throwable $exception) {
            return [];
        }

        return $claimed;
    }

    public static function complete(int $id, ?int $now = null): void
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return;
        }

        $currentTime = $now ?? time();

        try {
            $capsule = self::capsuleClass();
            $capsule::table(self::TABLE_NAME)
                ->where('id', $id)
                ->delete();
        } catch (\Throwable $exception) {
            return;
        }
    }

    public static function fail(int $id, string $error, int $attempts, ?int $now = null): void
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return;
        }

        $currentTime = $now ?? time();
        $nextAttempts = $attempts + 1;
        $isTerminal = $nextAttempts >= 5;

        try {
            $capsule = self::capsuleClass();
            $capsule::table(self::TABLE_NAME)
                ->where('id', $id)
                ->update([
                    'status' => $isTerminal ? 'failed' : 'pending',
                    'attempts' => $nextAttempts,
                    'last_error' => trim($error),
                    'available_at' => $isTerminal ? $currentTime : ($currentTime + ($nextAttempts * 60)),
                    'lease_until' => 0,
                    'updated_at' => date('Y-m-d H:i:s', $currentTime),
                ]);
        } catch (\Throwable $exception) {
            return;
        }
    }

    /**
     * @return array{pending: int, processing: int, failed: int}
     */
    public static function getStats(): array
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return ['pending' => 0, 'processing' => 0, 'failed' => 0];
        }

        try {
            $capsule = self::capsuleClass();

            return [
                'pending' => (int) $capsule::table(self::TABLE_NAME)->where('status', 'pending')->count(),
                'processing' => (int) $capsule::table(self::TABLE_NAME)->where('status', 'processing')->count(),
                'failed' => (int) $capsule::table(self::TABLE_NAME)->where('status', 'failed')->count(),
            ];
        } catch (\Throwable $exception) {
            return ['pending' => 0, 'processing' => 0, 'failed' => 0];
        }
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
                    $table->string('data_type', 64);
                    $table->string('status', 16)->default('pending');
                    $table->unsignedInteger('attempts')->default(0);
                    $table->unsignedInteger('available_at')->default(0);
                    $table->unsignedInteger('lease_until')->default(0);
                    $table->unsignedInteger('last_enqueued_at')->default(0);
                    $table->text('last_error');
                    $table->dateTime('created_at');
                    $table->dateTime('updated_at');
                    $table->unique(['account_hash', 'data_type'], 'pb_domain_refresh_account_type_unique');
                    $table->index(['status', 'available_at'], 'pb_domain_refresh_status_available_idx');
                });
            }
        } catch (\Throwable $exception) {
            return false;
        }

        $tableReady = true;

        return true;
    }

    private static function capsuleClass(): string
    {
        return '\\WHMCS\\Database\\Capsule';
    }
}
