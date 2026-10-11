## Owner Decisions — Marketplace Phase 2

Please use the following answers to resolve the open questions in `docs/02-system-design.md`. Update the documentation accordingly. Do not begin implementation until the architecture and remaining critical decisions have been reviewed and approved.

### Q1. Confirmed subdomain
Use `marketplace.dev-talk.lk` as a proposed subdomain for planning only. The actual domain and subdomain availability must be verified before configuration, deployment, or SEO setup. Do not change DNS.

### Q2. Dev Talk account integration and existing stack
For the MVP, use independent marketplace registration and authentication. Do not share the existing blog's user table, passwords, sessions, or authentication cookies.

Inspect the existing Dev Talk project to identify its framework, authentication system, and current architecture. Document the findings. Consider secure SSO as a future enhancement, not an MVP requirement.

### Q3. cPanel account
The preferred approach is to host the marketplace on a separate subdomain and use a separate database. Whether it will use the same cPanel account as Dev Talk is not yet confirmed. Document both options and verify account and resource limitations before deployment.

### Q4. PHP, database, SSH, Composer, and Imagick
The hosting specifications are not yet confirmed. Do not assume PHP or MySQL/MariaDB versions or the availability of SSH, Composer, or Imagick.

Document the minimum supported hosting requirements for the selected architecture. Verify compatibility with the hosting provider before finalising the Laravel version and deployment process. Provide alternatives if SSH, Composer, or Imagick is unavailable.

### Q5. Sinhala and Tamil
Use English as the initial MVP interface language. Design the application to support localisation later, including Sinhala and Tamil. Avoid hardcoding user-facing text in a way that makes future translation difficult.

### Q6. Phone number visibility
Allow sellers to control whether their phone number is visible publicly or only to logged-in users. Hide phone numbers by default until the seller explicitly chooses a visibility setting. Provide a WhatsApp contact option subject to the seller's privacy settings.

### Q7. Phone OTP verification
Do not require paid SMS OTP verification for the initial MVP. Use email verification and sensible anti-spam controls. Design authentication so phone verification can be added later. Do not integrate a paid SMS provider without approval of the provider and budget.

### Q8. Bot protection
Prefer Cloudflare Turnstile if the domain and hosting configuration support it. Verify whether the domain uses Cloudflare before implementation. If it is unavailable, document suitable alternatives and their trade-offs.

### Q9. Staff roles
Implement separate Owner/Super-admin, Admin, and Moderator roles.

- **Owner/Super-admin:** full control over marketplace settings, staff permissions, and administration.
- **Admin:** manage users, listings, categories, reports, and other authorised operational tasks.
- **Moderator:** review reported listings and moderate content with limited permissions.

Use role-based permissions and enforce them on the server. Design permissions so they can be expanded later.

### Q10. Internal messaging
Exclude internal messaging from the MVP. Launch with seller phone and WhatsApp contact options, controlled by seller privacy settings. Do not implement checkout, online orders, escrow, or transaction commissions. Buyers and sellers will arrange transactions directly.

Keep future messaging as a separate, extensible module.

### Q11. Categories and category-specific attributes
Support vehicles, land and property, spare parts, mobile phones, electronics, furniture, and other general buy/sell categories.

Review the proposed category tree and property attributes in §4.2.1 before finalising them. Use configurable categories and category-specific attributes rather than hardcoding separate database structures for every category. Present the recommended final category tree for approval. Do not add Jobs, Services, or Animals to the MVP without approval.

### Q12. Data retention
Propose a configurable retention policy for deleted accounts, listings, rejected listings, reports, and audit logs. Keep only the data necessary for operational, security, and legitimate legal requirements. Document deletion, anonymisation, backup retention, and account-deletion behaviour. Flag proposed retention periods for owner approval instead of treating unapproved periods as final.

### Q13. Frontend approach
Prefer Laravel Blade + Livewire for the initial MVP if the selected Laravel version and hosting environment support it. Prioritise SEO, responsive design, maintainability, performance, and compatibility with cPanel shared hosting.

Do not introduce React, Vue, or Inertia unless the existing project or verified hosting constraints justify the change. Document the trade-offs before proposing a different stack.

### Q14. Transactional email
Use the hosting provider's SMTP service for initial development if available. Evaluate a dedicated transactional email provider, such as Brevo, Mailgun, or Amazon SES, for production deliverability. Keep email configuration environment-based and never commit credentials. Confirm the final provider before production setup.

### Q15. Brand identity
Use a premium White + Black design with both light and dark themes, responsive layouts, accessible forms, consistent typography, and clean listing cards with controlled spacing.

Inspect the existing Dev Talk logo, fonts, and brand assets before creating new ones. Reuse suitable assets where appropriate and do not replace the existing site's branding without approval.

### Q16. Expected launch volume
The expected user and listing volume is not yet confirmed. Propose a reasonable initial hosting and performance plan for a small marketplace, clearly stating assumptions and resource limits. Include pagination, database indexing, image optimisation, caching where appropriate, and a future scaling path. Do not invent a confirmed user or listing target.

## Instructions for the next step

1. Update the open-questions document with these decisions and clearly label all unverified details.
2. Inspect the existing project and hosting-related configuration without changing production files or data.
3. Complete Phase 2 architecture documentation, including the system architecture, database ERD, category attributes, permissions, security, SEO, image uploads, and cPanel deployment design.
4. Separate confirmed decisions, assumptions, recommendations, and unresolved questions.
5. Present the proposed category tree, database design, hosting requirements, and architecture for my review.
6. Do not start implementation, install dependencies, modify production configuration, change DNS, or deploy anything until I approve the design.

Continue to follow the root `CLAUDE.md` and the existing SDLC workflow. Preserve the current Dev Talk blog and its data.

Read CLAUDE.md, the approved requirements and docs/02-system-design.md. Execute SDLC Phase 2 only. Produce the application architecture, database ERD, API contracts, authorization design, storage strategy and technical decisions. Do not begin implementation until the design is approved.