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

### Office scope

- Use `App\Support\OfficeScope` for office-limited admin screens: `ADMIN` and
  users with `users.office = 00` see every office; other roles see only
  `person.office_id` equal to their `users.office`; a non-ADMIN user without an
  office sees nothing.
- `/admin/personnel` is open to `ADMIN`, `LEAVE`, `PROMOTE`, and `FINANCE`.
  Apply the scope to the list, office options, edit, save, delete, and Excel
  import (`PersonnelImportService` office scope).
- `/admin/payslips` is open to `ADMIN` and `FINANCE` only. The list is scoped by
  joining `data_salary.cid` to `person`; an office-scoped import skips rows whose
  CID is not a person of the importer's office and reports the skipped count.
- The same scope applies to `/admin/leaves` (`ADMIN`, `LEAVE`),
  `/admin/salary-promotions` (`ADMIN`, `PROMOTE`), and
  `/admin/tax-certificates` (`ADMIN`, `FINANCE`): list, person search, edit,
  save, cancel/archive/delete, and imports. Records are scoped by joining their
  CID to `person.office_id`; imports skip out-of-office rows and report them.
- The leave Google Sheet sync (`/admin/leave-sheet-sync`, ability `leave-sync`)
  writes leave for every office, so only unrestricted users (`ADMIN` or `LEAVE`
  with office `00`) may open it or see its button on `/admin/leaves`.
- `/admin/signatories` (`ADMIN`, `FINANCE`) has no office data; signatories are
  shared system-wide. Only unrestricted users (`ADMIN` or office `00`) may create,
  edit, or delete them; other `FINANCE` users with an office have read-only
  access, and a non-ADMIN user without an office has no menu entry or access.

### Tax-certificate management

- The management route is `/admin/tax-certificates` (`admin.tax-certificates.index`).
  Enforce `AdminAccess::allows($user, 'tax-certificates')` in both the route
  and every state-changing Livewire action. Only `ADMIN` and `FINANCE` have
  this ability.
- `data_tax` is the single source for the management screen, `/tax-certificate`,
  preview, and downloaded PDF. Its identity is `(CID, income_year)`; never
  delete records that are absent from one uploaded source file.
- TXT imports accept only a user-selected `TIS-620` or `UTF-8` encoding. Do not
  guess encoding, log source rows, full CIDs, tax identifiers, or personal tax
  amounts. Import diagnostics must mask CIDs.
- A user-facing certificate query must always constrain `data_tax.CID` to the
  authenticated user's `users.pid`, including preview and PDF endpoints.
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

- `SKILL.md`: ThaiD login development notes and authentication flow.
- `README.md`: scheduler and Docker notes.
- `composer.json`: PHP dependencies and development scripts.
- `package.json`: Vite scripts and JavaScript dependencies.
