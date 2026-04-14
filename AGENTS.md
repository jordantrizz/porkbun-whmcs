# AGENTS.md
Agent and contributor operating guide for this repository.

This project is a WHMCS Domain Registrar Module targeting Porkbun. Follow this document to keep implementation consistent, secure, and release-ready.


## Development
* Always create a git commit message for each change made, use the format feat, fix, docs, style, refactor, perf, test, chore.
* Use Git Kraken to git commit and push changes to the repository, avoid using the command line for git operations.

## Mission

- Build a reliable WHMCS registrar module for Porkbun API.
- Prioritize correctness, security, and predictable WHMCS behavior.
- Keep changes small, reviewable, and well documented.

## Critical Business Priority

- Renewal date syncing is a core requirement, not optional polish.
- Sync accuracy directly impacts WHMCS renewal reminders, invoicing timing, and client trust.
- Any feature work that risks renewal-date integrity must be blocked until resolved.

## Core References

- WHMCS registrar modules: https://developers.whmcs.com/domain-registrars/
- WHMCS sample registrar module: https://github.com/WHMCS/sample-registrar-module
- WHMCS logging docs: https://developers.whmcs.com/advanced/logging/
- Porkbun API docs: https://porkbun.com/api/json/v3/documentation
- Porkbun knowledge base: https://kb.porkbun.com/

## Repository Expectations

Current repository starts documentation-first.

As code is added, keep this structure:

- README.md: project and integration documentation.
- AGENTS.md: rules for implementation and review.
- porkbun.php: WHMCS registrar entrypoint and exported functions.
- src/: internal API client, mappers, operation handlers.

## Coding Rules

- Follow WHMCS registrar function signatures exactly.
- Keep API credential handling centralized.
- Never hardcode secrets.
- Never log API keys, secret keys, or raw auth payloads.
- Return WHMCS-compatible success and error arrays.
- Keep business logic out of thin WHMCS wrapper functions when practical.

## Operation Mapping Strategy

Each WHMCS operation should map to one internal operation handler:

- RegisterDomain
- TransferDomain
- RenewDomain
- Sync (expiry/renewal date and transfer state synchronization)
- GetNameservers
- SaveNameservers
- GetContactDetails
- SaveContactDetails
- GetEPPCode
- GetRegistrarLock
- SaveRegistrarLock
- GetDNS
- SaveDNS

If a feature is unsupported by Porkbun API or TLD policy:

- Return a clear, non-ambiguous error message.
- Document the limitation in README and release notes.

## API Client Requirements

- Use a single HTTP request helper for all Porkbun calls.
- Apply consistent timeout and error parsing.
- Normalize network errors, HTTP errors, and API-level errors.
- Include sanitized structured context for diagnostics.

## Security Checklist

- Credentials only from WHMCS config fields.
- TLS required for all API requests.
- Input validation on all WHMCS-provided fields.
- Output escaping in any future UI/templates.
- No sensitive data in exceptions surfaced to users.

## Logging Checklist

When module logging is enabled:

- Log operation name and target domain.
- Log elapsed request time.
- Log response status and sanitized error code.
- Do not log full request/response bodies if they may contain sensitive data.

## Error Message Policy

- Admin-visible errors: specific enough to diagnose.
- Client-visible errors: concise and safe.
- Internal logs: include extra sanitized detail.

Use this format where possible:

- Operation failed: <operation> for <domain>. Reason: <safe reason>

## Review Standard

Every implementation PR should verify:

- Function signature compatibility with WHMCS docs.
- Correct request mapping to Porkbun endpoint fields.
- Correct response mapping back to WHMCS format.
- Renewal/expiry date sync correctness for WHMCS core domain sync behavior.
- Safe handling of missing/invalid input.
- No secret leakage in logs or thrown errors.

## Testing Expectations

Minimum per-operation checks before merge:

- Happy path success case.
- Invalid credentials handling.
- API timeout/failure behavior.
- Input validation failures.

Critical regression suite:

- Register domain
- Transfer domain
- Renew domain
- Renewal date sync and WHMCS reminder timing alignment
- Nameserver read/write

## Change Management

- Keep commits focused by operation or infrastructure concern.
- Update README when adding capability or limitations.
- Record known API caveats and workarounds.
- Maintain version tags and release notes.

## Definition Of Done

A feature is done only when:

- WHMCS operation works end-to-end in development.
- Renewal date synchronization is verified to keep WHMCS reminders accurate.
- Error handling is normalized and safe.
- Logs are useful and sanitized.
- README documentation is updated.

## First Implementation Order

Recommended order for fastest validation:

1. Module config + API auth helper
2. RegisterDomain
3. TransferDomain
4. RenewDomain
5. Nameserver operations
6. Contact operations
7. Lock/EPP/DNS operations

## Notes For Future Agents

- Treat WHMCS docs and current Porkbun API docs as source of truth.
- Validate assumptions against live/sandbox behavior before finalizing mappings.
- Prefer small, testable refactors over broad rewrites.
