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
| Config | porkbun_getConfigArray | Supported | API key/secret, timeout, lock cache TTL, debug logging |
| Test Connection | porkbun_TestConnection | Supported | Uses Porkbun ping endpoint |
| Register | porkbun_RegisterDomain | Supported | Endpoint mapping implemented |
| Transfer | porkbun_TransferDomain | Supported | Requires transfer auth/EPP code |
| Renew | porkbun_RenewDomain | Supported | Endpoint mapping implemented |
| Sync | porkbun_Sync | Supported | Includes renewal-date guardrails |
| Admin Custom Sync Command | porkbun_syncnow | Supported | Registrar Commands button to manually sync expiry date and domain status |
| Get Nameservers | porkbun_GetNameservers | Supported | Maps nameserver list to ns1..ns5 |
| Save Nameservers | porkbun_SaveNameservers | Supported | Validates at least one nameserver |
| Get Contact Details | porkbun_GetContactDetails | Supported | Maps API contacts to WHMCS shape |
| Save Contact Details | porkbun_SaveContactDetails | Supported | Maps WHMCS contacts to API payload |
| Get EPP Code | porkbun_GetEPPCode | Supported | TLD-dependent on registry support |
| Get Registrar Lock | porkbun_GetRegistrarLock | Supported | Cache-first lock lookup with listAll hydration |
| Save Registrar Lock | porkbun_SaveRegistrarLock | Supported | Lock on/off request mapping with cache write-through |
| Get DNS | porkbun_GetDNS | Not supported | Returns explicit limitation error |
| Save DNS | porkbun_SaveDNS | Not supported | Returns explicit limitation error |

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
7. Configure timeout, lock cache TTL, and debug logging as needed.
8. Run Test Connection.
9. Validate register, transfer, renew, and sync in a test environment.

### Upgrade

1. Back up current module files and database.
2. Replace files in:
- modules/registrars/porkbun/
3. Re-run Test Connection.
4. Re-validate sync behavior, nameservers, and contacts on a test domain.

### Rollback

1. Restore previous module files from backup.
2. Re-test Test Connection.
3. Re-run a sync check and confirm renewal-date behavior.

## Known Limitations

- DNS operations are currently returned as explicitly unsupported.
- If Porkbun returns `DOMAIN_IS_NOT_OPTED_IN_TO_API_ACCESS` for nameserver reads, the module returns a warning and uses existing WHMCS nameserver values instead of hard failing domain access.
- EPP code availability is TLD and registry policy dependent.
- Registrar lock behavior can vary by TLD policy.
- Reminder and invoice timing alignment must be validated in live WHMCS cron behavior.

## Lock Status Caching

- Lock reads use a persistent WHMCS DB cache (`mod_porkbun_lock_cache`) keyed by domain and credential fingerprint.
- Cache default TTL is 3600 seconds and can be changed with module setting `Lock Cache TTL`.
- On cache miss or stale data, the module hydrates lock states via `/domain/listAll` and writes results back to cache.
- Successful lock updates (`SaveRegistrarLock`) immediately update cache to reduce stale reads.
- If listAll hydration fails, the module falls back to direct `/domain/getLock/{domain}` lookup.

## Admin Sync Button

- A Registrar Commands button named `Sync Expiry and Status` is exposed in the WHMCS domain admin view.
- The command runs a manual sync against Porkbun for domains transferred from another registrar.
- The sync updates both expiry date and domain status fields in the WHMCS sync response.

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
