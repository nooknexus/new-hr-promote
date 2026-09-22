# HR Promote

ระบบเว็บ Laravel สำหรับงานข้อมูลบุคลากรและเอกสารที่เกี่ยวข้อง เช่น โปรไฟล์ผู้ใช้ สลิปเงินเดือน หนังสือรับรองภาษี และการขอเลื่อนเงินเดือน โดยมีการเชื่อมต่อระบบยืนยันตัวตนภายนอกของ ThaiD, ProviderID/HealthID และ MyMOPH

## เทคโนโลยีหลัก

- Laravel 12 และ PHP 8.2+
- Livewire 3
- Vite, Tailwind CSS และ jQuery
- MySQL
- TCPDF และ Dompdf สำหรับสร้างเอกสาร PDF
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
- `/profile`: ดูข้อมูลโปรไฟล์
- `/profile/{id}/edit`: แก้ไขโปรไฟล์
- `/profile/payslip`: ดูสลิปเงินเดือน
- `/profile/salary-promotion`: งานเลื่อนเงินเดือน
- `/tax-certificate`: หนังสือรับรองภาษี
- `/provider/profile/update`: เชื่อมต่อเพื่ออัปเดตข้อมูลจาก Provider
- `/provider-info`: ข้อมูล Provider
- `/training-info`: ข้อมูลการฝึกอบรม

เส้นทาง callback ของระบบภายนอก ได้แก่ `/thaid/callback`, `/provider/callback`, `/provider/profile/callback` และ `/mymoph/callback` โดยค่าตั้งค่าต้องอยู่ใน `.env` ตามชื่อที่ระบุใน `.env.example`

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
https://docs.google.com/document/d/1oRNVwrCe-StLwCCpKY-LsQzNzZ5qxd1z3m65uAJ3T-E/edit?tab=t.0

วิธีตรวจสอบว่า Schedule ทำงานถูกต้อง
เพิ่ม Cron Job บนเซิร์ฟเวอร์เพื่อให้ Laravel Scheduler ทำงาน:
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1


ทดสอบ Command โดยตรง:
php artisan temp:clean-pdf --days=1

Deploy
docker build -t provider-test .

Run
docker run -d -p 8080:8080 provider-test