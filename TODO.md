# TODO

## Cache vs API Endpoint for GetRegistrarLock
Implemented.

- GetRegistrarLock now resolves lock state from cache or hydrated `/domain/listAll` data.
- The unsupported direct lock lookup path has been removed.
- If Porkbun does not return lock data for the requested domain, the module now returns a safe error instead of a 404-driven parse failure.

## Convert getnameservers to use Cache
In progress.

- GetNameservers now reads from shared domain cache (`nameservers` type).
- Stale cache entries are returned immediately and trigger queued background refresh.
- Cache misses now queue refresh and return existing WHMCS values with a warning.

## Cache

In progress.

- Added shared multi-purpose cache storage class: `src/DomainCache.php`.
- Added refresh queue class for non-blocking stale refresh scheduling: `src/DomainRefreshQueue.php`.
- Added shared listAll hydrator operation: `src/Operations/HydrateDomainCacheFromListAllOperation.php`.
- Registrar lock reads/writes migrated to shared cache (`lock` type).
- Nameserver saves now write-through to shared cache (`nameservers` type).
- Added manual admin command button to process queued refresh jobs.
- Pending: wire automatic daily cron hook for queue processing and expand QA checks for stale/miss queue behavior.

## Cronjob for Domain Sync
* Add a cronjob that runs daily to sync domain expiry date and domain status with Porkbun and updates the WHMCS record. This is for domain names that have been transferred to Porkbun from another registrar.