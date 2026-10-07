# แผนดำเนินการระบบสิทธิ์แอดมินและจัดการผู้ใช้

> **สำหรับผู้ดำเนินงาน:** ต้องใช้ทักษะ `superpowers:subagent-driven-development` (แนะนำ) หรือ `superpowers:executing-plans` เพื่อทำตามแผนทีละงาน ใช้ช่องทำเครื่องหมาย (`- [ ]`) ติดตามความคืบหน้า

**เป้าหมาย:** เพิ่มการกำหนดสิทธิ์แอดมินแบบหนึ่งบทบาทต่อผู้ใช้ และหน้าจัดการผู้ใช้สำหรับ `ADMIN` โดยไม่แก้ข้อมูลตัวตนที่ระบบ OAuth เป็นเจ้าของ

**แนวทาง:** เก็บ `users.role` และ `users.office` แบบอนุญาตให้ว่างได้ กำหนดสิทธิ์ของแต่ละเส้นทางไว้ที่ส่วนกลาง แล้วตรวจทั้งระดับ route และการกระทำใน Livewire ย้ายสิทธิ์แอดมินเดิมมาที่ `role` เพียงครั้งเดียว หลังจากนั้นถือว่า `staff_type` เป็นข้อมูลจากผู้ให้บริการเท่านั้น หน้าจัดการผู้ใช้แก้ได้เฉพาะ role กับ office และบันทึกประวัติการเปลี่ยนแปลงแบบย่อ

**เทคโนโลยี:** Laravel 12, PHP 8.2+, Livewire 3, MySQL, Tailwind CSS, Pest

**เอกสารข้อกำหนด:** `docs/superpowers/specs/2026-10-01-admin-users-roles-and-promotion-excel-design.md`

## ข้อกำหนดร่วมทุกงาน

- ผู้ใช้หนึ่งคนมีได้หนึ่งบทบาท: `ADMIN`, `LEAVE`, `PROMOTE`, `FINANCE` หรือ `null`
- `ADMIN` เข้าได้ทุกหน้าแอดมิน; `LEAVE` เข้าหน้าจัดการลาและซิงค์ชีท; `PROMOTE` เข้าหน้าจัดการเลื่อนขั้นเงินเดือน; `FINANCE` เข้าหน้าจัดการผู้ลงนาม ส่วนหน้าจัดการบุคลากรและผู้ใช้จำกัดเฉพาะ `ADMIN`
- `users.office` เก็บรหัสตาม `person.office_id` เท่านั้น รอบนี้ยังไม่ใช้จำกัดการเข้าถึงข้อมูลตามหน่วยงาน
- ย้ายผู้ใช้เดิมที่ `staff_type = ADMIN` ไปเป็น `role = ADMIN` เพียงครั้งเดียว; หลังจากนั้นห้ามใช้ `staff_type` ตัดสินสิทธิ์
- ใช้ modal ของแอปสำหรับแก้ไขหรือยืนยัน และใช้ toast เมื่อบันทึกสำเร็จ; ห้ามเปิดให้แก้รหัส OAuth หรือรหัสผ่าน
- ห้ามทดสอบ migration หรือ import กับข้อมูลจริงในฐาน `providerid`

## จุดเสี่ยงที่ต้องตรวจ

- หลังย้ายข้อมูลแล้ว ผู้ใช้ที่ `staff_type = ADMIN` แต่ `role = null` ต้องถูกปฏิเสธ — ทดสอบในงาน 2
- การเรียก action ของ Livewire โดยตรงต้องถูกปฏิเสธ แม้ไม่เคยเปิด route ของหน้านั้น — ทดสอบในงาน 3
- รหัสหน่วยงานว่างหรือไม่อยู่ในระบบต้องไม่ถูกแปลงเป็นหน่วยงานอื่นโดยเงียบ ๆ — ทดสอบในงาน 4
- แอดมินสองคนลดสิทธิ์พร้อมกันต้องไม่ทำให้ระบบเหลือ `ADMIN` เป็นศูนย์ — ทดสอบในงาน 4
- การเข้าสู่ระบบ MyMOPH ที่อัปเดต `staff_type` ต้องไม่เปลี่ยน `role` หรือ `office` — ทดสอบในงาน 2

---

### Task 1: เพิ่มฟิลด์สิทธิ์ หน่วยงาน และประวัติการเปลี่ยนแปลง

**ไฟล์:**
- สร้าง: `database/migrations/2026_10_01_100000_add_role_and_office_to_users.php`
- สร้าง: `database/migrations/2026_10_01_100100_create_user_access_audits_table.php`
- แก้ไข: `app/Models/User.php`
- ทดสอบ: `tests/Feature/AdminUserAccessMigrationTest.php`

**ส่วนเชื่อมต่อ:**
- ใช้ข้อมูลเดิม: `users.id`, `users.staff_type` และ `person.office_id`
- ส่งมอบ: `users.role` (`varchar(20)`), `users.office` (`varchar(2)` ให้ตรงกับ `person.office_id`) ซึ่งเป็นค่าว่างได้ และตาราง `user_access_audits(actor_id,target_user_id,before_role,after_role,before_office,after_office,created_at)`

- [ ] **ขั้น 1: เขียนการทดสอบที่ยังไม่ผ่าน** ตรวจว่า migration เพิ่มสองฟิลด์ ย้ายเฉพาะผู้ใช้เดิมที่ `staff_type = ADMIN` ไม่ย้ายผู้ใช้ทั่วไป และย้อน migration ได้บนฐานทดสอบแยกต่างหาก
- [ ] **ขั้น 2: ตรวจ RED:** รัน `php artisan test tests/Feature/AdminUserAccessMigrationTest.php`; คาดว่าล้มเหลวเพราะยังไม่มี migration/ฟิลด์
- [ ] **ขั้น 3: ลงมือทำ:** สร้าง migration ทั้งสอง และให้ `User` อ่าน role/office ได้ แต่จัดการการกำหนดค่าผ่าน service ของหน้าจัดการผู้ใช้ ไม่เปิด mass assignment ทั่วไป การย้าย `ADMIN` ทำใน migration ไม่ใช่ callback ของ OAuth
- [ ] **ขั้น 4: ตรวจ GREEN:** รันการทดสอบเฉพาะไฟล์เดิม; ทุกกรณีต้องผ่าน
- [ ] **ขั้น 5: Commit:** เฉพาะไฟล์ของงาน 1 ด้วยข้อความ `feat: add user access fields and audit table`

### Task 2: กำหนดสิทธิ์ route จากตารางบทบาทส่วนกลาง

**ไฟล์:**
- สร้าง: `app/Support/AdminAccess.php`
- แก้ไข: `app/Http/Middleware/EnsureUserIsAdmin.php`
- แก้ไข: `routes/web.php`
- แก้ไข: `bootstrap/app.php` เฉพาะเมื่อจำเป็นต่อชื่อหรือพารามิเตอร์ middleware
- แก้ไข: `resources/views/components/layouts/app.blade.php`
- ทดสอบ: `tests/Feature/AdminRoleAccessTest.php`
- ทดสอบ: ไฟล์ทดสอบ route/เมนูแอดมินเดิมที่ยังสร้างผู้ใช้ทดสอบด้วย `staff_type` อย่างเดียว

**ส่วนเชื่อมต่อ:**
- ส่งมอบ: `AdminAccess::allows(?User $user, string $ability): bool` โดยมี ability คือ `users`, `personnel`, `leave`, `leave-sync`, `promotions`, `signatories`; route ส่ง ability ให้ middleware ในรูป `admin:<ability>`
- ผู้ใช้ส่วนเชื่อมต่อ: ทุก route แอดมิน, sidebar และตัวตรวจของ Livewire ในงาน 3

- [ ] **ขั้น 1: เขียนการทดสอบที่ยังไม่ผ่าน** ตรวจ role ทุกค่ากับ route ทุกหน้า รวม `null`/ค่าที่ไม่รู้จัก; ยืนยันว่า `staff_type = ADMIN` เพียงอย่างเดียวเข้าไม่ได้ และ sidebar แสดงเฉพาะลิงก์ที่มีสิทธิ์ เพิ่มกรณี MyMOPH เปลี่ยน `staff_type` โดยไม่กระทบ `role`/`office`
- [ ] **ขั้น 2: ตรวจ RED:** รัน `php artisan test tests/Feature/AdminRoleAccessTest.php`; คาดว่าล้มเหลวเพราะยังไม่มีตารางสิทธิ์ส่วนกลาง
- [ ] **ขั้น 3: ลงมือทำ:** เพิ่มตารางสิทธิ์ middleware ที่รับพารามิเตอร์ การกำหนด route และเงื่อนไข sidebar; คงชื่อ route เดิม และยังไม่กรองข้อมูลด้วย `users.office`
- [ ] **ขั้น 4: ตรวจ GREEN:** รันการทดสอบ role และ `php artisan test tests/Feature/AdminSalaryPromotionManagementTest.php tests/Feature/AdminLeaveManagementTest.php tests/Feature/AdminPersonnelManagementTest.php`; ปรับผู้ใช้จำลองใน test ให้มี `role = ADMIN` แล้วต้องผ่าน
- [ ] **ขั้น 5: Commit:** เฉพาะไฟล์ของงาน 2 ด้วยข้อความ `feat: enforce admin access by role`

### Task 3: ป้องกันทุกทางเข้าของ Livewire ฝั่งแอดมิน

**ไฟล์:**
- แก้ไข: `app/Livewire/Admin/PersonnelManagement.php`
- แก้ไข: `app/Livewire/Admin/LeaveManagement.php`
- แก้ไข: `app/Livewire/Admin/LeaveSheetSync.php`
- แก้ไข: `app/Livewire/Admin/SalaryPromotionManagement.php`
- แก้ไข: `app/Livewire/Admin/Signatories.php`
- ทดสอบ: `tests/Feature/AdminRoleLivewireTest.php`

**ส่วนเชื่อมต่อ:**
- ใช้: `AdminAccess::allows(?User $user, string $ability): bool` จากงาน 2
- ส่งมอบ: ตอบ 403 เมื่อผู้ไม่มีสิทธิ์เรียก mount, action ที่แก้ข้อมูล, ดาวน์โหลด หรือส่งข้อมูลแบบ stream โดยตรง ไม่ขึ้นกับการซ่อนเมนู

- [ ] **ขั้น 1: เขียนการทดสอบที่ยังไม่ผ่าน** เรียกแต่ละ component โดยตรงทั้งในฐานะผู้ไม่มีสิทธิ์และ role ที่ถูกต้อง ครอบคลุม action ที่เขียนข้อมูลอย่างน้อยหนึ่งรายการและทุก action ที่ดาวน์โหลด/stream; `FINANCE` แก้รายการเลื่อนขั้นไม่ได้ และ `PROMOTE` แก้ผู้ลงนามไม่ได้
- [ ] **ขั้น 2: ตรวจ RED:** รัน `php artisan test tests/Feature/AdminRoleLivewireTest.php`; คาดว่าพบ action ที่ยังข้ามการตรวจสิทธิ์ได้
- [ ] **ขั้น 3: ลงมือทำ:** ตรวจ role ตอน mount และที่ขอบเขตของทุก public action; ไม่อาศัย route middleware หรือการซ่อนปุ่มเพียงอย่างเดียว
- [ ] **ขั้น 4: ตรวจ GREEN:** รัน test นี้และ feature test เดิมของ component ที่แก้ไข; ต้องผ่าน
- [ ] **ขั้น 5: Commit:** เฉพาะไฟล์ของงาน 3 ด้วยข้อความ `fix: guard livewire admin actions by role`

### Task 4: สร้างหน้าจัดการ role และ office

**ไฟล์:**
- สร้าง: `app/Livewire/Admin/UserManagement.php`
- สร้าง: `resources/views/livewire/admin/user-management.blade.php`
- สร้าง: `app/Services/UserAccessService.php`
- แก้ไข: `routes/web.php`
- แก้ไข: `resources/views/components/layouts/app.blade.php`
- ทดสอบ: `tests/Feature/AdminUserManagementTest.php`

**ส่วนเชื่อมต่อ:**
- ส่งมอบ: `UserAccessService::update(int $actorId, int $targetUserId, ?string $role, ?string $office): void`; ล็อกผู้ใช้เป้าหมายและรายการ `ADMIN` ปัจจุบันใน transaction เดียว ตรวจ role/office ที่อนุญาต ป้องกันการลบสิทธิ์ `ADMIN` คนสุดท้าย และเขียน `user_access_audits` เฉพาะเมื่อค่ามีการเปลี่ยน
- ส่งมอบ: `UserManagement::edit(int $userId): void`, `save(): void`, `closeForm(): void`; route `admin.users.index` ที่ `/admin/users`

- [ ] **ขั้น 1: เขียนการทดสอบที่ยังไม่ผ่าน** ครอบคลุมสิทธิ์ `ADMIN` เท่านั้น การค้นหา/กรอง role และ office/แบ่งหน้า การเปิด-บันทึก-ปิด modal, toast, audit, role/office ที่ไม่ถูกต้อง, การรักษา `ADMIN` คนสุดท้ายรวมกรณีลดสิทธิ์พร้อมกัน และยืนยันว่าฟิลด์ OAuth ไม่เปลี่ยน
- [ ] **ขั้น 2: ตรวจ RED:** รัน `php artisan test tests/Feature/AdminUserManagementTest.php`; คาดว่าล้มเหลวเพราะยังไม่มี route/component/service
- [ ] **ขั้น 3: ลงมือทำ:** สร้าง service แบบ transaction และหน้า Livewire โดยใช้ `window.portalModal`/`window.portalToast` เดิม เลือกรหัส office จาก `person.office_id` ที่มีอยู่จริงเท่านั้น และแสดงข้อความชัดเจนว่ายังไม่บังคับสิทธิ์ตามหน่วยงาน
- [ ] **ขั้น 4: ตรวจ GREEN:** รัน test เฉพาะไฟล์เดิม; ต้องผ่าน
- [ ] **ขั้น 5: Commit:** เฉพาะไฟล์ของงาน 4 ด้วยข้อความ `feat: manage user roles and office metadata`

### Task 5: ตรวจการทำงานร่วมกันของระบบสิทธิ์

**ไฟล์:**
- แก้ไข: เฉพาะไฟล์ที่ระบุในงานก่อนหน้าเมื่อพบปัญหาการเชื่อมต่อ
- ทดสอบ: feature test ทั้งหมดที่ได้รับผลกระทบ

**ส่วนเชื่อมต่อ:**
- ใช้: ผลจากงาน 1–4
- ส่งมอบ: ชื่อ route เดิมยังใช้ได้ ไม่มีจุดตรวจสิทธิ์ด้วย `staff_type` และการทดสอบ PHP/frontend ผ่าน

- [ ] **ขั้น 1: เขียน regression test ที่ยังไม่ผ่าน** สำหรับกรณีข้าม component ซึ่งพบจากการตรวจ diff ทั้งหมด
- [ ] **ขั้น 2: ตรวจ RED:** รัน test เฉพาะกรณีและบันทึกผลที่ล้มเหลว (ข้ามคู่ขั้นนี้ได้เฉพาะเมื่อไม่พบช่องว่าง)
- [ ] **ขั้น 3: แก้ไขจุดเชื่อมต่อให้น้อยที่สุด** หากจำเป็น โดยไม่เปลี่ยนความหมายของข้อมูลตัวตนจาก OAuth
- [ ] **ขั้น 4: ตรวจ GREEN:** รัน `php artisan test`, `php artisan route:list` และ `npm run build`; แยกรายงานความล้มเหลวเดิมที่ไม่เกี่ยวข้อง และไม่อ้างว่าผ่านหากไม่มีผลรันจริง
- [ ] **ขั้น 5: Commit:** เฉพาะจุดแก้เชื่อมต่อ (ถ้ามี) ด้วยข้อความ `test: verify admin role integration`
