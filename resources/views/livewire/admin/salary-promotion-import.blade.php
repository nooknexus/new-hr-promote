<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="promotion-import-title">
    <div class="flex flex-col gap-1">
        <h2 id="promotion-import-title" class="font-bold text-brand-700">นำเข้า Excel การเลื่อนขั้นเงินเดือน</h2>
        <p class="text-sm leading-6 text-slate-500">ใช้ไฟล์ .xlsx รูปแบบเดิม ตรวจสอบจำนวนรายการก่อนยืนยันนำเข้า ข้อมูลเลขตำแหน่งจะอัปเดตเฉพาะบุคลากรและผู้ใช้ที่จับคู่ได้</p>
    </div>
    <form wire:submit="previewUpload" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
            <label for="salary-import-file" class="block text-sm font-semibold text-slate-700">ไฟล์ Excel</label>
            <input id="salary-import-file" type="file" wire:model="importFile" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
            @error('importFile')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="importFile,previewUpload" class="btn btn-primary">ตรวจสอบตัวอย่าง</button>
    </form>
    <p wire:loading wire:target="importFile,previewUpload,confirmImport" class="mt-3 text-sm text-slate-500" role="status">กำลังอ่านไฟล์…</p>

    @if($previewCounts !== [])
        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
            <p class="font-semibold text-slate-800">ตัวอย่างการนำเข้า</p>
            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-slate-700">
                <span>เพิ่มใหม่ {{ $previewCounts['insert_count'] }}</span>
                <span>ปรับปรุง {{ $previewCounts['update_count'] }}</span>
                <span>ไม่พบบุคลากร {{ $previewCounts['unmatched_person_count'] }}</span>
                <span>ไม่พบผู้ใช้ {{ $previewCounts['unmatched_user_count'] }}</span>
                @if(($previewCounts['skipped_count'] ?? 0) > 0)
                    <span>ข้าม (นอกต้นสังกัดหรือประเภทการจ้างที่รับผิดชอบ) {{ $previewCounts['skipped_count'] }}</span>
                @endif
            </div>
        </div>
    @endif
    @if($warnings !== [])
        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">คำเตือนทั้งหมด {{ $warningCount }} แถว: ระบบจะยึดตัวเลขเงินจากไฟล์</p>
            @if($warningCount > count($warnings))<p class="mt-1">แสดง {{ count($warnings) }} แถวแรก กรุณาตรวจไฟล์ก่อนยืนยัน</p>@endif
            <ul class="mt-1 list-disc space-y-1 pl-5">@foreach($warnings as $warning)<li>{{ $warning }}</li>@endforeach</ul>
        </div>
    @endif
    @if($importErrors !== [])
        <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <p class="font-semibold">พบข้อผิดพลาดในไฟล์ กรุณาแก้ไขก่อนนำเข้า</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">@foreach($importErrors as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($previewReady)
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" onclick="window.portalModal.show('salaryImportConfirmModal')" class="btn btn-primary">เริ่มนำเข้า</button>
            <button type="button" wire:click="cancelImport" class="btn btn-secondary">ยกเลิก</button>
        </div>
    @endif

    <div class="modal" id="salaryImportConfirmModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="salary-import-confirm-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document"><div class="modal-content">
            <div class="modal-header">
                <h2 id="salary-import-confirm-title" class="text-lg font-bold text-slate-900">ยืนยันนำเข้า Excel</h2>
                <button type="button" wire:click="cancelImport" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <p class="text-sm leading-6 text-slate-700">ยืนยันนำเข้าข้อมูลการเลื่อนขั้นเงินเดือนหรือไม่? รายการเดิมที่ตรงกับเลขบัตรประชาชนและรอบการประเมินจะถูกปรับปรุง รวมถึงเลขตำแหน่งของบุคลากรและผู้ใช้ที่จับคู่ได้</p>
                @if($previewCounts !== [])<p class="mt-3 text-sm font-semibold text-slate-800">เพิ่มใหม่ {{ $previewCounts['insert_count'] }} · ปรับปรุง {{ $previewCounts['update_count'] }}</p>@endif
                @error('importFile')<p role="alert" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="modal-footer">
                <button type="button" wire:click="cancelImport" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport" class="btn btn-primary">ยืนยันนำเข้า</button>
            </div>
        </div></div>
    </div>

    @script
        $wire.on('salary-import-confirm-closed', () => window.portalModal.hide(document.getElementById('salaryImportConfirmModal')));
        $wire.on('salary-import-saved', ({ message }) => window.portalToast.show(message));
    @endscript
</section>
