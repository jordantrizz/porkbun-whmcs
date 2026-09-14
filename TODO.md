# TODO

## Cache vs API Endpoint for GetRegistrarLock
Implemented.

- GetRegistrarLock now resolves lock state from cache or hydrated `/domain/listAll` data.
- The unsupported direct lock lookup path has been removed.
- If Porkbun does not return lock data for the requested domain, the module now returns a safe error instead of a 404-driven parse failure.

## Convert getnameservers to use Cache
Implemented.

- GetNameservers reads from shared domain cache (`nameservers` type).
- Stale cache entries are returned immediately and trigger queued background refresh.
- Cache misses queue refresh and return existing WHMCS values with a warning.
- Refresh is per domain via `/domain/getNs/{domain}` because `/domain/listAll` does not return nameservers.

## Cache

Implemented.

- Shared multi-purpose cache storage class: `src/DomainCache.php`.
- Refresh queue class for non-blocking stale refresh scheduling: `src/DomainRefreshQueue.php`.
- Shared listAll hydrator operation: `src/Operations/HydrateDomainCacheFromListAllOperation.php`.
- Per-domain nameserver refresh operation: `src/Operations/RefreshNameserversOperation.php`.
- Lock reads/writes migrated to shared cache (`lock` type, hydrated from `securityLock`).
- Nameserver saves write-through to shared cache (`nameservers` type).
- Refresh queue is domain-aware and migrates the queue table on upgrade.
- Manual admin command button to process queued refresh jobs.
- Automatic daily cron hook for queue processing is registered.

## Cronjob for Domain Sync
* WHMCS's native domain-sync cron calls `porkbun_Sync` for expiry/status propagation. This module additionally registers a `DailyCronJob` hook to process the cache refresh queue. A module-owned standalone full domain sync cron is not implemented; rely on WHMCS automation settings for domain sync scheduling.