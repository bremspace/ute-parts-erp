# ADR 0008: Theme System — localStorage + DB, Tailwind `dark:` Class Strategy

**Date:** 2025-09-19
**Status:** Accepted

## Context
Two zones: Backoffice (dark default) / Marketplace (light default). Users switch cabangs. Need manual toggle + auto (system preference) + cross-device sync.

## Decision
**localStorage + DB per user** (Option B) with **Tailwind `dark:` variant + class strategy** (Option C mechanism):
- Toggle writes to `localStorage` (instant) + `users.theme_preference` column (sync)
- On load: check `localStorage` → fallback to DB → fallback to `prefers-color-scheme`
- Implementation: Toggle `dark` class on `<html>` element, use Tailwind `dark:` variants

## Consequences
### Positive
- Instant UI toggle (localStorage)
- Cross-device sync when logged in (DB)
- Respects system preference (auto)
- Works with Tailwind 4 `@theme` custom properties

### Negative
- Slight complexity: dual persistence
- Need migration for `theme_preference` column

## Alternatives Considered
- **A. localStorage only** — No cross-device sync
- **C. Tailwind only (mechanism)** — This is the implementation approach, not the persistence strategy

## Related
- T-32 (Fase 10)
- PRD-Frontend §3 (Design System "Ute Prism")
- Backoffice = dark default, Marketplace = light default
