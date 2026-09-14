# porkbun-whmcs

WHMCS domain registrar module for Porkbun.

This module integrates WHMCS registrar operations with the Porkbun API for domain lifecycle management.

## Compatibility

- WHMCS: 8.8+
- PHP: 8.1, 8.2, 8.3
- Module type: Domain Registrar Module
- API transport: HTTPS JSON requests to Porkbun API v3

## Supported Features

| WHMCS Registrar Operation | Module Function | Status | Notes |
| --- | --- | --- | --- |
| Config | porkbun_getConfigArray | Supported | API key/secret, timeout, domain cache TTL, refresh cooldown, debug logging |
| Test Connection | porkbun_TestConnection | Supported | Uses Porkbun ping endpoint |
| Register | porkbun_RegisterDomain | Supported | Endpoint mapping implemented |
| Transfer | porkbun_TransferDomain | Supported | Requires transfer auth/EPP code |
| Renew | porkbun_RenewDomain | Supported | Endpoint mapping implemented |
| Sync | porkbun_Sync | Supported | Returns WHMCS `expirydate` plus `active`/`cancelled`/`transferredAway` from shared cache with renewal-date guardrails |
| Admin Custom Sync Command | porkbun_syncnow | Supported | Registrar Commands button that persists synced expiry date and status to WHMCS via `UpdateClientDomain` |
| Get Nameservers | porkbun_GetNameservers | Supported | Cache-first nameserver lookup refreshed per domain from `/domain/getNs/{domain}` |
| Save Nameservers | porkbun_SaveNameservers | Supported | Validates at least one nameserver |
| Get Contact Details | porkbun_GetContactDetails | Supported | Maps API contacts to WHMCS shape |
| Save Contact Details | porkbun_SaveContactDetails | Supported | Maps WHMCS contacts to API payload |
| Get EPP Code | porkbun_GetEPPCode | Supported | TLD-dependent on registry support |
| Get Registrar Lock | porkbun_GetRegistrarLock | Supported | Cache-first lock lookup hydrated from `/domain/listAll` `securityLock` |
| Save Registrar Lock | porkbun_SaveRegistrarLock | Supported | Lock on/off request mapping with cache write-through |
| Get DNS | porkbun_GetDNS | Not supported | Returns explicit limitation error |
| Save DNS | porkbun_SaveDNS | Not supported | Returns explicit limitation error |
| Cache Admin Page | porkbun_cache_admin_output | Supported | Companion addon module admin page for cache status and controls |

## Installation

### Fresh Install

1. Back up WHMCS files and database.
2. Create module directory:
- modules/registrars/porkbun/
3. Copy module files into that directory:
- porkbun.php
- src/
4. In WHMCS admin, go to:
- Configuration > System Settings > Domain Registrars
5. Activate the Porkbun registrar module.
6. Enter API Key and Secret API Key.
7. Configure timeout, domain cache TTL, refresh cooldown, and debug logging as needed.
8. Run Test Connection.
9. Validate register, transfer, renew, and sync in a test environment.
10. Activate the companion addon module `Porkbun Cache Admin` from WHMCS Addon Modules if you want cache status and manual cache controls in the admin area.

### Upgrade

1. Back up current module files and database.
2. Replace files in:
- modules/registrars/porkbun/
3. Re-run Test Connection.
4. Re-validate sync behavior, nameservers, and contacts on a test domain.

Upgrade note: the first cache or queue operation after upgrading automatically migrates `mod_porkbun_domain_refresh_queue` by adding the `domain` column and replacing the legacy (`account_hash`, `data_type`) unique index with (`account_hash`, `data_type`, `domain`). The migration is attempted until it succeeds and is safe to retry; no manual SQL is required. Legacy account-wide nameserver jobs are discarded and re-queued per domain on the next nameserver read.

### Rollback

1. Restore previous module files from backup.
2. Re-test Test Connection.
3. Re-run a sync check and confirm renewal-date behavior.

## Known Limitations

- DNS operations are currently returned as explicitly unsupported.
- If Porkbun returns `DOMAIN_IS_NOT_OPTED_IN_TO_API_ACCESS` for nameserver reads, the module returns a warning and uses existing WHMCS nameserver values instead of hard failing domain access.
- The manual sync command reports a domain missing from `/domain/listAll` as an error and does not automatically mark it `Transferred Away`; this avoids mass status changes if the API key is pointed at a different Porkbun account.
- EPP code availability is TLD and registry policy dependent.
- Registrar lock behavior can vary by TLD policy.
- Reminder and invoice timing alignment must be validated in live WHMCS cron behavior.

## Domain Cache

- Domain reads use a persistent WHMCS DB cache (`mod_porkbun_domain_cache`) keyed by account fingerprint, domain, and data type.
- Current cached data types and sources:
	- `lock` (bool) from `/domain/listAll` `securityLock`
	- `nameservers` (array<string>) from `/domain/getNs/{domain}` (listAll does not return nameservers)
	- `sync` (array<string, mixed>) from `/domain/listAll` `expireDate` and `status`
- Cache default TTL is 3600 seconds and can be changed with module setting `Domain Cache TTL`.
- Stale-while-revalidate behavior:
	- stale entries are returned immediately for non-blocking reads
	- stale/missing reads enqueue refresh work in `mod_porkbun_domain_refresh_queue`
	- queue processing hydrates locks from `/domain/listAll` and nameservers per domain from `/domain/getNs/{domain}`
- Sync reads refresh stale `sync` cache entries from `/domain/listAll` before resolving; the manual admin sync always forces a fresh read.
- Successful save operations (`SaveRegistrarLock`, `SaveNameservers`) perform cache write-through updates.
- Automatic queue processing runs through WHMCS's native `DailyCronJob` hook when the WHMCS system cron executes.

## Cache Admin Page

- Cache status and manual cache controls are exposed through the companion addon module `Porkbun Cache Admin`.
- In WHMCS admin, activate the addon from Setup > Addon Modules, then open it from the Addons menu.
- The page shows cached domain count, cached record count, last cache row update, last full cache hydration, queue counts by status, and the last observed queue processor run.
- `Generate Cache` performs an immediate `/domain/listAll` hydration using the stored registrar credentials and updates the shared cache in place.
- `Clear Cache` removes cached rows but does not change the WHMCS automation cron schedule.
- `Process Queue` runs queued refresh jobs immediately from the admin page.
- `Automatic Queue Processing` confirms that the module hook is registered and explains that execution depends on the WHMCS automation cron.
- `Next Queue Run` is displayed as a WHMCS-controlled schedule notice rather than a guessed timestamp; exact timing depends on the WHMCS automation cron configuration for the installation.

## Admin Sync Button

- A Registrar Commands button named `Sync Expiry and Status` is exposed in the WHMCS domain admin view.
- The command runs a manual sync against Porkbun for domains transferred from another registrar.
- The command always forces a fresh `/domain/listAll` registry read, so it never reuses a previously cached (possibly pre-renewal) expiry date.
- The sync hydrates shared domain cache data from `/domain/listAll` and then resolves the requested domain from cache for expiry and status updates.
- Unlike the WHMCS domain-sync cron, admin commands are not applied automatically by WHMCS, so the module persists the result itself through `localAPI('UpdateClientDomain')` (with a direct database fallback).
- Persisted fields:
	- `expirydate` is written when a valid registry date is resolved.
	- `nextduedate` is written only when the WHMCS `Sync Next Due Date` automation setting is enabled.
	- `status` is written as `Transferred Away` or `Cancelled` when the registry status indicates it, or as `Active` to reactivate a WHMCS domain currently marked `Expired`/`Cancelled`/`Transferred Away`.
- If the WHMCS domain update fails, the command returns a safe error; sync outcomes are always logged regardless of the debug logging setting.

## Documentation

- Development details: [DEVELOPMENT.md](DEVELOPMENT.md)
- Live validation criteria: [TESTING.md](TESTING.md)
- Roadmap and phase tracking: [TODO.md](TODO.md)
- Release history: [CHANGELOG.md](CHANGELOG.md)
- Contributor/agent rules: [AGENTS.md](AGENTS.md)

## Core References

- WHMCS registrar docs: https://developers.whmcs.com/domain-registrars/
- WHMCS sample registrar module: https://github.com/WHMCS/sample-registrar-module
- WHMCS module logging docs: https://developers.whmcs.com/advanced/logging/
- Porkbun API docs: https://porkbun.com/api/json/v3/documentation
- Porkbun knowledge base: https://kb.porkbun.com/

## Versioning

- Releases should use semantic version tags.
- Version history is tracked in [CHANGELOG.md](CHANGELOG.md).

## License

Add your intended license before first public release.
