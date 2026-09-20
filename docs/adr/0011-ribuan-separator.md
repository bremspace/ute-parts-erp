# ADR 0011: Ribuan Separator — Alpine.js `x-format-number` Component

**Date:** 2025-09-19
**Status:** Accepted

## Context
All nominal input fields (Buka Kas, POS, etc.) need thousand separators for readability + no typing lag.

## Decision
**Alpine.js standalone component** (Option A): `<input x-data="formatNumber()" x-format="number">`
- Client-side formatting only (display)
- Backend receives clean numeric value on submit
- Zero server roundtrip, works in any form (Livewire, plain HTML, modal)

## Consequences
### Positive
- Reusable across all forms (POS, Kasir, Servis, etc.)
- No Livewire overhead for display formatting
- Eliminates typing lag (pure client-side)
- Consistent behavior everywhere

### Negative
- Need to ensure backend validation accepts formatted string (strip separators)
- Must handle copy/paste with separators

## Alternatives Considered
- **B. Livewire component** — Server-side validation but more overhead, potential lag
- **C. Hybrid** — Alpine for display, Livewire for validation. Over-engineered for this need.

## Implementation
```js
// resources/js/components/format-number.js
Alpine.data('formatNumber', () => ({
  init() {
    this.$watch('value', v => {
      this.$el.value = this.format(v)
    })
  },
  format(n) {
    return Number(n.replace(/[^0-9]/g, '')).toLocaleString('id-ID')
  },
  getValue() {
    return Number(this.$el.value.replace(/[^0-9]/g, ''))
  }
}))
```
Usage: `<input x-data="formatNumber" x-model="value" @input="$el.value = format($el.value)">`

## Related
- T-34 (Fase 10)
- T-33 (Buka Kas nominal input)
- T-07/T-08 (POS nominal inputs)
