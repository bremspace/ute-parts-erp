# ADR 0010: Responsive Breakpoints — Custom `xs`/`xxs` in Tailwind `@theme`

**Date:** 2025-09-19
**Status:** Accepted

## Context
POS is primary mobile use case. Cashiers use various Android phones (320-414px). Tailwind 4 uses `@theme` for custom breakpoints.

## Decision
Add custom breakpoints to Tailwind 4 `@theme` in `vite.config.js` / CSS:
- `xs: 320px` (iPhone SE, small Android)
- `xxs: 375px` (iPhone 12/13/14 standard)
- Keep defaults: `sm: 640px`, `md: 768px`, `lg: 1024px`, `xl: 1280px`, `2xl: 1536px`

## Consequences
### Positive
- Covers all phone sizes without device-specific explosion
- Works with Tailwind 4's native `@theme` system
- Can use `xs:` and `xxs:` prefixes in Blade/Alpine components

### Negative
- Custom breakpoints need documentation for team
- Slight deviation from pure Tailwind defaults

## Alternatives Considered
- **A. Defaults only** — `sm: 640px` too large for phone-specific layouts
- **C. Device-specific** — Explosion of breakpoints (320, 375, 390, 414, 360, etc.)

## Related
- T-30 (Fase 10)
- PRD-Frontend §8 (mobile responsive test)
- vite.config.js for Tailwind 4 config
