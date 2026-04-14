# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project follows Semantic Versioning.

## [0.1.0] - 2026-04-14 (Initial Release Draft)

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

### Notes

- DNS record management is intentionally marked unsupported in the current module implementation.
- Final release tagging and WHMCS runtime regression evidence should be completed before publishing the first production tag.
