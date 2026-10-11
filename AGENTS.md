# Agent Guide

## Project

This is a Laravel 12 application using PHP 8.2+, Livewire 3, Vite, Tailwind CSS, MySQL, and database-backed sessions, cache, and queues.

The application includes ThaiD, ProviderID/HealthID, and MyMOPH authentication integrations. Treat all OAuth credentials, API keys, personal identifiers, and production data as sensitive.

## Local Setup

Run commands from the repository root:

```bash
composer install
npm install
```

Create `.env` from `.env.example` only when `.env` does not exist, then configure the local database and integration values. Never commit `.env` or expose its secrets.

The usual local database configuration is MySQL. When MySQL runs in Docker while Laravel runs on macOS, use `DB_HOST=127.0.0.1` and the host port published by Docker. When Laravel also runs inside Docker, use the Docker Compose service name as `DB_HOST` instead.

After the database is reachable:

```bash
php artisan migrate
php artisan storage:link
composer run dev
```

Open `http://localhost:8000`. `composer run dev` starts Laravel, the queue listener, and Vite together.

For a frontend production build:

```bash
npm run build
```

## Development Rules

- Follow Laravel conventions and keep changes within the owning controller, Livewire component, model, service, route, or view.
- Inspect nearby routes, Livewire components, and migrations before changing behavior.
- Preserve existing authentication and authorization checks, especially around ThaiD, ProviderID, and MyMOPH callbacks.
- Use migrations for schema changes and keep them reversible where practical.
- Every application table uses `utf8mb4` / `utf8mb4_unicode_ci`, including the
  shared legacy tables `person`, `data_promote`, `data_salary`, and `data_tax`.
  Keep new tables and columns on that collation: mixed collations stop joins on
  `cid` from using indexes and made `/admin/leaves` take seconds.
- Validate request input with Laravel validation. Prefer Eloquent and existing services over duplicated query logic.
- Do not log access tokens, client secrets, API keys, passwords, PIDs, or full personal profiles.
- Keep generated files, `vendor/`, `node_modules/`, and local environment files out of commits.
- Add or update focused tests for behavior changes.

### SSO personnel authorization

- Every SSO callback must authorize the identity against the exact `person.cid`
  value before creating or logging in a local `users` record.
- ProviderID/HealthID obtains the 13-digit CID from the decoded
  `access_token` JWT claim `scopes_detail.id_card` described in `docs/PROVIDER.md`.
  ThaiD uses the verified ID-token `pid`, and MyMOPH uses the user-info `cid`.
- Use `App\Services\SsoUserProvisioner` for the personnel lookup and account
  linking. A missing personnel record or ambiguous identity link must not
  create an account and must return a generic access-denied result.
- Preserve locally assigned `users.role` and `users.office` during SSO refresh.
  Never use remote `staff_type` or profile data to grant local permissions.

### Roles

- `users.role` is one of `AdminAccess::ROLES`: `SUPERADMIN`, `ADMIN`, `LEAVE`,
  `PROMOTE`, `FINANCE`, or null for a regular user.
- `SUPERADMIN` has every ability and sees every office. Existing `ADMIN`
  accounts were migrated to `SUPERADMIN` on 2026-10-11.
- `ADMIN` only opens `/admin/offices`, `/admin/users`, and `/admin/signatories`
  (`AdminAccess::ROLE_ABILITIES`), acting on every office there.
- On `/admin/users` only a `SUPERADMIN` may grant `SUPERADMIN` or change a
  `SUPERADMIN` account, and the last active `SUPERADMIN` cannot be demoted or
  disabled (`UserAccessService`). Use `AdminAccess::SUPERADMIN` / `ADMIN`
  constants instead of role string literals.

### Office scope

- The `offices` table (`code` 2 digits, `name`, `is_active`, `sort_order`) is
  the source of truth for office codes and names. `person.office_name` is a
  synced copy written from `offices`; never build office lists or validate
  office codes from `person`. Use `OfficeScope::options()` (pass
  `includeInactive: true` for filters), `OfficeScope::exists()`, and
  `OfficeScope::nameFor()`.
- Offices are deactivated, never deleted, and office `00` cannot be
  deactivated. Forms offer only active offices, except a record's current
  office. `/admin/offices` (ability `offices`) is limited to `SUPERADMIN`, `ADMIN`, and users
  of any role with office `00`; renaming an office updates `person.office_name`.
- Use `App\Support\OfficeScope` for office-limited admin screens: `SUPERADMIN` and
  users with `users.office = 00` see every office; other roles see only
  `person.office_id` equal to their `users.office`; a non-SUPERADMIN user without an
  office sees nothing.
- `/admin/personnel` is open to `SUPERADMIN`, `LEAVE`, `PROMOTE`, and `FINANCE`.
  Apply the scope to the list, office options, edit, save, delete, and Excel
  import (`PersonnelImportService` office scope).
- `/admin/payslips` is open to `SUPERADMIN` and `FINANCE` only. The list is scoped by
  joining `data_salary.cid` to `person`; an office-scoped import skips rows whose
  CID is not a person of the importer's office and reports the skipped count.
- On `/admin/tax-certificates` an office-scoped `FINANCE` user reaches only
  the monthly compensation tab; the Comptroller General certificate tab (list,
  edit, TXT import) and the สสจ. issuance tab are for `SUPERADMIN` and office `00`
  (`MonthlyCompensationService::isProvincialOffice`), enforced in every action.
- The same scope applies to `/admin/leaves` (`SUPERADMIN`, `LEAVE`) and
  `/admin/salary-promotions` (`SUPERADMIN`, `PROMOTE`): list, person search, edit,
  save, cancel/archive/delete, and imports. Records are scoped by joining their
  CID to `person.office_id`; imports skip out-of-office rows and report them.
- `/admin/salary-promotions` and its import are also limited by personnel type
  through `App\Support\PersonnelTypeScope` (`person.type_id`, types listed in
  `PersonnelTypeScope::TYPES`). A user must match both the office scope and one
  of their types in `user_personnel_types`; office `00` does not lift the type
  limit. `SUPERADMIN` is unrestricted, and a `PROMOTE` user without assigned types
  sees nothing and cannot add or import. `SUPERADMIN` and `ADMIN` assign types on
  `/admin/users` (`UserAccessService::updatePersonnelTypes`, audited). Other
  screens, including `/admin/personnel`, are not limited by type.
- The leave Google Sheet sync (`/admin/leave-sheet-sync`, ability `leave-sync`)
  writes leave for every office, so only unrestricted users (`SUPERADMIN` or `LEAVE`
  with office `00`) may open it or see its button on `/admin/leaves`.
- `/admin/signatories` (`SUPERADMIN`, `ADMIN`, `FINANCE`) has no office data; signatories are
  shared system-wide. Only unrestricted users (`SUPERADMIN`, `ADMIN`, or office `00`) may create,
  edit, or delete them; other `FINANCE` users with an office have read-only
  access, and a non-SUPERADMIN user without an office has no menu entry or access.

### Tax-certificate management

- The management route is `/admin/tax-certificates` (`admin.tax-certificates.index`).
  Enforce `AdminAccess::allows($user, 'tax-certificates')` in both the route
  and every state-changing Livewire action. Only `SUPERADMIN` and `FINANCE` have
  this ability.
- `data_tax` is the single source for the management screen, `/tax-certificate`,
  preview, and downloaded PDF. Its identity is `(CID, income_year)`; never
  delete records that are absent from one uploaded source file.
- TXT imports accept only a user-selected `TIS-620` or `UTF-8` encoding. Do not
  guess encoding, log source rows, full CIDs, tax identifiers, or personal tax
  amounts. Import diagnostics must mask CIDs.
- A user-facing certificate query must always constrain `data_tax.CID` to the
  authenticated user's `users.pid`, including preview and PDF endpoints.
- `data_tax.source` separates payers: `cgd` (Comptroller General TXT, the
  existing tab and import) and `pho` (provincial health office, issued from
  monthly compensation). The key is `(cid, income_year, source)`. The TXT import
  and the certificate tab read and write only `cgd`; never edit `pho` rows by
  hand, reissue them instead.
- Monthly compensation (`monthly_compensations`, tab "ค่าตอบแทนรายเดือน") is
  keyed by `FINANCE` within `OfficeScope`; a month keeps the office that keyed
  it (`office_id`). Items and the personnel types they apply to live in
  `App\Support\CompensationItems`; ลูกจ้างประจำ is never keyed. Writes go through
  `MonthlyCompensationService` / `MonthlyCompensationImportService`, which
  enforce office confirmation and year locks.
- Only `SUPERADMIN` and office `00` (`MonthlyCompensationService::isProvincialOffice`)
  set payer details, unconfirm offices, issue, and reopen a year
  (`CompensationCertificateIssuer`). Issuing totals every keyed month of the
  calendar year, keeps each person's `YYOOnnn` number (Buddhist year, office of the latest month, 3-digit running number per office), and locks the year;
  reopening clears every office confirmation but keeps issued certificates
  until the next issue.
- Preserve the existing A4 geometry and the issue-date signatory lookup (the signatory for the fiscal year of `date_report`, falling back to income year + 1) when
  changing certificate mapping or PDF rendering. Run the focused tax-certificate
  tests and `npm run build` for such changes.

## Frontend and Document Styling

- The frontend runtime is Tailwind CSS via Vite only. Do not add Bootstrap, SB Admin, jQuery, Bootstrap CDN links, or their JavaScript back into layouts, views, or bundles.
- Keep existing Bootstrap-like class names only where the Tailwind compatibility layer in `resources/css/app.css` already owns their styling; new UI should use Tailwind utilities or established app components.
- Use the application modal controller (`window.portalModal`) and `data-modal-close` for modal behavior, not Bootstrap modal APIs or `data-dismiss` attributes.
- Present confirmations, create, edit, and other dialog interactions as application modals; do not use browser `alert`, `confirm`, or `prompt` dialogs.
- Present successful mutation status through `window.portalToast`, not inline flash alerts. A successful action submitted from a modal must close that modal and then show its toast while remaining on the current page.
- Payslip preview and `/payslip/print-view/` must retain the same A4 geometry, scale, and fixed footer. The print view is intentionally self-styled under `.payslip-document`; do not add a third-party CSS dependency to it.
- Any change affecting payslip layout must preserve a one-page A4 PDF, keep both footer messages at the bottom of that page, and run the focused payslip layout test plus the relevant build.

## Verification

Before finishing a change, run the narrowest relevant checks. Common checks are:

```bash
php artisan test
php artisan route:list
php artisan config:clear
npm run build
```

If configuration, database, queue, or OAuth behavior is involved, verify the relevant local service is running and report any unavailable dependency instead of assuming it works.

## Deployment Notes

For a real web-server deployment, follow `docs/DEPLOYMENT_AAPANEL.md`. The web server document root must point to the project's `public/` directory. The full project directory should remain outside the public document root.

The repository contains `Dockerfile.txt`, but its build commands are not the authoritative local workflow. Check the current `composer.json`, `package.json`, and deployment environment before using that image definition; in particular, confirm the package manager and frontend build script match the current project.

## Existing References

- `MEMORY.md`: project decisions, environment facts, deployment steps, and open
  items. Read it with this guide and update it when a decision or open item
  changes.
- `SKILL.md`: ThaiD login development notes and authentication flow.
- `README.md`: scheduler and Docker notes.
- `composer.json`: PHP dependencies and development scripts.
- `package.json`: Vite scripts and JavaScript dependencies.
