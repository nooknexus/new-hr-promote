<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold text-brand-700">ข้อมูลระบบ</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">เกี่ยวกับ MyPPH</h1>
        <p class="mt-2 text-sm text-slate-500">ระบบข้อมูลบุคลากรของสำนักงานสาธารณสุขจังหวัดพิษณุโลก</p>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="project-heading">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <img src="{{ asset('img/logoMOPH.png') }}" alt="ตรากระทรวงสาธารณสุข" class="size-16 object-contain">
                <div>
                    <h2 id="project-heading" class="text-xl font-bold text-slate-900">MyPPH</h2>
                    <p class="mt-1 text-sm text-slate-500">สำนักงานสาธารณสุขจังหวัดพิษณุโลก</p>
                </div>
            </div>
            <span class="rounded-full bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700">เวอร์ชัน {{ config('app.version') }}</span>
        </div>
        <p class="mt-6 leading-7 text-slate-600">
            MyPPH เป็นระบบข้อมูลบุคลากรและเอกสารสำหรับบุคลากรของสำนักงานสาธารณสุขจังหวัดพิษณุโลก
            รวบรวมข้อมูลส่วนตัว ข้อมูลเงินเดือน เอกสารภาษี การเลื่อนขั้นเงินเดือน และประวัติการลาไว้ในระบบเดียว
            เพื่อให้บุคลากรตรวจสอบข้อมูลและดาวน์โหลดเอกสารที่เกี่ยวข้องได้สะดวก
            พร้อมสนับสนุนผู้ดูแลระบบในการจัดการข้อมูลตามหน้าที่และสิทธิ์ที่ได้รับ
        </p>
    </section>

    <section aria-labelledby="features-heading">
        <h2 id="features-heading" class="text-lg font-bold text-slate-900">ความสามารถหลัก</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                ['fa-user', 'ข้อมูลส่วนตัว', 'ตรวจสอบและปรับปรุงข้อมูลโปรไฟล์ของบุคลากร'],
                ['fa-file-invoice-dollar', 'สลิปเงินเดือน', 'ดูรายละเอียดรายรับ รายการหัก และดาวน์โหลดสลิปเงินเดือนเป็น PDF'],
                ['fa-receipt', 'ใบรับรองการหักภาษี', 'ตรวจสอบและดาวน์โหลดใบรับรองการหักภาษี ณ ที่จ่ายแยกตามปี'],
                ['fa-level-up-alt', 'เลื่อนขั้นเงินเดือน', 'ติดตามข้อมูลและประวัติการเลื่อนขั้นเงินเดือน'],
                ['fa-calendar-check', 'ประวัติการลา', 'ตรวจสอบรายการลาและสรุปข้อมูลการลาแยกตามปีงบประมาณ'],
                ['fa-users-cog', 'การจัดการข้อมูล', 'ผู้ดูแลระบบจัดการข้อมูลบุคลากร เงินเดือน ภาษี การลา และผู้ลงนามตามสิทธิ์ที่ได้รับ'],
            ] as [$icon, $heading, $description])
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <span class="inline-flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700" aria-hidden="true"><i class="fas {{ $icon }}"></i></span>
                    <h3 class="mt-4 font-semibold text-slate-900">{{ $heading }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $description }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="signin-heading">
        <h2 id="signin-heading" class="text-lg font-bold text-slate-900">การเข้าสู่ระบบ</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">
            รองรับการยืนยันตัวตนผ่าน ThaiD, ProviderID / HealthID และ MyMOPH
            โดยตรวจสอบข้อมูลบุคลากรก่อนให้เข้าใช้งาน และแสดงข้อมูลตามบัญชีผู้ใช้และสิทธิ์ที่ได้รับในระบบ
        </p>
    </section>
</div>
