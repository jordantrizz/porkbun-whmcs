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
