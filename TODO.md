# TODO

Development roadmap for the WHMCS Porkbun registrar module.

Top priority note:

- [ ] Treat renewal date syncing and reminder integrity as core WHMCS behavior for MVP.

## Phase 0 - Foundation and Repo Setup

- [x] Confirm target WHMCS versions and PHP compatibility matrix.
- [x] Decide module namespace and file layout (flat vs src-based handlers).
- [x] Add base files: porkbun.php, src/ApiClient.php, src/Errors.php, src/Mapper.php.
- [x] Define coding standards and linting approach (PHPCS/PHPStan optional).
- [x] Document local development workflow in README.

Exit criteria:

- [x] Repository has agreed structure and contributor workflow.
- [x] Team can run local validation checks consistently.

## Phase 1 - WHMCS Module Bootstrap

- [x] Implement porkbun_getConfigArray with required settings:
- [x] API Key
- [x] Secret API Key
- [x] Timeout
- [x] Debug logging toggle
- [x] Add shared parameter validation helper.
- [x] Add shared success/error response helpers for WHMCS return shape.
- [ ] Verify module appears and saves settings in WHMCS admin.
- [x] Add admin UI credential test path (porkbun_TestConnection) to validate API key pair.

Exit criteria:

- [ ] Module activates and stores config without errors.
- [x] Missing credentials produce clear admin-safe errors.

## Phase 2 - API Client and Error Normalization

- [x] Implement single request helper for all Porkbun calls.
- [x] Enforce TLS, timeout, and JSON request/response handling.
- [x] Normalize network, HTTP, and API-level failures to one internal error format.
- [x] Add redaction utility for secrets in logs.
- [x] Add structured logging context (operation, domain, duration, status).

Exit criteria:

- [x] Every API call path uses one client helper.
- [x] Logs are useful and do not leak credentials.

## Phase 3 - Core Domain Lifecycle Operations

- [x] Implement porkbun_RegisterDomain.
- [x] Implement porkbun_TransferDomain.
- [x] Implement porkbun_RenewDomain.
- [x] Map WHMCS params to Porkbun request schema.
- [x] Normalize operation responses to WHMCS-compatible arrays.

Exit criteria:

- [ ] Register, transfer, and renew complete successfully in development environment.
- [ ] Failure modes return stable, safe, actionable errors.

## Phase 4 - Renewal Date Sync and Reminder Integrity (Core)

- [x] Implement porkbun_Sync (or WHMCS-supported equivalent sync function) for domain status + expiry date synchronization.
- [x] Ensure synced expiry/next renewal dates update WHMCS domain records correctly.
- [ ] Validate behavior with WHMCS automation/cron so reminder and renewal invoice timing uses synced dates.
- [x] Handle Porkbun/WHMCS date format conversions and timezone normalization safely.
- [x] Add guardrails for stale or missing registry dates (safe fallback + clear logs).
- [x] Add explicit sanitized logs for sync source date, previous WHMCS date, and resulting updated date.

Exit criteria:

- [ ] WHMCS reminders align with the synced renewal date.
- [ ] Renewal invoice timing is correct after sync runs.
- [x] No destructive date regressions in repeated sync runs.

## Phase 5 - Nameserver and Contact Management

- [x] Implement porkbun_GetNameservers.
- [x] Implement porkbun_SaveNameservers.
- [x] Implement porkbun_GetContactDetails.
- [x] Implement porkbun_SaveContactDetails.
- [x] Validate field mapping and optional value behavior.

Exit criteria:

- [ ] Nameserver read/write round-trips correctly.
- [ ] Contact read/write round-trips correctly.

## Phase 6 - Security, EPP, Lock, and DNS Operations

- [x] Implement porkbun_GetEPPCode (if supported for TLD).
- [x] Implement porkbun_GetRegistrarLock.
- [x] Implement porkbun_SaveRegistrarLock.
- [x] Implement porkbun_GetDNS.
- [x] Implement porkbun_SaveDNS.
- [x] For unsupported operations/TLD behavior, return clear limitation errors.

Exit criteria:

- [ ] Supported advanced operations function correctly.
- [ ] Unsupported operations are explicit and documented.

## Phase 7 - Testing and QA Hardening

- [x] Build operation-level test checklist and evidence log.
- [x] Validate invalid credentials behavior across all operations.
- [x] Validate timeout/network failures and retry strategy.
- [x] Validate data sanitation and logging redaction.
- [ ] Run regression suite:
- [ ] Register domain
- [ ] Transfer domain
- [ ] Renew domain
- [ ] Sync renewal/expiry date
- [ ] Validate reminder and invoice timing after sync
- [ ] Nameserver read/write

Exit criteria:

- [ ] Critical regression suite passes.
- [ ] No sensitive values observed in logs.

## Phase 8 - Documentation and Release Readiness

- [x] Update README supported-feature matrix.
- [x] Document known limitations by operation/TLD.
- [x] Add changelog/release notes for initial release.
- [ ] Tag release version.
- [x] Verify deployment instructions for modules/registrars/porkbun/.

Exit criteria:

- [x] Documentation matches module behavior.
- [ ] Initial release is reproducible and supportable.

## Optional Phase 9 - Post-Release Improvements

- [x] Add richer diagnostics with correlation IDs.
- [x] Add automated test harness where feasible.
- [x] Add metrics around operation latency and failure rates.
- [ ] Improve compatibility matrix based on production feedback.
