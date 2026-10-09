<div class="space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">จัดการข้อมูล</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">ข้อมูลบุคลากร</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">บันทึกวันเริ่มงานเพื่อใช้ตรวจอายุงานและสิทธิการลาพักผ่อนในอนาคต</p>
        </div>
        <button type="button" wire:click="openCreate" onclick="window.portalModal.show('personnelFormModal')" class="btn btn-primary">
            <i class="fas fa-user-plus" aria-hidden="true"></i><span>เพิ่มบุคลากร</span>
        </button>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="personnel-import-heading">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 id="personnel-import-heading" class="font-bold text-slate-900">นำเข้าข้อมูลจาก Excel</h2>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">ระบบอ่านข้อมูลตามตำแหน่งคอลัมน์ ไม่ตรวจชื่อหัวตาราง และข้ามแถวที่ 1 เสมอ ระบบสร้างบุคลากรใหม่จาก CID ที่ยังไม่มี และอัปเดต CID ที่มีอยู่แล้ว ช่องข้อมูลเสริมที่ปล่อยว่างจะไม่ลบข้อมูลเดิม</p>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">A เลขประจำตัวประชาชน · B คำนำหน้า · C ชื่อ · D นามสกุล · E ประเภท (ตัวเลข) · F ชื่อประเภทเจ้าหน้าที่ · G ต้นสังกัด (ตัวเลข 2 หลัก) · H ชื่อต้นสังกัด · I วันเริ่มงาน</p>
            </div>
            <a href="{{ route('admin.personnel.template') }}" class="btn btn-secondary shrink-0">
                <i class="fas fa-file-download" aria-hidden="true"></i><span>ดาวน์โหลด template</span>
            </a>
        </div>

        <form wire:submit="importExcel" class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-start">
            <div class="min-w-0 flex-1">
                <label for="personnel-import-file" class="sr-only">ไฟล์ Excel ข้อมูลบุคลากร</label>
                <input id="personnel-import-file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" wire:model="importFile" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                @error('importFile') <p class="mt-2 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                <p wire:loading wire:target="importFile" class="mt-2 text-sm text-slate-500">กำลังเตรียมไฟล์...</p>
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="importExcel,importFile" class="btn btn-primary shrink-0">
                <i wire:loading.remove wire:target="importExcel" class="fas fa-file-import" aria-hidden="true"></i>
                <i wire:loading wire:target="importExcel" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                <span>นำเข้าข้อมูล</span>
            </button>
        </form>

        @if($importResult)
            <div class="mt-4 grid grid-cols-3 gap-3 border-t border-slate-100 pt-4 text-center">
                <div class="rounded-xl bg-emerald-50 px-3 py-3">
                    <p class="text-xs font-medium text-emerald-700">เพิ่มใหม่</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-emerald-900">{{ number_format($importResult['created']) }}</p>
                </div>
                <div class="rounded-xl bg-sky-50 px-3 py-3">
                    <p class="text-xs font-medium text-sky-700">ปรับปรุง</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-sky-900">{{ number_format($importResult['updated']) }}</p>
                </div>
                <div class="rounded-xl bg-slate-100 px-3 py-3">
                    <p class="text-xs font-medium text-slate-700">ข้าม</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">{{ number_format($importResult['skipped']) }}</p>
                </div>
            </div>
        @endif
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="personnel-list-heading">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 id="personnel-list-heading" class="font-bold text-brand-700">รายชื่อบุคลากร</h2>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <div class="sm:w-56">
                    <label for="personnel-office-filter" class="sr-only">กรองตามต้นสังกัด</label>
                    <select id="personnel-office-filter" wire:model.live="officeFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                        <option value="">ทุกต้นสังกัด</option>
                        @foreach($offices as $office)
                            <option value="{{ $office->office_id }}">{{ $office->office_id }} — {{ $office->office_name ?: 'ไม่ระบุชื่อต้นสังกัด' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-48">
                    <label for="personnel-type-filter" class="sr-only">กรองตามประเภทการจ้าง</label>
                    <select id="personnel-type-filter" wire:model.live="typeFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                        <option value="">ทุกประเภทการจ้าง</option>
                        @foreach($personnelTypes as $typeId => $typeName)
                            <option value="{{ $typeId }}">{{ $typeName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative w-full sm:w-72">
                    <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <label for="personnel-search" class="sr-only">ค้นหาบุคลากร</label>
                    <input id="personnel-search" type="search" wire:model.live.debounce.300ms="search" placeholder="ค้นหา CID, ชื่อ หรือหน่วยงาน" class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">บุคลากร</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">CID</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">ประเภท / หน่วยงาน</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">วันเริ่มงาน / สถานะ 6 เดือน</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($personnel as $person)
                        @php($startedAt = $person->employment_started_at ? \Illuminate\Support\Carbon::parse($person->employment_started_at) : null)
                        <tr wire:key="person-{{ $person->cid }}" class="hover:bg-slate-50/80">
                            <td class="whitespace-nowrap px-4 py-3.5 font-semibold text-slate-800">{{ trim(implode(' ', array_filter([$person->pname, $person->fname, $person->lname]))) }}</td>
                            <td class="whitespace-nowrap px-4 py-3.5 font-mono text-xs text-slate-600">{{ $person->cid }}</td>
                            <td class="px-4 py-3.5 text-slate-600">
                                <p>{{ $person->type_name ?: 'ไม่ระบุประเภท' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $person->office_name ?: 'ไม่ระบุหน่วยงาน' }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5">
                                @if($startedAt)
                                    <p class="font-medium text-slate-700">{{ $startedAt->format('d/m/Y') }}</p>
                                    @if($startedAt->copy()->addMonths(6)->lte(today()))
                                        <p class="mt-1 text-xs font-semibold text-emerald-700"><i class="fas fa-check-circle" aria-hidden="true"></i> ครบ 6 เดือนแล้ว</p>
                                    @else
                                        <p class="mt-1 text-xs font-semibold text-amber-700"><i class="fas fa-clock" aria-hidden="true"></i> ยังไม่ครบ 6 เดือน</p>
                                    @endif
                                @else
                                    <span class="text-slate-400">ยังไม่ระบุ</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                <button type="button" wire:click="edit('{{ $person->cid }}')" onclick="window.portalModal.show('personnelFormModal')" class="btn btn-info btn-sm"><i class="fas fa-pen" aria-hidden="true"></i><span>แก้ไข</span></button>
                                <button type="button" wire:click="prepareDelete('{{ $person->cid }}')" onclick="window.portalModal.show('personnelDeleteModal')" class="btn btn-sm ml-1 bg-rose-600 text-white hover:bg-rose-700"><i class="fas fa-trash" aria-hidden="true"></i><span>ลบ</span></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">ไม่พบข้อมูลบุคลากร</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($personnel->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $personnel->links() }}</div>
        @endif
    </section>

    <div class="modal" id="personnelFormModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="personnel-form-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="personnel-form-title" class="text-lg font-bold text-slate-900">{{ $editingCid ? 'แก้ไขข้อมูลบุคลากร' : 'เพิ่มบุคลากร' }}</h2>
                    <button type="button" wire:click="closeForm" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="person-cid" class="block text-sm font-semibold text-slate-700">เลขประจำตัวประชาชน</label>
                            <input id="person-cid" type="text" inputmode="numeric" maxlength="13" wire:model="cid" @disabled($editingCid) class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100 disabled:bg-slate-100">
                            @error('cid') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="person-pname" class="block text-sm font-semibold text-slate-700">คำนำหน้า</label>
                            <input id="person-pname" type="text" wire:model="pname" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                            @error('pname') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="person-started" class="block text-sm font-semibold text-slate-700">วันเริ่มงาน</label>
                            <input id="person-started" type="date" wire:model="employment_started_at" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                            @error('employment_started_at') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="person-fname" class="block text-sm font-semibold text-slate-700">ชื่อ</label>
                            <input id="person-fname" type="text" wire:model="fname" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                            @error('fname') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="person-lname" class="block text-sm font-semibold text-slate-700">นามสกุล</label>
                            <input id="person-lname" type="text" wire:model="lname" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                            @error('lname') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="person-type-name" class="block text-sm font-semibold text-slate-700">ประเภทเจ้าหน้าที่</label>
                            <select id="person-type-name" wire:model="type_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                                <option value="">เลือกประเภทเจ้าหน้าที่</option>
                                @foreach($personnelTypes as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('type_id') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="person-office-name" class="block text-sm font-semibold text-slate-700">ต้นสังกัด</label>
                            <select id="person-office-name" wire:model="office_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                                <option value="">เลือกต้นสังกัด</option>
                                @foreach($formOffices as $office)
                                    <option value="{{ $office->office_id }}">{{ $office->office_id }} — {{ $office->office_name }}{{ $office->is_active ? '' : ' (ปิดใช้งาน)' }}</option>
                                @endforeach
                            </select>
                            @error('office_id') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="closeForm" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn btn-primary"><span wire:loading.remove wire:target="save">บันทึก</span><span wire:loading wire:target="save">กำลังบันทึก...</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="personnelDeleteModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="personnel-delete-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header"><h2 id="personnel-delete-title" class="text-lg font-bold text-slate-900">ยืนยันการลบข้อมูล</h2><button type="button" wire:click="cancelDelete" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button></div>
                <div class="modal-body"><p class="text-sm leading-6 text-slate-600">ยืนยันการลบข้อมูลบุคลากร CID <span class="font-mono font-semibold text-slate-800">{{ $pendingDeletionCid }}</span> หรือไม่? ประวัติการลาที่มีอยู่จะไม่ถูกลบ แต่จะไม่สามารถจับคู่กับบุคลากรนี้ได้อีก</p></div>
                <div class="modal-footer"><button type="button" wire:click="cancelDelete" data-modal-close class="btn btn-secondary">ยกเลิก</button><button type="button" wire:click="deletePersonnel" wire:loading.attr="disabled" wire:target="deletePersonnel" data-modal-close class="btn bg-rose-600 text-white hover:bg-rose-700">ลบข้อมูล</button></div>
            </div>
        </div>
    </div>

    @script
        $wire.on('personnel-form-opened', () => window.portalModal.show('personnelFormModal'));
        $wire.on('personnel-form-closed', () => window.portalModal.hide(document.getElementById('personnelFormModal')));
        $wire.on('personnel-delete-confirmation-opened', () => window.portalModal.show('personnelDeleteModal'));
        $wire.on('personnel-delete-confirmation-closed', () => window.portalModal.hide(document.getElementById('personnelDeleteModal')));
    @endscript
</div>
