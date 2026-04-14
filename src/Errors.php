<?php

namespace PorkbunWhmcs\Registrar;

final class Errors
{
    /**
     * @return array{error: string}
     */
    public static function operationFailed(string $operation, string $domain, string $reason): array
    {
        return [
            'error' => sprintf('Operation failed: %s for %s. Reason: %s', $operation, $domain, $reason),
        ];
    }

    /**
     * @return array{error: string}
     */
    public static function missingCredential(string $fieldName): array
    {
        return [
            'error' => sprintf('Configuration error: missing required field %s.', $fieldName),
        ];
    }
}
