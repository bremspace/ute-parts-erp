# Domain Docs Layout: Single-Context

This repo uses **single-context** domain documentation.

## Structure

```
/var/www/test.uteparts.id/
├── CONTEXT.md              # Single source of truth for domain glossary & ADR index
├── docs/
│   ├── adr/                # Architecture Decision Records (numbered: 0001-title.md)
│   └── agents/             # Agent skill configs (issue-tracker.md, triage-labels.md, domain.md)
```

## CONTEXT.md

- **Purpose**: Define domain terms unambiguously, record decisions, link to ADRs
- **Format**: Markdown with sections for Glossary, Key Decisions, Open Questions
- **Consumer rules**:
  - Agent MUST read `CONTEXT.md` at session start (before any code work)
  - Agent MUST update `CONTEXT.md` when discovering new terms or making decisions
  - `/grill-with-docs` writes here; `/domain-modeling` reads/writes here
  - Never duplicate definitions across files — single source of truth

## ADRs (Architecture Decision Records)

- **Location**: `docs/adr/`
- **Naming**: `NNNN-short-title.md` (e.g., `0001-use-laravel-13.md`)
- **Format**: Markdown with standard ADR sections (Status, Context, Decision, Consequences)
- **Trigger**: Any hard-to-reverse technical decision (framework version, auth strategy, DB pattern, module boundary)
- **Index**: List of ADRs with status maintained in `CONTEXT.md`

## Multi-Context (Not Used)

This repo does **not** use `CONTEXT-MAP.md` or per-module `CONTEXT.md` files because:
- No monorepo structure detected (single Laravel app)
- Module boundaries are code-organization only, not deployment-separated
- Single team works on all modules

If this changes (e.g., split to microservices), re-run `/setup-matt-pocock-skills` to migrate.