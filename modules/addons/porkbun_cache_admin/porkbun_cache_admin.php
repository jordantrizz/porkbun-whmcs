<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

$registrarEntrypoints = [
    __DIR__ . '/../../../porkbun.php',
    __DIR__ . '/../../registrars/porkbun/porkbun.php',
];

foreach ($registrarEntrypoints as $registrarEntrypoint) {
    if (is_file($registrarEntrypoint)) {
        require_once $registrarEntrypoint;
        break;
    }
}

if (!function_exists('porkbun_getCacheAdminStatusData')) {
    throw new RuntimeException('Unable to load the Porkbun registrar module entrypoint for the cache admin addon.');
}

/**
 * @return array<string, mixed>
 */
function porkbun_cache_admin_config(): array
{
    return [
        'name' => 'Porkbun Cache Admin',
        'description' => 'Admin status page and controls for Porkbun cache hydration and refresh queue operations.',
        'version' => defined('PORKBUN_MODULE_VERSION') ? PORKBUN_MODULE_VERSION : '0.1.0',
        'author' => 'GitHub Copilot',
        'fields' => [],
    ];
}

/**
 * @return array{status: string, description: string}
 */
function porkbun_cache_admin_activate(): array
{
    return [
        'status' => 'success',
        'description' => 'Porkbun Cache Admin activated successfully.',
    ];
}

/**
 * @return array{status: string, description: string}
 */
function porkbun_cache_admin_deactivate(): array
{
    return [
        'status' => 'success',
        'description' => 'Porkbun Cache Admin deactivated successfully.',
    ];
}

/**
 * @param array<string, mixed> $vars
 */
function porkbun_cache_admin_output(array $vars): void
{
    $message = null;
    $pageToken = isset($vars['token']) ? (string) $vars['token'] : '';

    if (
        isset($_SERVER['REQUEST_METHOD'])
        && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST'
    ) {
        $action = isset($_POST['porkbunCacheAction']) ? trim((string) $_POST['porkbunCacheAction']) : '';
        if (in_array($action, ['generate', 'clear', 'process-queue'], true)) {
            $providedToken = isset($_POST['token']) ? (string) $_POST['token'] : '';
            if (!porkbun_isValidAdminSecurityToken($providedToken, $pageToken)) {
                $message = [
                    'type' => 'error',
                    'text' => 'Cache admin request was rejected due to an invalid security token.',
                ];
            } else {
                $message = porkbun_runCacheAdminAction($action, 'addon-page');
            }
        }
    }

    $moduleLink = isset($vars['modulelink']) ? (string) $vars['modulelink'] : '';
    $moduleVersion = isset($vars['version']) ? (string) $vars['version'] : (defined('PORKBUN_MODULE_VERSION') ? PORKBUN_MODULE_VERSION : '0.1.0');

    echo '<div class="porkbun-cache-admin">';
    echo '<h2>Porkbun Cache Admin</h2>';
    echo '<p style="margin-bottom:16px;color:#4b5563;">Manage shared cache hydration, refresh queue processing, and runtime status for the Porkbun registrar module.</p>';
    echo '<p style="margin-bottom:16px;color:#6b7280;"><strong>Module Version:</strong> ' . htmlspecialchars($moduleVersion, ENT_QUOTES, 'UTF-8') . '</p>';
    echo porkbun_renderCacheAdminPanel($moduleLink, $message, true, $pageToken);
    echo '</div>';
}