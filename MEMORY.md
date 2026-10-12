# Project Memory

Working context for continuing development of MyPPH (`new-hr-promote`). Read
this together with `AGENTS.md`: `AGENTS.md` holds the rules every change must
follow; this file records decisions, environment facts, and open items gathered
while building the system. Keep it free of secrets, CIDs, names, and other
personal data.

Last updated: 2026-10-12 (through commit `32fddb4`).

## Working agreement

- Communicate with the project owner in Thai; code, identifiers, and commit
  messages stay in English.
- For non-trivial requests, propose a plan first and confirm open decisions
  before coding. After implementing, run the focused tests, the full suite, and
  `npm run build`, then report and wait for approval.
- The owner approves with "commit แล้ว push เข้า main". Work is committed
  directly on `main` (no PR). Push with:
  `git -c credential.helper= -c 'credential.helper=!gh auth git-credential' push origin main`
- Never commit `.env`, `Example/` (real payroll files, gitignored), `tmp/`,
  `scratch/`, `output/`, or `docs/assurance/leave-excel-import/`.
- After each push, give the owner the server deployment steps (below) and call
  out anything that needs manual follow-up.
- Run the full suite with `php -d memory_limit=1G vendor/bin/pest` (the default
  128M limit stops it in the Excel tests).
- Login is SSO only, so the agent cannot sign in to the app. Forging a session
  for a test user is blocked by the permission system; do not retry it. For
  visual checks, render a component to HTML with an in-memory user and open it
  at phone width, using fully synthetic data only (a "sanitized" copy of real
  data once leaked real amounts into a screenshot). Delete any file copied into
  `public/` right after the check.

## Environments

- Repository: `nooknexus/new-hr-promote`, branch `main`.
- Local: macOS, MySQL in Docker. Tests use SQLite `:memory:` and each feature
  test creates the legacy tables it needs.
- Production: aaPanel, PHP 8.3, site at
  `/www/wwwroot/mypph.plkhealth.go.th/new-hr-promote`, database `mypph_db`.
  The previous system's database is `hrpromote_plkhea`; legacy data was
  migrated from it.
- Linux production is case-sensitive: directory names must match PSR-4
  namespaces exactly (a `ThaID` vs `Thaid` mismatch once broke autoloading).
- aaPanel `open_basedir` can block Laravel bootstrap; see
  `docs/DEPLOYMENT_AAPANEL.md`.

### Deployment steps (run in the site directory)

```bash
git pull
php artisan migrate --force   # only when the release adds migrations
npm run build
php artisan optimize:clear && php artisan optimize
```

Then restart PHP 8.3 in aaPanel. Config changes (for example
`config/salary_ceilings.php`) need the `optimize:clear` step.

## Domain decisions

### Offices (ต้นสังกัด) — commit `f56ed96`

- `offices` is the registry; codes stay 2 digits. `person.office_name` is kept
  as a synced copy. Offices are deactivated, never deleted.
- `/admin/offices` is managed by `SUPERADMIN`, `ADMIN`, and users of any role in
  office `00`.
- The migration seeded `offices` from `person` and `users.office`. After
  deploying, verify names match with:

  ```sql
  SELECT p.office_id, p.office_name, o.name AS registry_name, COUNT(*) AS people
  FROM person p JOIN offices o ON o.code = p.office_id
  WHERE NOT (p.office_name <=> o.name)
  GROUP BY p.office_id, p.office_name, o.name;
  ```

- `data_promote.office` is a historical text snapshot from the import file. The
  promotion notice PDF still prints it; the admin list prefers
  `person.office_name`.

### Personnel types and salary-promotion access — commit `a1bf56a`

- Types (`person.type_id`): 1 ข้าราชการ, 2 ลูกจ้างประจำ, 3 พนักงานราชการ,
  4 พนักงานกระทรวง (`App\Support\PersonnelTypeScope::TYPES`).
- A `PROMOTE` user may hold several types (`user_personnel_types`); a PROMOTE
  user with none sees nothing. Type and office scopes both apply; office `00`
  does not lift the type limit. Only `/admin/salary-promotions` is
  type-scoped.
- After deploying this release, an `ADMIN` must assign types to every existing
  `PROMOTE` user on `/admin/users`, otherwise their promotion page is empty.

### Salary promotion formula — commit `8940014`

- Increase = `base × percent / 100`, rounded up to the next 10 baht
  (981.13 → 990).
- Ceilings by type and level live in `config/salary_ceilings.php`; the level is
  the import file's "ระดับ" column, falling back to `person.position_level`.
  ระดับปฏิบัติงาน uses 38,750 (the higher-than-maximum ceiling), not 21,010.
- At the ceiling: salary stays, `sum = 0`, and the unrounded increase becomes
  `extra` (เงินค่าตอบแทนพิเศษ) for that round only — it does not accumulate.
- Reaching the ceiling within a round: salary is cut at the ceiling, and the
  remainder (unrounded increase − amount added) becomes `extra`.
- Imports keep the file's figures and only warn on rows that disagree with the
  formula. Legacy rows are never recalculated.
- Logic is in `App\Services\SalaryPromotionCalculator`; the form and the import
  check both use it.

### Monthly compensation → สสจ. tax certificates — 2026-10-11

- Offices key monthly income and deductions so the provincial health office
  (สสจ.) can issue its own 50 ทวิ, separate from the Comptroller General's
  (`data_tax.source` `pho` vs `cgd`). Tax year = calendar year of the payment
  month, stored as a Buddhist year.
- Items by type: เงินเดือน/ค่าจ้าง (พนักงานราชการ, พนักงานกระทรวง); พ.ต.ส. and
  เงินเพิ่มไม่ทำเวชฯ (ข้าราชการ); ค.ต.ส. (พนักงานราชการ); ตกเบิก, อื่นๆ (name
  required), ภาษีหัก ณ ที่จ่าย (all three); ประกันสังคม (พนักงานราชการ,
  พนักงานกระทรวง); กองทุนสำรองเลี้ยงชีพพนักงานกระทรวงสาธารณสุข (พนักงานกระทรวง).
  ลูกจ้างประจำ is not keyed. Import rows that break these rules are skipped.
- Certificate: payer from the settings form, ภ.ง.ด.1ก พิเศษ, หัก ณ ที่จ่าย, all
  income as 40(1), recipient address from `users.address` or else the payer's.
  The fund prints as `กสล.พกส` because the full name overflows the PDF slot.
- Certificate numbers are 7 digits `YYOOnnn` (owner's format), e.g. 6701001 =
  year 67, office 01, running 001 per office per year. The office is the one
  that keyed the person's latest month. Reissuing keeps each number. Longer
  numbers wrap in the PDF number boxes; CGD numbers are 6 digits, so they never
  collide.
- `/admin/tax-certificates` tabs, in order: monthly (default), สสจ. issuance,
  CGD certificates. Office-scoped `FINANCE` users only see the monthly tab;
  the other two are for `SUPERADMIN` and office `00`. The CGD tab loads one tax
  year at a time (current year, else the latest year with data) and filters
  by the person's office.
- Monthly import: one file per month; rows with no amount at all are counted
  as "ไม่มียอด" and never overwrite a stored month.
- Workflow: offices confirm their year (locks them; สสจ. can unconfirm); สสจ.
  issues, with an extra confirmation when offices are unconfirmed (their months
  still count); reopening clears all confirmations.

### SUPERADMIN and limited ADMIN — 2026-10-11

- Owner's decision: a new `SUPERADMIN` role took over everything `ADMIN` could
  do; the migration `2026_10_11_150000_move_admins_to_superadmin` converted
  every existing `ADMIN`. `ADMIN` now only manages offices, users and
  signatories for every office.
- Only `SUPERADMIN` grants `SUPERADMIN` or changes a `SUPERADMIN` account; the
  last active `SUPERADMIN` is protected. Rolling the migration back refuses
  while limited `ADMIN` accounts exist.
- Older notes in this file that say `ADMIN` for full access mean `SUPERADMIN`.

### Salary promotion years — 2026-10-12

- A promotion year is the Buddhist year of the evaluation date, holding its
  April (ครั้งที่ 1) and October (ครั้งที่ 2) rounds: 2568 = เมษายน 2568 and
  ตุลาคม 2568. It is not the government fiscal year (Oct–Sep), even though the
  screens still call it "ปีงบประมาณ". Both `/salary-promotion` and the admin
  year filter use this rule.

### Personal record pages — 2026-10-12

- `/payslip` and `/salary-promotion` moved out of `/profile` (route names
  `payslip`, `salary-promotion`, `salary-promotion.download`); the old
  `/profile/...` URLs redirect with 301.
- On `/payslip`, `/tax-certificate`, `/salary-promotion` and `/leave-history`,
  choosing a year scrolls to the records on screens up to 1023px
  (`window.portalScrollToOnPhone` in `resources/js/app.js`). It adds bottom
  padding when the page is too short to scroll. Opening a page never scrolls:
  `mount()` must not call the public `selectYear()`, which dispatches
  `year-selected`.
- Below 768px these pages show one card per record (`md:hidden` list) and keep
  the table for wider screens (`hidden md:block`).

### Livewire `@script` pitfall — 2026-10-11

- Livewire 3.6 runs only the code inside `<script>` tags of an `@script` block
  and silently drops anything else. Several admin pages had lost their toasts
  and modal closing this way. `tests/Unit/LivewireScriptBlocksTest.php` now
  fails on any `@script` without a `<script>` tag.
- `app.js` only closes the leave form on `leave-saved`; the leave page shows
  the toast, so it is not shown twice.

### Earlier fixes worth remembering

- Payslip `monthNO` values from legacy data can be single-digit; lookups must
  normalise the month (`ffdc244`).
- Tax certificates are signed by the signatory of the fiscal year of
  `date_report` (`1ac0cf8`).
- LINE's in-app browser breaks OAuth state; login pages are opened in the
  external browser (`927da7f`).
- When users have no records, pages tell them which staff to contact instead
  of showing empty year selectors (`99adfc6`).

## Data facts (local snapshot, 2026-10-09)

- 18 offices, codes `00`–`85` in steps of 5, one name per code.
- `person`: 2,356 people; `position_level` is empty for all of them locally, so
  salary ceilings cannot apply in the form until levels are imported.
- `data_promote`: 9,621 rows have no matching person or no `type_id`; only
  `SUPERADMIN` sees them.
- `data_tax`: 7,172 CGD certificates (2564–2567), all with 6-digit numbers; no
  certificates for พนักงานราชการ or พนักงานกระทรวง before monthly compensation.
- Of 5,897 promotion rows since 2024, 5,232 match the rounding rule, 656 are
  ceiling cases, and 9 do not match.

## Open items

- The payer tax ID on every tax certificate PDF (CGD and สสจ.) overlaps the
  "(ให้กรอกกรณีเป็นผู้ไม่มีเลขประจำตัวประชาชน)" hint in the payer block. It
  predates the สสจ. certificates; fix it in `TaxCertificatePdfService` if wanted.
- Ceiling tables for ลูกจ้างประจำ, พนักงานราชการ, and พนักงานกระทรวง are not yet
  supplied (legacy data shows some capped at 54,820). Until added, these types
  round without a ceiling and the form shows a warning.
- Level names in import files must match the config keys (the "ระดับ" prefix
  and spaces are ignored). Add aliases if files use abbreviations.
- The full suite exceeds PHP's default 128M memory in the Excel-writing tests;
  it passes with `php -d memory_limit=1G vendor/bin/pest`. Consider setting
  `memory_limit` in `phpunit.xml`.
- The four personal record pages (scroll after choosing a year, phone cards)
  have not been checked on a real phone. Only `/tax-certificate` was rendered
  at 375px.
- After deploying `32fddb4`: former ADMIN accounts are SUPERADMIN; สสจ. must
  enter the payer details before issuing the first year; PROMOTE users still
  need their personnel types assigned.
- Re-run the salary import preview on the real file after deployment to confirm
  that warnings drop to the genuinely wrong rows.
