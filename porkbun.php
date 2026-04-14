<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

if (!defined('PORKBUN_MODULE_VERSION')) {
    define('PORKBUN_MODULE_VERSION', '0.1.0');
}

require_once __DIR__ . '/src/ApiClient.php';
require_once __DIR__ . '/src/Errors.php';
require_once __DIR__ . '/src/Mapper.php';
require_once __DIR__ . '/src/Operations/RegisterDomainOperation.php';
require_once __DIR__ . '/src/Operations/TransferDomainOperation.php';
require_once __DIR__ . '/src/Operations/RenewDomainOperation.php';
require_once __DIR__ . '/src/Operations/SyncDomainOperation.php';
require_once __DIR__ . '/src/Operations/GetNameserversOperation.php';
require_once __DIR__ . '/src/Operations/SaveNameserversOperation.php';
require_once __DIR__ . '/src/Operations/GetContactDetailsOperation.php';
require_once __DIR__ . '/src/Operations/SaveContactDetailsOperation.php';
require_once __DIR__ . '/src/Operations/GetEppCodeOperation.php';
require_once __DIR__ . '/src/Operations/GetRegistrarLockOperation.php';
require_once __DIR__ . '/src/Operations/SaveRegistrarLockOperation.php';

use PorkbunWhmcs\Registrar\ApiClient;
use PorkbunWhmcs\Registrar\Mapper;
use PorkbunWhmcs\Registrar\Operations\RegisterDomainOperation;
use PorkbunWhmcs\Registrar\Operations\RenewDomainOperation;
use PorkbunWhmcs\Registrar\Operations\SyncDomainOperation;
use PorkbunWhmcs\Registrar\Operations\TransferDomainOperation;
use PorkbunWhmcs\Registrar\Operations\GetNameserversOperation;
use PorkbunWhmcs\Registrar\Operations\SaveNameserversOperation;
use PorkbunWhmcs\Registrar\Operations\GetContactDetailsOperation;
use PorkbunWhmcs\Registrar\Operations\SaveContactDetailsOperation;
use PorkbunWhmcs\Registrar\Operations\GetEppCodeOperation;
use PorkbunWhmcs\Registrar\Operations\GetRegistrarLockOperation;
use PorkbunWhmcs\Registrar\Operations\SaveRegistrarLockOperation;

/**
 * @return array{success: true}
 */
function porkbun_successResponse(): array
{
    return ['success' => true];
}

/**
 * @return array{success: false, error: string}
 */
function porkbun_errorResponse(string $message): array
{
    return [
        'success' => false,
        'error' => $message,
    ];
}

/**
 * @param array<string, mixed> $params
 */
function porkbun_getRequiredConfigValue(array $params, string $key): ?string
{
    $value = isset($params[$key]) ? trim((string) $params[$key]) : '';

    return $value !== '' ? $value : null;
}

/**
 * @param array<string, mixed> $params
 */
function porkbun_getTimeout(array $params, int $default = 20): int
{
    $timeout = isset($params['timeout']) ? (int) $params['timeout'] : $default;
    if ($timeout <= 0) {
        return $default;
    }

    return $timeout;
}

/**
 * @param array<string, mixed> $params
 */
function porkbun_getDomainName(array $params): ?string
{
    $directDomain = isset($params['domain']) ? trim((string) $params['domain']) : '';
    if ($directDomain !== '') {
        return strtolower($directDomain);
    }

    $sld = isset($params['sld']) ? trim((string) $params['sld']) : '';
    $tld = isset($params['tld']) ? trim((string) $params['tld']) : '';
    if ($sld === '' || $tld === '') {
        return null;
    }

    return Mapper::normalizeDomain($sld, $tld);
}

/**
 * @param array<string, mixed> $params
 */
function porkbun_createClientFromParams(array $params): ?ApiClient
{
    $apiKey = porkbun_getRequiredConfigValue($params, 'apiKey');
    $secretApiKey = porkbun_getRequiredConfigValue($params, 'secretApiKey');
    if ($apiKey === null || $secretApiKey === null) {
        return null;
    }

    return new ApiClient($apiKey, $secretApiKey, porkbun_getTimeout($params));
}

/**
 * @param array<string, mixed> $params
 */
function porkbun_getYears(array $params): int
{
    $years = (int) ($params['regperiod'] ?? $params['NumYears'] ?? 1);

    return $years > 0 ? $years : 1;
}

/**
 * @param array<string, mixed> $params
 */
function porkbun_getTransferAuthCode(array $params): ?string
{
    $authCode = trim((string) ($params['eppcode'] ?? $params['authCode'] ?? ''));

    return $authCode !== '' ? $authCode : null;
}

/**
 * @param array<string, mixed> $params
 */
function porkbun_getPreviousExpiryDate(array $params): ?string
{
    $expiryDate = trim((string) ($params['expirydate'] ?? $params['nextduedate'] ?? ''));

    return $expiryDate !== '' ? $expiryDate : null;
}

/**
 * @param array<string, mixed> $params
 * @return array<int, string>
 */
function porkbun_extractNameserversFromParams(array $params): array
{
    $values = [];
    foreach (['ns1', 'ns2', 'ns3', 'ns4', 'ns5'] as $key) {
        $value = trim((string) ($params[$key] ?? ''));
        if ($value === '') {
            continue;
        }

        $values[] = strtolower($value);
    }

    return array_values(array_unique($values));
}

/**
 * @param array<int, string> $nameservers
 * @return array{ns1?: string, ns2?: string, ns3?: string, ns4?: string, ns5?: string}
 */
function porkbun_mapNameserversToWhmcs(array $nameservers): array
{
    $mapped = [];
    for ($i = 0; $i < 5; $i++) {
        if (!isset($nameservers[$i]) || trim($nameservers[$i]) === '') {
            continue;
        }

        $mapped['ns' . ($i + 1)] = $nameservers[$i];
    }

    return $mapped;
}

/**
 * @param array<string, mixed> $params
 * @param array<string, mixed> $request
 * @param array<string, mixed> $response
 */
function porkbun_logModuleCall(array $params, string $action, array $request, array $response): void
{
    $isDebugEnabled = isset($params['debugLogging']) && (string) $params['debugLogging'] === 'on';
    if (!$isDebugEnabled || !function_exists('logModuleCall')) {
        return;
    }

    logModuleCall(
        'porkbun',
        $action,
        ApiClient::redactContext($request + ['moduleVersion' => PORKBUN_MODULE_VERSION]),
        ApiClient::redactContext($response + ['moduleVersion' => PORKBUN_MODULE_VERSION])
    );
}

/**
 * WHMCS registrar module configuration.
 *
 * @return array<string, array<string, mixed>>
 */
function porkbun_getConfigArray(): array
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Porkbun Registrar',
        ],
        'apiKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '60',
            'Description' => 'Porkbun API key',
        ],
        'secretApiKey' => [
            'FriendlyName' => 'Secret API Key',
            'Type' => 'password',
            'Size' => '60',
            'Description' => 'Porkbun secret API key',
        ],
        'timeout' => [
            'FriendlyName' => 'Request Timeout',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '20',
            'Description' => 'Request timeout in seconds',
        ],
        'debugLogging' => [
            'FriendlyName' => 'Enable Debug Logging',
            'Type' => 'yesno',
            'Description' => 'Log sanitized diagnostic details in module log',
        ],
    ];
}

/**
 * Allows admins to verify API credentials from module settings.
 *
 * @param array<string, mixed> $params
 * @return array{success: bool, error?: string}
 */
function porkbun_TestConnection(array $params): array
{
    $apiKey = porkbun_getRequiredConfigValue($params, 'apiKey');
    $secretApiKey = porkbun_getRequiredConfigValue($params, 'secretApiKey');
    $timeout = porkbun_getTimeout($params);

    if ($apiKey === null) {
        porkbun_logModuleCall(
            $params,
            'TestConnection',
            ['operation' => 'TestConnection'],
            ['success' => false, 'error' => 'Missing API Key']
        );

        return porkbun_errorResponse('Configuration error: missing required field API Key.');
    }

    if ($secretApiKey === null) {
        porkbun_logModuleCall(
            $params,
            'TestConnection',
            ['operation' => 'TestConnection'],
            ['success' => false, 'error' => 'Missing Secret API Key']
        );

        return porkbun_errorResponse('Configuration error: missing required field Secret API Key.');
    }

    $client = new ApiClient($apiKey, $secretApiKey, $timeout);
    $result = $client->validateCredentials();
    $context = is_array($result['context'] ?? null) ? $result['context'] : [];

    porkbun_logModuleCall(
        $params,
        'TestConnection',
        [
            'operation' => 'TestConnection',
            'endpoint' => '/ping',
            'timeout' => $timeout,
            'apiKey' => $apiKey,
            'secretApiKey' => $secretApiKey,
        ],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'context' => $context,
        ]
    );

    if ($result['success'] === true) {
        return porkbun_successResponse();
    }

    return porkbun_errorResponse($result['details'] ?? ($result['error'] ?? 'Credential test failed.'));
}

/**
 * @param array<string, mixed> $params
 * @return array{success: bool, error?: string}
 */
function porkbun_RegisterDomain(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return porkbun_errorResponse('Configuration error: missing required domain information.');
    }

    $client = porkbun_createClientFromParams($params);
    if ($client === null) {
        return porkbun_errorResponse('Configuration error: missing API credentials.');
    }

    $years = porkbun_getYears($params);
    $result = RegisterDomainOperation::execute($client, $domain, $years);

    porkbun_logModuleCall(
        $params,
        'RegisterDomain',
        is_array($result['request'] ?? null) ? $result['request'] : ['operation' => 'RegisterDomain'],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'context' => $result['context'] ?? [],
        ]
    );

    if (($result['success'] ?? false) === true) {
        return porkbun_successResponse();
    }

    return porkbun_errorResponse('Operation failed: RegisterDomain for ' . $domain . '. Reason: ' . ($result['details'] ?? 'Unknown error.'));
}

/**
 * @param array<string, mixed> $params
 * @return array{success: bool, error?: string}
 */
function porkbun_TransferDomain(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return porkbun_errorResponse('Configuration error: missing required domain information.');
    }

    $client = porkbun_createClientFromParams($params);
    if ($client === null) {
        return porkbun_errorResponse('Configuration error: missing API credentials.');
    }

    $authCode = porkbun_getTransferAuthCode($params);
    if ($authCode === null) {
        return porkbun_errorResponse('Operation failed: TransferDomain for ' . $domain . '. Reason: Missing transfer auth code.');
    }

    $result = TransferDomainOperation::execute($client, $domain, $authCode);

    porkbun_logModuleCall(
        $params,
        'TransferDomain',
        is_array($result['request'] ?? null) ? $result['request'] : ['operation' => 'TransferDomain'],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'context' => $result['context'] ?? [],
        ]
    );

    if (($result['success'] ?? false) === true) {
        return porkbun_successResponse();
    }

    return porkbun_errorResponse('Operation failed: TransferDomain for ' . $domain . '. Reason: ' . ($result['details'] ?? 'Unknown error.'));
}

/**
 * @param array<string, mixed> $params
 * @return array{success: bool, error?: string}
 */
function porkbun_RenewDomain(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return porkbun_errorResponse('Configuration error: missing required domain information.');
    }

    $client = porkbun_createClientFromParams($params);
    if ($client === null) {
        return porkbun_errorResponse('Configuration error: missing API credentials.');
    }

    $years = porkbun_getYears($params);
    $result = RenewDomainOperation::execute($client, $domain, $years);

    porkbun_logModuleCall(
        $params,
        'RenewDomain',
        is_array($result['request'] ?? null) ? $result['request'] : ['operation' => 'RenewDomain'],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'context' => $result['context'] ?? [],
        ]
    );

    if (($result['success'] ?? false) === true) {
        return porkbun_successResponse();
    }

    return porkbun_errorResponse('Operation failed: RenewDomain for ' . $domain . '. Reason: ' . ($result['details'] ?? 'Unknown error.'));
}

/**
 * @param array<string, mixed> $params
 * @return array{active?: bool, expired?: bool, expirydate?: string, transferredAway?: bool, error?: string}
 */
function porkbun_Sync(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return ['error' => 'Configuration error: missing required domain information.'];
    }

    $client = porkbun_createClientFromParams($params);
    if ($client === null) {
        return ['error' => 'Configuration error: missing API credentials.'];
    }

    $previousExpiryDate = porkbun_getPreviousExpiryDate($params);
    $result = SyncDomainOperation::execute($client, $domain, $previousExpiryDate);

    porkbun_logModuleCall(
        $params,
        'Sync',
        is_array($result['request'] ?? null)
            ? $result['request']
            : ['operation' => 'SyncDomain', 'endpoint' => '/domain/get/' . $domain],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'sourceExpiryDate' => (string) ($result['sourceExpiryDate'] ?? ''),
            'previousWhmcsExpiryDate' => (string) ($result['previousExpiryDate'] ?? ''),
            'syncedExpiryDate' => (string) ($result['syncedExpiryDate'] ?? ''),
            'guardrail' => (string) ($result['guardrail'] ?? ''),
            'context' => $result['context'] ?? [],
        ]
    );

    if (($result['success'] ?? false) !== true) {
        return [
            'error' => 'Operation failed: Sync for ' . $domain . '. Reason: ' . ($result['details'] ?? 'Unknown error.'),
        ];
    }

    $syncResponse = [
        'expirydate' => (string) ($result['syncedExpiryDate'] ?? ''),
        'active' => (bool) ($result['active'] ?? true),
        'expired' => (bool) ($result['expired'] ?? false),
        'transferredAway' => (bool) ($result['transferredAway'] ?? false),
    ];

    if ($syncResponse['expirydate'] === '') {
        unset($syncResponse['expirydate']);
    }

    return $syncResponse;
}

/**
 * @param array<string, mixed> $params
 * @return array{ns1?: string, ns2?: string, ns3?: string, ns4?: string, ns5?: string, error?: string}
 */
function porkbun_GetNameservers(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return ['error' => 'Configuration error: missing required domain information.'];
    }

    $client = porkbun_createClientFromParams($params);
    if ($client === null) {
        return ['error' => 'Configuration error: missing API credentials.'];
    }

    $result = GetNameserversOperation::execute($client, $domain);

    porkbun_logModuleCall(
        $params,
        'GetNameservers',
        is_array($result['request'] ?? null)
            ? $result['request']
            : ['operation' => 'GetNameservers', 'endpoint' => '/domain/getNs/' . $domain],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'context' => $result['context'] ?? [],
        ]
    );

    if (($result['success'] ?? false) !== true) {
        return [
            'error' => 'Operation failed: GetNameservers for ' . $domain . '. Reason: ' . ($result['details'] ?? 'Unknown error.'),
        ];
    }

    $nameservers = is_array($result['nameservers'] ?? null) ? $result['nameservers'] : [];

    return porkbun_mapNameserversToWhmcs($nameservers);
}

/**
 * @param array<string, mixed> $params
 * @return array{success: bool, error?: string}
 */
function porkbun_SaveNameservers(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return porkbun_errorResponse('Configuration error: missing required domain information.');
    }

    $client = porkbun_createClientFromParams($params);
    if ($client === null) {
        return porkbun_errorResponse('Configuration error: missing API credentials.');
    }

    $nameservers = porkbun_extractNameserversFromParams($params);
    if ($nameservers === []) {
        return porkbun_errorResponse('Operation failed: SaveNameservers for ' . $domain . '. Reason: At least one nameserver is required.');
    }

    $result = SaveNameserversOperation::execute($client, $domain, $nameservers);

    porkbun_logModuleCall(
        $params,
        'SaveNameservers',
        is_array($result['request'] ?? null)
            ? $result['request']
            : ['operation' => 'SaveNameservers', 'endpoint' => '/domain/updateNs/' . $domain],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'context' => $result['context'] ?? [],
        ]
    );

    if (($result['success'] ?? false) !== true) {
        return porkbun_errorResponse('Operation failed: SaveNameservers for ' . $domain . '. Reason: ' . ($result['details'] ?? 'Unknown error.'));
    }

    return porkbun_successResponse();
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function porkbun_GetContactDetails(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return ['error' => 'Configuration error: missing required domain information.'];
    }

    $client = porkbun_createClientFromParams($params);
    if ($client === null) {
        return ['error' => 'Configuration error: missing API credentials.'];
    }

    $result = GetContactDetailsOperation::execute($client, $domain);

    porkbun_logModuleCall(
        $params,
        'GetContactDetails',
        is_array($result['request'] ?? null)
            ? $result['request']
            : ['operation' => 'GetContactDetails', 'endpoint' => '/domain/getContacts/' . $domain],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'context' => $result['context'] ?? [],
        ]
    );

    if (($result['success'] ?? false) !== true) {
        return [
            'error' => 'Operation failed: GetContactDetails for ' . $domain . '. Reason: ' . ($result['details'] ?? 'Unknown error.'),
        ];
    }

    return is_array($result['contacts'] ?? null) ? $result['contacts'] : [];
}

/**
 * @param array<string, mixed> $params
 * @return array{success: bool, error?: string}
 */
function porkbun_SaveContactDetails(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return porkbun_errorResponse('Configuration error: missing required domain information.');
    }

    $client = porkbun_createClientFromParams($params);
    if ($client === null) {
        return porkbun_errorResponse('Configuration error: missing API credentials.');
    }

    $contactDetails = isset($params['contactdetails']) && is_array($params['contactdetails'])
        ? $params['contactdetails']
        : [];

    if ($contactDetails === []) {
        return porkbun_errorResponse('Operation failed: SaveContactDetails for ' . $domain . '. Reason: Missing contact details payload.');
    }

    $result = SaveContactDetailsOperation::execute($client, $domain, $contactDetails);

    porkbun_logModuleCall(
        $params,
        'SaveContactDetails',
        is_array($result['request'] ?? null)
            ? $result['request']
            : ['operation' => 'SaveContactDetails', 'endpoint' => '/domain/updateContacts/' . $domain],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'context' => $result['context'] ?? [],
        ]
    );

    if (($result['success'] ?? false) !== true) {
        return porkbun_errorResponse('Operation failed: SaveContactDetails for ' . $domain . '. Reason: ' . ($result['details'] ?? 'Unknown error.'));
    }

    return porkbun_successResponse();
}

/**
 * @param array<string, mixed> $params
 * @return array{eppcode?: string, error?: string}
 */
function porkbun_GetEPPCode(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return ['error' => 'Configuration error: missing required domain information.'];
    }

    $client = porkbun_createClientFromParams($params);
    if ($client === null) {
        return ['error' => 'Configuration error: missing API credentials.'];
    }

    $result = GetEppCodeOperation::execute($client, $domain);

    porkbun_logModuleCall(
        $params,
        'GetEPPCode',
        is_array($result['request'] ?? null)
            ? $result['request']
            : ['operation' => 'GetEPPCode', 'endpoint' => '/domain/getAuthCode/' . $domain],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'context' => $result['context'] ?? [],
        ]
    );

    if (($result['success'] ?? false) !== true) {
        return [
            'error' => 'Operation failed: GetEPPCode for ' . $domain . '. Reason: ' . ($result['details'] ?? 'Unknown error.'),
        ];
    }

    return [
        'eppcode' => (string) ($result['eppCode'] ?? ''),
    ];
}

/**
 * @param array<string, mixed> $params
 * @return array{lock?: string, error?: string}
 */
function porkbun_GetRegistrarLock(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return ['error' => 'Configuration error: missing required domain information.'];
    }

    $client = porkbun_createClientFromParams($params);
    if ($client === null) {
        return ['error' => 'Configuration error: missing API credentials.'];
    }

    $result = GetRegistrarLockOperation::execute($client, $domain);

    porkbun_logModuleCall(
        $params,
        'GetRegistrarLock',
        is_array($result['request'] ?? null)
            ? $result['request']
            : ['operation' => 'GetRegistrarLock', 'endpoint' => '/domain/getLock/' . $domain],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'context' => $result['context'] ?? [],
        ]
    );

    if (($result['success'] ?? false) !== true) {
        return [
            'error' => 'Operation failed: GetRegistrarLock for ' . $domain . '. Reason: ' . ($result['details'] ?? 'Unknown error.'),
        ];
    }

    return [
        'lock' => (bool) ($result['lockEnabled'] ?? false) ? 'locked' : 'unlocked',
    ];
}

/**
 * @param array<string, mixed> $params
 * @return array{success: bool, error?: string}
 */
function porkbun_SaveRegistrarLock(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return porkbun_errorResponse('Configuration error: missing required domain information.');
    }

    $client = porkbun_createClientFromParams($params);
    if ($client === null) {
        return porkbun_errorResponse('Configuration error: missing API credentials.');
    }

    $lockParam = strtolower(trim((string) ($params['lockenabled'] ?? $params['lockstatus'] ?? '')));
    $lockEnabled = in_array($lockParam, ['1', 'true', 'on', 'locked', 'yes'], true);

    $result = SaveRegistrarLockOperation::execute($client, $domain, $lockEnabled);

    porkbun_logModuleCall(
        $params,
        'SaveRegistrarLock',
        is_array($result['request'] ?? null)
            ? $result['request']
            : ['operation' => 'SaveRegistrarLock', 'endpoint' => '/domain/updateLock/' . $domain],
        [
            'success' => (bool) ($result['success'] ?? false),
            'details' => (string) ($result['details'] ?? ''),
            'context' => $result['context'] ?? [],
        ]
    );

    if (($result['success'] ?? false) !== true) {
        return porkbun_errorResponse('Operation failed: SaveRegistrarLock for ' . $domain . '. Reason: ' . ($result['details'] ?? 'Unknown error.'));
    }

    return porkbun_successResponse();
}

/**
 * @param array<string, mixed> $params
 * @return array{error: string}
 */
function porkbun_GetDNS(array $params): array
{
    $domain = porkbun_getDomainName($params);
    $domainText = $domain ?? '<unknown-domain>';

    $message = 'Operation failed: GetDNS for ' . $domainText . '. Reason: DNS management is not currently supported by this Porkbun WHMCS module implementation.';
    porkbun_logModuleCall(
        $params,
        'GetDNS',
        ['operation' => 'GetDNS'],
        ['success' => false, 'error' => $message]
    );

    return ['error' => $message];
}

/**
 * @param array<string, mixed> $params
 * @return array{success: bool, error?: string}
 */
function porkbun_SaveDNS(array $params): array
{
    $domain = porkbun_getDomainName($params);
    $domainText = $domain ?? '<unknown-domain>';

    $message = 'Operation failed: SaveDNS for ' . $domainText . '. Reason: DNS management is not currently supported by this Porkbun WHMCS module implementation.';
    porkbun_logModuleCall(
        $params,
        'SaveDNS',
        ['operation' => 'SaveDNS'],
        ['success' => false, 'error' => $message]
    );

    return porkbun_errorResponse($message);
}
