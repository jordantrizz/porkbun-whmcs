<?php

namespace PorkbunWhmcs\Registrar\Operations;

use PorkbunWhmcs\Registrar\ApiClient;

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
    public static function execute(ApiClient $client, string $domain): array
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
     */
    private static function extractLockState(array $data): ?bool
    {
        $candidates = [
            $data['locked'] ?? null,
            $data['lock'] ?? null,
            $data['registrarLock'] ?? null,
            $data['domain']['locked'] ?? null,
            $data['domain']['lock'] ?? null,
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
