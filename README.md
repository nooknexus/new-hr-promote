https://docs.google.com/document/d/1oRNVwrCe-StLwCCpKY-LsQzNzZ5qxd1z3m65uAJ3T-E/edit?tab=t.0

วิธีตรวจสอบว่า Schedule ทำงานถูกต้อง
เพิ่ม Cron Job บนเซิร์ฟเวอร์เพื่อให้ Laravel Scheduler ทำงาน:
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1


ทดสอบ Command โดยตรง:
php artisan temp:clean-pdf --days=1