# Changelog

All notable changes to this project will be documented in this file.

This changelog is generated from git release history and commit history.

## [Unreleased]

### Added

- Manual admin sync now persists synced expiry date and status to WHMCS through `UpdateClientDomain` (with a direct database fallback).
- Sync outcomes are always written to the module log, even when `Enable Debug Logging` is off.

### Changed

- Sync now returns the WHMCS-recognized `cancelled` and `transferredAway` status flags instead of the ignored `expired` key, and no longer forces `active` for cancelled domains.
- Registrar lock cache hydration now reads Porkbun's `securityLock` field.
- Nameserver cache refresh now fetches `/domain/getNs/{domain}` per domain; the refresh queue is domain-aware and migrates the queue table on upgrade.
- Manual sync honors the WHMCS `Sync Next Due Date` offset (`DomainSyncNextDueDateDays`) when updating `nextduedate`, rather than setting it equal to the expiry date.
- Removed the stale `/domain/listAll` nameserver extraction path.
- Archived TODO roadmap into docs/BUILD.md (commit: bf26908).
- Streamlined README and moved development-heavy content to DEVELOPMENT.md (commit: 0b701df).
- Added dedicated installation method section in README (commit: c6bc59c).
- Added persistent registrar lock cache with `/domain/listAll` hydration and TTL configuration.
- Removed the unsupported direct registrar lock lookup fallback and now return lock state from cache or hydrated domain-list data.
- Replaced sync's unsupported direct domain lookup with `/domain/listAll` pagination for expiry and status sync.
- Sync now hydrates all domains from `/domain/listAll` into shared cache and resolves per-domain sync data from cache.

### Fixed

- Fixed the `Sync Expiry and Status` registrar command, which previously discarded the sync result and never updated the WHMCS domain record.
- Sync no longer resolves from a stale `sync` cache entry: stale entries are refreshed from `/domain/listAll`, and the manual admin sync always forces a fresh registry read (prevents writing a pre-renewal expiry date).

## [0.1.0] - 2026-04-14

### Added

- WHMCS registrar module bootstrap and configuration fields.
- Credential validation flow via module test connection.
- Unified API request helper with normalized error handling.
- Secret redaction utility and structured operation logging.
- Core domain lifecycle operations:
  - Register
  - Transfer
  - Renew
- Domain sync operation with renewal-date safeguards.
- Nameserver get/save operations.
- Contact details get/save operations.
- EPP code retrieval support.
- Registrar lock get/save support.
- Explicit unsupported responses for DNS operations.
- QA automation script and Phase 7 evidence/checklist artifacts.
- Module version constant surfaced in registrar logs.

### Notes

- DNS record management is intentionally marked unsupported in the current module implementation.
- Release commit: c40a23c ("Release 0.1.0").
