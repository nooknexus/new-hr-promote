# ระบบดึงข้อมูลการลาจาก Google Sheet และบันทึกลงฐานข้อมูล

เอกสารนี้อธิบายฟีเจอร์ดึงข้อมูลการลาจาก Google Sheet ในรูปแบบ CSV แล้วบันทึกหรือปรับปรุงข้อมูลในฐานข้อมูล MySQL ผ่าน Prisma โดยอ้างอิง implementation ของโปรเจค WFH ปัจจุบัน เนื้อหาช่วงท้ายเสนอแนวทางปรับปรุงสำหรับนำไปใช้กับโปรเจคอื่น

## 1. ภาพรวมระบบ

ฟีเจอร์ประกอบด้วย 4 ชั้นหลัก

```text
หน้า Admin
  -> tRPC Mutation: leave.syncFromGoogleSheet
  -> Service: syncLeavesFromGoogleSheet()
  -> Google Sheet CSV
  -> Normalize / Match / Upsert
  -> MySQL: leave_requests, leave_types, users
```

ไฟล์ที่เกี่ยวข้องในโปรเจคนี้คือ

- หน้า Admin: `src/app/(dashboard)/admin/leaves/page.tsx`
- API Router: `src/server/routers/leave.ts`
- Sync Service: `src/server/services/leave-sync.ts`
- Prisma Schema: `prisma/schema.prisma`
- ฐานข้อมูลหลัก: ตาราง `users`, `leave_types`, `leave_requests`

การแยก service ออกจากหน้าเว็บทำให้สามารถเรียกกระบวนการ Sync จากปุ่มในระบบ, Cron Job หรือ CLI ได้โดยไม่ต้องทำซ้ำ logic

## 2. รูปแบบข้อมูลต้นทาง

Google Sheet ต้องเปิดให้ระบบปลายทางดาวน์โหลดข้อมูลได้ และต้องมีหัวตารางตรงกับชื่อที่ service อ่านอยู่ในปัจจุบัน

| หัวตาราง Google Sheet | ความหมาย | ฟิลด์ปลายทาง |
|---|---|---|
| `เลขประจำตัวประชาชน` | เลขประจำตัวประชาชน | `leave_requests.cid` |
| `ชื่อ + นามสกุล` | ชื่อผู้ลา | `leave_requests.external_name` |
| `ประเภทการลา` | ประเภทการลา | `leave_types.name` |
| `วันที่(เริ่ม)` | วันเริ่มลา | `leave_requests.start_date` |
| `วันที่(สิ้นสุด)` | วันสิ้นสุดลา | `leave_requests.end_date` |
| `จำนวนวัน` | จำนวนวันลา อาจเป็น 0.5 | `leave_requests.total_days` |
| `สถานะวันลา` | สถานะ เช่น อนุมัติ | `leave_requests.status` |
| `หมายเหตุ` (ไม่บังคับ) | หมายเหตุหรือเหตุผล | `leave_requests.reason`; หากไม่มีใช้ `ซิงค์จาก Google Sheet` |

หากเปลี่ยนชื่อคอลัมน์ ต้องแก้ mapping ใน service หรือสร้าง configuration สำหรับ mapping แทนการเขียนชื่อไว้กระจายตามโค้ด

ชีต `ข้อมูลการลา` สามารถมีคอลัมน์เสริม เช่น คำนำหน้า, ตำแหน่งสายงาน และประเภทเจ้าหน้าที่ได้ ระบบจะอ่านเฉพาะคอลัมน์ที่ใช้สร้างประวัติการลา และรองรับวันที่รูปแบบ `30/9/26` เป็น `2026-09-30`

## 3. การดาวน์โหลด Google Sheet

Implementation ปัจจุบันใช้ URL สำหรับ Export เป็น CSV โดยไม่ใช้ Google Sheets API

```ts
const SHEET_URL =
  "https://docs.google.com/spreadsheets/d/{SPREADSHEET_ID}/export?format=csv&gid={SHEET_GID}";

const response = await fetch(SHEET_URL);

if (!response.ok) {
  throw new Error(`Failed to fetch sheet data: ${response.statusText}`);
}

const csvText = await response.text();
```

เงื่อนไขที่ต้องเตรียม

1. Spreadsheet ต้องมีสิทธิ์ให้ server อ่านข้อมูลได้ หรือใช้วิธี authenticate ที่เหมาะกับข้อมูลภายใน
2. ต้องระบุ `SPREADSHEET_ID` และ `SHEET_GID` ให้ถูกต้อง
3. สำหรับระบบนี้ ADMIN จะวาง URL ของชีตที่ต้องการในช่องบนหน้าซิงค์ในแต่ละครั้ง ไม่ต้องกำหนด `LEAVE_SHEET_URL` ใน environment และระบบไม่บันทึก URL ไว้ในประวัติการซิงค์ รองรับลิงก์ Google Sheets แบบปกติและ CSV export URL โดยระบบแปลงลิงก์ชีตเป็น export URL ก่อนดาวน์โหลด ข้อมูลที่ซิงค์เก็บใน `providerid.leave_requests` และประเภทการลาอยู่ใน `providerid.leave_types`; ห้ามอ้าง schema ของระบบอื่น
4. ควรกำหนด timeout ให้ request และตรวจสอบขนาด response เพื่อป้องกันการค้างหรือรับไฟล์ผิดขนาด

## 4. การ Parse CSV

โปรเจคนี้ใช้แพ็กเกจ `csv-parse` ซึ่งรองรับการอ่าน header และข้ามบรรทัดว่าง

```ts
import { parse } from "csv-parse/sync";

const records = parse(csvText, {
  columns: true,
  skip_empty_lines: true,
  trim: true,
});
```

`columns: true` ทำให้แต่ละแถวกลายเป็น object ที่เข้าถึงด้วยชื่อ header ได้ เช่น

```ts
const rawCid = record["เลขประจำตัวประชาชน"];
const fullName = record["ชื่อ + นามสกุล"];
```

ระบบควรถือว่าแต่ละแถวเป็นข้อมูลที่ไม่น่าเชื่อถือจนกว่าจะผ่านการตรวจสอบ เพราะ CSV อาจมี header ผิด, สูตรใน Sheet คืนค่า `#REF!`, วันที่ว่าง หรือจำนวนวันเป็นข้อความ

หากหัวตารางมีชื่อซ้ำ ระบบปัจจุบันจะใช้คอลัมน์ที่พบครั้งแรกของชื่อนั้น และละเว้นคอลัมน์ซ้ำถัดไป เพื่อให้ import ดำเนินต่อได้โดยมี mapping ที่แน่นอน

## 5. การ Normalize และคัดกรองข้อมูล

### 5.1 Normalize CID

โค้ดปัจจุบันลบอักขระที่ไม่ใช่ตัวเลข เพื่อให้ CID ที่เขียนเป็น `1-2345-67890-12-3` เทียบกับข้อมูลในระบบได้

```ts
const rawCid = record["เลขประจำตัวประชาชน"]?.toString().trim();
const cid = rawCid?.replace(/[^0-9]/g, "");
```

สำหรับโปรเจคใหม่ควรตรวจความยาวและ checksum ของเลขบัตรประชาชนไทยเพิ่มเติม หากข้อมูลไม่ผ่าน validation ควรเก็บเป็น invalid row พร้อมเหตุผล แทนการปล่อยให้ข้อมูลผิดเข้าสู่ระบบ

### 5.2 ตรวจข้อมูลที่ไม่ใช่รายการลา

ระบบปัจจุบันข้ามแถวที่ไม่มีชื่อหรือมีค่า `#REF!` และข้ามรายการ WFH

```ts
if (!fullName || fullName === "#REF!") continue;
if (cid === "#REF!") continue;

if (
  leaveTypeName?.includes("WFH") ||
  leaveTypeName?.includes("ปฏิบัติงานที่บ้าน")
) {
  skippedCount++;
  continue;
}
```

คำว่า WFH และประเภทที่ต้องข้ามควรแยกเป็น configuration เช่น `EXCLUDED_LEAVE_TYPES` เพื่อให้แต่ละโปรเจคกำหนดนโยบายเองได้

## 6. การนำเข้าข้อมูลของทุกคน

การ Sync นำเข้ารายการลาของทุกคนใน Google Sheet โดยไม่ตรวจสอบหรือจับคู่กับตาราง `person`

- ใช้ชื่อจากคอลัมน์ `ชื่อ + นามสกุล` เก็บใน `leave_requests.external_name` ตามต้นทาง
- ใช้ CID เป็นตัวระบุเจ้าของรายการลา หน้าประวัติการลาของผู้ใช้ค้นหาด้วย `leave_requests.cid` ตรงกับ PID ที่ login
- แถวที่ไม่ถูกนำเข้ามีเฉพาะแถวว่าง, `#REF!`, ประเภทลาที่ตั้งค่าให้ข้าม และแถวที่ข้อมูลไม่ผ่าน validation (เช่น CID หรือวันที่ไม่ถูกต้อง)

## 7. การสร้างประเภทการลา

หากไม่พบประเภทการลาที่มีชื่อเดียวกัน ระบบจะสร้าง `leave_types` ใหม่โดยใช้ code ที่ขึ้นต้นด้วย `SYNC_`

```ts
const typeName = (leaveTypeName || "อื่นๆ").trim();

let leaveType = await db.leaveType.findFirst({
  where: { name: typeName },
});

if (!leaveType) {
  leaveType = await db.leaveType.create({
    data: {
      name: typeName,
      code: ("SYNC_" + typeName).substring(0, 20),
    },
  });
}
```

แนวทางนี้ใช้งานได้กับข้อมูลปริมาณไม่มาก แต่โปรเจคใหม่ควรกำหนด unique key ของประเภทการลาให้ชัดเจน เช่น `external_code` หรือ mapping table เพราะการตัด string ให้เหลือ 20 ตัวอักษรอาจทำให้ code ซ้ำกันได้

## 8. การแปลงวันที่ไทย

ข้อมูลต้นทางใช้รูปแบบ `วัน/เดือน/ปี` และอาจเป็นปี พ.ศ. ระบบจึงแปลงเป็นปี ค.ศ. และสร้างวันที่ด้วย `Date.UTC` เพื่อป้องกัน timezone ทำให้วันที่ใน MySQL `DATE` เลื่อน

```ts
function parseThaiDate(dateStr: string) {
  const [day, month, rawYear] = dateStr.split("/").map(Number);
  let year = rawYear;

  if (year < 100) {
    year = year > 50 ? year + 2500 : year + 2000;
  }

  const adYear = year > 2400 ? year - 543 : year;
  return new Date(Date.UTC(adYear, month - 1, day));
}
```

สิ่งที่ควรตรวจสอบเพิ่มในระบบใหม่

- วันที่ต้องเป็นวันที่จริง ไม่ใช่ `new Date()` เมื่อ input ว่าง
- `start_date` ต้องไม่มากกว่า `end_date`
- ตรวจสอบ leap year และจำนวนวันของเดือน
- กำหนด timezone ของ source ให้ชัดเจน
- เก็บ raw date ไว้เมื่อจำเป็นต้องตรวจสอบย้อนหลัง

## 9. การแปลงสถานะ

สถานะจาก Sheet ถูกแปลงเป็น enum ของระบบ

| สถานะจาก Sheet | สถานะฐานข้อมูล |
|---|---|
| `อนุมัติ` | `approved` |
| `ไม่อนุมัติ` | `rejected` |
| ค่าอื่นหรือว่าง | `pending` |

```ts
let leaveStatus: "pending" | "approved" | "rejected" = "pending";

if (statusStr === "อนุมัติ") leaveStatus = "approved";
if (statusStr === "ไม่อนุมัติ") leaveStatus = "rejected";
```

ควร normalize whitespace และกำหนด mapping เป็น object เพื่อรองรับข้อความที่มีหลายรูปแบบ เช่น `อนุมัติแล้ว`, `ไม่อนุมัติ ` หรือสถานะภาษาอังกฤษ

## 10. การป้องกันข้อมูลซ้ำและการบันทึก

ระบบปัจจุบันค้นหารายการเดิมด้วยเงื่อนไขบุคคลและช่วงวันที่

```sql
SELECT id, leave_type_id, status
FROM leave_requests
WHERE cid = ?
  AND DATE(start_date) = ?
  AND DATE(end_date) = ?
LIMIT 1;
```

หากไม่พบจะสร้างรายการใหม่ หากพบจะ update รายการเดิมจากข้อมูลในชีตใหม่ รายการที่ไม่มีในชีตใหม่จะไม่ถูกลบอัตโนมัติ

```ts
const dataToSave = {
  external_name: matchedPersonName ?? fullName,
  cid,
  leave_type_id: leaveType.id,
  start_date: startDate,
  end_date: endDate,
  total_days: totalDays,
  reason: remark || "Synced from Google Sheet",
  status: leaveStatus,
  step1_status: leaveStatus,
  step2_status: leaveStatus,
};
```

### ข้อจำกัดของวิธีปัจจุบัน

การใช้ `cid + start_date + end_date` เป็น identity อาจชนกันได้หากบุคคลเดียวกันมีรายการลาประเภทเดียวกันในช่วงเดียวกัน หรือหาก CID ว่างหลายแถว นอกจากนี้การค้นหาแล้วค่อย create/update ยังมี race condition หากกด Sync พร้อมกันหลายครั้ง

### แนวทางที่แนะนำสำหรับโปรเจคใหม่

เพิ่ม source identity ในตาราง เช่น

```prisma
model LeaveRequest {
  id              Int      @id @default(autoincrement())
  source          String?
  source_record_id String?
  // fields อื่นๆ

  @@unique([source, source_record_id])
}
```

ถ้า Google Sheet ไม่มี row ID ที่ถาวร ให้สร้าง fingerprint จากฟิลด์ที่กำหนด เช่น

```ts
const sourceRecordId = createHash("sha256")
  .update([cid, startDate, endDate, leaveTypeName].join("|"))
  .digest("hex");
```

ควรใช้ database transaction และ unique constraint ร่วมกันเพื่อให้การ Sync ปลอดภัยเมื่อมีการทำงานพร้อมกัน

## 11. การเปิดใช้งานผ่าน tRPC และหน้า Admin

Router เรียก service ผ่าน protected mutation

```ts
export const leaveRouter = createTRPCRouter({
  syncFromGoogleSheet: protectedProcedure.mutation(async () => {
    return syncLeavesFromGoogleSheet();
  }),
});
```

หน้า Admin เรียก mutation เมื่อผู้ดูแลกดปุ่ม Sync และ invalidate query หลังสำเร็จ

```tsx
const syncMutation = trpc.leave.syncFromGoogleSheet.useMutation({
  onSuccess: (data) => {
    Swal.fire(
      "Sync สำเร็จ",
      `นำเข้า ${data.successCount} รายการ ข้าม ${data.skippedCount} รายการ`,
      "success",
    );
    utils.leave.getAllLeaves.invalidate();
  },
});
```

`protectedProcedure` ในโปรเจคนี้ตรวจสอบว่ามี session และมี user ID แต่ router ของ Sync ยังไม่ได้ตรวจ role ระดับ Admin โดยตรง โปรเจคใหม่ควรเพิ่ม authorization เช่น `requireRole(["hradmin", "superadmin"])` เพื่อไม่ให้ผู้ใช้ที่ login แล้วทุกคนเรียก Sync ได้

## 12. รูปแบบผลลัพธ์ที่ควรส่งกลับ

ผลลัพธ์ปัจจุบันมีเพียงจำนวนสำเร็จและจำนวนที่ข้าม

```ts
return { successCount, skippedCount };
```

สำหรับระบบที่ต้องตรวจสอบข้อมูล ควรส่งผลลัพธ์ละเอียดขึ้น

```ts
type SyncResult = {
  fetchedCount: number;
  createdCount: number;
  updatedCount: number;
  skippedCount: number;
  failedCount: number;
  errors: Array<{
    rowNumber: number;
    message: string;
  }>;
};
```

ไม่ควรทำให้ทั้ง batch ล้มเหลวเพราะข้อมูลเสียเพียงหนึ่งแถว เว้นแต่เป็นความผิดพลาดระดับ source เช่น fetch ไม่ได้หรือ header หลักหายไป

## 13. Error Handling และ Logging

Implementation ปัจจุบันครอบแต่ละแถวด้วย `try/catch` และเขียน error ลง console ทำให้แถวอื่นยังประมวลผลต่อได้

ควรแยก error เป็น 2 ระดับ

- Source error: ดาวน์โหลดไม่ได้, HTTP status ไม่สำเร็จ, CSV parse ไม่ได้, header ไม่ครบ ควรหยุด batch และแจ้งผู้เรียก
- Row error: CID ผิด, วันที่ผิด, ประเภทลาไม่ถูกต้อง ควรข้ามเฉพาะแถวและบันทึก row number กับสาเหตุ

ระบบ production ควรมี sync log table เช่น `leave_sync_runs` เพื่อเก็บผู้เรียก, เวลาเริ่ม/จบ, จำนวนรายการ, status และ error summary แทนการพึ่ง `console.log` เพียงอย่างเดียว

## 14. Security และ Privacy

ข้อมูล CID เป็นข้อมูลส่วนบุคคล ควรปฏิบัติตามหลักต่อไปนี้

- ไม่ฝัง Spreadsheet URL ที่เป็นความลับใน source code
- จำกัดสิทธิ์การอ่าน Google Sheet ให้เฉพาะ service account หรือผู้ใช้ที่จำเป็น
- ไม่แสดง CID เต็มใน log หรือ error ที่ส่งกลับ client
- ตรวจ role ก่อนอนุญาตให้ Sync
- บันทึก audit ว่าใครเป็นผู้สั่ง Sync
- จำกัดขนาด CSV และกำหนด request timeout
- หลีกเลี่ยงการใช้ `$queryRawUnsafe` เมื่อสามารถใช้ Prisma query หรือ parameterized query ได้

## 15. Test Cases

ควรมีการทดสอบอย่างน้อยรายการต่อไปนี้

| กรณีทดสอบ | ผลลัพธ์ที่คาดหวัง |
|---|---|
| ดาวน์โหลด CSV สำเร็จ | อ่านจำนวนแถวได้ถูกต้อง |
| Google Sheet ตอบ HTTP error | Sync ล้มเหลวพร้อมข้อความที่เข้าใจได้ |
| header ไม่ครบ | หยุด batch และแจ้ง source error |
| CID มีขีด | ถูก normalize เป็นตัวเลข |
| CID ไม่มีในตาราง `person` | ยังนำเข้ารายการลา และบันทึกชื่อจาก Sheet ใน `external_name` |
| ชื่อประเภทลายังไม่มี | สร้าง `leave_type` ตาม policy |
| วันที่ พ.ศ. | แปลงเป็น ค.ศ. ถูกต้อง |
| วันที่ไม่ถูกต้อง | ข้ามแถวและบันทึก row error |
| ประเภท WFH | ไม่ถูกบันทึกใน leave_requests |
| Sync ซ้ำ | ไม่สร้างรายการซ้ำ |
| สถานะเปลี่ยนใน Sheet | update รายการเดิม |
| Sync พร้อมกันสองครั้ง | ไม่เกิด duplicate จาก unique constraint |
| ผู้ใช้ไม่มี role ที่อนุญาต | ได้รับ `FORBIDDEN` |

## 16. Checklist สำหรับนำไปใช้กับโปรเจคอื่น

- กำหนด schema ของ Google Sheet และชื่อ header ให้ชัดเจน
- แยก source adapter, transformer, matcher และ repository ออกจากกัน
- รับ Sheet URL จาก ADMIN ในหน้า sync และไม่บันทึก URL ลง audit history
- เพิ่ม validation ของทุกฟิลด์ก่อนเขียนฐานข้อมูล
- กำหนด source record identity ที่เป็น unique
- เพิ่ม unique constraint ในฐานข้อมูลเพื่อป้องกัน duplicate
- ใช้ transaction สำหรับการสร้างประเภทลาและบันทึกรายการ
- กำหนด role ที่สามารถสั่ง Sync
- คืนผลลัพธ์ created, updated, skipped และ failed แยกกัน
- เก็บ sync run และ row errors สำหรับ audit
- เขียน unit test ให้กับ date parser, status mapper และ deduplication
- เพิ่ม retry เฉพาะกรณี network error และใช้ timeout ทุกครั้ง

## 17. สรุป

ฟีเจอร์นี้ใช้ Google Sheet เป็น source แบบ CSV, parse ข้อมูล, normalize CID และวันที่, resolve ประเภทการลา แล้ว create/update `providerid.leave_requests` ใน MySQL ผ่าน Laravel service โดยหน้า ADMIN เรียก Livewire action และ refresh ประวัติหลัง Sync สำเร็จ

เมื่อนำไปพัฒนาต่อ ควรให้ความสำคัญกับ source identity ที่เป็น unique, authorization ระดับ role, validation, transaction, sync audit และการรายงาน error รายแถว เพราะส่วนเหล่านี้เป็นจุดที่มีผลต่อความถูกต้องของข้อมูลเมื่อระบบมีผู้ใช้หรือจำนวนข้อมูลเพิ่มขึ้น
