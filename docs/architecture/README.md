# Phase 2 — System Architecture and Technical Design

**Status: APPROVED by the owner on 2026-10-11 (revision 2).** Hosting verification conditions in [architecture §12](02-system-architecture.md) still apply before scaffolding or deployment. No implementation, dependency installation, DNS, hosting or production change has been made.

| # | Document | Contents |
|---|---|---|
| 01 | [Existing Dev Talk inspection](01-existing-devtalk-inspection.md) | Read-only findings: domain, host, stack, auth, cookies, brand assets |
| 02 | [System architecture](02-system-architecture.md) | Stack, modules, flows, lifecycle, background work, caching, SEO, localisation, scaling, **critical decisions CD-01–CD-12** |
| 03 | [Database design](03-database-design.md) | ERD, tables, indexes, attribute storage |
| 04 | [Category tree](04-category-tree.md) | Proposed categories and attributes |
| 05 | [Authorization](05-authorization.md) | Roles, permissions matrix, policies |
| 06 | [Security and uploads](06-security-and-uploads.md) | Threats, sessions, rate limits, image pipeline, bot protection, headers |
| 07 | [Routes and contracts](07-routes-and-contracts.md) | Web routes, search query contract, Livewire and JSON contracts |
| 08 | [Hosting and deployment](08-hosting-and-deployment.md) | Known vs unknown host facts, minimum requirements, capacity, account options, deploy without SSH, email |
| 09 | [Data retention](09-data-retention.md) | Retention and deletion periods (initial plan, legal review pending) |
| 10 | [Package assessment](10-package-assessment.md) | Laravel 13 / PHP baseline, package compatibility, licences, final package set |
| 11 | [Domain migration and portability](11-domain-migration-and-portability.md) | Portability rules, env inventory, redirect strategy, migration runbook, staged scaling, backups |
| 12 | [Authentication and 2FA](12-authentication-and-2fa.md) | TOTP method, enforcement, recovery process, sensitive-action protection |
| 13 | [Global marketplace](13-global-marketplace.md) | **Draft (2026-10-11):** impact assessment, countries, currencies and money formatting, location hierarchy and GeoNames dataset, measurement units, search rules, indexing, legal by country, 15 test scenarios, owner decisions G-1–G-13 |
| — | [Decision log](../decisions/README.md) | ADR-001 to ADR-023 (019–023 proposed for the global marketplace) |

Labels used throughout: **[CONFIRMED]** owner decision · **[VERIFIED]** checked evidence · **[INFERRED]** strong evidence, unconfirmed · **[RECOMMENDED]** proposal · **[ASSUMPTION]** · **[UNVERIFIED]** / **[OPEN]** must be resolved.
