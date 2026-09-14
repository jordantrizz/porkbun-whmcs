# DEVELOPMENT

Technical implementation notes and development workflow for the WHMCS Porkbun registrar module.

## Architecture

- Namespace: PorkbunWhmcs\\Registrar
- Entry point: [porkbun.php](porkbun.php)
- Internal logic: [src](src)
- Operation handlers: [src/Operations](src/Operations)

## Expected Module Structure

- [porkbun.php](porkbun.php)
- [src/ApiClient.php](src/ApiClient.php)
- [src/Mapper.php](src/Mapper.php)
- [src/Errors.php](src/Errors.php)
- [src/Operations](src/Operations)
- [modules/addons/porkbun_cache_admin/porkbun_cache_admin.php](modules/addons/porkbun_cache_admin/porkbun_cache_admin.php)
- [tests](tests)

## WHMCS Function Surface

Primary exported functions:

- porkbun_getConfigArray
- porkbun_TestConnection
- porkbun_RegisterDomain
- porkbun_TransferDomain
- porkbun_RenewDomain
- porkbun_Sync
- porkbun_AdminCustomButtonArray
- porkbun_syncnow
- porkbun_GetNameservers
- porkbun_SaveNameservers
- porkbun_GetContactDetails
- porkbun_SaveContactDetails
- porkbun_GetEPPCode
- porkbun_GetRegistrarLock
- porkbun_SaveRegistrarLock
- porkbun_GetDNS
- porkbun_SaveDNS

## Configuration Fields

Current config fields in module settings:

- API Key
- Secret API Key
- Request Timeout
- Domain Cache TTL
- Refresh Queue Cooldown
- Enable Debug Logging

Guidance:

- Validate required credentials before any API request.
- Return safe, admin-readable error responses.
- Never include secrets in user-facing errors or logs.

## API Integration Notes

- Centralized request handling in [src/ApiClient.php](src/ApiClient.php).
- TLS-only request policy.
- Normalized error categories for configuration/network/http/api/parse failures.
- Context includes operation, endpoint, status, latency, and correlation ID.

## Mapping Notes

- Domain input normalized to lowercase.
- Nameserver output mapped to WHMCS ns1..ns5 format.
- Contact objects mapped between WHMCS contact shape and Porkbun payload fields.
- Sync maps registry expiry information from shared domain cache hydrated via `/domain/listAll` to WHMCS `expirydate` with regression guardrails.
- Sync refreshes the `sync` cache from `/domain/listAll` when the entry is missing or stale, and accepts a `forceRefresh` flag that always bypasses cache; the manual admin sync passes `forceRefresh = true`.
- Sync returns WHMCS-recognized `active`, `cancelled` and `transferredAway` flags (not `expired`) and maps registry `status` accordingly.
- The manual sync command persists results via `localAPI('UpdateClientDomain')`, writing `expirydate`, optional `nextduedate`, and a mapped status.
- Registrar lock reads are cache-first and hydrated from `/domain/listAll` `securityLock`; nameserver reads are cache-first and refreshed per domain from `/domain/getNs/{domain}`.

## Domain Cache

- Storage class: [src/DomainCache.php](src/DomainCache.php)
- Storage table: `mod_porkbun_domain_cache` (auto-created on first use)
- Key: (`account_hash`, `domain`, `data_type`)
- Queue class: [src/DomainRefreshQueue.php](src/DomainRefreshQueue.php)
- Queue table: `mod_porkbun_domain_refresh_queue`
- Queue key: (`account_hash`, `data_type`, `domain`) after the domain-aware migration; lock jobs use an empty `domain`.
- Module state table: `mod_porkbun_module_state`
- Account partitioning: `account_hash` is a SHA-256 fingerprint of API key + secret from `ApiClient::getCredentialFingerprint()`.
- Cached data types in current implementation:
	- `lock` (bool) from `/domain/listAll` `securityLock`
	- `nameservers` (array<string>) from `/domain/getNs/{domain}`
	- `sync` (array<string, mixed>) from `/domain/listAll` `expireDate` and `status`
- Freshness columns:
	- `fetched_at` (unix timestamp)
	- `stale_at` (unix timestamp)
	- `expires_at` (unix timestamp)
	- `created_at`, `updated_at`
- Default TTL: 3600 seconds (`Domain Cache TTL` setting overrides per module config; minimum effective TTL is 60 seconds in writer)
- Expired-row cleanup: opportunistic cleanup runs during writes (approx. 1% of writes), deleting entries older than one day past `expires_at`.

### Queue Table Migration

- `mod_porkbun_domain_refresh_queue` gained a `domain` column and the unique key changed from (`account_hash`, `data_type`) to (`account_hash`, `data_type`, `domain`) so multiple domains can queue per account/type.
- Migration runs lazily inside `DomainRefreshQueue::ensureTable()` on the first queue operation after upgrade (add column, drop legacy unique index, add new index). Each step is guarded and the routine retries on later requests until it succeeds; no manual SQL is required.
- Fresh installs create the final schema directly.
- Legacy rows created before the migration have an empty `domain`. Domain-less `nameservers` jobs cannot be satisfied from `/domain/listAll` and are discarded; the next `GetNameservers` read re-queues a per-domain job.

### Cache Read Flow

1. `porkbun_GetRegistrarLock` and `porkbun_GetNameservers` check `DomainCache` first.
2. Fresh cache entries return immediately.
3. Stale cache entries return immediately and enqueue non-blocking refresh jobs (lock = account-wide, nameservers = per domain).
4. Cache misses enqueue non-blocking refresh jobs and return safe operation responses.

### Refresh Queue Flow

1. Queue requests are deduplicated by (`account_hash`, `data_type`, `domain`) with cooldown window.
2. Queue processor function `porkbun_ProcessDomainCacheRefreshQueue` claims jobs and processes them by type.
3. `lock` jobs run the shared hydrator [src/Operations/HydrateDomainCacheFromListAllOperation.php](src/Operations/HydrateDomainCacheFromListAllOperation.php), which fetches `/domain/listAll` and refreshes lock and sync cache entries.
4. `nameservers` jobs run [src/Operations/RefreshNameserversOperation.php](src/Operations/RefreshNameserversOperation.php), which fetches `/domain/getNs/{domain}` and writes that domain's nameserver cache entry.
5. Successful jobs are removed; failures are retried with backoff and eventually marked failed.
6. Automatic processing is registered through WHMCS native `DailyCronJob` hook in [porkbun.php](porkbun.php).
7. Cron credential resolution reads the module's configured settings from `tblregistrars` and matches queued jobs by `ApiClient::getCredentialFingerprint()`.
8. If WHMCS exposes encrypted password values at rest, the resolver attempts `decrypt()` when available for `secretApiKey`.
9. Successful listAll hydrations record module-owned runtime state so the settings page can show the last full cache hydration and the last observed queue processor run.

### Addon Admin Page Cache Controls

- Implemented through the companion addon module entry point in [modules/addons/porkbun_cache_admin/porkbun_cache_admin.php](modules/addons/porkbun_cache_admin/porkbun_cache_admin.php).
- Shared cache-admin helpers remain in [porkbun.php](porkbun.php) so both the registrar module and addon module use the same cache and queue behavior.
- The addon page displays:
	- cached domain count
	- cached record count
	- last cache row update time (UTC)
	- last full cache hydration time/source/result
	- queue counts by status (pending, processing, failed)
	- last queue processor run/source/result
	- WHMCS-controlled next-run notice
- The addon page exposes `Generate Cache`, `Clear Cache`, and `Process Queue` actions.
- Admin-page action security:
	- accepts POST only
	- validates WHMCS session token (`token`)
	- uses shared helpers for `generate`, `clear`, and `process-queue`
- Generate action behavior:
	- reads stored Porkbun registrar settings from `tblregistrars`
	- runs `HydrateDomainCacheFromListAllOperation::execute()` synchronously
	- records last hydration metadata in `mod_porkbun_module_state`
- Queue processing behavior:
	- uses the same `porkbun_runDomainCacheRefreshQueue()` path as cron and registrar admin commands

### Operational Notes

- If WHMCS DB/Capsule is unavailable, cache methods fail safely and lock operations continue via API path.
- `GetRegistrarLock` may use endpoint `/domain/listAll` in logs even when serving a cache hit, because listAll is the primary cache hydration source.
- The cache table stores no raw credentials or secrets.

## Security Requirements

- Keep API credentials only in WHMCS registrar configuration.
- Redact API key/secret and sensitive fields in logs.
- Require TLS for all API requests.
- Avoid exposing sensitive internals in operation errors.

## Logging and Diagnostics

- Logging path uses sanitized payloads.
- Debug logs include module version metadata.
- Sync outcomes (automatic `Sync` and manual `ManualSyncNow`) are always written to the module log, even when `Enable Debug Logging` is off; other operations remain gated by the debug setting.
- Request correlation IDs are generated per API call.
- In-memory request metrics track success/failure and average latency per operation.

## Development Workflow

1. Start from a clean branch.
2. Implement operation logic in [src/Operations](src/Operations) first.
3. Wire operation into [porkbun.php](porkbun.php).
4. Run local syntax and QA checks.
5. Update docs and TODO status.
6. Commit with focused conventional commit message.

## Local Validation Commands

Syntax checks:

- php -l porkbun.php
- find src -name "*.php" -print0 | xargs -0 -n1 php -l

Optional static checks:

- phpcs --standard=PSR12 porkbun.php src
- phpstan analyse porkbun.php src

QA harness:

- php tests/qa/run_phase7_checks.php

## Testing Guidance

- Live-only validation criteria are tracked in [TESTING.md](TESTING.md).
- Manual regression checklist is in [tests/qa/PHASE7_MANUAL_REGRESSION_CHECKLIST.md](tests/qa/PHASE7_MANUAL_REGRESSION_CHECKLIST.md).
- Automated evidence is tracked in [tests/evidence/phase7-evidence.md](tests/evidence/phase7-evidence.md).

## Release Workflow

1. Confirm README, DEVELOPMENT, TESTING, TODO, and CHANGELOG are current.
2. Verify module behavior in WHMCS runtime.
3. Record release notes in [CHANGELOG.md](CHANGELOG.md).
4. Tag release using semantic versioning.
