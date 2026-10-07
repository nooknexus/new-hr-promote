# แผนดำเนินงานระบบจัดการใบรับรองการหักภาษี

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** ให้ `ADMIN` และ `FINANCE` จัดการและนำเข้า TXT ใบรับรองการหักภาษีจากกรมบัญชีกลาง โดยหน้า `/tax-certificate` และ PDF ใช้ข้อมูลเดียวกัน

**Architecture:** แยกตัวอ่าน/ตรวจ/บันทึก TXT เป็น `TaxCertificateTxtImportService` เพื่อไม่ให้ parser ผูกกับ Livewire และกำหนด access ผ่าน `AdminAccess` เดิม หน้าจัดการเป็น Livewire component ที่ใช้ modal และ toast ส่วนหน้าใบรับรองเดิมใช้ record `data_tax` เดียวกันเพื่อผูกค่าใน preview/PDF โดยไม่เปลี่ยน A4 layout

**Tech Stack:** Laravel 12, PHP 8.2+, Livewire 3, MySQL, Tailwind CSS, Pest

**Spec:** `docs/superpowers/specs/2026-10-02-tax-certificate-management-design.md`

## Global Constraints

- อนุญาตเฉพาะ `ADMIN` และ `FINANCE`; ทุก route และ Livewire action ต้องตรวจซ้ำด้วย `AdminAccess`
- ใช้ `data_tax` เดิมและคีย์ `(cid, income_year)`; ไม่มี migration ใหม่ และไม่ลบ records ที่ไม่อยู่ในไฟล์
- รับเฉพาะ TXT ไม่เกิน 5MB, delimiter `$`, และ 20 คอลัมน์ตาม specification; ผู้ใช้งานต้องเลือกรหัสอักขระ `TIS-620` (ค่าเริ่มต้นตามไฟล์เดิมของกรมบัญชีกลาง) หรือ `UTF-8` ก่อน preview/import เพราะบาง byte sequence แยกอัตโนมัติอย่างน่าเชื่อถือไม่ได้
- ห้าม log เนื้อหาไฟล์, CID เต็ม, เลขภาษี, หรือยอดเงินรายบุคคล
- ทุก dialog ใช้ application modal; สำเร็จแล้วปิด modal และใช้ `window.portalToast`; ห้าม browser dialog
- คง route names, authorization หน้า `/tax-certificate` และ A4 geometry ของ preview/PDF เดิม
- ห้ามใช้หรือทดสอบกับข้อมูลจริงในฐาน `providerid`

## Review Focus

- ไฟล์ TIS-620 ที่มีภาษาไทยต้องแปลงก่อน parse และชื่อที่ได้ต้องเป็น UTF-8 — ทดสอบใน Task 1
- จำนวนเงิน fixed-width ที่เป็นศูนย์ล้วน/มีศูนย์นำหน้าต้องไม่ถูกตีความผิด — ทดสอบใน Task 1
- แถวที่ CID ไม่ครบ 13 หลัก, มีคอลัมน์ไม่ครบ, วันที่ พ.ศ. ไม่จริง หรือรหัสประเภทนอก mapping ต้องไม่เขียนฐานข้อมูล — ทดสอบใน Task 1
- ไฟล์ที่มี `(cid, income_year)` ซ้ำต้องรายงานและไม่เลือกผลแบบ “แถวสุดท้ายชนะ” โดยเงียบ ๆ — ทดสอบใน Task 2
- FINANCE ต้องเรียก create/edit/delete/import ได้ แต่ LEAVE/PROMOTE ต้องถูกปฏิเสธแม้เรียก Livewire action โดยตรง — ทดสอบใน Task 3

## File Structure

- `app/Services/TaxCertificateTxtImportService.php` รับผิดชอบแปลง ตรวจ และบันทึกข้อมูล TXT โดยไม่ผูกกับ HTTP/Livewire
- `app/Livewire/Admin/TaxCertificateManagement.php` และ Blade view รับผิดชอบรายการ CRUD, upload flow, modal และ toast
- `app/Support/AdminAccess.php`, `routes/web.php` และ app layout เป็นจุดเดียวของสิทธิ์ route และเมนู
- `app/Livewire/TaxCertificate.php`, controller, PDF service และ view เดิม รับผิดชอบแสดงข้อมูล certificate ของผู้ใช้ที่มี CID ตรงเท่านั้น

---

### Task 1: สร้าง parser และตัวตรวจไฟล์ TXT ของกรมบัญชีกลาง

**Files:**
- Create: `app/Services/TaxCertificateTxtImportService.php`
- Test: `tests/Unit/TaxCertificateTxtImportServiceTest.php`

**Interfaces:**
- Consumes: temporary upload path และ encoding ที่ผู้ใช้เลือก
- Produces: `TaxCertificateTxtImportService::preview(string $path, string $encoding = 'tis-620'): array` ที่คืน `rows`, `errors`, `read`, `valid`, `skipped`; แต่ละ row มี `line`, `cid`, `income_year` และ field ชื่อเดียวกับ `data_tax`

- [ ] **Step 1: Write the failing parser tests** ใน `tests/Unit/TaxCertificateTxtImportServiceTest.php` ด้วย fixture literal 20 คอลัมน์ที่คั่น `$`; assert TIS-620 default และ UTF-8 เมื่อระบุ encoding, เงิน `000003155000` เป็น `31550.00`, วันที่ `29012568`, mapping ภ.ง.ด./กองทุน/วิธีออกเอกสาร, แถวคอลัมน์ไม่ครบ, CID ไม่ครบ 13 หลัก, วันที่ไม่จริง, รหัสนอก mapping และไฟล์ใหญ่เกิน 5MB
- [ ] **Step 2: Verify RED** โดยรัน `php artisan test tests/Unit/TaxCertificateTxtImportServiceTest.php`; คาดว่า FAIL เพราะ service ยังไม่มี
- [ ] **Step 3: Implement `TaxCertificateTxtImportService`** ใน `app/Services/TaxCertificateTxtImportService.php`; ตรวจ extension/size, อ่าน stream และแปลง encoding ที่ผู้ใช้เลือกเป็น UTF-8 ก่อน parse ด้วย `fgetcsv(..., '$')`, map 20 ฟิลด์, mask CID ใน error message, และคืน preview โดยไม่เขียน DB
- [ ] **Step 4: Verify GREEN** โดยรัน test file เดิม; ทุก parser/validation case ต้อง PASS
- [ ] **Step 5: Commit** เฉพาะ service และ unit test ด้วยข้อความ `feat: parse central tax certificate text files`

### Task 2: บันทึกข้อมูลภาษีแบบ upsert อย่างปลอดภัย

**Files:**
- Modify: `app/Services/TaxCertificateTxtImportService.php`
- Test: `tests/Feature/TaxCertificateTxtImportCommitTest.php`

**Interfaces:**
- Consumes: `TaxCertificateTxtImportService::preview(string $path): array`
- Produces: `TaxCertificateTxtImportService::commit(string $path, string $encoding = 'tis-620'): array` ที่คืน `read`, `inserted`, `updated`, `skipped`, `failed`, `errors`; commit ต้อง stream ทุกแถวที่อยู่ในไฟล์ ไม่ใช้ preview `rows` ที่ถูกจำกัดเพื่อป้องกัน memory
- Writes: `data_tax` fields และ `r_update`; identity คือ `(cid, income_year)`

- [ ] **Step 1: Write failing commit tests** สร้าง `data_tax` schema ทดสอบครบ 25 ฟิลด์; assert แถวใหม่ถูกเพิ่ม, แถวเดิม `(cid, income_year)` ถูกอัปเดต, record คน/ปีอื่นไม่ถูกลบ, ไฟล์ที่มี key ซ้ำถูก skip พร้อม error, แถวผิดไม่เขียน DB และ commit stream ได้ครบแม้ preview rows ถูกจำกัด
- [ ] **Step 2: Verify RED** โดยรัน `php artisan test tests/Feature/TaxCertificateTxtImportCommitTest.php`; คาดว่า FAIL เพราะ `commit` ยังไม่เขียนข้อมูล
- [ ] **Step 3: Implement `commit(string $path, string $encoding = 'tis-620'): array`** ให้ stream/parse ซ้ำทุกแถวด้วย encoding เดียวกับ preview (เก็บเฉพาะ chunk ที่กำลัง upsert และข้อผิดพลาดตามขอบเขต), ปฏิเสธ duplicate key ภายในไฟล์, query existing keys แบบ parameter binding, แล้ว `upsert` rows ที่ valid ใน transaction เดียว; กำหนด `r_update = now()` และห้าม query/SQL ที่ต่อจากเนื้อหาไฟล์
- [ ] **Step 4: Verify GREEN** โดยรัน test commit; ต้อง PASS และยังคง valid row import ได้เมื่อไฟล์มีแถวเสีย
- [ ] **Step 5: Commit** เฉพาะ service และ commit test ด้วยข้อความ `feat: upsert imported tax certificates`

### Task 3: เพิ่มสิทธิ์ route และเมนูจัดการใบรับรองภาษี

**Files:**
- Modify: `app/Support/AdminAccess.php`
- Modify: `routes/web.php`
- Modify: `resources/views/components/layouts/app.blade.php`
- Test: `tests/Feature/AdminTaxCertificateAccessTest.php`

**Interfaces:**
- Produces: ability `tax-certificates`; `ADMIN` และ `FINANCE` ได้รับอนุญาต
- Produces: route `admin.tax-certificates.index` ที่ `/admin/tax-certificates` พร้อม middleware `admin:tax-certificates`
- Consumed later by: `App\Livewire\Admin\TaxCertificateManagement`

- [ ] **Step 1: Write failing authorization tests** ตรวจ `AdminAccess` ให้ `ADMIN`/`FINANCE` ได้ ability, route มีชื่อและ middleware `admin:tax-certificates`, request จาก `LEAVE`/`PROMOTE` ถูก 403 ก่อน resolve component, และ sidebar ของ FINANCE มี route ใหม่ แต่ LEAVE ไม่มี; Task 4 จะทดสอบ authorized request ได้ HTTP 200 หลัง component ถูกสร้าง
- [ ] **Step 2: Verify RED** โดยรัน `php artisan test tests/Feature/AdminTaxCertificateAccessTest.php`; คาดว่า FAIL เพราะ ability/route/menu ยังไม่มี
- [ ] **Step 3: Implement central authorization** เพิ่ม ability ใน `AdminAccess`, route ใหม่ และ sidebar link; คง ability `signatories` เดิมของ FINANCE
- [ ] **Step 4: Verify GREEN** โดยรัน test เดิมและ `php artisan route:list --path=admin/tax-certificates`; ต้อง PASS และ route มี middleware ถูกต้อง
- [ ] **Step 5: Commit** เฉพาะ access/route/layout/test ด้วยข้อความ `feat: authorize tax certificate management`

### Task 4: สร้างหน้าจัดการ CRUD และนำเข้า TXT

**Files:**
- Create: `app/Livewire/Admin/TaxCertificateManagement.php`
- Create: `resources/views/livewire/admin/tax-certificate-management.blade.php`
- Test: `tests/Feature/AdminTaxCertificateManagementTest.php`

**Interfaces:**
- Consumes: `AdminAccess::allows(Auth::user(), 'tax-certificates')`, `TaxCertificateTxtImportService::preview()` และ `::commit()`
- Produces: `openCreateModal()`, `edit(string $cid, int $incomeYear)`, `save()`, `confirmDelete()`, `delete()`, `previewImport()`, `confirmImport()`, `closeModal()`
- Writes: one `data_tax` record identified by `(cid, income_year)`; all import mutations use Task 2 service

- [ ] **Step 1: Write failing Livewire tests** ครอบคลุม FINANCE add/edit/delete ด้วย CID/ปีภาษี, validation CID/ปี/ยอดเงิน, filters ปี/CID/ชื่อ, direct action denial สำหรับ role ที่ไม่มีสิทธิ์, preview upload `.txt` พร้อมเลือก `TIS-620` หรือ `UTF-8`, modal confirmation, summary counters/errors, cancel ที่ไม่เขียนข้อมูล, success toast และ modal ปิดหลัง save/import
- [ ] **Step 2: Verify RED** โดยรัน `php artisan test tests/Feature/AdminTaxCertificateManagementTest.php`; คาดว่า FAIL เพราะ component/view/actions ยังไม่มี
- [ ] **Step 3: Implement component and Blade view** ใช้ `WithFileUploads`, pagination, grouped form fieldsของ `data_tax`, strict validation, input เลือก encoding (`TIS-620` default/`UTF-8`), accessible app modal และ `window.portalModal`/`window.portalToast`; ส่ง encoding เดียวกันเข้า preview และ commit, แสดง CID แบบ mask ในรายชื่อข้อผิดพลาด import แต่แสดง CID เต็มเฉพาะข้อมูลที่ผู้ใช้ FINANCE ได้รับสิทธิ์จัดการ
- [ ] **Step 4: Verify GREEN** โดยรัน test เดิม; ทุก mutation คงอยู่หน้าเดิม, ปิด modal และแจ้ง toast
- [ ] **Step 5: Commit** เฉพาะ component/view/test ด้วยข้อความ `feat: manage and import tax certificates`

### Task 5: ผูกข้อมูลนำเข้ากับ preview และ PDF ใบรับรองเดิม

**Files:**
- Modify: `app/Livewire/TaxCertificate.php`
- Modify: `app/Http/Controllers/TaxCertificateController.php`
- Modify: `app/Services/TaxCertificatePdfService.php`
- Modify: `resources/views/livewire/tax-certificate.blade.php`
- Test: `tests/Feature/TaxCertificateDownloadTest.php`
- Test: `tests/Unit/TaxCertificatePdfServiceTest.php`

**Interfaces:**
- Consumes: full `data_tax` row loaded only with `CID = Auth::user()->pid` and selected `income_year`
- Produces: preview/PDF certificate payload containing payer name/tax ID/address, recipient name/tax ID/address, income form type, pension fund type, payment type, totals/text and date from the stored row
- Preserves: current A4 geometry, PDF route name, authorization filter and signatory lookup by `income_year`

- [ ] **Step 1: Write failing integration tests** insert an imported-style full `data_tax` row and assert preview/PDF payload renders its payer/recipient/form/fund/payment data, while a user cannot preview/download another CID and the selected-year/signatory behavior remains intact
- [ ] **Step 2: Verify RED** โดยรัน `php artisan test tests/Feature/TaxCertificateDownloadTest.php tests/Unit/TaxCertificatePdfServiceTest.php`; คาดว่า FAIL เพราะ code ยังใช้ hard-coded/current-user fields
- [ ] **Step 3: Implement record-to-certificate mapping** ดึงเฉพาะ full stored row, share mapping between Livewire/controller where practical, pass mapped values to existing preview/PDF markup and service; เปลี่ยนเฉพาะ dynamic values ไม่เปลี่ยน CSS A4 coordinates/lines
- [ ] **Step 4: Verify GREEN** โดยรัน tests เดิม; PDF ยังเป็นหน้า A4 เดียวและ preview ตรงกับข้อมูล record
- [ ] **Step 5: Commit** เฉพาะ mapping/view/PDF tests ด้วยข้อความ `feat: render imported tax certificate details`

### Task 6: ตรวจงานรวมและความเข้ากันได้

**Files:**
- Modify: เฉพาะไฟล์จาก Tasks 1–5 เมื่อพบ regression ที่พิสูจน์ได้
- Test: tests จาก Tasks 1–5 และ test ที่เกี่ยวข้องกับ `TaxCertificate`

**Interfaces:**
- Consumes: feature ทั้งหมดจาก Tasks 1–5
- Produces: data imported by FINANCE is viewable only by the matching user on `/tax-certificate`, while unauthorized management and malformed uploads remain blocked

- [ ] **Step 1: Write any missing regression test** จากการตรวจ integration; ห้ามเพิ่ม test ถ้าไม่มี observable gap
- [ ] **Step 2: Verify RED** เฉพาะเมื่อเพิ่ม regression test และบันทึกสาเหตุที่ล้มเหลว
- [ ] **Step 3: Apply the smallest integration fix** โดยไม่ขยาย scope หรือเปลี่ยน document layout
- [ ] **Step 4: Verify candidate** รัน `php artisan test`, `php artisan route:list`, `npm run build` และตรวจ `git diff --check`; แยก failure เดิมที่ไม่เกี่ยวข้องในรายงาน
- [ ] **Step 5: Commit** เฉพาะ integration fix (ถ้ามี) ด้วยข้อความ `test: verify tax certificate management integration`

## สถานะปัจจุบัน (4 ตุลาคม 2026)

Tasks 1–5 ถูกนำไปใช้ใน codebase แล้ว: parser/streaming upsert, RBAC และ route
ผู้ดูแล, CRUD/import ด้วย modal และ toast, และ mapping ข้อมูล record เดียวกันไปยัง
preview/PDF ของเจ้าของข้อมูล ส่วน Task 6 ไม่พบ observable integration gap ที่ต้อง
เพิ่ม regression test

ผลตรวจล่าสุด:

- focused tax-certificate suite: 65 tests, 779 assertions ผ่าน
- `php artisan test --compact`: 228 tests, 1,448 assertions ผ่าน
- `php artisan route:list --path=admin/tax-certificates`: พบ route
  `admin.tax-certificates.index`
- `npm run build`: ผ่าน (มีเพียงคำเตือน path ฟอนต์ THSarabun ที่ Vite ปล่อยให้
  resolve ตอน runtime)
- `git diff --check`: ผ่าน

รายละเอียดการใช้งานและข้อจำกัดปัจจุบันให้ดู
`docs/TAX_CERTIFICATE_MANAGEMENT.md`; เอกสารนั้นเป็นคู่มือส่งต่อที่ควรอัปเดตเมื่อ
เปลี่ยนพฤติกรรม ไม่ใช่ checklist ด้านบนซึ่งเก็บลำดับการออกแบบ/ดำเนินงานเดิม

## Plan Self-Review

- Spec coverage: Tasks 1–2 ครอบคลุม TXT/parser/upsert; Task 3 ครอบคลุม role/menu/route; Task 4 ครอบคลุม CRUD/modal/toast; Task 5 ครอบคลุมข้อมูลเดียวกันบน preview/PDF; Task 6 ครอบคลุม verification
- Type consistency: ทุก task ใช้ชื่อ ability `tax-certificates`, route `admin.tax-certificates.index`, service `TaxCertificateTxtImportService`, และ identity `(cid, income_year)` ตรงกัน
- Review focus: ความเสี่ยงทั้งห้าข้อถูกผูกเข้ากับ Task 1–3 แล้ว
- Proportion: แผนระบุ decision และ tests แต่ไม่ลงรายละเอียด implementation body
