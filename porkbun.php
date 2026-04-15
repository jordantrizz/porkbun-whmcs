<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

if (!defined('PORKBUN_MODULE_VERSION')) {
    define('PORKBUN_MODULE_VERSION', '0.1.0');
}

require_once __DIR__ . '/src/ApiClient.php';
require_once __DIR__ . '/src/Errors.php';
require_once __DIR__ . '/src/DomainCache.php';
require_once __DIR__ . '/src/DomainRefreshQueue.php';
require_once __DIR__ . '/src/ModuleStateStore.php';
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
require_once __DIR__ . '/src/Operations/HydrateDomainCacheFromListAllOperation.php';

use PorkbunWhmcs\Registrar\ApiClient;
use PorkbunWhmcs\Registrar\DomainCache;
use PorkbunWhmcs\Registrar\DomainRefreshQueue;
use PorkbunWhmcs\Registrar\Mapper;
use PorkbunWhmcs\Registrar\ModuleStateStore;
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
use PorkbunWhmcs\Registrar\Operations\HydrateDomainCacheFromListAllOperation;
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
function porkbun_getLockCacheTtl(array $params): int
{
    $ttl = isset($params['lockCacheTtl']) ? (int) $params['lockCacheTtl'] : DomainCache::defaultTtlSeconds();

    return $ttl > 0 ? $ttl : DomainCache::defaultTtlSeconds();
}

/**
 * @param array<string, mixed> $params
 */
function porkbun_getCacheRefreshCooldown(array $params): int
{
    $cooldown = isset($params['cacheRefreshCooldown']) ? (int) $params['cacheRefreshCooldown'] : 300;

    return $cooldown > 0 ? $cooldown : 300;
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
 * @return array<string, array<string, mixed>>
 */
function porkbun_getCronCredentialContexts(array $params = []): array
{
    $contexts = [];

    $directClient = porkbun_createClientFromParams($params);
    if ($directClient instanceof ApiClient) {
        $contexts[$directClient->getCredentialFingerprint()] = $params;

        return $contexts;
    }

    $stored = porkbun_getStoredRegistrarSettings();
    if ($stored === null) {
        return [];
    }

    $storedClient = porkbun_createClientFromParams($stored);
    if ($storedClient instanceof ApiClient) {
        $contexts[$storedClient->getCredentialFingerprint()] = $stored;
    }

    return $contexts;
}

/**
 * @return array<string, mixed>|null
 */
function porkbun_getStoredRegistrarSettings(): ?array
{
    if (!class_exists('\\WHMCS\\Database\\Capsule')) {
        return null;
    }

    try {
        $capsule = '\\WHMCS\\Database\\Capsule';
        $schema = $capsule::schema();
        if (!$schema->hasTable('tblregistrars')) {
            return null;
        }

        $columns = $schema->getColumnListing('tblregistrars');
        $settingColumn = in_array('setting', $columns, true)
            ? 'setting'
            : (in_array('setting_name', $columns, true) ? 'setting_name' : null);
        $valueColumn = in_array('value', $columns, true)
            ? 'value'
            : (in_array('setting_value', $columns, true) ? 'setting_value' : null);

        if ($settingColumn === null || $valueColumn === null) {
            return null;
        }

        /** @var iterable<object> $rows */
        $rows = $capsule::table('tblregistrars')
            ->where('registrar', 'porkbun')
            ->get();

        $params = [];
        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }

            $key = isset($row->{$settingColumn}) ? trim((string) $row->{$settingColumn}) : '';
            if ($key === '') {
                continue;
            }

            $value = isset($row->{$valueColumn}) ? (string) $row->{$valueColumn} : '';
            $params[$key] = porkbun_normalizeStoredRegistrarSettingValue($key, $value);
        }

        return $params !== [] ? $params : null;
    } catch (\Throwable $exception) {
        return null;
    }
}

function porkbun_normalizeStoredRegistrarSettingValue(string $key, string $value): string
{
    if ($value === '') {
        return '';
    }

    if ($key !== 'secretApiKey' || !function_exists('decrypt')) {
        return $value;
    }

    try {
        $decrypted = call_user_func('decrypt', $value);
        if (is_string($decrypted) && trim($decrypted) !== '') {
            return $decrypted;
        }
    } catch (\Throwable $exception) {
        return $value;
    }

    return $value;
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

    call_user_func(
        'logModuleCall',
        'porkbun',
        $action,
        ApiClient::redactContext($request + ['moduleVersion' => PORKBUN_MODULE_VERSION]),
        ApiClient::redactContext($response + ['moduleVersion' => PORKBUN_MODULE_VERSION])
    );
}

/**
 * @return array<string, mixed>
 */
function porkbun_getCacheHydrationStatus(): array
{
    $status = ModuleStateStore::get('cache_hydration_status', []);

    return is_array($status) ? $status : [];
}

/**
 * @param array<string, mixed> $result
 */
function porkbun_recordCacheHydrationStatus(array $result, string $source): void
{
    $now = time();
    $existing = porkbun_getCacheHydrationStatus();
    $success = (($result['success'] ?? false) === true);

    $status = [
        'lastRunAt' => $now,
        'lastSuccessAt' => $success ? $now : (int) ($existing['lastSuccessAt'] ?? 0),
        'lastSource' => $source,
        'lastSuccess' => $success,
        'lastDetails' => (string) ($result['details'] ?? ''),
        'lastDomainsHydrated' => (int) ($result['domainsHydrated'] ?? 0),
        'lastPagesFetched' => (int) ($result['pagesFetched'] ?? 0),
    ];

    ModuleStateStore::put('cache_hydration_status', $status, $now);
}

/**
 * @return array<string, mixed>
 */
function porkbun_getQueueProcessorStatus(): array
{
    $status = ModuleStateStore::get('cache_queue_processor_status', []);

    return is_array($status) ? $status : [];
}

/**
 * @param array<string, mixed> $result
 */
function porkbun_recordQueueProcessorStatus(array $result, string $source): void
{
    $now = time();
    $existing = porkbun_getQueueProcessorStatus();
    $success = (($result['success'] ?? false) === true);

    $status = [
        'lastRunAt' => $now,
        'lastSuccessAt' => $success ? $now : (int) ($existing['lastSuccessAt'] ?? 0),
        'lastSource' => $source,
        'lastSuccess' => $success,
        'lastDetails' => (string) ($result['details'] ?? ''),
        'lastProcessed' => (int) ($result['processed'] ?? 0),
        'lastFailed' => (int) ($result['failed'] ?? 0),
    ];

    ModuleStateStore::put('cache_queue_processor_status', $status, $now);
}

function porkbun_formatAdminTimestamp($timestamp, string $fallback = 'Never'): string
{
    if (!is_int($timestamp) || $timestamp <= 0) {
        return $fallback;
    }

    return gmdate('Y-m-d H:i:s', $timestamp) . ' UTC';
}

function porkbun_formatAdminSource(string $source): string
{
    $normalized = strtolower(trim($source));
    if ($normalized === 'daily-cron') {
        return 'WHMCS DailyCronJob';
    }

    if ($normalized === 'settings-generate') {
        return 'Registrar config page';
    }

    if ($normalized === 'addon-page') {
        return 'Cache Admin Page';
    }

    if ($normalized === 'admin-command') {
        return 'Admin command';
    }

    if ($normalized === '') {
        return 'Unknown';
    }

    return ucwords(str_replace(['-', '_'], ' ', $normalized));
}

/**
 * @return array{success: bool, details: string, domainsHydrated?: int, pagesFetched?: int}
 */
function porkbun_generateCacheFromStoredSettings(string $source = 'settings-generate'): array
{
    $stored = porkbun_getStoredRegistrarSettings();
    if ($stored === null) {
        return [
            'success' => false,
            'details' => 'Cache generation failed: Porkbun registrar settings are not available.',
        ];
    }

    $client = porkbun_createClientFromParams($stored);
    if (!$client instanceof ApiClient) {
        return [
            'success' => false,
            'details' => 'Cache generation failed: missing API credentials.',
        ];
    }

    $result = HydrateDomainCacheFromListAllOperation::execute(
        $client,
        $client->getCredentialFingerprint(),
        porkbun_getLockCacheTtl($stored)
    );

    porkbun_recordCacheHydrationStatus($result, $source);

    if (($result['success'] ?? false) !== true) {
        return [
            'success' => false,
            'details' => (string) ($result['details'] ?? 'Cache generation failed.'),
            'domainsHydrated' => (int) ($result['domainsHydrated'] ?? 0),
            'pagesFetched' => (int) ($result['pagesFetched'] ?? 0),
        ];
    }

    return [
        'success' => true,
        'details' => 'Cache generated successfully.',
        'domainsHydrated' => (int) ($result['domainsHydrated'] ?? 0),
        'pagesFetched' => (int) ($result['pagesFetched'] ?? 0),
    ];
}

function porkbun_isValidAdminSecurityToken(string $providedToken, ?string $expectedToken = null): bool
{
    $sessionToken = isset($_SESSION['token']) ? (string) $_SESSION['token'] : '';

    if ($providedToken === '') {
        return false;
    }

    if (is_string($expectedToken) && $expectedToken !== '' && hash_equals($expectedToken, $providedToken)) {
        return true;
    }

    return $sessionToken !== ''
        && hash_equals($sessionToken, $providedToken);
}

/**
 * @return array<string, mixed>
 */
function porkbun_getCacheAdminStatusData(): array
{
    $stats = DomainCache::getStats();
    $queueStats = DomainRefreshQueue::getStats();
    $hydrationStatus = porkbun_getCacheHydrationStatus();
    $queueProcessorStatus = porkbun_getQueueProcessorStatus();
    $lastFetchedAt = $stats['lastFetchedAt'] ?? null;

    return [
        'totalRecords' => (int) ($stats['totalRecords'] ?? 0),
        'totalDomains' => (int) ($stats['totalDomains'] ?? 0),
        'lastUpdatedText' => porkbun_formatAdminTimestamp($lastFetchedAt),
        'lastHydrationText' => porkbun_formatAdminTimestamp(
            isset($hydrationStatus['lastSuccessAt']) ? (int) $hydrationStatus['lastSuccessAt'] : null,
            porkbun_formatAdminTimestamp($lastFetchedAt)
        ),
        'lastHydrationSource' => porkbun_formatAdminSource((string) ($hydrationStatus['lastSource'] ?? '')),
        'lastHydrationDetails' => trim((string) ($hydrationStatus['lastDetails'] ?? '')),
        'lastHydrationPages' => (int) ($hydrationStatus['lastPagesFetched'] ?? 0),
        'lastHydratedRecords' => (int) ($hydrationStatus['lastDomainsHydrated'] ?? 0),
        'queuePending' => (int) ($queueStats['pending'] ?? 0),
        'queueProcessing' => (int) ($queueStats['processing'] ?? 0),
        'queueFailed' => (int) ($queueStats['failed'] ?? 0),
        'automaticProcessingText' => function_exists('add_hook')
            ? 'Hook registered. Execution depends on the WHMCS automation cron running DailyCronJob.'
            : 'Unavailable because WHMCS hook registration is not available.',
        'lastQueueRunText' => porkbun_formatAdminTimestamp(isset($queueProcessorStatus['lastRunAt']) ? (int) $queueProcessorStatus['lastRunAt'] : null),
        'lastQueueSource' => porkbun_formatAdminSource((string) ($queueProcessorStatus['lastSource'] ?? '')),
        'lastQueueDetails' => trim((string) ($queueProcessorStatus['lastDetails'] ?? '')),
        'lastQueueProcessed' => (int) ($queueProcessorStatus['lastProcessed'] ?? 0),
        'lastQueueFailed' => (int) ($queueProcessorStatus['lastFailed'] ?? 0),
        'nextRunText' => 'Controlled by the WHMCS automation cron schedule. Exact next run timing is not exposed by this module.',
    ];
}

/**
 * @return array{type: string, text: string, success: bool}
 */
function porkbun_runCacheAdminAction(string $action, string $source = 'addon-page'): array
{
    $normalizedAction = strtolower(trim($action));

    if ($normalizedAction === 'clear') {
        $deleted = DomainCache::clearAll();

        return [
            'type' => 'success',
            'text' => 'Cache cleared. Removed ' . $deleted . ' cached record(s).',
            'success' => true,
        ];
    }

    if ($normalizedAction === 'generate') {
        $generated = porkbun_generateCacheFromStoredSettings($source);
        $generatedRecords = (int) ($generated['domainsHydrated'] ?? 0);
        $pagesFetched = (int) ($generated['pagesFetched'] ?? 0);

        return [
            'type' => (($generated['success'] ?? false) === true) ? 'success' : 'error',
            'text' => (($generated['success'] ?? false) === true)
                ? ('Cache generated successfully. Hydrated ' . $generatedRecords . ' cached record(s) across ' . max(1, $pagesFetched) . ' page(s).')
                : (string) ($generated['details'] ?? 'Cache generation failed.'),
            'success' => (($generated['success'] ?? false) === true),
        ];
    }

    if ($normalizedAction === 'process-queue') {
        $result = porkbun_runDomainCacheRefreshQueue([], $source);

        return [
            'type' => (($result['success'] ?? false) === true) ? 'success' : 'error',
            'text' => (string) ($result['details'] ?? 'Queue processing failed.'),
            'success' => (($result['success'] ?? false) === true),
        ];
    }

    return [
        'type' => 'error',
        'text' => 'Unsupported cache admin action: ' . $action,
        'success' => false,
    ];
}

/**
 * @param array<string, mixed>|null $message
 */
function porkbun_renderCacheAdminPanel(
    string $formAction = '',
    ?array $message = null,
    bool $includeProcessQueueButton = true,
    ?string $tokenValue = null
): string
{
    $status = porkbun_getCacheAdminStatusData();

    $messageHtml = '';
    if (is_array($message)) {
        $isError = (($message['type'] ?? '') === 'error');
        $color = $isError ? '#b91c1c' : '#166534';
        $messageText = htmlspecialchars((string) ($message['text'] ?? ''), ENT_QUOTES, 'UTF-8');
        $messageHtml = '<div style="margin-top:12px;padding:10px 12px;border-radius:4px;background:'
            . ($isError ? '#fef2f2' : '#f0fdf4')
            . ';color:' . $color . ';">' . $messageText . '</div>';
    }

    $token = is_string($tokenValue) && $tokenValue !== ''
        ? $tokenValue
        : (isset($_SESSION['token']) ? (string) $_SESSION['token'] : '');
    $tokenField = '';
    if ($token !== '') {
        $tokenField = '<input type="hidden" name="token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    $formActionAttribute = trim($formAction) !== ''
        ? ' action="' . htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') . '"'
        : '';
    $processQueueButton = $includeProcessQueueButton
        ? '<button type="submit" name="porkbunCacheAction" value="process-queue" class="btn btn-default btn-sm" style="margin-left:6px;">Process Queue</button>'
        : '';

    $formHtml = '<form method="post"' . $formActionAttribute . ' style="margin-top:12px;">'
            . $tokenField
            . '<button type="submit" name="porkbunCacheAction" value="generate" class="btn btn-default btn-sm">Generate Cache</button>'
            . '<button type="submit" name="porkbunCacheAction" value="clear" class="btn btn-default btn-sm" style="margin-left:6px;">Clear Cache</button>'
            . $processQueueButton
            . '</form>';

    return '<div class="panel panel-default"><div class="panel-body">'
        . '<div style="margin-bottom:8px;"><strong>Cached Domains:</strong> ' . (int) ($status['totalDomains'] ?? 0) . '</div>'
        . '<div style="margin-bottom:8px;"><strong>Cached Records:</strong> ' . (int) ($status['totalRecords'] ?? 0) . '</div>'
        . '<div style="margin-bottom:8px;"><strong>Last Cache Record Update:</strong> ' . htmlspecialchars((string) ($status['lastUpdatedText'] ?? 'Never'), ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div style="margin-bottom:8px;"><strong>Last Full Cache Hydration:</strong> ' . htmlspecialchars((string) ($status['lastHydrationText'] ?? 'Never'), ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div style="margin-bottom:8px;"><strong>Last Hydration Source:</strong> ' . htmlspecialchars((string) ($status['lastHydrationSource'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div style="margin-bottom:8px;"><strong>Last Hydration Result:</strong> ' . (int) ($status['lastHydratedRecords'] ?? 0) . ' record(s)'
        . ((int) ($status['lastHydrationPages'] ?? 0) > 0 ? ' across ' . (int) ($status['lastHydrationPages'] ?? 0) . ' page(s)' : '')
        . (((string) ($status['lastHydrationDetails'] ?? '')) !== '' ? ' <span style="color:#6b7280;">' . htmlspecialchars((string) ($status['lastHydrationDetails'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>' : '')
        . '</div>'
        . '<div style="margin-bottom:8px;"><strong>Refresh Queue:</strong> ' . (int) ($status['queuePending'] ?? 0) . ' pending, ' . (int) ($status['queueProcessing'] ?? 0) . ' processing, ' . (int) ($status['queueFailed'] ?? 0) . ' failed</div>'
        . '<div style="margin-bottom:8px;"><strong>Automatic Queue Processing:</strong> ' . htmlspecialchars((string) ($status['automaticProcessingText'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div style="margin-bottom:8px;"><strong>Last Queue Run:</strong> ' . htmlspecialchars((string) ($status['lastQueueRunText'] ?? 'Never'), ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div style="margin-bottom:8px;"><strong>Last Queue Source:</strong> ' . htmlspecialchars((string) ($status['lastQueueSource'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div style="margin-bottom:8px;"><strong>Last Queue Result:</strong> ' . (int) ($status['lastQueueProcessed'] ?? 0) . ' processed, ' . (int) ($status['lastQueueFailed'] ?? 0) . ' failed'
        . (((string) ($status['lastQueueDetails'] ?? '')) !== '' ? ' <span style="color:#6b7280;">' . htmlspecialchars((string) ($status['lastQueueDetails'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>' : '')
        . '</div>'
        . '<div style="margin-bottom:8px;"><strong>Next Queue Run:</strong> ' . htmlspecialchars((string) ($status['nextRunText'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
        . $formHtml
        . $messageHtml
        . '</div></div>';
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
        'lockCacheTtl' => [
            'FriendlyName' => 'Domain Cache TTL',
            'Type' => 'text',
            'Size' => '6',
            'Default' => (string) DomainCache::defaultTtlSeconds(),
            'Description' => 'Cache freshness window in seconds before stale responses are served (default 3600)',
        ],
        'cacheRefreshCooldown' => [
            'FriendlyName' => 'Refresh Queue Cooldown',
            'Type' => 'text',
            'Size' => '6',
            'Default' => '300',
            'Description' => 'Minimum seconds between repeated refresh queue requests for the same cache type.',
        ],
        'debugLogging' => [
            'FriendlyName' => 'Enable Debug Logging',
            'Type' => 'yesno',
            'Description' => 'Log sanitized diagnostic details in module log',
        ],
    ];
}

/**
 * Add custom registrar command buttons shown in WHMCS admin domain view.
 *
 * @return array<string, string>
 */
function porkbun_AdminCustomButtonArray(): array
{
    return [
        'Sync Expiry and Status' => 'syncnow',
        'Process Cache Refresh Queue' => 'processcachequeue',
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
    $result = SyncDomainOperation::execute($client, $domain, $previousExpiryDate, porkbun_getLockCacheTtl($params));

    porkbun_logModuleCall(
        $params,
        'Sync',
        is_array($result['request'] ?? null)
            ? $result['request']
            : ['operation' => 'SyncDomain', 'endpoint' => '/domain/listAll'],
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
 * Manual admin sync command for transferred domains.
 *
 * @param array<string, mixed> $params
 * @return array{success: bool, error?: string}
 */
function porkbun_syncnow(array $params): array
{
    $domain = porkbun_getDomainName($params);
    if ($domain === null) {
        return porkbun_errorResponse('Configuration error: missing required domain information.');
    }

    $syncResult = porkbun_Sync($params);

    porkbun_logModuleCall(
        $params,
        'ManualSyncNow',
        [
            'operation' => 'ManualSyncNow',
            'domain' => $domain,
        ],
        [
            'success' => !isset($syncResult['error']),
            'expirydate' => (string) ($syncResult['expirydate'] ?? ''),
            'active' => (bool) ($syncResult['active'] ?? false),
            'expired' => (bool) ($syncResult['expired'] ?? false),
            'transferredAway' => (bool) ($syncResult['transferredAway'] ?? false),
            'error' => (string) ($syncResult['error'] ?? ''),
        ]
    );

    if (isset($syncResult['error'])) {
        return porkbun_errorResponse((string) $syncResult['error']);
    }

    return porkbun_successResponse();
}

/**
 * Manual admin command to process queued cache refresh jobs.
 *
 * @param array<string, mixed> $params
 * @return array{success: bool, error?: string}
 */
function porkbun_processcachequeue(array $params): array
{
    $result = porkbun_runDomainCacheRefreshQueue($params, 'admin-command');

    porkbun_logModuleCall(
        $params,
        'ProcessDomainCacheRefreshQueue',
        [
            'operation' => 'ProcessDomainCacheRefreshQueue',
        ],
        $result
    );

    if (($result['success'] ?? false) !== true) {
        return porkbun_errorResponse((string) ($result['details'] ?? 'Queue processing failed.'));
    }

    return porkbun_successResponse();
}

/**
 * @param array<string, mixed> $params
 * @return array{ns1?: string, ns2?: string, ns3?: string, ns4?: string, ns5?: string, warning?: string, error?: string}
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

    $result = GetNameserversOperation::execute($client, $domain, porkbun_getCacheRefreshCooldown($params));

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
        $warningCode = strtoupper(trim((string) ($result['warningCode'] ?? '')));
        if ($warningCode === 'DOMAIN_IS_NOT_OPTED_IN_TO_API_ACCESS' || $warningCode === 'CACHE_REFRESH_QUEUED') {
            $fallbackNameservers = porkbun_extractNameserversFromParams($params);
            $response = porkbun_mapNameserversToWhmcs($fallbackNameservers);
            $response['warning'] = (string) ($result['warning'] ?? 'Nameserver sync warning: cache refresh is queued and existing WHMCS values were used.');

            return $response;
        }

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
 * Queue processor for stale cache refresh jobs.
 *
 * @param array<string, mixed> $params
 * @return array{success: bool, processed: int, failed: int, details: string}
 */
function porkbun_runDomainCacheRefreshQueue(array $params = [], string $source = 'manual'): array
{
    $contexts = porkbun_getCronCredentialContexts($params);
    if ($contexts === []) {
        $result = [
            'success' => false,
            'processed' => 0,
            'failed' => 0,
            'details' => 'Configuration error: missing API credentials.',
        ];

        porkbun_recordQueueProcessorStatus($result, $source);

        return $result;
    }

    $jobs = DomainRefreshQueue::claimBatch(5);
    if ($jobs === []) {
        $result = [
            'success' => true,
            'processed' => 0,
            'failed' => 0,
            'details' => 'No queued refresh jobs.',
        ];

        porkbun_recordQueueProcessorStatus($result, $source);

        return $result;
    }

    $processed = 0;
    $failed = 0;
    $clients = [];

    foreach ($jobs as $job) {
        $jobId = (int) ($job['id'] ?? 0);
        $accountHash = (string) ($job['accountHash'] ?? '');
        $attempts = (int) ($job['attempts'] ?? 0);
        $type = strtolower(trim((string) ($job['dataType'] ?? '')));

        if ($jobId <= 0 || $accountHash === '' || !in_array($type, ['lock', 'nameservers'], true)) {
            $failed++;
            continue;
        }

        if (!array_key_exists($accountHash, $contexts)) {
            DomainRefreshQueue::fail($jobId, 'No matching Porkbun registrar credentials were available for queued refresh.', $attempts);
            $failed++;
            continue;
        }

        if (!isset($clients[$accountHash])) {
            $clients[$accountHash] = porkbun_createClientFromParams($contexts[$accountHash]);
        }

        $client = $clients[$accountHash];
        if (!$client instanceof ApiClient) {
            DomainRefreshQueue::fail($jobId, 'Unable to initialize Porkbun API client for queued refresh.', $attempts);
            $failed++;
            continue;
        }

        $ttl = porkbun_getLockCacheTtl($contexts[$accountHash]);
        $result = HydrateDomainCacheFromListAllOperation::execute($client, $accountHash, $ttl);
        if (($result['success'] ?? false) === true) {
            porkbun_recordCacheHydrationStatus($result, $source);
            DomainRefreshQueue::complete($jobId);
            $processed++;
            continue;
        }

        DomainRefreshQueue::fail($jobId, (string) ($result['details'] ?? 'Refresh failed.'), $attempts);
        $failed++;
    }

    $result = [
        'success' => $failed === 0,
        'processed' => $processed,
        'failed' => $failed,
        'details' => $failed === 0
            ? ('Processed ' . $processed . ' cache refresh job(s).')
            : ('Processed ' . $processed . ' job(s), ' . $failed . ' failed.'),
    ];

    porkbun_recordQueueProcessorStatus($result, $source);

    return $result;
}

/**
 * Queue processor for stale cache refresh jobs.
 *
 * @param array<string, mixed> $params
 * @return array{success: bool, processed: int, failed: int, details: string}
 */
function porkbun_ProcessDomainCacheRefreshQueue(array $params = []): array
{
    return porkbun_runDomainCacheRefreshQueue($params, 'manual');
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

    $result = GetRegistrarLockOperation::execute(
        $client,
        $domain,
        porkbun_getLockCacheTtl($params),
        porkbun_getCacheRefreshCooldown($params)
    );

    porkbun_logModuleCall(
        $params,
        'GetRegistrarLock',
        is_array($result['request'] ?? null)
            ? $result['request']
            : ['operation' => 'GetRegistrarLock', 'endpoint' => '/domain/listAll'],
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

    $result = SaveRegistrarLockOperation::execute($client, $domain, $lockEnabled, porkbun_getLockCacheTtl($params));

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

if (function_exists('add_hook')) {
    call_user_func('add_hook', 'DailyCronJob', 1, function ($vars): void {
        $result = porkbun_runDomainCacheRefreshQueue([], 'daily-cron');

        if (!function_exists('logActivity')) {
            return;
        }

        $processed = (int) ($result['processed'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);
        if ($processed < 1 && $failed < 1) {
            return;
        }

        call_user_func(
            'logActivity',
            'Porkbun domain cache cron processed '
            . $processed
            . ' job(s) with '
            . $failed
            . ' failure(s).'
        );
    });
}
