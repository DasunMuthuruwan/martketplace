# Authentication, Two-Factor Authentication and Recovery (CD-11)

| Field | Value |
|---|---|
| Status | **Approved 2026-10-11** (TOTP only, recovery process, two Owner accounts recommended) |
| Decision basis | CD-11 approved: 2FA mandatory for Owner and Admin. Owner asked for the method and recovery process to be documented first. |

> **Implementation status (T2.2, 2026-10-11):** TOTP with confirmation, recovery codes, the challenge screen, Owner/Admin enforcement on `/admin`, a 15-minute password re-check (`auth.password_timeout = 900`, applied to every user, not only staff: simpler and stricter), and `marketplace:reset-2fa` (clears 2FA, ends sessions, emails the user) are implemented and tested. **Not yet implemented:** email alerts for password/role changes; audit-log entries (these arrive with the audit log, M4/M6); Owner-initiated 2FA reset in Admin → Staff (T6.2).
>
> **S8.5 (2026-10-21):** a password **reset** now also signs the account out everywhere (S-04); CSRF, session fixation, logout and reset-link host safety are covered by `tests/Feature/Security/{CsrfProtectionTest,SessionSecurityTest}.php`. Security emails (S-05, owner approved): `SecurityChange` notices after a password change or reset, 2FA turned off, recovery codes regenerated, and an email change (sent to the old address). Still not implemented: notices for staff role changes (the audit log records them).

> **T2.3 additions:** a password change needs the current password and signs out every other session (database sessions deleted, remember token cycled). An email change needs the current password and a new verification. Email alerts for these changes still wait for the security-email work (M4/M6).
>
> **T2.4 additions:** staff idle timeout (30 min) and absolute lifetime (8 h) are enforced by `EnforceStaffSessionLimits` (web group; limits in `config/marketplace.php` → `staff_session`, env-overridable). A staff session restored from a "remember me" cookie is rejected, so staff always sign in with password and 2FA. Session timestamps live in the session (`staff_session.started_at`, `staff_session.last_seen`).

## 1. Method

| Item | Design |
|---|---|
| Library | Laravel Fortify `twoFactorAuthentication` feature with `confirm: true` and `confirmPassword: true` |
| Factor | **TOTP** (RFC 6238, 6 digits, 30 s), works with Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden |
| Secret storage | `users.two_factor_secret` and `two_factor_recovery_codes` are **encrypted with `APP_KEY`** (Fortify default) |
| Recovery codes | A set of single-use codes (Fortify generates 8). Each is invalidated after use. Can be regenerated (needs password confirmation). |
| Passkeys / WebAuthn | **Not enabled** (ADR-015). Passkeys are bound to the domain (`relying_party_id`), so the planned domain migration would invalidate every registered passkey. Can be added after the final domain is settled. |
| SMS / email codes | Not used (cost; SIM-swap risk; Q7) |

## 2. Who Must Use It

| Role | 2FA |
|---|---|
| Owner | **Mandatory** |
| Admin | **Mandatory** |
| Moderator | Optional (recommended) |
| Regular user | Not offered in the MVP (can be enabled later) |

**Enforcement:** the `EnsureStaffTwoFactor` middleware on the `/admin` group. An Owner or Admin with no confirmed 2FA (`two_factor_confirmed_at` null) is redirected to `/account/settings/security` and cannot reach any admin page or Livewire admin action until setup is confirmed. Livewire admin components also check it in `mount()` and through persistent middleware.

## 3. Enrolment Flow

1. A staff role is assigned by the Owner (audit logged). The user gets an email: "Set up two-factor authentication to access the admin panel".
2. The user opens Security settings, confirms their password, and clicks Enable. A QR code and text secret are shown.
3. The user enters a valid code to **confirm**. Until then, 2FA is not active and admin access stays blocked.
4. Recovery codes are shown once, with "copy" and "download .txt" options and an "I have stored these codes" checkbox.
5. An audit log entry is written: `2fa.enabled`.

## 4. Login Flow

Password → (rate-limited) → `/two-factor-challenge` → TOTP code **or** a recovery code → session regenerated → admin access.
The challenge is rate-limited (5/min per user+IP). Failed attempts are logged as security events.

## 5. Recovery Process

| Situation | Process |
|---|---|
| Lost phone, has recovery codes | Log in with a recovery code, disable and re-enable 2FA with the new device, regenerate codes. Self-service. |
| Lost phone **and** codes — **Admin** or **Moderator** | The **Owner** resets that user's 2FA from Admin → Staff (`staff.manage`, password confirmation, a reason is required, audited). The user gets an email and must enrol again on the next login. |
| Lost phone **and** codes — **Owner** (with another Owner account) | The other Owner resets it as above. **Recommendation: create two Owner accounts** (the second can be a break-glass account whose credentials are stored offline). |
| Lost phone **and** codes — **sole Owner** | Break-glass CLI command `php artisan marketplace:reset-2fa {email}` run on the server (SSH, or a one-off cPanel cron if no SSH). Requires server access, so it is as strong as hosting access. Audited and emails the account. Documented in the operations runbook (Phase 10). |
| Suspected compromise | Owner suspends the account (all sessions deleted), resets the password and 2FA, and reviews the audit log |

**Never:** disable 2FA via a support email request alone, or store recovery codes in the database unencrypted.

## 6. Protecting Sensitive Administrative Actions

| Control | Applies to |
|---|---|
| Server-side permission check in every route and Livewire action | All admin actions ([authorization](05-authorization.md)) |
| **Password re-confirmation** (`password.confirm`, timeout **15 minutes** for staff instead of Laravel's 3-hour default) | Staff role changes, site settings, user ban/delete, 2FA disable/reset, regenerating recovery codes, account deletion |
| Session regeneration on login, 2FA challenge and role change | All users |
| Staff idle timeout: 30 minutes. Absolute session lifetime: 8 hours for staff. | Staff |
| Logout other sessions on password change (`Auth::logoutOtherDevices`, DB sessions) | All users |
| Audit log entry with actor, target, reason, IP hash | All staff mutations and 2FA events |
| Email alert to the affected account | Password changed, 2FA enabled/disabled/reset, role changed |

## 7. Portability Notes (Domain Migration)

- TOTP secrets depend on `APP_KEY`, not on the domain. **`APP_KEY` must be carried over unchanged** in any hosting or domain migration, otherwise every 2FA secret becomes unreadable ([domain migration](11-domain-migration-and-portability.md) §5).
- Session cookies are host-only, so users simply log in again on the new domain.
