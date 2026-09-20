# ADR 0009: Thermal Print Architecture — Hybrid Web Bluetooth + CUPS Fallback

**Date:** 2025-09-19
**Status:** Accepted

## Context
Cashiers use local Bluetooth thermal printers (58mm/80mm). VPS is remote. Need direct print without complex setup.

## Decision
**Hybrid** (Option C):
- **Primary**: Web Bluetooth API — browser connects directly to Bluetooth printer. Works on Chrome Android/Chrome OS/Edge. HTTPS required.
- **Fallback**: Backend CUPS/escpos-php — browser sends print job to Laravel queue → server prints via USB/Network printer. For iOS Safari (no Web Bluetooth) and desktop.

## Consequences
### Positive
- Covers 90%+ cashier devices (Android dominant in Indonesia)
- No server-side printer config for Android users
- iOS/desktop users still supported via fallback

### Negative
- Dual implementation effort
- Web Bluetooth requires HTTPS (already have on VPS)
- iOS users need server-side printer setup (CUPS)
- Web Bluetooth API still experimental in some browsers

## Alternatives Considered
- **A. Web Bluetooth only** — Excludes iOS Safari entirely (major gap)
- **B. Backend only** — All printers must connect to VPS. Latency, config complexity, network dependency.

## Implementation Notes
- 58mm: simple receipt (struk kasir)
- 80mm: full invoice (faktur penjualan)
- Use `picqer/php-barcode-generator` for barcode labels (reuse T-15)
- Print CSS `@page` with `size: 58mm auto` / `size: 80mm auto`

## Related
- T-35 (Fase 10)
- T-15 (barcode generation)
- PRD-Frontend §7 (print requirements)
