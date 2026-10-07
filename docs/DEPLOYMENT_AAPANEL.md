# คู่มือนำ HR Promote ขึ้น production ด้วย aaPanel

คู่มือนี้ใช้กับเซิร์ฟเวอร์ Linux ที่ติดตั้ง [aaPanel](https://www.aapanel.com/) และใช้
Nginx + PHP-FPM + MySQL/MariaDB เมนูของ aaPanel แต่ละเวอร์ชันอาจเรียกชื่อต่างกันเล็กน้อย
ให้ยึดตามหลักในแต่ละขั้นตอน

> `Dockerfile.txt` ในโปรเจกต์ไม่ใช่วิธี deploy หลัก ให้ใช้คู่มือนี้แทน

## สารบัญ

1. [สิ่งที่ต้องเตรียม](#1-สิ่งที่ต้องเตรียม)
2. [ติดตั้งซอฟต์แวร์ใน aaPanel](#2-ติดตั้งซอฟต์แวร์ใน-aapanel)
3. [ตั้งค่า PHP](#3-ตั้งค่า-php)
4. [สร้างฐานข้อมูล](#4-สร้างฐานข้อมูล)
5. [สร้างเว็บไซต์](#5-สร้างเว็บไซต์)
6. [นำโค้ดขึ้นเซิร์ฟเวอร์](#6-นำโค้ดขึ้นเซิร์ฟเวอร์)
7. [ตั้งค่า .env](#7-ตั้งค่า-env)
8. [ติดตั้งและเตรียมระบบ](#8-ติดตั้งและเตรียมระบบ)
9. [ฟอนต์ภาษาไทยสำหรับ PDF](#9-ฟอนต์ภาษาไทยสำหรับ-pdf)
10. [Cron](#10-cron)
11. [กำหนดผู้ดูแลระบบคนแรก](#11-กำหนดผู้ดูแลระบบคนแรก)
12. [ตรวจสอบหลังติดตั้ง](#12-ตรวจสอบหลังติดตั้ง)
13. [การอัปเดตเวอร์ชันครั้งถัดไป](#13-การอัปเดตเวอร์ชันครั้งถัดไป)
14. [แก้ปัญหาที่พบบ่อย](#14-แก้ปัญหาที่พบบ่อย)

ในคู่มือใช้ค่าตัวอย่างดังนี้ ให้เปลี่ยนเป็นค่าจริง

| ค่าตัวอย่าง | ความหมาย |
|---|---|
| `hr.example.go.th` | โดเมนของระบบ |
| `/www/wwwroot/hr.example.go.th` | โฟลเดอร์โปรเจกต์ (aaPanel สร้างให้ตามชื่อโดเมน) |
| `/www/server/php/82/bin/php` | PHP 8.2 ของ aaPanel (ถ้าใช้ 8.3 เปลี่ยน `82` เป็น `83`) |
| `providerid` | ชื่อฐานข้อมูล |

---

## 1. สิ่งที่ต้องเตรียม

- เซิร์ฟเวอร์ที่ติดตั้ง aaPanel แล้ว และเข้าผ่าน SSH ได้
- โดเมนที่ชี้ DNS มายัง IP ของเซิร์ฟเวอร์แล้ว (จำเป็นสำหรับ SSL)
- สิทธิ์อ่าน repository บน GitHub (deploy key หรือ personal access token แบบอ่านอย่างเดียว)
- credential ของ ThaiD, ProviderID/HealthID และ MyMOPH สำหรับโดเมน production
  - redirect URI ของแต่ละระบบต้องลงทะเบียนเป็นโดเมนจริง ดูข้อ [7](#7-ตั้งค่า-env)
- **ถ้ามีฐานข้อมูลเดิมอยู่แล้ว:** สำรองฐานข้อมูลก่อนเริ่มทุกครั้ง
  - เมนู **Database → Backup** หรือ `mysqldump`
  - migration ของรอบนี้แปลงตาราง `person`, `data_promote`, `data_salary`, `data_tax` เป็น utf8mb4

## 2. ติดตั้งซอฟต์แวร์ใน aaPanel

เมนู **App Store** ติดตั้งรายการต่อไปนี้

| ซอฟต์แวร์ | เวอร์ชัน | หมายเหตุ |
|---|---|---|
| Nginx | ล่าสุดที่มีให้เลือก | |
| PHP | 8.2 หรือ 8.3 | Laravel 12 ต้องการ PHP 8.2 ขึ้นไป |
| MySQL หรือ MariaDB | MySQL 8.0 / MariaDB 10.6+ | พัฒนาและทดสอบบน MariaDB 10.3 แต่แนะนำเวอร์ชันที่ยังได้รับการดูแล |
| Node.js version manager | Node.js 20 LTS | ใช้ตอน `npm run build` (Vite 6 ต้องการ Node 18 ขึ้นไป) |
| phpMyAdmin | ไม่บังคับ | |

ติดตั้ง Composer ผ่าน SSH ถ้ายังไม่มี ขั้นตอนนี้ต้องใช้ `sudo` เพราะเขียนลง `/usr/local/bin`

```bash
cd /tmp
/www/server/php/82/bin/php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo /www/server/php/82/bin/php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f composer-setup.php
/www/server/php/82/bin/php /usr/local/bin/composer --version
```

- ถ้าไม่มีสิทธิ์ `sudo` ให้ติดตั้งไว้ใน `~/bin` แทน (`--install-dir=$HOME/bin`) แล้วใช้ path นั้นแทน `/usr/local/bin/composer` ในทุกขั้นตอน
- ใช้ `sudo` เฉพาะตอนติดตั้ง Composer อย่ารัน `composer install` ด้วย `sudo` เพราะไฟล์ใน `vendor/` จะเป็นของ root
- เรียก Composer ผ่าน PHP เต็ม path ทุกครั้ง เพราะ `php` ใน PATH อาจไม่ใช่เวอร์ชันเดียวกับเว็บไซต์

## 3. ตั้งค่า PHP

เปิด **App Store → PHP-8.2 → Setting**

### 3.1 ติดตั้ง extension

แท็บ **Install extensions** ติดตั้งตัวที่ยังไม่ได้ติดตั้ง

- `fileinfo` (aaPanel ไม่ได้ติดตั้งให้เป็นค่าเริ่มต้น แต่ Laravel และการอัปโหลดไฟล์ต้องใช้)
- `opcache`
- `intl`
- `redis` ไม่จำเป็น (โปรเจกต์ใช้ฐานข้อมูลเก็บ session, cache และ queue)

extension ต่อไปนี้มักติดตั้งมาแล้ว ให้ตรวจด้วย `php -m`: `pdo_mysql`, `mbstring`, `openssl`,
`curl`, `zip`, `gd`, `xml`, `dom`, `iconv`, `bcmath`, `ctype`, `tokenizer`

```bash
/www/server/php/82/bin/php -m | grep -iE "fileinfo|pdo_mysql|mbstring|openssl|curl|zip|gd|xml|dom|iconv|intl|opcache"
```

- PhpSpreadsheet (นำเข้า Excel) ใช้ `zip`, `xml`, `gd`, `iconv`
- การนำเข้า TXT ภาษาไทยแบบ TIS-620 ใช้ `iconv`

### 3.2 ปลดฟังก์ชันที่ถูกปิด

แท็บ **Disabled functions** ลบชื่อเหล่านี้ออกจากรายการ

| ฟังก์ชัน | ใครใช้ |
|---|---|
| `putenv` | Laravel / Composer |
| `proc_open` | Composer, `php artisan` บางคำสั่ง |
| `symlink` | `php artisan storage:link` |

ให้คง `exec`, `shell_exec`, `system`, `passthru` ไว้ในรายการปิดตามเดิม การแปลงฟอนต์ PDF (ข้อ 9) ทำผ่าน SSH ไม่ต้องเปิด `exec` ให้เว็บ

### 3.3 ค่าการตั้งค่า

แท็บ **Configuration** (หรือแก้ `php.ini`)

| ค่า | ตั้งเป็น | เหตุผล |
|---|---|---|
| `upload_max_filesize` | `20M` | นำเข้าสลิปและเลื่อนขั้นได้ถึง 10 MB, Livewire รับได้ถึง 12 MB |
| `post_max_size` | `25M` | ต้องมากกว่า `upload_max_filesize` |
| `max_execution_time` | `300` | การซิงค์การลาจาก Google Sheets และการนำเข้าไฟล์ใหญ่ |
| `memory_limit` | `512M` | อ่าน Excel หลายพันแถว |
| `date.timezone` | `Asia/Bangkok` | |

กด **Save** แล้ว **Restart** PHP

## 4. สร้างฐานข้อมูล

เมนู **Database → Add database**

- **Database name / User / Password:** ตั้งตามต้องการ
- **Charset:** `utf8mb4`
- **Access permission:** `Local server`

ทุกตารางของแอปใช้ `utf8mb4_unicode_ci` ถ้าใช้ฐานข้อมูลเดิมที่มีตาราง `person`, `data_*` อยู่แล้ว
ไม่ต้องสร้างใหม่ ให้สำรองข้อมูลก่อน แล้วใช้ฐานข้อมูลนั้นต่อ migration จะแปลง charset ให้เอง

## 5. สร้างเว็บไซต์

### 5.1 เพิ่มเว็บไซต์

เมนู **Website → Add site**

- **Domain:** `hr.example.go.th`
- **PHP version:** PHP-82
- **Database:** ไม่ต้องสร้าง (สร้างไว้แล้วในข้อ 4)

### 5.2 ตั้งค่าเว็บไซต์

คลิกชื่อเว็บไซต์ → **Site directory**

- **Running directory:** เลือก `/public` แล้ว Save
  - **สำคัญมาก:** ห้ามให้เว็บเปิดโฟลเดอร์โปรเจกต์ตรง ๆ ไม่อย่างนั้นไฟล์ `.env` จะถูกเปิดอ่านได้จากภายนอก
- **Site directory** ต้องเป็นรากของโปรเจกต์ (โฟลเดอร์ที่มี `artisan`, `vendor/`, `.env`) **ไม่ใช่** `.../public`
  - ถ้าตั้ง Site directory เป็น `public` aaPanel จะจำกัด open_basedir ไว้แค่ `public/`
  - Laravel จะอ่าน `vendor/autoload.php` ไม่ได้ และขึ้น `open_basedir restriction in effect`
- **Anti-XSS attack (open_basedir):** เปิดไว้ได้เมื่อ Site directory ถูกต้อง ถ้ายังเจอ error `open_basedir restriction in effect` ให้ปิดตัวเลือกนี้แล้ว Restart PHP
  - ถ้ายังไม่หาย ให้ลบ `.user.ini` ที่ค้างใน `public/` ด้วย `sudo chattr -i public/.user.ini && sudo rm -f public/.user.ini`

**URL rewrite:** เลือกชุด `laravel5` แล้ว Save ซึ่งจะได้

```nginx
location / {
    try_files $uri $uri/ /index.php$is_args$query_string;
}
```

**SSL:** เลือก **Let's Encrypt** ติ๊กโดเมน กด Apply แล้วเปิด **Force HTTPS**

**Config file** (ไม่บังคับ แนะนำ): เพิ่มใน block `server` เพื่อกันไฟล์ซ่อนและเพิ่มขนาดอัปโหลด

```nginx
client_max_body_size 25m;

location ~ /\.(?!well-known) {
    deny all;
}
```

## 6. นำโค้ดขึ้นเซิร์ฟเวอร์

aaPanel สร้างโฟลเดอร์ที่มีไฟล์ `index.html` และ `.user.ini` ไว้ให้ ต้องล้างโฟลเดอร์ก่อน clone

```bash
cd /www/wwwroot/hr.example.go.th
chattr -i .user.ini 2>/dev/null; rm -f .user.ini index.html 404.html .htaccess
git clone -b main https://github.com/nooknexus/new-hr-promote.git .
```

- ถ้า repo เป็น private ให้ใช้ deploy key หรือ token แบบอ่านอย่างเดียว ห้ามใช้บัญชีส่วนตัว
- **ห้ามคัดลอกโฟลเดอร์ `Example/`, `scratch/`, `tmp/` หรือไฟล์ `.env` จากเครื่องพัฒนาขึ้นเซิร์ฟเวอร์** มีข้อมูลเงินเดือนและข้อมูลทดลอง
- ถ้าลบ `.user.ini` ไปแล้วต้องการ open_basedir ให้เปิดตัวเลือก Anti-XSS ในข้อ 5.2 ใหม่ aaPanel จะสร้างไฟล์ให้

## 7. ตั้งค่า .env

```bash
cp .env.example .env
chmod 640 .env
```

แก้ `.env` (`nano .env`) อย่างน้อยค่าต่อไปนี้

```env
APP_NAME="HR Promote"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hr.example.go.th
APP_LOCALE=th

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=providerid
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_LIFETIME=420
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

**ค่าที่ห้ามลืม**
- `APP_DEBUG=false` เสมอ ถ้าเป็น `true` หน้า error จะแสดงข้อมูลลับและข้อมูลบุคคล
- `SESSION_SECURE_COOKIE=true` ใช้ได้เมื่อเปิด HTTPS แล้วเท่านั้น

### ค่าเชื่อมต่อ SSO

กรอกตามที่ได้รับจากผู้ให้บริการ (ชื่อตัวแปรดูได้ใน `.env.example`)

| ระบบ | ตัวแปร | redirect URI ที่ต้องลงทะเบียน |
|---|---|---|
| ThaiD | `THAID_CLIENT_ID`, `THAID_CLIENT_SECRET`, `THAID_API_KEY`, `THAID_SCOPE`, `THAID_WELL_KNOWN_URL`, `THAID_ISSUER`, `THAID_REDIRECT_URI` | `https://hr.example.go.th/thaid/callback` |
| ProviderID / HealthID | `HEALTHID_CLIENT_ID`, `HEALTHID_SECRET_KEY`, `PROVIDER_CLIENT_ID`, `PROVIDER_SECRET_KEY`, `PROVIDER_AUTH_URL`, `PROVIDER_REDIRECT_URI` | `https://hr.example.go.th/provider/callback` |
| MyMOPH | `MYMOPH_CLIENT_ID`, `MYMOPH_CLIENT_SECRET`, `MYMOPH_REDIRECT_URI` | `https://hr.example.go.th/mymoph/callback` |

- redirect URI ใน `.env` ต้องตรงกับที่ลงทะเบียนไว้กับผู้ให้บริการทุกตัวอักษร รวม `https` และ `/` ท้าย URL
- ถ้าไม่ตั้ง `PROVIDER_REDIRECT_URI` หรือ `MYMOPH_REDIRECT_URI` ระบบจะใช้ `APP_URL` ต่อด้วย `/provider/callback` หรือ `/mymoph/callback`
- รายละเอียด ProviderID ดู `docs/PROVIDER.md` และ ThaiD ดู `SKILL.md`
- เซิร์ฟเวอร์ต้องออกอินเทอร์เน็ตไปยัง `imauth.bora.dopa.go.th`, `moph.id.th`, `auth.moph.go.th`, `docs.google.com` และ `calendar.google.com` ได้

### ค่าการซิงค์การลา

`LEAVE_SHEET_MAX_BYTES`, `LEAVE_SYNC_EXCLUDED_TYPES` ใช้ค่าตาม `.env.example` ได้

## 8. ติดตั้งและเตรียมระบบ

รันในโฟลเดอร์โปรเจกต์ ใช้ PHP ของ aaPanel ให้ตรงกับเวอร์ชันของเว็บไซต์

```bash
cd /www/wwwroot/hr.example.go.th
PHP=/www/server/php/82/bin/php

# 1) PHP dependencies (ไม่ติดตั้ง debugbar และเครื่องมือพัฒนา)
$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction

# 2) สร้าง APP_KEY เฉพาะการติดตั้งครั้งแรก
$PHP artisan key:generate --force

# 3) Frontend assets
npm ci
npm run build

# 4) ฐานข้อมูล (สำรองก่อนถ้าเป็นฐานข้อมูลเดิม)
$PHP artisan migrate --force

# 5) ลิงก์ storage
$PHP artisan storage:link

# 6) ฟอนต์ PDF ดูข้อ 9

# 7) cache สำหรับ production
$PHP artisan optimize

# 8) สิทธิ์ไฟล์ (aaPanel รัน PHP-FPM ด้วยผู้ใช้ www)
mkdir -p public/temp storage/app/livewire-tmp
chown -R www:www /www/wwwroot/hr.example.go.th
find storage bootstrap/cache public/temp -type d -exec chmod 775 {} \;
find storage bootstrap/cache public/temp -type f -exec chmod 664 {} \;
chmod 640 .env
```

**ข้อควรรู้**
- `key:generate` ห้ามรันซ้ำบนระบบที่ใช้งานแล้ว เพราะ session ที่เข้ารหัสไว้จะใช้ไม่ได้ทั้งหมด
- `npm run build` ทำบนเครื่องอื่นได้ ถ้าเซิร์ฟเวอร์ไม่มี Node.js ให้ build ในเครื่อง แล้วอัปโหลดโฟลเดอร์ `public/build/` ขึ้นไปแทน
- `php artisan optimize` สร้าง cache ของ config, route, view และ event
  - หลังแก้ `.env` ทุกครั้งต้องรัน `php artisan optimize` ใหม่ ไม่อย่างนั้นระบบยังใช้ค่าเดิม

**Queue worker: ข้ามได้ ยังไม่ต้องทำ**

ตอนนี้โค้ดยังไม่ส่งงานเข้า queue เลย จึงยังไม่ต้องตั้ง worker

> **ห้ามพิมพ์คำสั่ง `queue:work` เองใน SSH** คำสั่งนี้รันค้างตลอดเพื่อรอ job ใหม่ หน้าจอจะดูเหมือนค้าง ถ้าเผลอรันไปแล้วให้กด `Ctrl + C` เพื่อหยุด ไม่มีผลเสียกับระบบ

ถ้าอนาคตมี job ค่อยติดตั้ง **Supervisor Manager** จาก App Store แล้วให้ Supervisor รันคำสั่งนี้เบื้องหลังด้วยผู้ใช้ `www`

```
/www/server/php/82/bin/php /www/wwwroot/hr.example.go.th/artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

## 9. ฟอนต์ภาษาไทยสำหรับ PDF

สลิปเงินเดือนและหนังสือรับรองภาษีใช้ฟอนต์ TCPDF ชื่อ `thsarabunnew` และ `thsarabunnewb`
ซึ่งต้องแปลงจากไฟล์ใน `storage/fonts/` ไปไว้ใน `vendor/tecnickcom/tcpdf/fonts/`

- ไฟล์ที่แปลงแล้วอยู่ใน `vendor/` จึงไม่ได้มากับ git
- **ต้องแปลงใหม่ทุกครั้งที่ `composer install` ติดตั้ง TCPDF ใหม่** ถ้าไม่แปลง PDF จะสร้างไม่ได้ หรือใช้ฟอนต์สำรองที่แสดงภาษาไทยไม่สวย

```bash
cd /www/wwwroot/hr.example.go.th
/www/server/php/82/bin/php vendor/tecnickcom/tcpdf/tools/tcpdf_addfont.php -b -t TrueTypeUnicode \
  -i "storage/fonts/THSarabunNew.ttf,storage/fonts/THSarabunNew Bold.ttf"

ls vendor/tecnickcom/tcpdf/fonts/ | grep thsarabunnew
```

ต้องเห็นไฟล์ทั้ง 6 ไฟล์

```
thsarabunnew.ctg.z  thsarabunnew.php  thsarabunnew.z
thsarabunnewb.ctg.z thsarabunnewb.php thsarabunnewb.z
```

## 10. Cron

เมนู **Cron → Add Task** ประเภท **Shell Script** และผู้ใช้ `www` ถ้ามีให้เลือก

### งานที่ 1: Laravel scheduler (ทุก 1 นาที)

ตอนนี้ยังไม่มีงานที่ตั้งเวลาไว้ในโค้ด แต่ควรตั้งไว้สำหรับงานที่เพิ่มในอนาคต

```bash
cd /www/wwwroot/hr.example.go.th && /www/server/php/82/bin/php artisan schedule:run >> /dev/null 2>&1
```

### งานที่ 2: ลบ PDF ชั่วคราว (ทุกวัน เช่น 02:00)

ไฟล์ PDF ที่สร้างให้ผู้ใช้ดาวน์โหลดเก็บไว้ใน `public/temp/` และเปิดผ่าน URL ได้ จึงต้องลบเป็นประจำ
คำสั่งนี้ยังไม่ได้อยู่ใน scheduler จึงต้องตั้ง cron แยก

```bash
cd /www/wwwroot/hr.example.go.th && /www/server/php/82/bin/php artisan temp:clean-pdf --days=1 >> /dev/null 2>&1
```

## 11. กำหนดผู้ดูแลระบบคนแรก

บัญชีใหม่ทุกบัญชียังไม่มี role จึงไม่เห็นเมนูผู้ดูแล ครั้งแรกต้องกำหนดผู้ดูแลจาก SSH

1. ให้ผู้ดูแลเข้าสู่ระบบผ่าน SSO หนึ่งครั้ง เพื่อให้ระบบสร้างบัญชีใน `users`
   - เลขบัตรประชาชนต้องมีอยู่ในตาราง `person` ก่อน ไม่อย่างนั้นระบบจะไม่สร้างบัญชี
2. หา `id` ของบัญชีนั้นจากชื่อ

   ```bash
   /www/server/php/82/bin/php artisan tinker --execute='foreach (DB::table("users")->where("name", "like", "%ชื่อผู้ดูแล%")->get(["id","name","role","office"]) as $u) echo json_encode($u, JSON_UNESCAPED_UNICODE), PHP_EOL;'
   ```

3. กำหนด role เป็น `ADMIN` และต้นสังกัด `00` (แทน `ID` ด้วยเลขที่ได้)

   ```bash
   /www/server/php/82/bin/php artisan tinker --execute='DB::table("users")->where("id", ID)->update(["role" => "ADMIN", "office" => "00", "is_active" => true]);'
   ```

4. หลังจากนั้นกำหนดสิทธิ์ผู้ใช้คนอื่นได้จากหน้า `/admin/users`

**สรุปสิทธิ์** (รายละเอียดใน `AGENTS.md` หัวข้อ Office scope)

| Role | เมนูที่เข้าได้ | ขอบเขตข้อมูล |
|---|---|---|
| `ADMIN` | ทุกเมนู | ทุกต้นสังกัด |
| `LEAVE` | บุคลากร, การลา (ซิงค์ Google Sheets เฉพาะต้นสังกัด `00`) | ต้นสังกัด `00` เห็นทุกต้นสังกัด, ต้นสังกัดอื่นเห็นเฉพาะของตนเอง |
| `PROMOTE` | บุคลากร, เลื่อนขั้นเงินเดือน | เหมือนข้างบน |
| `FINANCE` | บุคลากร, สลิป, หนังสือรับรองภาษี, ผู้ลงนาม | เหมือนข้างบน ผู้ลงนามแก้ได้เฉพาะต้นสังกัด `00` |

ผู้ใช้ที่มี role แต่ยังไม่กำหนดต้นสังกัด จะเห็นเมนูแต่ไม่เห็นข้อมูล

## 12. ตรวจสอบหลังติดตั้ง

- [ ] เปิด `https://hr.example.go.th` ได้ และ HTTPS ใช้งานได้
- [ ] เปิด `https://hr.example.go.th/.env` แล้วได้ 403 หรือ 404 (ห้ามเห็นเนื้อหา)
- [ ] เข้าสู่ระบบผ่าน ThaiD, ProviderID และ MyMOPH ได้ทุกช่องทางที่ใช้
- [ ] เมนูผู้ดูแลแสดงตาม role
- [ ] นำเข้าไฟล์ทดสอบได้: บุคลากร (Excel), สลิป (TXT/Excel), เลื่อนขั้น (Excel), ภาษี (TXT)
- [ ] ดาวน์โหลด PDF สลิปและหนังสือรับรองภาษีได้ และภาษาไทยแสดงด้วยฟอนต์ TH Sarabun
- [ ] หน้า `/admin/leaves` เปิดเร็ว (ถ้าช้า ตรวจว่า migration utf8mb4 รันแล้ว ดูข้อ 14)
- [ ] `php artisan about` แสดง Environment เป็น `production` และ Debug Mode เป็น `OFF`
- [ ] Cron ทั้งสองงานแสดงผลการรันใน aaPanel

```bash
cd /www/wwwroot/hr.example.go.th
/www/server/php/82/bin/php artisan about --only=environment
/www/server/php/82/bin/php artisan migrate:status | tail -5
```

## 13. การอัปเดตเวอร์ชันครั้งถัดไป

```bash
cd /www/wwwroot/hr.example.go.th
PHP=/www/server/php/82/bin/php

# 0) สำรองฐานข้อมูล (aaPanel → Database → Backup)

# 1) ปิดระบบชั่วคราว ผู้ดูแลยังเข้าได้ผ่าน https://hr.example.go.th/<รหัสลับ>
$PHP artisan down --secret="เปลี่ยนเป็นรหัสลับ"

# 2) ดึงโค้ดใหม่
git pull origin main

# 3) dependencies และ assets
$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

# 4) ฟอนต์ PDF (รันซ้ำได้ ไม่มีผลเสีย)
$PHP vendor/tecnickcom/tcpdf/tools/tcpdf_addfont.php -b -t TrueTypeUnicode \
  -i "storage/fonts/THSarabunNew.ttf,storage/fonts/THSarabunNew Bold.ttf"

# 5) ฐานข้อมูลและ cache
$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan optimize

# 6) สิทธิ์ไฟล์ และเปิดระบบ
chown -R www:www /www/wwwroot/hr.example.go.th
$PHP artisan up
```

หลังอัปเดตให้กด **Restart** PHP-8.2 ใน App Store เพื่อล้าง OPcache

**ถ้าต้องย้อนเวอร์ชัน**
1. `git checkout <commit เดิม>`
2. ทำขั้นตอน 3–6 ซ้ำ
3. ถ้า migration ใหม่เปลี่ยนข้อมูลไปแล้ว ให้กู้จาก backup แทนการ `migrate:rollback` เพราะบาง migration ปฏิเสธการ rollback เมื่อมีข้อมูลแล้ว

## 14. แก้ปัญหาที่พบบ่อย

| อาการ | สาเหตุและวิธีแก้ |
|---|---|
| หน้า 500 Server Error | ดู `storage/logs/laravel-*.log` ส่วนใหญ่เป็นสิทธิ์ไฟล์ ให้รัน `chown -R www:www` และ `chmod 775` ในข้อ 8 ใหม่ |
| `Vite manifest not found` | ยังไม่ได้ `npm run build` หรือไม่ได้อัปโหลด `public/build/` |
| `symlink() has been disabled` | ปลด `symlink` ในข้อ 3.2 แล้ว `php artisan storage:link` ใหม่ |
| `proc_open()` / `putenv()` has been disabled | ปลดฟังก์ชันในข้อ 3.2 |
| `open_basedir restriction in effect` | ปิด Anti-XSS (open_basedir) ในข้อ 5.2 แล้ว Restart PHP |
| 419 Page Expired | `APP_URL` ไม่ตรงกับโดเมนจริง, ตั้ง `SESSION_SECURE_COOKIE=true` แต่เปิดผ่าน http, หรือแก้ `.env` แล้วไม่ได้ `php artisan optimize` |
| SSO ล็อกอินแล้ว error redirect | redirect URI ใน `.env` ไม่ตรงกับที่ลงทะเบียนไว้ ดูข้อ 7 |
| ล็อกอินแล้วขึ้นปฏิเสธการเข้าถึง | เลขบัตรไม่อยู่ใน `person`, หรือบัญชีถูกปิด (`users.is_active`) |
| เห็นเมนูผู้ดูแลแต่ไม่มีข้อมูล | ยังไม่กำหนดต้นสังกัด (`users.office`) ให้ผู้ใช้ |
| PDF ภาษาไทยเป็นสี่เหลี่ยม หรือสร้าง PDF ไม่ได้ | ยังไม่แปลงฟอนต์ ทำข้อ 9 |
| อัปโหลดไฟล์แล้ว error 413 หรือไฟล์ไม่ขึ้น | เพิ่ม `client_max_body_size` (ข้อ 5.2) และ `upload_max_filesize` / `post_max_size` (ข้อ 3.3) |
| แถบความคืบหน้าการซิงค์การลาไม่ขยับจนเสร็จ | Nginx buffer คำตอบแบบ stream ไว้ เพิ่ม `fastcgi_buffering off;` ใน block `location ~ [^/]\.php(/\|$)` ของ config เว็บไซต์ แล้ว reload Nginx |
| ซิงค์การลาหรือนำเข้าไฟล์ใหญ่แล้ว 504 | เพิ่ม `max_execution_time` (ข้อ 3.3) และ `fastcgi_read_timeout 300;` ใน config เว็บไซต์ |
| `/admin/leaves` เปิดช้าหลายวินาที | ตารางยัง collation ไม่ตรงกัน ตรวจด้วย `php artisan migrate:status` ว่า `2026_10_07_110000_convert_legacy_tables_to_utf8mb4` รันแล้ว |
| เห็นแถบ debug ด้านล่างจอ | ติดตั้งโดยไม่ใส่ `--no-dev` หรือ `APP_DEBUG=true` แก้แล้วรัน `php artisan optimize` |

## ความปลอดภัยและข้อมูลส่วนบุคคล

ระบบนี้เก็บเลขบัตรประชาชน เงินเดือน และข้อมูลภาษี

- ตั้ง **Running directory** เป็น `/public` เสมอ
- ไม่เปิด `APP_DEBUG` บน production
- จำกัดสิทธิ์ `.env` เป็น `640`
- ใช้รหัสผ่านฐานข้อมูลเฉพาะของแอปนี้ และให้สิทธิ์เฉพาะฐานข้อมูลของแอป
- เปิด **Database → Backup** แบบตั้งเวลา และเก็บ backup ไว้นอกเซิร์ฟเวอร์
- ใน aaPanel ปิดพอร์ตที่ไม่ใช้ (**Security**) และไม่เปิดพอร์ต MySQL (3306) ออกภายนอก
- ไม่เก็บไฟล์นำเข้าตัวอย่างหรือไฟล์ส่งออกไว้ในโฟลเดอร์ `public/`

> **ข้อควรระวังของ repository ปัจจุบัน:** ใน git มีไฟล์ PDF สลิปเงินเดือนตัวอย่างอยู่ใน
> `public/temp/` ซึ่งถูก commit ไว้ก่อนจะเพิ่ม `.gitignore`
> - หลัง `git clone` ไฟล์เหล่านี้จะเปิดผ่าน URL ได้ ให้ลบทิ้งด้วย `rm -f public/temp/*.pdf` ทันที (cron ข้อ 10 ก็ลบให้ในวันถัดไป)
> - ควรเอาไฟล์ออกจาก git ด้วย
