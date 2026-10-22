# Authorization Design

| Field | Value |
|---|---|
| Status | **Revised 2026-10-11: roles confirmed (Q9), 2FA approved (CD-11). Awaiting approval of the revised design.** |
| Roles | Owner/Super-admin, Admin, Moderator [CONFIRMED Q9]. Regular users have no role. |
| Mechanism | spatie/laravel-permission (roles → permissions) + Laravel Policies for ownership [RECOMMENDED] |

## 1. Principles

1. **Server-side only.** Every route, Livewire action and job that reads or changes protected data authorises on the server. Hiding a button is not authorisation. Livewire actions are callable from the browser console, so each one calls `$this->authorize()`.
2. **Code checks permissions, not roles.** `can('listings.moderate')`, never `hasRole('admin')`. New roles can then be added by assigning permissions, with no code changes.
3. **Ownership through Policies.** A user may act on their own listings. Staff permissions extend this to others.
4. **Hierarchy guard.** Staff cannot act on accounts with an equal or higher rank (Owner > Admin > Moderator > User). Only the Owner manages staff roles.
5. **Last-owner protection.** The last Owner cannot be demoted, suspended or deleted.
6. **Admin area** (`/admin`) requires `access-admin` (any staff role), a verified email, an active account and, for Owner/Admin, confirmed 2FA (CD-11). Sensitive actions also need password re-confirmation within 15 minutes ([2FA doc](12-authentication-and-2fa.md) §6).
7. **Audit:** every staff action that changes data writes an `audit_logs` row.

> **Implemented in T2.2 (2026-10-11).** Matrix in `App\Authorization\Permissions` (single source for seeder, gates and tests; a test checks every cell against §3). Hierarchy in `App\Authorization\StaffHierarchy`. **Refinement:** Owners may act on every account except their own, including other Owners. This is needed to reset a locked-out Owner's 2FA (2FA doc §5). Everyone else needs a strictly higher rank. The last-Owner guard (`ChangeStaffRole` locks the Owner set in a transaction) stops two Owners demoting each other at the same moment from leaving zero Owners. The first Owner is appointed with `php artisan marketplace:make-owner {email}` (server access only). `EnsureAccountActive` signs out suspended/banned users on their next request; `EnsureStaffTwoFactor` (`staff.2fa`) guards `/admin`.

## 2. Permissions

| Permission | Description |
|---|---|
| `access-admin` | Enter the admin panel |
| `listings.view-any` | See listings in any status (incl. drafts, pending, rejected) |
| `listings.moderate` | Approve, reject or request changes on pending listings |
| `listings.suspend` | Suspend or reinstate active listings |
| `listings.edit-any` | Edit another user's listing (category fix, typo, image removal) |
| `listings.delete-any` | Soft delete any listing |
| `reports.view` | View reports queue |
| `reports.resolve` | Resolve or dismiss reports |
| `users.view` | View user list and profiles (email/phone visible) |
| `users.suspend` | Suspend or reinstate regular users |
| `users.ban` | Ban users (and block re-registration by hashed email/phone) |
| `users.delete` | Delete or anonymise a user account on request |
| `categories.manage` | CRUD categories |
| `attributes.manage` | CRUD attributes, options and category attachments |
| `locations.manage` | CRUD cities (provinces and districts are fixed seeds) |
| `pages.manage` | Edit static pages |
| `blocklist.manage` | Manage blocked terms |
| `settings.manage` | Site settings (moderation mode, limits, contact toggles, expiry, retention) |
| `staff.manage` | Assign or remove staff roles, adjust role permissions |
| `audit.view` | View audit log |
| `boosts.manage` | Grant and end paid boosts; later orders and prices (ADR-026, B3; synced to existing databases by migration `2026_10_21_000002`) |

## 3. Role Matrix

| Permission | Owner | Admin | Moderator |
|---|:-:|:-:|:-:|
| access-admin | ✔ | ✔ | ✔ |
| listings.view-any | ✔ | ✔ | ✔ |
| listings.moderate | ✔ | ✔ | ✔ |
| listings.suspend | ✔ | ✔ | ✔ |
| listings.edit-any | ✔ | ✔ | — |
| listings.delete-any | ✔ | ✔ | — |
| reports.view | ✔ | ✔ | ✔ |
| reports.resolve | ✔ | ✔ | ✔ (listing reports only) |
| users.view | ✔ | ✔ | ✔ (limited fields: no email/phone) |
| users.suspend | ✔ | ✔ | — |
| users.ban | ✔ | ✔ | — |
| users.delete | ✔ | — | — |
| categories.manage | ✔ | ✔ | — |
| attributes.manage | ✔ | ✔ | — |
| locations.manage | ✔ | ✔ | — |
| pages.manage | ✔ | ✔ | — |
| blocklist.manage | ✔ | ✔ | ✔ |
| settings.manage | ✔ | — | — |
| staff.manage | ✔ | — | — |
| audit.view | ✔ | ✔ | — |
| boosts.manage | ✔ | ✔ | — |

Owner is granted every permission explicitly. There is no blanket `Gate::before` bypass, so a forgotten permission check shows up in tests. The matrix is seeded and Owner-editable later (`staff.manage`) [RECOMMENDED: keep it fixed in the MVP; make it editable post-MVP].

## 4. Regular User (Ownership) Rules — Policies

| Action | Rule |
|---|---|
| View a listing | Public if `active` (public scope). Otherwise only the owner or `listings.view-any`. Everyone else gets **404** (not 403), so hidden listings aren't revealed. |
| Create listing | Authenticated, email verified, account `active`, under limits (LST-09), Turnstile passed |
| Update / delete / renew / mark sold | Owner (account active) or the relevant staff permission. Owners cannot edit while `suspended` by staff. |
| Upload or reorder images | Owner of the draft or listing |
| Reveal contact | Allowed by `ContactResolver` (visibility + listing toggles), rate-limited. The owner viewing their own listing always sees it. |
| Favourite | Authenticated, not own listing |
| Report | Authenticated, verified, not own listing, one open report per target |
| Account settings / deletion | Self only (password confirmation required) |

## 5. Account Status Enforcement

`EnsureAccountActive` middleware, on every authenticated request:

- `suspended` (and `suspended_until` in the future or null) or `banned` → log out, invalidate the session, show a notice with the reason.
- The listing public scope also hides listings of suspended or banned users immediately.

## 6. Testing Requirements (Phase 5/7)

- A feature test per permission: allowed role → 2xx, each disallowed role and a regular user → 403/404.
- A Livewire test per admin action with a non-authorised user (actions called directly).
- Hierarchy tests: an Admin cannot suspend the Owner or another Admin; a Moderator cannot see emails.
- An ownership test matrix for listings in each status.
