<?php

namespace PorkbunWhmcs\Registrar\Operations;

use DateTimeImmutable;
use PorkbunWhmcs\Registrar\ApiClient;
use PorkbunWhmcs\Registrar\Mapper;

final class SyncDomainOperation
{
    /**
     * @return array{
     *   success: bool,
     *   details?: string,
     *   syncedExpiryDate?: string,
     *   sourceExpiryDate?: string,
     *   previousExpiryDate?: string,
     *   guardrail?: string,
     *   active?: bool,
     *   expired?: bool,
     *   transferredAway?: bool,
     *   context?: array<string, mixed>,
     *   request?: array<string, mixed>
     * }
     */
    public static function execute(ApiClient $client, string $domain, ?string $previousExpiryDate): array
    {
        $endpoint = '/domain/get/' . $domain;
        $response = $client->request('SyncDomain', $endpoint, []);
        if (($response['success'] ?? false) !== true) {
            $error = is_array($response['error'] ?? null) ? $response['error'] : [];

            return [
                'success' => false,
                'details' => (string) ($error['message'] ?? 'Sync request failed.'),
                'context' => [
                    'request' => $response['context'] ?? [],
                    'errorType' => (string) ($error['type'] ?? 'unknown'),
                    'statusCode' => (int) ($error['statusCode'] ?? 0),
                ],
                'request' => [
                    'operation' => 'SyncDomain',
                    'endpoint' => $endpoint,
                    'payload' => [],
                ],
            ];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $rawExpiryDate = self::extractExpiryDate($data);
        $normalizedSourceDate = $rawExpiryDate !== null ? Mapper::toWhmcsDate($rawExpiryDate) : null;

        $normalizedPreviousDate = null;
        if ($previousExpiryDate !== null && trim($previousExpiryDate) !== '') {
            $normalizedPreviousDate = Mapper::toWhmcsDate($previousExpiryDate);
        }

        $guardrail = null;
        $syncedDate = $normalizedSourceDate;

        if ($syncedDate === null && $normalizedPreviousDate !== null) {
            $syncedDate = $normalizedPreviousDate;
            $guardrail = 'missing_or_invalid_registry_date';
        }

        if ($syncedDate === null) {
            return [
                'success' => false,
                'details' => 'Sync failed: no valid expiry date was returned by registry.',
                'context' => [
                    'request' => $response['context'] ?? [],
                    'rawExpiryDate' => $rawExpiryDate,
                ],
                'request' => [
                    'operation' => 'SyncDomain',
                    'endpoint' => $endpoint,
                    'payload' => [],
                ],
            ];
        }

        if ($normalizedPreviousDate !== null && self::isDestructiveRegression($normalizedPreviousDate, $syncedDate)) {
            $syncedDate = $normalizedPreviousDate;
            $guardrail = 'stale_regression_protection';
        }

        $status = self::extractStatus($data);
        $transferredAway = self::isTransferredAway($status);
        $active = !$transferredAway;
        $expired = self::isExpired($syncedDate);

        return [
            'success' => true,
            'syncedExpiryDate' => $syncedDate,
            'sourceExpiryDate' => $normalizedSourceDate,
            'previousExpiryDate' => $normalizedPreviousDate,
            'guardrail' => $guardrail,
            'active' => $active,
            'expired' => $expired,
            'transferredAway' => $transferredAway,
            'context' => [
                'request' => $response['context'] ?? [],
                'status' => $status,
            ],
            'request' => [
                'operation' => 'SyncDomain',
                'endpoint' => $endpoint,
                'payload' => [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractExpiryDate(array $data): ?string
    {
        $candidatePaths = [
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
        ];

        foreach ($candidatePaths as $path) {
            $value = self::getNestedValue($data, $path);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractStatus(array $data): string
    {
        $candidatePaths = [
            ['status'],
            ['domainStatus'],
            ['domain', 'status'],
            ['domain', 'domainStatus'],
        ];

        foreach ($candidatePaths as $path) {
            $value = self::getNestedValue($data, $path);
            if (is_string($value) && trim($value) !== '') {
                return strtolower(trim($value));
            }
        }

        return '';
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

    private static function isTransferredAway(string $status): bool
    {
        if ($status === '') {
            return false;
        }

        return str_contains($status, 'transfer')
            || str_contains($status, 'away')
            || str_contains($status, 'inactive')
            || str_contains($status, 'cancel');
    }

    private static function isExpired(string $expiryDate): bool
    {
        $expiry = new DateTimeImmutable($expiryDate . ' 23:59:59');
        $now = new DateTimeImmutable('now');

        return $expiry < $now;
    }

    private static function isDestructiveRegression(string $previousDate, string $newDate): bool
    {
        $previous = new DateTimeImmutable($previousDate);
        $new = new DateTimeImmutable($newDate);

        if ($new >= $previous) {
            return false;
        }

        $differenceDays = (int) $previous->diff($new)->format('%a');

        return $differenceDays > 45;
    }
}
