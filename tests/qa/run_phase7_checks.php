<?php

declare(strict_types=1);

define('WHMCS', true);
define('ADMINAREA', true);
require_once __DIR__ . '/../../porkbun.php';
require_once __DIR__ . '/../../src/ApiClient.php';
require_once __DIR__ . '/../../modules/addons/porkbun_cache_admin/porkbun_cache_admin.php';

use PorkbunWhmcs\Registrar\ApiClient;

$results = [];
$failures = 0;

/**
 * @param array<int, array<string, mixed>> $results
 */
function addResult(array &$results, string $name, bool $passed, string $details = ''): void
{
    $results[] = [
        'name' => $name,
        'passed' => $passed,
        'details' => $details,
    ];
}

function assertContains(string $needle, string $haystack): bool
{
    return stripos($haystack, $needle) !== false;
}

$GLOBALS['porkbunTestModuleLogCalls'] = [];

if (!function_exists('logModuleCall')) {
    function logModuleCall($module, $action, $request, $response, $replaceVars = '', $replaceVarsWith = ''): void
    {
        $GLOBALS['porkbunTestModuleLogCalls'][] = [
            'module' => $module,
            'action' => $action,
            'request' => $request,
            'response' => $response,
        ];
    }
}

$operationTests = [
    'porkbun_RegisterDomain' => ['sld' => 'example', 'tld' => 'com', 'regperiod' => 1],
    'porkbun_TransferDomain' => ['sld' => 'example', 'tld' => 'com', 'eppcode' => 'ABC-123'],
    'porkbun_RenewDomain' => ['sld' => 'example', 'tld' => 'com', 'regperiod' => 1],
    'porkbun_Sync' => ['sld' => 'example', 'tld' => 'com', 'expirydate' => '2027-01-01'],
    'porkbun_GetNameservers' => ['sld' => 'example', 'tld' => 'com'],
    'porkbun_SaveNameservers' => ['sld' => 'example', 'tld' => 'com', 'ns1' => 'ns1.example.test'],
    'porkbun_GetContactDetails' => ['sld' => 'example', 'tld' => 'com'],
    'porkbun_SaveContactDetails' => [
        'sld' => 'example',
        'tld' => 'com',
        'contactdetails' => [
            'Registrant' => ['First Name' => 'Test'],
        ],
    ],
    'porkbun_GetEPPCode' => ['sld' => 'example', 'tld' => 'com'],
    'porkbun_GetRegistrarLock' => ['sld' => 'example', 'tld' => 'com'],
    'porkbun_SaveRegistrarLock' => ['sld' => 'example', 'tld' => 'com', 'lockenabled' => '1'],
];

foreach ($operationTests as $functionName => $params) {
    if (!function_exists($functionName)) {
        addResult($results, $functionName . ' exists', false, 'Function not found');
        $failures++;
        continue;
    }

    /** @var array<string, mixed> $response */
    $response = $functionName($params);
    $error = (string) ($response['error'] ?? '');
    $ok = assertContains('missing api credentials', $error);

    if (!$ok) {
        $failures++;
    }

    addResult(
        $results,
        $functionName . ' invalid credentials handling',
        $ok,
        $ok ? 'Returned expected credential error.' : ('Unexpected response: ' . json_encode($response))
    );
}

if (function_exists('porkbun_TestConnection')) {
    $testConnectionResponse = porkbun_TestConnection([]);
    $error = (string) ($testConnectionResponse['error'] ?? '');
    $ok = assertContains('missing required field api key', $error);

    if (!$ok) {
        $failures++;
    }

    addResult(
        $results,
        'porkbun_TestConnection missing API key handling',
        $ok,
        $ok ? 'Returned expected missing API key error.' : ('Unexpected response: ' . json_encode($testConnectionResponse))
    );
}

if (function_exists('porkbun_getConfigArray')) {
    $config = porkbun_getConfigArray();
    $hasStatusField = array_key_exists('lockCacheStatus', $config);
    $hasRegistrarSettings = isset($config['apiKey'], $config['secretApiKey'], $config['lockCacheTtl'], $config['cacheRefreshCooldown']);
    $ok = !$hasStatusField && $hasRegistrarSettings;

    if (!$ok) {
        $failures++;
    }

    addResult(
        $results,
        'porkbun_getConfigArray supported fields only',
        $ok,
        $ok ? 'Registrar config exposes supported settings fields without the unsupported status panel.' : ('Unexpected config array: ' . json_encode(array_keys($config)))
    );
}

if (function_exists('porkbun_mapSyncResultToWhmcsStatus') && function_exists('porkbun_buildDomainSyncUpdate')) {
    $statusCases = [
        'transferredAway flag maps to Transferred Away' => [
            ['transferredAway' => true],
            'Active',
            'Transferred Away',
        ],
        'cancelled flag maps to Cancelled' => [
            ['cancelled' => true],
            'Active',
            'Cancelled',
        ],
        'active flag reactivates Expired domain' => [
            ['active' => true],
            'Expired',
            'Active',
        ],
        'active flag reactivates Transferred Away domain' => [
            ['active' => true],
            'Transferred Away',
            'Active',
        ],
        'active domain keeps Active status unchanged' => [
            ['active' => true],
            'Active',
            null,
        ],
        'transferredAway takes precedence over active' => [
            ['active' => true, 'transferredAway' => true],
            'Active',
            'Transferred Away',
        ],
    ];

    foreach ($statusCases as $name => $case) {
        [$syncResult, $currentStatus, $expected] = $case;
        $actual = porkbun_mapSyncResultToWhmcsStatus($syncResult, $currentStatus);
        $ok = $actual === $expected;

        if (!$ok) {
            $failures++;
        }

        addResult(
            $results,
            'Sync status mapping: ' . $name,
            $ok,
            $ok ? 'Mapped correctly.' : ('Expected ' . json_encode($expected) . ', got ' . json_encode($actual) . '.')
        );
    }

    $update = porkbun_buildDomainSyncUpdate(
        ['expirydate' => '2027-01-01', 'cancelled' => true],
        'Active',
        true
    );
    $updateOk = ($update['expirydate'] ?? '') === '2027-01-01'
        && ($update['nextduedate'] ?? '') === '2027-01-01'
        && ($update['status'] ?? '') === 'Cancelled';

    if (!$updateOk) {
        $failures++;
    }

    addResult(
        $results,
        'Sync update payload: expiry, next due date and cancelled status',
        $updateOk,
        $updateOk ? 'Payload carried expected expiry, next due date and status.' : ('Unexpected payload: ' . json_encode($update))
    );

    $activeUpdate = porkbun_buildDomainSyncUpdate(['expirydate' => '2027-01-01', 'active' => true], 'Active', false);
    $activeUpdateOk = !array_key_exists('status', $activeUpdate)
        && !array_key_exists('nextduedate', $activeUpdate)
        && ($activeUpdate['expirydate'] ?? '') === '2027-01-01';

    if (!$activeUpdateOk) {
        $failures++;
    }

    addResult(
        $results,
        'Sync update payload: active domain leaves status and due date alone',
        $activeUpdateOk,
        $activeUpdateOk ? 'Active domain produced an expiry-only update.' : ('Unexpected payload: ' . json_encode($activeUpdate))
    );
}

if (function_exists('porkbun_applyDomainSyncUpdate')) {
    $missingId = porkbun_applyDomainSyncUpdate(0, ['expirydate' => '2027-01-01']);
    $missingIdOk = ($missingId['success'] ?? true) === false
        && assertContains('missing whmcs domain id', (string) ($missingId['details'] ?? ''));

    if (!$missingIdOk) {
        $failures++;
    }

    addResult(
        $results,
        'Sync apply: missing domain ID fails safely',
        $missingIdOk,
        $missingIdOk ? 'Rejected update without a domain ID.' : ('Unexpected response: ' . json_encode($missingId))
    );

    $noChange = porkbun_applyDomainSyncUpdate(123, []);
    $noChangeOk = ($noChange['success'] ?? false) === true;

    if (!$noChangeOk) {
        $failures++;
    }

    addResult(
        $results,
        'Sync apply: empty update is a successful no-op',
        $noChangeOk,
        $noChangeOk ? 'Empty update reported no required changes.' : ('Unexpected response: ' . json_encode($noChange))
    );
}

if (function_exists('porkbun_logModuleCall') && function_exists('porkbun_syncnow')) {
    $GLOBALS['porkbunTestModuleLogCalls'] = [];
    porkbun_logModuleCall(
        ['debugLogging' => 'off'],
        'SyncForced',
        ['operation' => 'SyncTest', 'apiKey' => 'pk1_secret'],
        ['success' => true],
        true
    );

    $forcedCalls = $GLOBALS['porkbunTestModuleLogCalls'];
    $forcedCall = $forcedCalls[0] ?? [];
    $forcedOk = count($forcedCalls) === 1
        && ($forcedCall['action'] ?? '') === 'SyncForced'
        && (string) (($forcedCall['request']['apiKey'] ?? '')) === '***redacted***';

    if (!$forcedOk) {
        $failures++;
    }

    addResult(
        $results,
        'Sync logging: forced entry written when debug logging is off',
        $forcedOk,
        $forcedOk ? 'Forced sync log recorded with redacted credentials.' : ('Unexpected calls: ' . json_encode($forcedCalls))
    );

    $GLOBALS['porkbunTestModuleLogCalls'] = [];
    porkbun_logModuleCall(
        ['debugLogging' => 'off'],
        'Gated',
        ['operation' => 'GatedTest'],
        ['success' => true]
    );

    $gatedOk = $GLOBALS['porkbunTestModuleLogCalls'] === [];

    if (!$gatedOk) {
        $failures++;
    }

    addResult(
        $results,
        'Sync logging: non-forced entries stay gated by debug logging',
        $gatedOk,
        $gatedOk ? 'Gated call was not logged while debug logging is off.' : ('Unexpected calls: ' . json_encode($GLOBALS['porkbunTestModuleLogCalls']))
    );

    $GLOBALS['porkbunTestModuleLogCalls'] = [];
    porkbun_logModuleCall(
        ['debugLogging' => 'on'],
        'DebugOn',
        ['operation' => 'DebugOnTest'],
        ['success' => true]
    );

    $debugOnOk = count($GLOBALS['porkbunTestModuleLogCalls']) === 1;

    if (!$debugOnOk) {
        $failures++;
    }

    addResult(
        $results,
        'Sync logging: debug-enabled entries still written',
        $debugOnOk,
        $debugOnOk ? 'Debug-enabled call was logged.' : ('Unexpected calls: ' . json_encode($GLOBALS['porkbunTestModuleLogCalls']))
    );

    $GLOBALS['porkbunTestModuleLogCalls'] = [];
    porkbun_syncnow(['sld' => 'example', 'tld' => 'com', 'debugLogging' => 'off']);

    $syncNowActions = array_map(static function (array $call): string {
        return (string) ($call['action'] ?? '');
    }, $GLOBALS['porkbunTestModuleLogCalls']);
    $syncNowOk = in_array('ManualSyncNow', $syncNowActions, true);

    if (!$syncNowOk) {
        $failures++;
    }

    addResult(
        $results,
        'Sync logging: ManualSyncNow failure logs without debug logging',
        $syncNowOk,
        $syncNowOk ? 'Manual sync failure was logged while debug logging is off.' : ('Unexpected calls: ' . json_encode($GLOBALS['porkbunTestModuleLogCalls']))
    );
}

$hydrateClass = 'PorkbunWhmcs\\Registrar\\Operations\\HydrateDomainCacheFromListAllOperation';
if (class_exists($hydrateClass)) {
    try {
        $lockMethod = new \ReflectionMethod($hydrateClass, 'extractLockState');
        $lockMethod->setAccessible(true);
        $lockOn = $lockMethod->invoke(null, ['securityLock' => '1']);
        $lockOff = $lockMethod->invoke(null, ['securityLock' => '0']);
        $lockInt = $lockMethod->invoke(null, ['securityLock' => 1]);
        $lockOk = $lockOn === true && $lockOff === false && $lockInt === true;
    } catch (\Throwable $exception) {
        $lockOk = false;
    }

    if (!$lockOk) {
        $failures++;
    }

    addResult(
        $results,
        'Cache hydration: securityLock maps to lock state',
        $lockOk,
        $lockOk ? 'securityLock 1/0 mapped to locked/unlocked.' : 'securityLock was not mapped to a lock boolean.'
    );

    try {
        $dataMethod = new \ReflectionMethod($hydrateClass, 'extractDomainData');
        $dataMethod->setAccessible(true);
        $typed = $dataMethod->invoke(null, [
            'status' => 'SUCCESS',
            'count' => 1,
            'domains' => [
                [
                    'domain' => 'Example.com',
                    'status' => 'ACTIVE',
                    'tld' => 'com',
                    'createDate' => '2020-01-01 00:00:00',
                    'expireDate' => '2027-03-04 12:00:00',
                    'securityLock' => '1',
                    'autoRenew' => '1',
                ],
            ],
        ]);

        $typedOk = is_array($typed)
            && ($typed['example.com']['lock'] ?? null) === true
            && ($typed['example.com']['sync']['expiryDate'] ?? '') === '2027-03-04 12:00:00'
            && ($typed['example.com']['sync']['status'] ?? '') === 'ACTIVE'
            && !array_key_exists('nameservers', $typed['example.com'] ?? []);
    } catch (\Throwable $exception) {
        $typedOk = false;
        $typed = [];
    }

    if (!$typedOk) {
        $failures++;
    }

    addResult(
        $results,
        'Cache hydration: listAll maps lock and sync without nameservers',
        $typedOk,
        $typedOk ? 'listAll item mapped to lock + sync cache entries.' : ('Unexpected hydrated data: ' . json_encode($typed))
    );
}

if (class_exists('PorkbunWhmcs\\Registrar\\Operations\\RefreshNameserversOperation')) {
    $nsFromFlat = \PorkbunWhmcs\Registrar\Operations\RefreshNameserversOperation::extractNameservers([
        'status' => 'SUCCESS',
        'ns' => ['NS1.Example.com', ' ns2.example.net '],
    ]);
    $nsFromNested = \PorkbunWhmcs\Registrar\Operations\RefreshNameserversOperation::extractNameservers([
        'domain' => ['nameservers' => ['a.example.test']],
    ]);
    $nsEmpty = \PorkbunWhmcs\Registrar\Operations\RefreshNameserversOperation::extractNameservers(['status' => 'SUCCESS']);

    $nsOk = $nsFromFlat === ['ns1.example.com', 'ns2.example.net']
        && $nsFromNested === ['a.example.test']
        && $nsEmpty === [];

    if (!$nsOk) {
        $failures++;
    }

    addResult(
        $results,
        'Nameserver refresh: getNs response normalization',
        $nsOk,
        $nsOk ? 'Flat and nested getNs payloads normalized correctly.' : ('Unexpected results: ' . json_encode([$nsFromFlat, $nsFromNested, $nsEmpty]))
    );

    $nsPublic = (new \ReflectionMethod(\PorkbunWhmcs\Registrar\Operations\RefreshNameserversOperation::class, 'extractNameservers'))->isPublic();

    if (!$nsPublic) {
        $failures++;
    }

    addResult(
        $results,
        'Nameserver refresh: extraction is directly testable',
        $nsPublic,
        $nsPublic ? 'extractNameservers is public for cache-pipeline verification.' : 'extractNameservers is not public.'
    );
}

if (function_exists('porkbun_cache_admin_output')) {
    $_SESSION['token'] = 'phase7-session-token';
    ob_start();
    porkbun_cache_admin_output([
        'modulelink' => 'addonmodules.php?module=porkbun_cache_admin',
        'version' => defined('PORKBUN_MODULE_VERSION') ? PORKBUN_MODULE_VERSION : '0.1.0',
        'token' => 'phase7-page-token',
    ]);
    $output = (string) ob_get_clean();
    $hasAdminPage = assertContains('Porkbun Cache Admin', $output)
        && assertContains('Generate Cache', $output)
        && assertContains('Clear Cache', $output)
        && assertContains('Process Queue', $output)
        && assertContains('Automatic Queue Processing', $output)
        && assertContains('phase7-page-token', $output)
        && !assertContains('phase7-session-token', $output);

    if (!$hasAdminPage) {
        $failures++;
    }

    addResult(
        $results,
        'porkbun_cache_admin_output page rendering',
        $hasAdminPage,
        $hasAdminPage ? 'Rendered addon admin page with cache controls.' : ('Unexpected addon output: ' . $output)
    );
}

$client = new ApiClient('test-api-key', 'test-secret-key');
$redacted = ApiClient::redactContext([
    'apiKey' => 'test-api-key',
    'secretApiKey' => 'test-secret-key',
    'nested' => [
        'authorization' => 'Bearer token',
        'value' => 'safe',
    ],
]);

$redactionOk =
    (string) ($redacted['apiKey'] ?? '') === '***redacted***'
    && (string) ($redacted['secretApiKey'] ?? '') === '***redacted***'
    && (string) (($redacted['nested']['authorization'] ?? '')) === '***redacted***'
    && (string) (($redacted['nested']['value'] ?? '')) === 'safe';

if (!$redactionOk) {
    $failures++;
}

addResult(
    $results,
    'ApiClient redaction behavior',
    $redactionOk,
    $redactionOk ? 'Sensitive keys were redacted recursively.' : ('Unexpected redaction output: ' . json_encode($redacted))
);

$networkClient = new ApiClient('k', 's', 1, 'https://127.0.0.1:1/api/json/v3');
$networkResponse = $networkClient->request('Phase7NetworkTest', '/ping');
$networkErrorType = (string) (($networkResponse['error']['type'] ?? ''));
$networkDuration = (int) (($networkResponse['context']['durationMs'] ?? 0));
$networkCorrelationId = (string) (($networkResponse['context']['correlationId'] ?? ''));
$networkMessage = (string) (($networkResponse['error']['message'] ?? ''));
$isCurlEnvironmentLimit = $networkErrorType === 'configuration'
    && assertContains('curl extension is required', $networkMessage);
$networkOk = ($networkResponse['success'] ?? true) === false
    && (
        in_array($networkErrorType, ['network', 'http', 'parse', 'api'], true)
        || $isCurlEnvironmentLimit
    )
    && $networkCorrelationId !== ''
    && $networkDuration < 5000;

if (!$networkOk) {
    $failures++;
}

addResult(
    $results,
    'ApiClient timeout/network failure behavior',
    $networkOk,
    $networkOk
        ? ($isCurlEnvironmentLimit
            ? 'Environment lacks cURL; configuration failure path is normalized and fails fast.'
            : 'Request failed fast with normalized error and timing context.')
        : ('Unexpected network response: ' . json_encode($networkResponse))
);

ApiClient::resetMetrics();
$metricClient = new ApiClient('k', 's', 1, 'https://127.0.0.1:1/api/json/v3');
$metricClient->request('Phase9MetricsTest', '/ping');
$metrics = ApiClient::getMetricsSnapshot();
$metricOperation = is_array($metrics['operations']['Phase9MetricsTest'] ?? null)
    ? $metrics['operations']['Phase9MetricsTest']
    : [];

$metricsOk = (int) ($metrics['requestsTotal'] ?? 0) >= 1
    && (int) (($metricOperation['requests'] ?? 0)) >= 1
    && array_key_exists('avgDurationMs', $metricOperation)
    && array_key_exists('failure', $metricOperation);

if (!$metricsOk) {
    $failures++;
}

addResult(
    $results,
    'ApiClient metrics snapshot behavior',
    $metricsOk,
    $metricsOk
        ? 'Metrics counters and per-operation latency fields were updated.'
        : ('Unexpected metrics snapshot: ' . json_encode($metrics))
);

echo "Phase 7 automated checks\n";
echo "========================\n";
foreach ($results as $result) {
    $status = $result['passed'] ? 'PASS' : 'FAIL';
    echo '- [' . $status . '] ' . $result['name'] . '\n';
    if ($result['details'] !== '') {
        echo '  ' . $result['details'] . '\n';
    }
}

echo "\nSummary: " . (count($results) - $failures) . '/' . count($results) . " checks passed.\n";

exit($failures > 0 ? 1 : 0);
