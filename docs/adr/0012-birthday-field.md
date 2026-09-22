# ADR 0012: Birthday Field — Standard Date Input (YYYY-MM-DD)

**Date:** 2025-09-19
**Status:** Accepted

## Context
CRM needs birthday field for marketing campaigns (birthday promos). Privacy considerations for year.

## Decision
**Standard HTML5 date input** (Option A):
- `<input type="date" name="tanggal_lahir">`
- Format: YYYY-MM-DD (browser handles localization)
- Year required by browser, but nullable in DB
- Query campaigns by `MONTH(tanggal_lahir)` and `DAY(tanggal_lahir)`

## Consequences
### Positive
- Zero custom JS needed
- Browser validation + native picker (mobile friendly)
- Easy month/day queries for campaigns
- Standard Laravel date casting handles it

### Negative
- Browser requires year (can't do MM-DD only)
- Workaround: default year to current or 2000 if user doesn't care, but store as-is

## Alternatives Considered
- **B. Date + optional year** — Custom input, complex, over-engineered for birthday promos
- **C. Full datetime** — Unnecessary

## Implementation Notes
- Migration: add `tanggal_lahir` (nullable date) to `pelanggan` table
- Form: standard date input in CRM create/edit modal
- Campaign query: `Pelanggan::whereMonth('tanggal_lahir', now()->month)->whereDay('tanggal_lahir', now()->day)->get()`

## Related
- T-37 part 1 (Fase 10)
- T-36 (customer create modal reuses CRM form)
- T-23 (CRM broadcast for campaigns)
