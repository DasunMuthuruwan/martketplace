# Data Retention Policy (Proposal)

| Field | Value |
|---|---|
| Status | **Approved as the initial plan (CD-07, 2026-10-11).** All periods are configurable. **A privacy/legal review is required before production.** Periods may change after that review. |
| Principle | Keep only what is needed to operate the marketplace, prevent abuse, and meet legitimate legal requirements. Aligned with the principles of Sri Lanka's Personal Data Protection Act No. 9 of 2022. Legal confirmation is the owner's responsibility. |
| Mechanism | All periods live in `config/marketplace.php` → `retention.*` (env-overridable) and are enforced by the daily `marketplace:prune` command and Laravel `Prunable` models. |

## 1. Retention Periods

| Data | Trigger | Proposed action and period | Why |
|---|---|---|---|
| **Deleted account** | User deletes their account | Immediately: soft delete, log out everywhere, hide all listings, remove favourites. **After 30 days:** anonymise (name → "Deleted user", email → `deleted+{id}@invalid`, phone/whatsapp nulled, 2FA cleared), delete images and listings. | 30-day window to handle abuse reports filed just before deletion, and accidental deletion (restore on request) |
| **Banned account** | Staff ban | Anonymise after 30 days like the above, **but keep HMAC hashes of email and phone for 2 years** to block re-registration | Abuse prevention |
| **Inactive account** | No login for 3 years | Email warning, then deletion as above after 30 days | Minimisation |
| **Listing deleted by user** | Soft delete | Images deleted after **30 days**. Row hard-deleted after **180 days**. | Dispute and report handling |
| **Expired listing** | `expires_at` passed | Renewable for **90 days**. Then images deleted and listing hard-deleted after **180 days** from expiry. | Disk and inode limits (hosting doc §3) |
| **Sold listing** | Marked sold | Public "sold" page for 30 days (noindex). Images deleted after **90 days**. Row deleted after **180 days**. | |
| **Rejected listing** | Moderation reject | Kept **90 days** (seller can fix and resubmit), then deleted with images | |
| **Drafts** | Never submitted | Deleted after **60 days** without edits (email reminder at day 45) | |
| **Orphan uploads / temp files** | Not attached | **24 hours** | |
| **Reports** | Resolved or dismissed | **2 years** after resolution, then deleted. Reporter id nulled if the reporter's account is anonymised. | Repeat-offender patterns |
| **Audit logs** | Created | **2 years**, append-only | Security and accountability |
| **Contact reveal logs** | Created | **90 days** | Rate limiting and seller stats |
| **Database notifications** | Read | **90 days** | |
| **Sessions** | Expired | Laravel garbage collection (lifetime 120 min) | |
| **Password reset tokens** | Created | 60 minutes (framework) | |
| **Failed jobs** | Failed | **30 days** | |
| **Application logs** | Daily file | **14 days** rotation | May contain IPs |
| **Backups** | Daily | Daily ×7, weekly ×4, monthly ×3, so **up to about 90 days**. Deleted data can persist in backups until they rotate out. This is disclosed in the Privacy Policy. Backups are not restored selectively to revive deleted personal data. | Disaster recovery |

## 2. Account Deletion Behaviour (User-Facing)

1. Settings → Security → Delete account. Requires password confirmation and states what happens.
2. Immediate: signed out on all devices, listings hidden, favourites removed, confirmation email sent.
3. Within 30 days: the user can ask support to restore the account (manual, staff with `users.delete`).
4. After 30 days: anonymised and purged as above. Staff audit records keep the anonymised user id only.

> **Implementation status (T2.3):** steps 1 and 2 are implemented (`AppActionsAccountDeleteAccount`): password check, soft delete, staff roles removed (a restored account does not regain staff powers), all database sessions ended and the remember token cycled, confirmation email, and a last-Owner guard. The email stays reserved while the row is soft-deleted (registration and email changes reject it). Hiding listings comes with the public scope (T4.1), removing favourites with T4.6, and anonymisation/purge with the retention job (M7).

## 3. Legal Hold

Staff with `users.delete` can set `legal_hold` on a user or listing, for example during a police request or a fraud investigation. Pruning skips records under hold. Every hold change is audited. [RECOMMENDED; adds a boolean column on `users` and `listings`.]

## 4. Open Points for the Owner

- Approve or adjust every period in §1.
- Confirm a support contact address for data requests (access, correction, deletion) to publish in the Privacy Policy.
- Confirm whether a legal review of the Terms and Privacy Policy will be arranged before launch.
