# Changelog

All notable changes to this project will be documented in this file.

This changelog is generated from git release history and commit history.

## [Unreleased]

### Changed

- Archived TODO roadmap into docs/BUILD.md (commit: bf26908).
- Streamlined README and moved development-heavy content to DEVELOPMENT.md (commit: 0b701df).
- Added dedicated installation method section in README (commit: c6bc59c).
- Added persistent registrar lock cache with `/domain/listAll` hydration and TTL configuration.
- Removed the unsupported direct registrar lock lookup fallback and now return lock state from cache or hydrated domain-list data.
- Replaced sync's unsupported direct domain lookup with `/domain/listAll` pagination for expiry and status sync.
- Sync now hydrates all domains from `/domain/listAll` into shared cache and resolves per-domain sync data from cache.

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
