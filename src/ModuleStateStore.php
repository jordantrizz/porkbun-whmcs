<?php

namespace PorkbunWhmcs\Registrar;

final class ModuleStateStore
{
    private const TABLE_NAME = 'mod_porkbun_module_state';

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return $default;
        }

        try {
            $capsule = self::capsuleClass();
            /** @var object|null $row */
            $row = $capsule::table(self::TABLE_NAME)
                ->where('state_key', trim($key))
                ->first();
        } catch (\Throwable $exception) {
            return $default;
        }

        if (!is_object($row) || !isset($row->state_value) || !is_string($row->state_value)) {
            return $default;
        }

        $decoded = json_decode($row->state_value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $default;
        }

        return $decoded;
    }

    /**
     * @param mixed $value
     */
    public static function put(string $key, $value, ?int $now = null): bool
    {
        if (!self::isStorageAvailable() || !self::ensureTable()) {
            return false;
        }

        $normalizedKey = trim($key);
        if ($normalizedKey === '') {
            return false;
        }

        $encoded = json_encode($value);
        if (!is_string($encoded)) {
            return false;
        }

        $currentTime = $now ?? time();
        $timestamp = date('Y-m-d H:i:s', $currentTime);

        try {
            $capsule = self::capsuleClass();
            /** @var object|null $existing */
            $existing = $capsule::table(self::TABLE_NAME)
                ->where('state_key', $normalizedKey)
                ->first();

            if (is_object($existing) && isset($existing->id)) {
                $capsule::table(self::TABLE_NAME)
                    ->where('id', (int) $existing->id)
                    ->update([
                        'state_value' => $encoded,
                        'updated_at' => $timestamp,
                    ]);

                return true;
            }

            $capsule::table(self::TABLE_NAME)->insert([
                'state_key' => $normalizedKey,
                'state_value' => $encoded,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return true;
        } catch (\Throwable $exception) {
            return false;
        }
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
                    $table->string('state_key', 128);
                    $table->text('state_value');
                    $table->dateTime('created_at');
                    $table->dateTime('updated_at');
                    $table->unique('state_key', 'pb_module_state_key_unique');
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
