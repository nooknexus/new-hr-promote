# Admin user access and salary-promotion Excel import

Date: 2026-10-01

## Objective and scope

Provide an administrator-only user-management page and import existing salary-promotion Excel workbooks from `/admin/salary-promotions`. Keep OAuth-owned identity fields intact. Implement role-based access immediately; store an office code for future office-scoped authorization without claiming that office isolation exists in this release.

This release does not create or delete user accounts, edit passwords or OAuth identifiers, update `person`, or introduce general-purpose permission tables or configurable spreadsheet mappings.

## Chosen approach

Use one nullable `users.role` value per user and a centralized, explicit route/component authorization map. This is smaller and easier to audit than a permission-package migration. Keep `staff_type` as provider-origin personnel data rather than an authorization input, because MyMOPH updates it on login.

Use the supplied Thai Excel headers with a validation-and-preview step, then one confirmed database transaction. This is safer than importing rows immediately or recalculating imported salary figures with a formula that does not reproduce the workbook's rounded amounts.

## User fields, access, and management

- Add nullable `users.role` with allowed non-null values `ADMIN`, `LEAVE`, `PROMOTE`, and `FINANCE`. Null is an ordinary user. Backfill `role = ADMIN` only for existing users whose `staff_type` is `ADMIN`, preserving the current administrator. Do not grant a role automatically from `staff_type` on future logins.
- Add nullable `users.office` containing a canonical `person.office_id` code selected from existing offices. It is metadata only for now: do not filter queries or authorize access by office in this release. Show this limitation clearly on the page.
- Authorization map: `ADMIN` may access all admin pages; `LEAVE` may access leave management and Google Sheet leave sync; `PROMOTE` may access salary-promotion management and Excel import; `FINANCE` may access signatory management. Personnel and user management remain `ADMIN` only. All other admin pages deny users with no matching role.
- Enforce the map both on routes and on every Livewire entry/action, including upload, download, and streamed actions. The sidebar mirrors the same map but is not itself an authorization boundary.
- Add `/admin/users` with search, role/office filters, pagination, and an edit modal for role and office only. Name, email, provider identifiers, and password are not editable here. A successful save closes the modal and shows the application toast. Prevent removal of the last `ADMIN` role and record each role/office change with actor, target, before/after values, and time, without storing full identity profiles in audit records.

## Excel import contract

Accept `.xlsx` using the existing PhpSpreadsheet dependency. Match the supplied header labels after trimming whitespace, including non-breaking spaces; reject missing required or duplicated recognized headers. Dates may be ISO text or valid Excel date cells. Parse numeric amounts with grouping commas and blank optional cells. Never log uploaded rows or personal identifiers.

| Excel column | Destination |
| --- | --- |
| เลขบัตรประชาชน | `data_promote.CID` |
| รอบการประเมิน | `data_promote.evaluation_date` |
| เลื่อน/N | `data_promote.promote` |
| หมายเหตุ | `data_promote.ps` |
| ส่วนราชการ | `data_promote.office` |
| กลุ่มงาน | `data_promote.department` |
| เงินเดือนก่อนเลื่อน | `data_promote.before_salary` |
| ฐานในการคำนวณ | `data_promote.base` |
| ร้อยละที่ได้เลื่อน | `data_promote.percent` |
| เงินที่ได้เลื่อน | `data_promote.sum` |
| เงินค่าตอบแทนพิเศษ | `data_promote.extra` |
| เงินเดือนที่ได้รับ | `data_promote.new_salary` |
| เลขตำแหน่ง | `users.no_position`, when one existing user can be matched safely |

`ชื่อ - นามสกุล` is for preview/context only. `รหัส`, `ตำแหน่ง`, and `ระดับ` have no destination in this release and are not persisted. Do not create a `person` or `users` row from spreadsheet text.

Match an existing promotion by `(CID, evaluation_date)`: insert a new row or update an active row. Reject a match to an archived row rather than silently restoring it. Reject duplicate keys within the uploaded workbook. Use the imported `sum` and `new_salary` as authoritative; do not overwrite them with `before_salary + base × percent ÷ 100`. A disagreement among the workbook's financial fields appears as a preview warning, not an automatic recalculation. Apply the same numeric/range validation used by the manual form where compatible with imported historical data.

For `no_position`, match the 13-digit CID exactly against `users.pid` or `users.thaid_pid`. Update only if the union of those matches identifies exactly one existing user. If there is no user match, leave `users` untouched and report the unmatched count; do not block salary import. If multiple users match, reject the workbook before committing to avoid changing the wrong account. Never infer identity from a name or the provider-specific `cid_hash`.

The page shows a preview of insert/update/unmatched-user counts and row-level errors, followed by an application confirmation modal. A file with validation errors cannot be confirmed. Confirming revalidates the file and applies promotion and matched-user changes in one transaction. Audit each promotion create/update and show a toast with final counts. No partial write is left if the transaction fails.

## Verification and boundaries

Use test-first feature tests for all role/route/Livewire access cases, last-admin protection, OAuth login retaining role, Excel header/number/date parsing, duplicate and archived-row handling, preview without writes, user-position matching, and transaction rollback. Verify the existing salary-promotion and personnel flows remain unchanged, then run the full PHP test suite and Vite build. Existing unrelated failures must be reported separately, not hidden.

Do not run the importer against production or the local `providerid` data during development verification; use isolated test databases and synthetic workbooks. Applying the schema migration to a real database is a separate deployment step after review.
