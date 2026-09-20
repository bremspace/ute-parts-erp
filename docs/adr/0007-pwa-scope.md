# ADR 0007: PWA Scope — Basic Install Only

**Date:** 2025-09-19
**Status:** Accepted

## Context
Need PWA for "installable on any device". POS kasir is primary use case.

## Decision
**Basic install only** (Option A):
- `manifest.json` + service worker (stale-while-revalidate for assets, network-first for API)
- Works offline for cached pages
- No push notifications, no background sync, no offline POS transactions

## Consequences
### Positive
- Fast to implement
- Meets "installable" requirement
- Low maintenance (standard SW patterns)

### Negative
- No offline transaction capability (cashiers lose connectivity → can't sell)
- No push notifications for WA/Email alerts

## Alternatives Considered
- **B. Basic + Offline POS** — IndexedDB queue for offline transactions, sync on reconnect. Significant complexity (conflict resolution, duplicate detection).
- **C. Full offline-first** — Background sync, push notifications. Major effort.

## Future
Offline POS → Fase 11+ after core PWA validated in production.

## Related
- T-31 (Fase 10)
- PRD-Frontend §8 (mobile requirements)
