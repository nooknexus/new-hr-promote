<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#056b3e">
    <title>Provider ID คืออะไร? | MyPPH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bai+Jamjuree:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:py-16">
        <header class="mb-10 text-center">
            <img src="{{ asset('img/logoMOPH.png') }}" alt="ตรากระทรวงสาธารณสุข" class="mx-auto mb-5 size-24 rounded-3xl bg-white p-2 shadow-sm">
            <p class="text-sm font-semibold tracking-wide text-brand-600">DIGITAL HEALTH IDENTITY</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Provider ID คืออะไร?</h1>
            <a href="{{ route('login') }}" class="btn btn-primary mt-6"><i class="fas fa-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ</a>
        </header>

        <div class="space-y-5">
            <article class="card"><div class="card-header font-bold text-brand-700">Provider ID คืออะไร?</div><div class="card-body space-y-3 leading-7"><p>Provider ID คือระบบการพิสูจน์และยืนยันตัวตนทางดิจิทัลสำหรับบุคลากรทางการแพทย์และสาธารณสุข ที่พัฒนาโดยกระทรวงสาธารณสุข</p><p>ช่วยให้เข้าถึงระบบต่าง ๆ ของกระทรวงสาธารณสุขได้ด้วยบัญชีเดียว โดยไม่ต้องจดจำชื่อผู้ใช้และรหัสผ่านหลายชุด</p></div></article>
            <article class="card"><div class="card-header font-bold text-brand-700">ประโยชน์ของ Provider ID</div><div class="card-body"><ul class="list-disc space-y-2 ps-5 leading-7"><li>เข้าถึงระบบต่าง ๆ ของกระทรวงสาธารณสุขด้วยบัญชีเดียว</li><li>ลดความยุ่งยากในการจดจำชื่อผู้ใช้และรหัสผ่านหลายชุด</li><li>เพิ่มความปลอดภัยในการเข้าถึงข้อมูลและระบบสารสนเทศ</li><li>ระบุตัวตนของผู้ให้บริการสาธารณสุขได้ชัดเจน</li><li>ช่วยให้การทำงานกับระบบต่าง ๆ ราบรื่นและมีประสิทธิภาพ</li></ul></div></article>
            <article class="card"><div class="card-header font-bold text-brand-700">วิธีการลงทะเบียนและใช้งาน</div><div class="card-body grid gap-6 md:grid-cols-2"><section><h2 class="font-semibold text-slate-900">การลงทะเบียน</h2><ol class="mt-3 list-decimal space-y-2 ps-5 text-sm leading-6"><li>เข้าสู่ <a class="font-medium text-brand-600 underline" href="https://provider.moph.go.th" target="_blank" rel="noopener noreferrer">provider.moph.go.th</a></li><li>เลือก “ลงทะเบียนใหม่”</li><li>กรอกข้อมูลส่วนตัวและข้อมูลการทำงาน</li><li>ยืนยันตัวตนตามขั้นตอนที่กำหนด</li><li>รอการอนุมัติจากผู้ดูแลระบบ</li></ol></section><section><h2 class="font-semibold text-slate-900">การใช้งาน</h2><ol class="mt-3 list-decimal space-y-2 ps-5 text-sm leading-6"><li>เลือกเข้าสู่ระบบด้วย Provider ID</li><li>กรอกชื่อผู้ใช้และรหัสผ่าน</li><li>ยืนยันตัวตนตามขั้นตอนที่กำหนด</li><li>ระบบนำคุณเข้าสู่บริการที่ต้องการโดยอัตโนมัติ</li></ol></section></div></article>
            <article class="card"><div class="card-header font-bold text-brand-700">ข้อมูลเพิ่มเติมและการติดต่อ</div><div class="card-body space-y-4 leading-7"><ul class="space-y-1"><li>เว็บไซต์: <a class="font-medium text-brand-600 underline" href="https://provider.moph.go.th" target="_blank" rel="noopener noreferrer">provider.moph.go.th</a></li><li>อีเมล: provider@moph.go.th</li><li>โทรศัพท์: 02-XXX-XXXX</li></ul><div class="alert alert-info"><strong>หมายเหตุ:</strong> Provider ID เป็นส่วนหนึ่งของโครงการพัฒนาระบบสารสนเทศภายใต้ยุทธศาสตร์ Digital Health</div></div></article>
        </div>
    </main>
</body>
</html>
