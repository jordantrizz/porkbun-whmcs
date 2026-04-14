# porkbun-whmcs

WHMCS domain registrar module for Porkbun.

This project implements a WHMCS Registrar Module that allows WHMCS to register, transfer, renew, manage nameservers, and manage contact details for domains through the Porkbun API.

## Supported Feature Matrix

| WHMCS Registrar Operation | Module Function | Current Status | Notes |
| --- | --- | --- | --- |
| Config | porkbun_getConfigArray | Supported | API key/secret, timeout, debug logging |
| Test Connection | porkbun_TestConnection | Supported | Uses Porkbun ping endpoint |
| Register | porkbun_RegisterDomain | Supported | Endpoint mapping implemented |
| Transfer | porkbun_TransferDomain | Supported | Requires transfer auth/EPP code |
| Renew | porkbun_RenewDomain | Supported | Endpoint mapping implemented |
| Sync | porkbun_Sync | Supported | Includes renewal-date guardrails |
| Get Nameservers | porkbun_GetNameservers | Supported | Maps nameserver list to ns1..ns5 |
| Save Nameservers | porkbun_SaveNameservers | Supported | Validates at least one nameserver |
| Get Contact Details | porkbun_GetContactDetails | Supported | Maps API contacts to WHMCS shape |
| Save Contact Details | porkbun_SaveContactDetails | Supported | Maps WHMCS contacts to API payload |
| Get EPP Code | porkbun_GetEPPCode | Supported | TLD-dependent on registry support |
| Get Registrar Lock | porkbun_GetRegistrarLock | Supported | Normalized lock-state handling |
| Save Registrar Lock | porkbun_SaveRegistrarLock | Supported | Lock on/off request mapping |
| Get DNS | porkbun_GetDNS | Not supported in module | Returns explicit limitation error |
| Save DNS | porkbun_SaveDNS | Not supported in module | Returns explicit limitation error |

## Compatibility Matrix

- WHMCS: 8.8+
- PHP: 8.1, 8.2, 8.3
- Module type: Domain Registrar Module
- API transport: HTTPS JSON requests to Porkbun API v3

Notes:

- Versions above are the current project target baseline for development and testing.
- If a production environment uses older PHP/WHMCS, compatibility must be validated before rollout.

## Goals

- Implement a production-ready WHMCS registrar integration for Porkbun.
- Follow WHMCS registrar module conventions and expected function signatures.
- Keep credentials and API operations secure, auditable, and easy to troubleshoot.
- Minimize behavioral surprises for WHMCS admins and customers.

## Scope

In scope:

- Registrar module implementation under WHMCS registrar module standards.
- Porkbun API authentication and domain lifecycle operations.
- Logging, error normalization, and operational guidance.
- Developer and agent documentation for consistent implementation.

Out of scope for initial milestone:

- WHMCS provisioning/addon module features unrelated to registrar actions.
- Multi-registrar abstraction framework.
- Billing customization unrelated to registrar module behavior.

## WHMCS Registrar Module Basics

Primary reference:

- WHMCS registrar module docs: https://developers.whmcs.com/domain-registrars/

Sample implementation reference:

- WHMCS sample registrar module: https://github.com/WHMCS/sample-registrar-module

Typical registrar module path inside a WHMCS install:

- modules/registrars/porkbun/porkbun.php

Common function entry points to implement (names per WHMCS standard):

- porkbun_getConfigArray
- porkbun_RegisterDomain
- porkbun_TransferDomain
- porkbun_RenewDomain
- porkbun_GetNameservers
- porkbun_SaveNameservers
- porkbun_GetContactDetails
- porkbun_SaveContactDetails
- porkbun_GetEPPCode
- porkbun_GetRegistrarLock
- porkbun_SaveRegistrarLock
- porkbun_GetDNS
- porkbun_SaveDNS
- porkbun_IDProtectToggle (if supported by Porkbun API + TLD policy)

Return contract reminders:

- Success usually returns array("success" => true) or documented WHMCS equivalent.
- Failures should return array("error" => "Human-readable message") and avoid leaking secrets.

## Porkbun API Resources

Primary API reference:

- Porkbun API docs: https://porkbun.com/api/json/v3/documentation

Porkbun platform resources:

- Main site: https://porkbun.com/
- Support: https://kb.porkbun.com/
- API status and behavior should be validated against current docs before release.

Authentication model (verify current docs):

- API Key and Secret API Key are required.
- Requests include credentials in request body for JSON endpoints.
- Credentials must be stored in WHMCS registrar module settings and never logged.

## Suggested Project Structure

Repository currently includes documentation only. As implementation starts, use:

- /README.md
- /AGENTS.md
- /TODO.md
- /porkbun.php (module entrypoint while developing in repo root)
- /src/ApiClient.php
- /src/Mapper.php
- /src/Errors.php
- /src/Operations/RegisterDomain.php
- /src/Operations/TransferDomain.php
- /src/Operations/RenewDomain.php
- /src/Operations/Nameservers.php
- /src/Operations/Contacts.php
- /src/Operations/DomainLock.php
- /src/Operations/Dns.php
- /tests/ (if test harness added)

When deploying to WHMCS:

- Copy module files into modules/registrars/porkbun/

## Phase 0 Architecture Decision

- Namespace: PorkbunWhmcs\\Registrar
- File layout: thin WHMCS entrypoint in porkbun.php with src-based internal classes
- Immediate base classes:
- ApiClient: auth payload + request transport helper foundation
- Mapper: normalize domain and date mapping logic
- Errors: standardized WHMCS-safe error array generation

## Configuration Fields (WHMCS Admin)

Recommended registrar config fields in porkbun_getConfigArray:

- API Key
- Secret API Key
- Use Sandbox (if Porkbun offers separate safe testing behavior)
- Request Timeout (seconds)
- Enable Debug Logging (non-sensitive)

Guidance:

- Keep labels admin-friendly.
- Validate required fields before making API calls.
- Fail fast with clear error text when credentials are missing.

Admin validation flow:

- Use module test connection support in WHMCS admin to validate API Key and Secret API Key.
- The module performs a Porkbun API ping request and reports a success/failure result.

## Known Limitations

- DNS operations in this module currently return an explicit unsupported-operation response.
- EPP code availability is TLD and policy dependent; some domains may not return a code.
- Registrar lock behavior can vary by TLD policy and registry constraints.
- End-to-end reminder/invoice timing alignment requires WHMCS cron validation in a live test environment.
- Live register/transfer/renew success depends on valid account state, domain eligibility, and Porkbun-side policy checks.

## Implementation Notes

Domain formatting:

- Normalize domain input to lowercase.
- Handle IDN/punycode conversion if needed (based on WHMCS provided values and Porkbun support).

Contact handling:

- Map WHMCS contact arrays to Porkbun expected fields exactly.
- Preserve optional fields only when present.

Nameserver handling:

- Respect WHMCS expected ns1..ns5 shape.
- Convert empty values safely and avoid sending invalid hostname strings.

Error handling:

- Centralize Porkbun API error translation to WHMCS-friendly messages.
- Include request correlation data in logs where possible.
- Never include API secrets in log output.

Idempotency and retries:

- Add conservative retry logic only for safe transient failures.
- Avoid automatic retries for actions that can create side effects unless API guarantees idempotency.

## Security Requirements

- Do not store API credentials outside WHMCS encrypted configuration fields.
- Redact secrets and auth payloads from module logs.
- Use TLS for all API requests.
- Validate and sanitize all outbound fields.
- Escape all user-facing output in admin/client templates if introduced later.

## Logging and Troubleshooting

WHMCS utility references:

- Module logging docs: https://developers.whmcs.com/advanced/logging/

Best practices:

- Log operation name, domain, elapsed time, and sanitized API status.
- Provide a single structured error message returned to WHMCS.
- Keep debug mode optional and safe by default.

Diagnostics implemented:

- Each API request carries a generated correlation ID in request context.
- API request context includes operation, endpoint, status code, latency, and correlation ID.
- In-memory request metrics track success/failure counts and average latency per operation.

## Development Workflow

1. Confirm PHP and WHMCS versions match the compatibility matrix above.
2. Build against the WHMCS sample registrar pattern and current WHMCS docs.
3. Implement one operation at a time with consistent API client helpers.
4. Validate each operation in a development WHMCS instance.
5. Add regression checks for critical flows (register, transfer, renew, sync, nameservers).
6. Prepare release notes with supported feature matrix and known gaps.

## Coding Standards and Validation

Coding standards:

- Follow PSR-12 formatting and naming conventions for PHP code.
- Keep WHMCS exported functions minimal and delegate logic into src/ classes.
- Keep all secrets out of logs and user-visible error messages.

Optional static analysis and style tools:

- PHPCS with PSR-12 ruleset.
- PHPStan at an initial practical level (for example level 5+).

Baseline local validation commands:

- php -l porkbun.php
- find src -name "*.php" -print0 | xargs -0 -n1 php -l

If PHPCS is installed:

- phpcs --standard=PSR12 porkbun.php src

If PHPStan is installed:

- phpstan analyse porkbun.php src

## Local Development Workflow

1. Start from a clean branch and update docs/TODO task status before coding.
2. Implement change in src/ first, then wire through porkbun.php exported function.
3. Run baseline local validation commands.
4. Test the target operation inside a WHMCS development instance.
5. Capture edge cases and limitations in README before committing.

## Testing Checklist

- Module activates in WHMCS without warnings.
- Credential validation fails clearly when invalid/missing.
- Domain register succeeds and reports success to WHMCS.
- Domain transfer handles EPP/auth code path correctly.
- Renewal works for supported TLDs.
- Nameserver get/save round-trips expected values.
- Contact get/save round-trips expected values.
- Registrar lock get/save behaves correctly when supported.
- API failures produce friendly WHMCS errors and safe logs.

## Versioning and Release

- Use semantic versioning tags when possible.
- Maintain a changelog section in release notes.
- Document supported WHMCS versions and tested Porkbun API behavior.

Release artifacts in this repository:

- CHANGELOG.md (release notes and version history)

## Deployment Instructions

1. Prepare target folder in WHMCS:
- modules/registrars/porkbun/
2. Copy module files into that folder:
- porkbun.php
- src/ (all PHP files and operation handlers)
3. In WHMCS admin, enable the Porkbun registrar module.
4. Enter API Key and Secret API Key.
5. Set timeout and optionally enable debug logging.
6. Run the module Test Connection action.
7. Run a domain sync in development and verify renewal date updates.

## Initial Release Notes (Draft)

See CHANGELOG.md for the initial release entry.

Highlights for initial release:

- Core lifecycle operations: register, transfer, renew.
- Renewal date sync with guardrails for reminder/invoice integrity.
- Nameserver and contact read/write support.
- EPP and registrar-lock support.
- Explicit unsupported behavior for DNS operations.
- Structured sanitized logging and credential redaction.

## Quick Start (Current Repo)

1. Read AGENTS.md for implementation rules and coding workflow.
2. Scaffold the registrar module functions in porkbun.php.
3. Add a minimal API client with auth and request helper.
4. Implement RegisterDomain first, then TransferDomain and RenewDomain.

## Useful Links

- WHMCS Domain Registrar Modules: https://developers.whmcs.com/domain-registrars/
- WHMCS Sample Registrar Module: https://github.com/WHMCS/sample-registrar-module
- WHMCS Logging: https://developers.whmcs.com/advanced/logging/
- Porkbun API Docs: https://porkbun.com/api/json/v3/documentation
- Porkbun Knowledge Base: https://kb.porkbun.com/

## License

Add your intended license here (for example, MIT) before first public release.
