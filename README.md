# HR Promote

ระบบเว็บ Laravel สำหรับงานข้อมูลบุคลากรและเอกสารที่เกี่ยวข้อง เช่น โปรไฟล์ผู้ใช้ สลิปเงินเดือน หนังสือรับรองภาษี และการขอเลื่อนเงินเดือน โดยมีการเชื่อมต่อระบบยืนยันตัวตนภายนอกของ ThaiD, ProviderID/HealthID และ MyMOPH

## เทคโนโลยีหลัก

- Laravel 12 และ PHP 8.2+
- Livewire 3
- Vite 6, Tailwind CSS 4.3 และ JavaScript modules ของแอป
- MySQL
- TCPDF และ Dompdf สำหรับสร้างเอกสาร PDF (payslip ใช้ TCPDF)
- Pest/PHPUnit สำหรับทดสอบ

## ความต้องการของระบบ

ติดตั้งเครื่องมือเหล่านี้ก่อนเริ่มพัฒนา:

- PHP 8.2 ขึ้นไป พร้อม extensions ที่ Laravel ต้องใช้
- Composer
- Node.js และ npm
- MySQL หรือ MySQL ที่รันอยู่ใน Docker
- บริการภายนอกและ credentials สำหรับฟีเจอร์ OAuth ที่ต้องการใช้งาน

## การติดตั้งในเครื่อง

รันคำสั่งจากโฟลเดอร์ root ของโปรเจค:

```bash
composer install
npm install
```

ถ้ายังไม่มี `.env` ให้สร้างจากไฟล์ตัวอย่าง:

```bash
cp .env.example .env
php artisan key:generate
```

จากนั้นแก้ค่าใน `.env` ให้ตรงกับเครื่อง โดยเฉพาะ:

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ชื่อฐานข้อมูล
DB_USERNAME=ชื่อผู้ใช้
DB_PASSWORD=รหัสผ่าน
```

โปรเจคใช้ database-backed session, cache และ queue ดังนั้นต้องสร้างฐานข้อมูลและตรวจสอบว่า MySQL เชื่อมต่อได้ก่อนรัน migration:

```bash
php artisan migrate
php artisan storage:link
```

### กรณี MySQL อยู่ใน Docker

ถ้า Laravel รันบน macOS โดยตรง แต่ MySQL รันอยู่ใน Docker ให้ดู port ที่ publish ด้วย:

```bash
docker ps
```

ตัวอย่าง ถ้า Docker แสดง `0.0.0.0:3307->3306/tcp` ให้ใช้:

```env
DB_HOST=127.0.0.1
DB_PORT=3307
```

ถ้า Laravel รันอยู่ใน Docker network เดียวกับ MySQL ให้ใช้ชื่อ service ของ MySQL เป็น `DB_HOST` แทน `127.0.0.1`

## การรันระบบระหว่างพัฒนา

คำสั่งนี้จะเปิด Laravel server, queue listener และ Vite พร้อมกัน:

```bash
composer run dev
```

เปิดระบบที่ <http://localhost:8000>

ถ้าต้องการเปิดเฉพาะ frontend asset watcher:

```bash
npm run dev
```

สร้าง frontend assets สำหรับ production:

```bash
npm run build
```

## ฟีเจอร์หลัก

เส้นทางสำคัญของระบบประกอบด้วย:

- `/login` และ `/register`: เข้าสู่ระบบและลงทะเบียน
- `/`: หน้าหลักหลัง login
- `/profile`: ดูและแก้ไขข้อมูลของตนเองผ่าน modal
- `/profile/payslip`: ดูสลิปเงินเดือน
- `/profile/salary-promotion`: งานเลื่อนเงินเดือน
- `/tax-certificate`: หนังสือรับรองภาษี
- `/admin/tax-certificates`: จัดการและนำเข้าใบรับรองภาษี (เฉพาะ `ADMIN` และ `FINANCE`)
- `/provider-info`: ข้อมูล Provider
- `/leave-history`: ประวัติการลา เลือกดูแยกตามปีงบประมาณ
- `/admin/leaves`: จัดการประวัติการลาและเพิ่มรายการด้วยตนเอง (เฉพาะ ADMIN)
- `/admin/leave-sheet-sync`: ซิงค์ประวัติการลาจาก Google Sheet (เฉพาะ ADMIN)
- `/admin/users`: แก้ไขสิทธิ์และหน่วยงานจากแถวผู้ใช้ พร้อมเปิด/ปิดบัญชี (เฉพาะ ADMIN)

เส้นทาง callback ของระบบภายนอก ได้แก่ `/thaid/callback`, `/provider/callback` และ `/mymoph/callback` โดยค่าตั้งค่าต้องอยู่ใน `.env` ตามชื่อที่ระบุใน `.env.example`

การซิงค์ข้อมูลลาทำได้จากหน้า ADMIN โดยวาง URL ของ Google Sheets ที่ต้องการในช่องก่อนเริ่มแต่ละครั้ง รองรับลิงก์ชีตทั่วไปและ CSV export URL; ข้อมูลจะเก็บในฐานข้อมูลหลักของแอป (`providerid.leave_requests` และ `providerid.leave_types`) เท่านั้น บัญชีที่ server ใช้เปิดชีตต้องมีสิทธิ์อ่านข้อมูล ค่า `.env` ใช้กำหนดเฉพาะขนาด CSV สูงสุดและประเภทการลาที่ไม่ต้องนำเข้า

ทั้งการเพิ่มรายการเองและการซิงค์รองรับลาคลอดบุตร, ลาไปช่วยเหลือภริยาที่คลอดบุตร, ลาอุปสมบท และลาศึกษา หน้า `/leave-history` จะไม่แสดงการ์ดสรุปของสี่ประเภทนี้หากไม่มีรายการในปีงบประมาณที่เลือก

## บัญชีผู้ใช้และ SSO

Session มีอายุเมื่อไม่ได้ใช้งาน 420 นาที (7 ชั่วโมง) กำหนดด้วย `SESSION_LIFETIME=420` ใน `.env` หากเปลี่ยนค่าบนเครื่องที่ cache config อยู่ ให้รัน `php artisan config:clear`

ทุก SSO ใช้ `App\Services\SsoUserProvisioner` ตรวจเลขบัตร 13 หลักกับ `person.cid` ก่อนสร้างหรือเข้าสู่บัญชี ไม่ให้ข้อมูลภายนอกเปลี่ยนสิทธิ์ `users.role` หรือหน่วยงาน `users.office` ที่ผู้ดูแลกำหนดไว้

| SSO | แหล่งเลขบัตร | ฟิลด์ใน `users` |
| --- | --- | --- |
| ThaiD | `pid` จาก ID token ที่ตรวจสอบแล้ว | `pid`, `thaid_pid` |
| ProviderID / HealthID | `scopes_detail.id_card` จาก access-token JWT | `pid` |
| MyMOPH | `cid` จาก user-info | `pid` |

ทุกช่องทางเก็บ SHA-256 ของเลขบัตรใน `cid_hash` ด้วย ProviderID ยังเก็บ `provider_id` แต่ไม่อ่านหรือบันทึก `account_id` อีก

หน้า `/admin/users` บันทึกสิทธิ์และหน่วยงานทันทีเมื่อเลือกค่า การปิด/เปิดบัญชีต้องยืนยันผ่าน modal และมี audit ระบบไม่อนุญาตให้ปิดบัญชีตัวเองหรือทำให้ไม่มี ADMIN ที่เปิดใช้งานเหลืออยู่

บัญชีที่ `users.is_active = false` จะถูกปฏิเสธก่อนตรวจบุคลากร แม้เลขบัตรอยู่ใน `person` การปิดบัญชีล้าง session ในฐานข้อมูลและ remember token ของบัญชีนั้น และ middleware ปฏิเสธการเข้าถึงจาก session เดิมในการร้องขอถัดไป การเปิดบัญชีกลับไม่เปลี่ยนเลขบัตร สิทธิ์ หรือหน่วยงาน

สำหรับฐานข้อมูลเดิม ต้องรัน migration ก่อนใช้หน้าและโค้ดล็อกอินใหม่:

```bash
php artisan migrate --path=database/migrations/2026_10_06_150000_add_account_status_to_users.php
```

Migration สร้าง `is_active` โดยเปิดบัญชีเดิมทั้งหมด ลบคอลัมน์ `account_id` ที่เลิกใช้ และเพิ่มสถานะก่อน/หลังใน audit การ rollback จะถูกปฏิเสธหากมีบัญชีที่ปิดไว้หรือมี audit สถานะแล้ว และไม่สามารถคืนค่า Provider account ID ที่ถูกลบได้

การแก้ไขจาก `/profile` มีผลจนกว่าจะเข้าสู่ระบบใหม่ ซึ่งข้อมูลที่ SSO ส่งมาจะถูกนำมาแทนที่ ผู้ใช้ควรแก้ข้อมูลที่แอปภาครัฐต้นทางให้เป็นปัจจุบัน สำหรับที่อยู่ ThaiD ระบบแทนที่เมื่อได้รับค่าที่ไม่ว่าง หากไม่ส่งหรือส่งค่าว่างจะคงที่อยู่เดิมไว้ ปุ่มและเส้นทางอัปเดตข้อมูลจาก ThaiD/ProviderID แยกต่างหากถูกถอดออกแล้ว

## จัดการใบรับรองการหักภาษี

ผู้ใช้บทบาท `ADMIN` และ `FINANCE` ใช้หน้า `/admin/tax-certificates` เพื่อค้นหา
เพิ่ม แก้ไข ลบ และนำเข้าใบรับรองจากกรมบัญชีกลาง ข้อมูลทุกทางเก็บใน
`data_tax` เดียวกับที่ผู้ใช้ดูจาก `/tax-certificate` และ PDF จึงใช้ข้อมูลชุดเดียวกัน
โดยผู้ใช้ทั่วไปจะเห็นได้เฉพาะรายการที่ `data_tax.CID` ตรงกับ `users.pid` ของตนเอง

การนำเข้าเลือกไฟล์ `.txt` ขนาดไม่เกิน 5 MB ซึ่งมี `$` เป็นตัวคั่นและมี 20 คอลัมน์
ตามรูปแบบกรมบัญชีกลาง ก่อน preview และยืนยันนำเข้าผู้ดูแลต้องเลือกรหัสอักขระของไฟล์
ให้ตรงกับต้นทาง:

- `TIS-620` — ค่าเริ่มต้นสำหรับไฟล์ต้นทางแบบเดิม
- `UTF-8` — สำหรับไฟล์ที่ส่งออกเป็น UTF-8

ระบบไม่เดารหัสอักขระเอง เพราะข้อความภาษาไทยบางชุดแยก TIS-620 กับ UTF-8
โดยอัตโนมัติได้ไม่แน่นอน การนำเข้าจะ upsert ด้วยคีย์ `(CID, income_year)`:
รายการคนและปีเดิมถูกปรับปรุง รายการที่ไม่มีในไฟล์จะไม่ถูกลบ และแถวผิดรูปแบบหรือมี
key ซ้ำในไฟล์จะถูกข้ามพร้อมสรุปข้อผิดพลาดที่ปกปิดเลขบัตรประชาชน

รายละเอียดสำหรับผู้พัฒนาต่ออยู่ที่
[`docs/TAX_CERTIFICATE_MANAGEMENT.md`](docs/TAX_CERTIFICATE_MANAGEMENT.md)

## สถานะ UI และเอกสารล่าสุด

- Frontend runtime ใช้ Tailwind CSS ผ่าน Vite เท่านั้น ไม่โหลด Bootstrap, SB Admin, jQuery หรือ Bootstrap CDN
- Modal ใช้ `window.portalModal` และ `data-modal-close`; ห้ามเปลี่ยนกลับไปใช้ Bootstrap modal API หรือ jQuery
- `/profile/payslip` มี modal preview ขนาด A4 และ `/payslip/print-view/{id}` ใช้ geometry เอกสารเดียวกัน
- Payslip preview และ print view ใช้ footer แบบ fixed ที่ขอบล่างกระดาษ และ PDF ต้องเป็น A4 หน้าเดียว
- ลายเซ็นใน preview และ PDF ใช้ block ชิดขวากว้าง 85 มม. โดยข้อความทุกบรรทัดจัดกึ่งกลางภายใน block; รูปลายเซ็นกว้าง 12 มม. และเพิ่มช่องว่างระหว่างข้อความ 20%
- PDF ใช้ `App\Services\PayslipPdfService`; ลายเซ็นกำหนดความกว้างโดยไม่บังคับความสูง เพื่อรักษาอัตราส่วนภาพ และชื่อผู้ใช้จะใช้ `user->name` ก่อน fallback ไป `firstname`/`lastname`
- CSS เอกสาร print อยู่ใน `.payslip-document` และไม่ควรเพิ่ม third-party stylesheet กลับเข้าไป

การทดสอบ layout อยู่ที่ `tests/Unit/PayslipPdfLayoutTest.php` ครอบคลุมจำนวนหน้า A4, Bootstrap-free print view, สัดส่วนลายเซ็น, ชื่อผู้ใช้ใน PDF และตำแหน่ง signature block

## การนำขึ้น production

ขั้นตอนติดตั้งบนเซิร์ฟเวอร์ที่ใช้ aaPanel ดูที่ [`docs/DEPLOYMENT_AAPANEL.md`](docs/DEPLOYMENT_AAPANEL.md)

## Scheduler และคำสั่งดูแลไฟล์ PDF

ระบบมีคำสั่งลบไฟล์ PDF ชั่วคราวใน `public/temp` ที่เก่ากว่าจำนวนวันที่กำหนด:

```bash
php artisan temp:clean-pdf --days=1
```

บน production ให้เพิ่ม cron เพื่อเรียก Laravel Scheduler ทุกนาที:

```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

ตรวจสอบคำสั่ง Artisan และ route ที่มีอยู่ได้ด้วย:

```bash
php artisan list
php artisan route:list
```

## การทดสอบ

รัน test suite:

```bash
php artisan test

# ตรวจเฉพาะ payslip layout/PDF
php artisan test tests/Unit/PayslipPdfLayoutTest.php

# ตรวจ parser, การนำเข้า, สิทธิ์ผู้ดูแล และเอกสารภาษี
php artisan test tests/Unit/TaxCertificateTxtImportServiceTest.php \
  tests/Feature/TaxCertificateTxtImportCommitTest.php \
  tests/Feature/AdminTaxCertificateAccessTest.php \
  tests/Feature/AdminTaxCertificateManagementTest.php \
  tests/Feature/TaxCertificateDownloadTest.php \
  tests/Unit/TaxCertificatePdfServiceTest.php

# ตรวจ frontend production bundle
npm run build
```

หรือใช้ script ที่กำหนดไว้ใน Composer:

```bash
composer run test
```

## Deployment

สำหรับการติดตั้งบน web server:

1. วาง source code ของโปรเจคไว้บน server โดยไม่จำเป็นต้องย้ายไปไว้ใน `htdocs` หรือ public root โดยตรง
2. ตั้ง document root ของ web server ให้ชี้ไปที่โฟลเดอร์ `public/`
3. ตั้งค่า `.env` สำหรับ production และสร้าง `APP_KEY`
4. ติดตั้ง PHP dependencies และสร้าง frontend assets
5. รัน migration อย่างระมัดระวังตามขั้นตอน deployment
6. ตั้ง permission ให้ `storage/` และ `bootstrap/cache/` เขียนได้โดย process ของ web server
7. ตั้ง queue worker และ scheduler ตามความต้องการของระบบ

ตัวอย่างคำสั่ง build เบื้องต้น:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

ใน production ห้ามเปิด `APP_DEBUG=true` และห้ามนำ `.env`, credentials หรือข้อมูลผู้ใช้งานขึ้น repository

## Docker

ใน repository มี `Dockerfile.txt` สำหรับอ้างอิงการ build image แต่ควรตรวจสอบให้ตรงกับ `composer.json` และ `package.json` ปัจจุบันก่อนใช้งานจริง โดยเฉพาะชื่อไฟล์ Dockerfile, package manager และคำสั่ง build frontend

## โครงสร้างที่ควรรู้

- `app/Livewire/`: หน้าจอและพฤติกรรมของ Livewire
- `app/Http/Controllers/`: controller และ OAuth callback บางส่วน
- `app/Models/`: Eloquent models
- `app/Services/`: service logic เช่น การสร้าง payslip PDF
- `database/migrations/`: โครงสร้างฐานข้อมูล
- `resources/views/`: Blade views
- `routes/web.php`: web routes และ middleware
- `public/temp/`: ไฟล์ PDF ชั่วคราวที่ command สามารถลบได้
- `SKILL.md`: เอกสารแนวทางพัฒนาส่วน ThaiD
- `AGENTS.md`: แนวทางสำหรับ agent ที่พัฒนาต่อใน repository นี้

## ความปลอดภัย

ห้าม commit หรือแสดงค่าเหล่านี้ใน log และ issue:

- OAuth client secret และ API key
- access token และ refresh token
- รหัสผ่านฐานข้อมูล
- เลขประจำตัวประชาชนและข้อมูลส่วนบุคคล
- ข้อมูล production ในไฟล์ dump หรือไฟล์ PDF
