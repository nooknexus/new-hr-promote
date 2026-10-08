# ระบบจัดการใบรับรองการหักภาษี

เอกสารนี้เป็นจุดอ้างอิงสำหรับพัฒนาต่อของฟีเจอร์ใบรับรองการหักภาษี
สถานะนี้อ้างอิงจากโค้ดใน repository ณ วันที่ 4 ตุลาคม 2026

## เส้นทางและสิทธิ์

| งาน | เส้นทาง/ชื่อ route | ผู้มีสิทธิ์ |
| --- | --- | --- |
| ดูรายการส่วนบุคคล | `/tax-certificate` / `tax.certificate` | ผู้ใช้ที่ login แล้ว โดย CID ต้องตรงกับข้อมูล |
| ดาวน์โหลด PDF | `/tax-certificate/download/{taxYear}/{certificateNo}` / `tax.certificate.download` | ผู้ใช้ที่ login แล้ว และเป็นเจ้าของ CID ของรายการ |
| จัดการ/นำเข้า | `/admin/tax-certificates` / `admin.tax-certificates.index` | `ADMIN`, `FINANCE` |

สิทธิ์รวมศูนย์ที่ `App\Support\AdminAccess` ด้วย ability
`tax-certificates` และ route มี middleware `admin:tax-certificates` แล้ว
Livewire action ที่เขียนข้อมูลต้องตรวจสิทธิ์ซ้ำเสมอ ห้ามอาศัยการซ่อนเมนูเพียงอย่างเดียว

## แหล่งข้อมูลและการแสดงผลเอกสาร

ตาราง `data_tax` เป็นแหล่งข้อมูลเดียวของหน้าผู้ดูแล, `/tax-certificate`,
preview และ PDF โดย key ของข้อมูลคือ `(CID, income_year)`

- หน้าผู้ใช้ต้อง filter `CID = auth()->user()->pid` ทุกครั้ง ทั้งการเปิด preview
  และการดาวน์โหลด PDF
- `TaxCertificatePdfService::fromTaxRecord()` แปลงข้อมูลจาก record ไปยัง payload
  เอกสาร เช่น ผู้จ่ายเงิน/เลขภาษี/ที่อยู่, ผู้ถูกหักภาษี/เลขบัตร/ที่อยู่,
  ประเภท ภ.ง.ด., กองทุน, วิธีจ่าย, ยอดเงิน, ภาษี, ข้อความภาษี และวันออกเอกสาร
- `PayslipSignatory::forTaxCertificate($dateReport, $incomeYear)` เลือกลายเซ็น
  ของผู้ลงนามในปีงบประมาณของวันที่ออกหนังสือรับรอง (`date_report`) เช่น ปีภาษี
  2567 ออก 29 ม.ค. 2568 ใช้ผู้ลงนามปีงบประมาณ 2568 ถ้าไม่มีวันที่ออกเอกสารใช้
  ปีภาษี + 1 และใช้เฉพาะปีที่ตรงกันเท่านั้น
- รักษา geometry A4, เส้นตาราง และตำแหน่งที่ `TaxCertificatePdfService`
  กำหนดไว้ การปรับ mapping ต้องไม่เปลี่ยน layout โดยไม่มีการทดสอบ PDF จริง

## นำเข้าไฟล์ TXT

บริการ `App\Services\TaxCertificateTxtImportService` รับเฉพาะ `.txt` ไม่เกิน
5 MiB ใช้ `$` เป็น delimiter และคาดว่าหนึ่งแถวมี 20 คอลัมน์:

| ช่วงคอลัมน์ | ความหมาย |
| --- | --- |
| 0–3 | เลขที่เอกสาร/ชื่อ/เลขภาษี/ที่อยู่ผู้จ่ายเงิน |
| 4–8 | CID, เลขภาษี, ชื่อ, ที่อยู่, ลำดับผู้ถูกหัก |
| 9–10 | รหัส ภ.ง.ด. และปีภาษี พ.ศ. |
| 11–15 | เงินได้, ภาษี, ยอดรวม, ภาษีรวม, ภาษีตัวอักษร |
| 16–17 | ประเภทและยอดกองทุน |
| 18–19 | วิธีออกหนังสือรับรอง และวันที่ `DDMMYYYY` พ.ศ. |

ก่อน preview/import ให้ผู้ดูแลเลือก encoding ของไฟล์ใน UI:

- `TIS-620`: ค่าเริ่มต้นสำหรับไฟล์เดิมของกรมบัญชีกลาง
- `UTF-8`: สำหรับไฟล์ที่ส่งออกเป็น UTF-8

การเลือกนี้เป็นข้อบังคับเชิงความถูกต้อง ไม่ควรเพิ่ม auto-detect เพราะ byte ของ
ภาษาไทยอาจแปลงผิดโดยไม่มี error ที่ตรวจพบได้แน่นอน

### กติกาการเขียนฐานข้อมูล

1. ตรวจ CID 13 หลัก, จำนวนคอลัมน์, รหัสประเภท, รหัสกองทุน, รหัสวิธีจ่าย,
   วันที่ พ.ศ. และยอด fixed-width ก่อนเขียน
2. เลขยอดที่มี 2 หลักสุดท้ายเป็นสตางค์ เช่น `000003155000` คือ `31550.00`
3. แถว invalid หรือ key `(CID, income_year)` ซ้ำภายในไฟล์ถูกข้ามและแสดงผลสรุป
4. commit อ่าน stream ทั้งไฟล์เป็นชุดย่อย ไม่ใช้เฉพาะแถวที่ retained เพื่อ preview
5. upsert รายการ valid ใน transaction เดียว: key เดิมถูกปรับปรุง, key ใหม่ถูกเพิ่ม,
   และรายการที่ไม่มีในไฟล์ไม่ถูกลบ
6. ข้อผิดพลาดที่แสดงผู้ใช้ต้อง mask CID; ห้าม log ข้อมูลแถวต้นฉบับ เลขบัตรเต็ม
   เลขภาษี หรือยอดเงินเฉพาะบุคคล

## ส่วนประกอบหลัก

| ส่วนประกอบ | หน้าที่ |
| --- | --- |
| `TaxCertificateTxtImportService` | validate, preview และ upsert TXT แบบ stream |
| `App\Livewire\Admin\TaxCertificateManagement` | CRUD, filter, pagination, upload, modal และ toast |
| `App\Livewire\TaxCertificate` | เลือกปี/รายการ และสร้าง payload preview สำหรับเจ้าของข้อมูล |
| `TaxCertificateController` | ตรวจ ownership แล้วส่ง PDF download |
| `TaxCertificatePdfService` | mapping `data_tax` และสร้าง PDF A4 |

## การทดสอบที่ต้องรันเมื่อแก้ไข

```bash
php artisan test tests/Unit/TaxCertificateTxtImportServiceTest.php \
  tests/Feature/TaxCertificateTxtImportCommitTest.php \
  tests/Feature/AdminTaxCertificateAccessTest.php \
  tests/Feature/AdminTaxCertificateManagementTest.php \
  tests/Feature/TaxCertificateDownloadTest.php \
  tests/Unit/TaxCertificatePdfServiceTest.php

php artisan route:list --path=admin/tax-certificates
npm run build
```

ก่อนส่งมอบการเปลี่ยนแปลงใหญ่ ให้รัน `php artisan test`, `php artisan route:list`
และ `git diff --check` เพิ่มเติมด้วย
