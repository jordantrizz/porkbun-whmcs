# TESTING

Live production validation checklist extracted from roadmap exit criteria in TODO.md.

This document tracks only the exit criteria that require live WHMCS and registrar-side behavior verification. These checks cannot be fully satisfied by local static checks alone.

## How To Use

- Run each check in a real WHMCS environment connected to Porkbun.
- Capture evidence for each item (timestamp, domain, operation, result, and relevant logs).
- Mark each item complete only after successful end-to-end verification.

## Phase 1 Exit Criteria (Live)

- [ ] Module activates and stores config without errors.
- [ ] Evidence captured (WHMCS admin screenshots or logs).

## Phase 3 Exit Criteria (Live)

- [ ] Register, transfer, and renew complete successfully in development/live-like environment.
- [ ] Failure modes return stable, safe, actionable errors in WHMCS UI/logs.
- [ ] Evidence captured for all three operations.

## Phase 4 Exit Criteria (Live)

- [ ] WHMCS reminders align with synced renewal date.
- [ ] Renewal invoice timing is correct after sync runs.
- [ ] Evidence captured for sync run plus reminder/invoice timing behavior.

## Phase 5 Exit Criteria (Live)

- [ ] Nameserver read/write round-trips correctly.
- [ ] Contact read/write round-trips correctly.
- [ ] Evidence captured for get/save + read-back verification.

## Phase 6 Exit Criteria (Live)

- [ ] Supported advanced operations function correctly.
- [ ] Unsupported operations are explicit and documented in runtime behavior.
- [ ] Evidence captured for EPP/lock operations and unsupported DNS behavior.

## Phase 7 Exit Criteria (Live)

- [ ] Critical regression suite passes.
- [ ] No sensitive values observed in logs.
- [ ] Evidence captured for each regression scenario and log redaction check.

## Phase 8 Exit Criteria (Release Validation)

- [ ] Initial release is reproducible and supportable.
- [ ] Evidence captured for reproducible deployment and operation verification.

## Evidence Template

Use this template per test execution:

- Timestamp:
- Environment:
- Domain:
- Phase/criterion:
- Operation(s):
- Expected:
- Actual:
- Pass/Fail:
- Log evidence:
- Notes/follow-up:
