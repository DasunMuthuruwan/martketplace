# Dev Talk Marketplace — Project Instructions

## Project Objective

Develop a secure, scalable, modern multi-category classifieds marketplace hosted on a dedicated subdomain of my existing Dev Talk website.

Example: `marketplace.devtalk.lk`. Treat this as a placeholder until the actual domain is confirmed.

The marketplace must be a separate application with its own database, environment configuration, uploads and deployment process.

## Business Model

This is a direct buyer-to-seller classifieds marketplace.

- Buyers browse listings and contact sellers directly.
- Support configurable phone, WhatsApp and internal messaging options.
- Do not implement mandatory shopping carts, checkout, transaction commissions or escrow in the MVP.
- Paid featured listings and advertisement subscriptions can be implemented in a later phase.

## Supported Categories

- Vehicles and motorcycles
- Land, houses, apartments and commercial property
- Vehicle spare parts and accessories
- Mobile phones, computers and electronics
- Furniture, home appliances, fashion and other configurable categories

Administrators must be able to configure categories and category-specific listing attributes.

## Mandatory SDLC Workflow

Follow these phases in order:

1. Requirements analysis
2. System architecture and technical design
3. UI/UX design
4. Implementation planning
5. Backend development
6. Frontend development and integration
7. Testing and quality assurance
8. Security review
9. Deployment to cPanel
10. Maintenance and future enhancements

Read the relevant phase document before starting its work.

Do not implement the entire project in one step. Work in small, reviewable tasks and wait for approval at major decision points.

## Existing Project Protection

Before making changes:

- Inspect the repository, framework versions, dependencies, routes and existing functionality.
- Identify which files and modules will be affected.
- Preserve existing functionality and avoid unnecessary rewrites.
- Never overwrite production data, environment files or unrelated project files.
- Use version control and feature branches where available.
- Do not deploy, modify live DNS or run destructive production commands without explicit approval.

## Engineering Standards

- Prefer the existing supported technology stack unless a change is justified.
- Follow Laravel conventions and maintainable frontend architecture.
- Validate requests on the server.
- Enforce authorization on the backend.
- Use database migrations and appropriate indexes.
- Prevent insecure file uploads and unauthorized access.
- Keep secrets out of source control.
- Use reusable components and consistent UI patterns.
- Provide meaningful loading, empty, validation and error states.
- Optimize database queries and images.
- Document significant architecture decisions.

## UI/UX Standards

Use a premium White + Black design with light and dark themes, responsive layouts, consistent typography, controlled spacing, accessible forms and reusable components.

Avoid excessive padding, inconsistent colors, unnecessary animations and duplicated components.

## Testing and Completion Rules

For each task:

1. Inspect relevant existing code.
2. Explain the intended changes.
3. Implement one logical feature at a time.
4. Run applicable tests, linting and build checks.
5. Fix regressions before continuing.
6. Update relevant documentation and the task checklist.
7. Report changed files, test results, limitations and the next step.

Never claim that an implementation or test is complete unless it has been verified.

## Project Memory

Maintain `CLAUDE.md`, `docs/` and a project progress document as the source of truth.

Record completed tasks, architectural decisions, known issues and pending work. Do not assume that a new session remembers undocumented decisions.

## Project Status and Key Documents

- Progress, decisions and blockers: `docs/PROGRESS.md` (read first in every session)
- Requirements specification (Phase 1 output): `docs/requirements/requirements-specification.md`
- Architecture and technical design (Phase 2 output): `docs/architecture/README.md`
- Architecture decision log: `docs/decisions/README.md`
- UI/UX design (Phase 3 output): `docs/design/README.md` (design system, components, layouts, accessibility, prototype)
- Implementation plan (Phase 4 output): `docs/implementation/implementation-plan.md`. Task status: `docs/implementation/task-checklist.md` (update after every task). Follow its Definition of Done and branch-per-task workflow.
- Phase prompt files: `docs/01-…` to `docs/10-…` (`docs/02-system-design.md` also holds the owner's answers to Q1–Q16)
- Existing Dev Talk blog (read-only reference, never modify): `C:\php8.2\htdocs\blog-site\blog`
- The marketplace has its own modern marketplace-portal theme. Do not reuse Dev Talk's theme.
- Portability is mandatory (the domain and hosting will change later). Never hardcode the domain: all URLs come from `APP_URL` / `route()` / `Storage::url()`. Follow rules P1–P11 in `docs/architecture/11-domain-migration-and-portability.md`.
- Binding implementation requirements from approved decisions: `docs/architecture/02-system-architecture.md` §12–§13.
- **Global marketplace (2026-10-11):** multi-country. Never hardcode a country, currency, phone format, location label or land unit (`LKR`, `Sri Lanka`, `+94`, `Province`, `perch`) in code. These live in data (countries, currencies, regions, cities, measurement units). See `docs/architecture/13-global-marketplace.md`.
- Local database for this project: standalone MariaDB 10.11 at `C:\mariadb10.11`, `127.0.0.1:3307` (start with `C:\mariadb10.11\start-marketplace-db.bat`). Do not use XAMPP's MariaDB on port 3306 (pre-existing corruption; shared with other projects).
- Local PHP for this project: `C:\php8.4\php.exe` (standalone PHP 8.4, not on PATH). Never modify `C:\php8.2` (used by Dev Talk), other PHP folders or the global PATH.

## Initial Instruction

Start with Phase 1 only. Inspect the repository and create the requirements analysis documentation. Do not start implementation until the requirements and proposed architecture have been reviewed and approved.