# Phase 7 Manual Regression Checklist

Use this checklist against a WHMCS development instance with a configured Porkbun registrar module.

## Pre-check

- [ ] Module is enabled in WHMCS registrar settings.
- [ ] API Key and Secret API Key are configured.
- [ ] Debug logging is enabled during test execution.

## Critical Regression Suite

- [ ] Register domain
: Submit a standard registration and confirm WHMCS receives success.
- [ ] Transfer domain
: Submit transfer with valid auth code and confirm success response handling.
- [ ] Renew domain
: Submit renewal and confirm success response handling.
- [ ] Sync renewal/expiry date
: Run domain sync and confirm `expirydate` is updated correctly.
- [ ] Validate reminder and invoice timing after sync
: Confirm WHMCS next reminder/invoice timing aligns to synced renewal date.
- [ ] Nameserver read/write
: Save nameservers and read back to confirm round-trip consistency.

## Additional Phase 7 Checks

- [ ] Invalid credentials behavior
: Temporarily break credentials and confirm operations return safe, clear errors.
- [ ] Timeout/network failure behavior
: Simulate upstream network issues and confirm normalized failure response.
- [ ] Logging redaction
: Confirm module logs do not contain API key or secret key values.

## Evidence Capture

- [ ] Save WHMCS module log excerpts for each test case.
- [ ] Record date/time, domain used, operation, and outcome in `tests/evidence/phase7-evidence.md`.
