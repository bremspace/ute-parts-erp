# Triage Label Vocabulary

Canonical five roles, label string = role name (default).

| Role | Label | Description |
|------|-------|-------------|
| Needs Triage | `needs-triage` | New issue/bug report arrives; not yet categorized |
| Needs Info | `needs-info` | Cannot proceed until reporter clarifies something |
| Ready for Agent | `ready-for-agent` | Spec complete, blocking edges resolved, agent can start coding |
| Ready for Human | `ready-for-human` | Blocked on human decision (credentials, approval, design sign-off) |
| Won't Fix | `wontfix` | Explicitly declined; not a bug, not in scope, or duplicate |

## Usage Rules

1. **Every issue starts as `needs-triage`** — auto-applied on creation
2. **Triage process** (manual or `/triage` skill) moves issue to one of the other four
3. **Only `ready-for-agent` issues are picked up by `/implement`**
4. **`blocked-by:T-XX` and `blocks:T-XX` labels** encode blocking edges (not part of the five canonical roles)
5. **No custom labels** unless they map 1:1 to the five roles above

## GitHub Label Colors (suggested)

- `needs-triage` — `#FFA500` (orange)
- `needs-info` — `#007BFF` (blue)
- `ready-for-agent` — `#28A745` (green)
- `ready-for-human` — `#6F42C1` (purple)
- `wontfix` — `#6C757D` (gray)