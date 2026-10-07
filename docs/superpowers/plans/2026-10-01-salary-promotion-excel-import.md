# แผนดำเนินการนำเข้า Excel ข้อมูลการเลื่อนขั้นเงินเดือน

> **สำหรับผู้ดำเนินงาน:** ต้องใช้ทักษะ `superpowers:subagent-driven-development` (แนะนำ) หรือ `superpowers:executing-plans` เพื่อทำตามแผนทีละงาน ใช้ช่องทำเครื่องหมาย (`- [ ]`) ติดตามความคืบหน้า

**เป้าหมาย:** นำเข้าไฟล์ Excel เลื่อนขั้นเงินเดือนรูปแบบเดิม พร้อมอัปเดตเลขตำแหน่งไปยังทั้ง `person.no_position` และ `users.no_position` เมื่อจับคู่บุคคลได้แน่ชัด

**แนวทาง:** แยก service สำหรับอ่านหัวตารางภาษาไทย ตรวจข้อมูล และแสดงตัวอย่างโดยยังไม่เขียนฐานข้อมูล เมื่อผู้ใช้ยืนยันให้อ่านและตรวจไฟล์อีกครั้ง ก่อนบันทึกรายการเลื่อนขั้น ข้อมูลบุคลากร และข้อมูลผู้ใช้ใน transaction เดียว Livewire component แยกต่างหากบน `/admin/salary-promotions` ดูแลการอัปโหลด ตัวอย่าง modal ยืนยัน และ toast ยึดตัวเลขเงินเดือนจาก Excel และเก็บค่าก่อน/หลังของเลขตำแหน่งใน audit

**เทคโนโลยี:** Laravel 12, Livewire 3, PhpSpreadsheet (มีอยู่ใน Composer), MySQL, Pest

**เอกสารข้อกำหนด:** `docs/superpowers/specs/2026-10-01-admin-users-roles-and-promotion-excel-design.md`

## ข้อกำหนดร่วมทุกงาน

- ทำหลังแผน `2026-10-01-admin-roles-and-user-management.md`; เฉพาะ `PROMOTE` หรือ `ADMIN` จึงดูตัวอย่างหรือยืนยันนำเข้าได้
- รับเฉพาะ `.xlsx` ที่ล็อกตำแหน่งคอลัมน์และแถวตามตัวอย่าง โดยไม่ขึ้นกับชื่อหัวตารางไม่เพิ่มระบบจับคู่คอลัมน์แบบกำหนดเอง ไม่สร้างบัญชีหรือข้อมูลบุคลากรใหม่
- เลขตำแหน่ง: อัปเดต `person.no_position` จาก `person.cid` ที่ตรงกันเท่านั้น และอัปเดต `users.no_position` เมื่อผลรวมของการจับคู่ `users.pid`/`users.thaid_pid` แบบตรงตัวระบุผู้ใช้ได้เพียงหนึ่งคน ถ้าช่องเลขตำแหน่งว่างให้คงค่าทั้งสองตาราง
- `data_promote.sum` และ `data_promote.new_salary` ต้องใช้ค่าจาก Excel ไม่คำนวณทับ
- หากไฟล์มีคู่ `(CID,evaluation_date)` ซ้ำ ตรงกับรายการที่ยกเลิก หรือจับคู่ผู้ใช้ได้หลายคน ต้องปฏิเสธทั้งไฟล์ก่อนบันทึก
- การยืนยันหนึ่งครั้งบันทึกรายการเลื่อนขั้น เลขตำแหน่ง และ `data_promote_audits` ใน transaction เดียว; ห้ามเหลือข้อมูลที่เขียนสำเร็จเพียงบางส่วน
- ใช้ modal ยืนยันและ toast ไม่ใช้ dialog ของเบราว์เซอร์; ห้ามทดสอบ import กับข้อมูลจริงในฐาน `providerid`

## จุดเสี่ยงที่ต้องตรวจ

- เลขตำแหน่งที่เป็นข้อความและขึ้นต้นด้วยศูนย์ใน Excel ต้องเก็บศูนย์หน้าไว้ — ทดสอบในงาน 2
- CID ที่ตรงทั้ง `users.pid` หรือ `users.thaid_pid` เพราะบางคนไม่ได้ใช้ ThaiD ล็อกอินจึงไม่มีค่านี้ในฟิลด์ดังกล่าวของผู้ใช้คนเดียวกัน ให้นับเป็นหนึ่งคน ไม่ใช่ข้อมูลกำกวม — ทดสอบในงาน 3
- ไฟล์ที่เปลี่ยนหลังดูตัวอย่างแต่ก่อนยืนยันต้องถูกอ่านใหม่ และปฏิเสธหากข้อมูลใหม่ผิด — ทดสอบในงาน 4
- เซลล์สูตรหรือเซลล์ error ในคอลัมน์บังคับของตัวเลข/CID ต้องถูกปฏิเสธ ไม่แปลงเป็นศูนย์ — ทดสอบในงาน 2
- หากบันทึก audit หรือเลขตำแหน่งล้มเหลวในช่วงท้าย transaction ต้องย้อนข้อมูลที่เปลี่ยนทั้งหมด — ทดสอบในงาน 3

---

### Task 1: เพิ่มที่เก็บเลขตำแหน่งในตารางบุคลากร

**ไฟล์:**
- สร้าง: `database/migrations/2026_10_01_100200_add_no_position_to_person_table.php`
- ทดสอบ: `tests/Feature/PersonPositionMigrationTest.php`

**ส่วนเชื่อมต่อ:**
- ใช้ข้อมูลเดิม: ตาราง `person` ซึ่งใช้ `cid` เป็นคีย์
- ส่งมอบ: `person.no_position` แบบว่างได้ (`varchar(255)` เท่ากับ `users.no_position`), migration ที่ย้อนกลับได้; ไม่แก้ฟิลด์ `users.no_position` เดิม

- [ ] **ขั้น 1: เขียนการทดสอบที่ยังไม่ผ่าน** ตรวจการเพิ่มฟิลด์ อ่าน/เขียนค่า และย้อน migration บนฐานทดสอบแยกต่างหาก โดยข้อมูล `person` เดิมต้องไม่หาย
- [ ] **ขั้น 2: ตรวจ RED:** รัน `php artisan test tests/Feature/PersonPositionMigrationTest.php`; คาดว่าล้มเหลวเพราะยังไม่มีฟิลด์
- [ ] **ขั้น 3: ลงมือทำ:** สร้าง migration ที่ย้อนกลับได้และใช้ชนิดข้อความที่รักษารูปแบบเลขตำแหน่งจาก Excel
- [ ] **ขั้น 4: ตรวจ GREEN:** รัน test เดิม; ต้องผ่าน
- [ ] **ขั้น 5: Commit:** ไฟล์ของงาน 1 ด้วยข้อความ `feat: store personnel position number`

### Task 2: อ่านและตรวจไฟล์ Excel รูปแบบเดิม

**ไฟล์:**
- สร้าง: `app/Services/SalaryPromotionImportService.php`
- ทดสอบ: `tests/Feature/SalaryPromotionImportParsingTest.php`

**ส่วนเชื่อมต่อ:**
- ส่งมอบ: `SalaryPromotionImportService::preview(string $path): array` คืนข้อมูลแต่ละแถวที่จัดรูปแบบแล้ว จำนวนเพิ่ม/ปรับปรุง/จับคู่ไม่พบ คำเตือน และข้อผิดพลาดรายแถว โดยไม่เขียนฐานข้อมูล; ปฏิเสธชนิดไฟล์ผิด หัวตารางบังคับหายหรือซ้ำ คู่ `(CID,evaluation_date)` ซ้ำ และการจับคู่ตัวตนที่ไม่ปลอดภัย งาน 3 จะเพิ่ม `commit` ใน service นี้
- ใช้ข้อมูลเดิม: หัวตารางภาษาไทย 17 ช่องตามเอกสารข้อกำหนด, PhpSpreadsheet, `data_promote` ทั้งรายการปกติและที่ยกเลิก, `person`/`users` ที่มีอยู่

- [ ] **ขั้น 1: เขียนการทดสอบที่ยังไม่ผ่านด้วยไฟล์จำลอง** ครอบคลุมช่องว่าง/NBSP ในหัวตาราง หัวตารางซ้ำหรือขาด CID 13 หลัก วันที่แบบ ISO และเซลล์วันที่ Excel ตัวเลขที่มีจุลภาค ช่องเสริมที่ว่าง เลขตำแหน่งข้อความที่ขึ้นต้นด้วยศูนย์ เซลล์สูตร/error คู่คีย์ซ้ำ รายการที่ยกเลิก จำนวนที่จับคู่ไม่พบ และคำเตือนเมื่อตัวเลขเงินเดือนไม่ตรงสูตร; การดูตัวอย่างต้องไม่เขียนข้อมูลใด
- [ ] **ขั้น 2: ตรวจ RED:** รัน `php artisan test tests/Feature/SalaryPromotionImportParsingTest.php`; คาดว่าล้มเหลวเพราะยังไม่มี service หรืออ่านข้อมูลไม่ครบ
- [ ] **ขั้น 3: ลงมือทำ:** สร้าง `preview(string $path): array` พร้อมแผนผังคอลัมน์ที่กำหนดชัดเจน คงตัวเลขเงินตามไฟล์ จำกัดจำนวนแถวตามตัวนำเข้าบุคลากรเดิม และห้ามบันทึกข้อมูลแต่ละแถวหรือ CID ลง log
- [ ] **ขั้น 4: ตรวจ GREEN:** รัน test เดิม; ต้องผ่าน
- [ ] **ขั้น 5: Commit:** ไฟล์ของงาน 2 ด้วยข้อความ `feat: preview salary promotion workbooks`

### Task 3: บันทึกรายการเลื่อนขั้นและเลขตำแหน่งสองตารางพร้อมกัน

**ไฟล์:**
- แก้ไข: `app/Services/SalaryPromotionImportService.php`
- ทดสอบ: `tests/Feature/SalaryPromotionImportCommitTest.php`

**ส่วนเชื่อมต่อ:**
- ใช้: ตัวอ่านและตรวจไฟล์จากงาน 2
- ส่งมอบ: `SalaryPromotionImportService::commit(string $path, int $actorId): array` คืนจำนวนเพิ่ม/ปรับปรุง/ไม่พบบุคลากร/ไม่พบผู้ใช้; ตรวจไฟล์ใหม่ ปฏิเสธข้อผิดพลาดทุกแถว แล้วบันทึกใน transaction เดียว

- [ ] **ขั้น 1: เขียนการทดสอบที่ยังไม่ผ่าน** ตรวจการเพิ่ม/ปรับปรุงตาม `(CID,evaluation_date)` การยึด `sum/new_salary` จาก Excel การอัปเดต `person.cid` แบบตรงตัว การรวมผลจับคู่ `users.pid`/`users.thaid_pid` ให้เหลือผู้ใช้คนเดียว กรณีหนึ่งคนตรงทั้งสองฟิลด์ กรณีไม่พบบุคลากร/ผู้ใช้ การจับคู่ผู้ใช้กำกวม ช่องเลขตำแหน่งว่าง ค่า audit ก่อน/หลัง รายการที่กลายเป็นยกเลิกหลังดูตัวอย่าง และการย้อนข้อมูลทั้งหมดเมื่อเขียน audit ล้มเหลวตอนท้าย
- [ ] **ขั้น 2: ตรวจ RED:** รัน `php artisan test tests/Feature/SalaryPromotionImportCommitTest.php`; คาดว่าล้มเหลวเพราะยังไม่มี `commit` หรือบันทึกไม่เป็นธุรกรรมเดียว
- [ ] **ขั้น 3: ลงมือทำ:** สร้าง `commit(string $path, int $actorId): array` ใช้ transaction เดียว ล็อกแถวที่จับคู่ได้ และเขียน `data_promote_audits` เดิม; ไม่สร้าง `person`/`users` ที่ไม่มีอยู่ ไม่จับคู่จากชื่อหรือ `cid_hash`
- [ ] **ขั้น 4: ตรวจ GREEN:** รัน test นี้และ `php artisan test tests/Feature/AdminSalaryPromotionManagementTest.php`; ต้องผ่าน
- [ ] **ขั้น 5: Commit:** ไฟล์ของงาน 3 ด้วยข้อความ `feat: commit salary import with position audit`

### Task 4: เพิ่มอัปโหลด ตัวอย่าง modal และ toast

**ไฟล์:**
- สร้าง: `app/Livewire/Admin/SalaryPromotionImport.php`
- สร้าง: `resources/views/livewire/admin/salary-promotion-import.blade.php`
- แก้ไข: `resources/views/livewire/admin/salary-promotion-management.blade.php`
- ทดสอบ: `tests/Feature/AdminSalaryPromotionImportTest.php`

**ส่วนเชื่อมต่อ:**
- ใช้: `AdminAccess::allows(?User $user, 'promotions')`, `SalaryPromotionImportService::preview(string $path): array` และ `::commit(string $path, int $actorId): array`
- ส่งมอบ: `SalaryPromotionImport::previewUpload(): void`, `confirmImport(): void`, `cancelImport(): void`; ฝัง component ใน route แอดมินเดิมโดยไม่เปลี่ยนชื่อ route

- [ ] **ขั้น 1: เขียนการทดสอบ Livewire ที่ยังไม่ผ่าน** ตรวจว่า `PROMOTE`/`ADMIN` ทำได้ แต่ `LEAVE`/`FINANCE` เรียก preview หรือ confirm โดยตรงไม่ได้ อัปโหลด `.xlsx` ได้ เห็นจำนวน/ข้อผิดพลาด/คำเตือน ยืนยันไม่ได้เมื่อไฟล์ผิด เปิด-ปิด modal ได้ ตรวจไฟล์ซ้ำก่อนยืนยัน ปฏิเสธไฟล์ที่เปลี่ยน แสดง toast เมื่อสำเร็จ และยกเลิกแล้วไม่เขียนข้อมูล
- [ ] **ขั้น 2: ตรวจ RED:** รัน `php artisan test tests/Feature/AdminSalaryPromotionImportTest.php`; คาดว่าล้มเหลวเพราะยังไม่มี component/action
- [ ] **ขั้น 3: ลงมือทำ:** เพิ่มอัปโหลดและหน้าตัวอย่างขนาดเหมาะสมตามรูปแบบ modal/toast เดิม เรียก service อีกครั้งตอนยืนยัน และแยกจำนวนไม่พบบุคลากรกับไม่พบผู้ใช้
- [ ] **ขั้น 4: ตรวจ GREEN:** รัน test เดิม; ต้องผ่าน
- [ ] **ขั้น 5: Commit:** ไฟล์ของงาน 4 ด้วยข้อความ `feat: add salary promotion Excel import UI`

### Task 5: ตรวจการทำงานร่วมกันของระบบนำเข้า

**ไฟล์:**
- แก้ไข: เฉพาะไฟล์ที่ระบุในงานก่อนหน้าเมื่อพบปัญหาการเชื่อมต่อ
- ทดสอบ: test ระบบนำเข้าและการเลื่อนขั้นเงินเดือนเดิมที่ได้รับผลกระทบ

**ส่วนเชื่อมต่อ:**
- ใช้: ผลจากงาน 1–4 และแผนระบบสิทธิ์ผู้ใช้
- ส่งมอบ: ฟอร์มเพิ่มรายการด้วยมือยังทำงานเดิม ตัวอย่างไม่เขียนข้อมูล การนำเข้าที่ได้รับการยืนยันบันทึกแบบธุรกรรมเดียว และผลตรวจระบบผ่าน

- [ ] **ขั้น 1: เขียน regression test ที่ยังไม่ผ่าน** สำหรับกรณีข้ามตารางหรือปฏิสัมพันธ์ UI ที่พบจากการตรวจ diff ทั้งหมด
- [ ] **ขั้น 2: ตรวจ RED:** รัน test เฉพาะกรณีและบันทึกผลที่ล้มเหลว (ข้ามคู่ขั้นนี้ได้เฉพาะเมื่อไม่พบช่องว่าง)
- [ ] **ขั้น 3: แก้ไขจุดเชื่อมต่อให้น้อยที่สุด** หากจำเป็น โดยไม่เปลี่ยนสูตรของฟอร์มกรอกด้วยมือ
- [ ] **ขั้น 4: ตรวจ GREEN:** รัน test ระบบนำเข้า, `php artisan test`, `php artisan route:list` และ `npm run build`; แยกรายงานความล้มเหลวเดิมที่ไม่เกี่ยวข้อง
- [ ] **ขั้น 5: Commit:** เฉพาะจุดแก้เชื่อมต่อ (ถ้ามี) ด้วยข้อความ `test: verify salary promotion import integration`
