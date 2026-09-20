# ADR 0006: Security Scan Approach — Automated + Manual Review

**Date:** 2025-09-19
**Status:** Accepted
**Deciders:** User + Agent

## Context
Application runs on public IP (VPS). Laravel 13 + Sanctum + Spatie Permission. Need comprehensive security scan before Fase 10 implementation.

## Decision
Use **Automated + Manual Review** (Option B):
- Automated: `composer audit`, `npm audit`, Laravel security headers check, OWASP ZAP against local dev
- Manual: Code review of auth flows, RBAC enforcement, API input validation, file upload handlers (Excel import)
- Skip: Full penetration test on public IP (needs separate staging env)

## Consequences
### Positive
- Catches known vulnerabilities (CVEs) + logic flaws tools miss
- Practical for 1-person/agent team
- No staging environment required

### Negative
- May miss sophisticated attack chains
- Manual review time scales with codebase size
- No external validation of public-facing attack surface

## Alternatives Considered
- **A. Automated only** — Faster but misses auth bypass, RBAC gaps, business logic flaws
- **C. Automated + Manual + Pen-test** — Most thorough but requires staging env + more time

## Related
- T-28 (Fase 10)
- AGENTS.md security constraints
