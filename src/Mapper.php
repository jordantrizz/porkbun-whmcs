<?php

namespace PorkbunWhmcs\Registrar;

use DateTimeImmutable;
use DateTimeZone;

final class Mapper
{
    public static function normalizeDomain(string $sld, string $tld): string
    {
        return strtolower(trim($sld) . '.' . ltrim(trim($tld), '.'));
    }

    /**
     * Converts date-like values to WHMCS-friendly Y-m-d format.
     */
    public static function toWhmcsDate(string $value): ?string
    {
        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (\Throwable $exception) {
            return null;
        }

        return $date->format('Y-m-d');
    }
}
