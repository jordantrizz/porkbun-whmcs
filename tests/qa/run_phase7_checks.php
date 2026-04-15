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

if (function_exists('porkbun_cache_admin_output')) {
    $_SESSION['token'] = 'phase7-token';
    ob_start();
    porkbun_cache_admin_output([
        'modulelink' => 'addonmodules.php?module=porkbun_cache_admin',
        'version' => defined('PORKBUN_MODULE_VERSION') ? PORKBUN_MODULE_VERSION : '0.1.0',
    ]);
    $output = (string) ob_get_clean();
    $hasAdminPage = assertContains('Porkbun Cache Admin', $output)
        && assertContains('Generate Cache', $output)
        && assertContains('Clear Cache', $output)
        && assertContains('Process Queue', $output)
        && assertContains('Automatic Queue Processing', $output);

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
