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
- Lock Cache TTL
- Lock Cache Status
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
- Sync maps registry expiry information to WHMCS expirydate with regression guardrails.
- Registrar lock reads are cache-first and hydrate via `/domain/listAll` when cache is stale/missing.

## Lock Cache

- Storage class: [src/LockStatusCache.php](src/LockStatusCache.php)
- Storage table: `mod_porkbun_lock_cache` (auto-created on first use)
- Key: (`account_hash`, `domain`)
- Account partitioning: `account_hash` is a SHA-256 fingerprint of API key + secret from `ApiClient::getCredentialFingerprint()`.
- Cached columns:
	- `lock_enabled` (`0|1`)
	- `fetched_at` (unix timestamp)
	- `expires_at` (unix timestamp)
	- `created_at`, `updated_at`
- Indexes:
	- unique index on (`account_hash`, `domain`)
	- non-unique index on `expires_at`
- Default TTL: 3600 seconds (`Lock Cache TTL` setting overrides per module config; minimum effective TTL is 60 seconds in cache writer)
- Expired-row cleanup: opportunistic cleanup runs during writes (approx. 1% of writes), deleting entries older than one day past `expires_at`.

### Lock Read Flow

1. `porkbun_GetRegistrarLock` passes configured TTL into `GetRegistrarLockOperation::execute`.
2. Operation checks `LockStatusCache::getFresh(account_hash, domain)` first.
3. On cache miss/stale:
	 - call `/domain/listAll` with pagination (`start` increments by 1000)
	 - extract domain + lock status candidates from returned structures
	 - bulk upsert rows via `LockStatusCache::putMany`
4. Re-check cache for requested domain.
5. If still unresolved or listAll fails, fallback to direct `/domain/getLock/{domain}`.
6. Successful direct fallback is written back with `LockStatusCache::put`.

### Lock Write Flow

1. `porkbun_SaveRegistrarLock` passes configured TTL into `SaveRegistrarLockOperation::execute`.
2. After successful `/domain/updateLock/{domain}`, operation performs write-through cache update for the same domain.

### Settings Page Cache Controls

- Implemented in [porkbun.php](porkbun.php) via:
	- `porkbun_renderCacheStatusField()`
	- `porkbun_handleCacheSettingsAction()`
- `Lock Cache Status` field displays:
	- last cache refresh time (UTC)
	- cached record count
- Includes `Clear Cache` button in admin settings context.
- Clear action security:
	- accepts POST only
	- checks admin area context
	- validates WHMCS session token (`token`)
- Clear action behavior:
	- executes `LockStatusCache::clearAll()`
	- displays inline success/error feedback in settings field HTML

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
