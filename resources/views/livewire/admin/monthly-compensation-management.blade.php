@php
    $inputClass = 'mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100 disabled:bg-slate-100';
    $money = static fn ($value): string => (float) $value == 0.0 ? '—' : number_format((float) $value, 2);
    $incomeFields = array_keys(array_filter($items, fn ($item) => $item['kind'] === 'income'));
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="compensation-confirm-heading">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 id="compensation-confirm-heading" class="font-bold text-brand-700">สถานะการยืนยันข้อมูลปี {{ $year }}</h2>
            <p class="mt-1 text-sm text-slate-500">เมื่อบันทึกครบทั้งปีแล้ว ให้กดยืนยันข้อมูล หลังยืนยันจะแก้ไขไม่ได้จนกว่าสำนักงานสาธารณสุขจังหวัดจะปลดการยืนยัน</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500"><tr>
                    <th scope="col" class="px-4 py-3">ต้นสังกัด</th><th scope="col" class="px-4 py-3 text-right">เดือนที่บันทึก</th><th scope="col" class="px-4 py-3 text-right">บุคลากร</th><th scope="col" class="px-4 py-3">สถานะ</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($summary as $office)
                        <tr wire:key="compensation-office-{{ $office->office_id }}">
                            <td class="px-4 py-3">{{ $office->office_name ?? $office->office_id }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $office->months }}/12</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($office->people) }}</td>
                            <td class="px-4 py-3">
                                @if($office->confirmed_at)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">ยืนยันแล้ว</span>
                                @elseif(! $yearLocked)
                                    <button type="button" wire:click="prepareConfirmYear('{{ $office->office_id }}')" onclick="window.portalModal.show('compensationConfirmModal')" class="btn btn-sm btn-outline-primary">ยืนยันข้อมูลปี {{ $year }}</button>
                                @else
                                    <span class="text-xs text-slate-500">ไม่ได้ยืนยัน</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">ยังไม่มีข้อมูลปี {{ $year }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="compensation-period-heading">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 id="compensation-period-heading" class="font-bold text-brand-700">ค่าตอบแทนรายเดือน</h2>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">บันทึกรายรับและรายการหักที่ต้นสังกัดจ่ายเอง เพื่อออกหนังสือรับรองการหักภาษี ณ ที่จ่ายในนามสำนักงานสาธารณสุขจังหวัด ปีภาษีนับตามเดือนที่จ่ายเงิน (ม.ค.–ธ.ค.) ลูกจ้างประจำไม่ต้องบันทึก</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-[10rem_10rem_auto]">
                <div>
                    <label for="compensation-year" class="block text-sm font-semibold text-slate-700">ปีภาษี</label>
                    <select id="compensation-year" wire:model.live="year" class="{{ $inputClass }}">@foreach($years as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
                </div>
                <div>
                    <label for="compensation-month" class="block text-sm font-semibold text-slate-700">เดือนที่จ่าย</label>
                    <select id="compensation-month" wire:model.live="month" class="{{ $inputClass }}">@foreach($months as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                </div>
                @unless($yearLocked)
                    <div class="flex items-end">
                        <button type="button" wire:click="openCreate" onclick="window.portalModal.show('compensationFormModal')" class="btn btn-primary w-full"><i class="fas fa-plus" aria-hidden="true"></i><span>เพิ่มรายการ</span></button>
                    </div>
                @endunless
            </div>
        </div>
        @if($yearLocked)
            <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">ปี {{ $year }} ออกหนังสือรับรองแล้ว แก้ไขหรือนำเข้าไม่ได้จนกว่าสำนักงานสาธารณสุขจังหวัดจะปลดล็อก</p>
        @endif
    </section>

    @unless($yearLocked)
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="compensation-import-heading">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="compensation-import-heading" class="font-bold text-brand-700">นำเข้า Excel เดือน{{ $months[$month] }} {{ $year }}</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">1 ไฟล์ต่อ 1 เดือน บุคลากรที่มียอดในไฟล์จะถูกแทนที่ยอดของเดือนนี้ แถวที่ไม่มียอดเงินเลยจะไม่ถูกนำเข้า คนที่ไม่อยู่ในไฟล์ไม่ถูกลบ แถวที่อยู่นอกต้นสังกัดหรือมียอดในรายการที่ไม่ตรงประเภทการจ้างจะถูกข้าม</p>
                </div>
                <a href="{{ route('admin.tax-certificates.compensation-template') }}" class="btn btn-outline-primary btn-sm whitespace-nowrap"><i class="fas fa-file-excel" aria-hidden="true"></i> ดาวน์โหลดแม่แบบ</a>
            </div>
            <form wire:submit="previewImport" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="compensation-import-file" class="block text-sm font-semibold text-slate-700">ไฟล์ Excel</label>
                    <input id="compensation-import-file" type="file" wire:model="importFile" accept=".xlsx" class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="importFile,previewImport" class="btn btn-primary">ตรวจสอบตัวอย่าง</button>
            </form>
            @error('importFile')<p role="alert" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
            @if($importPreview !== [])
                <div class="mt-4 space-y-3 text-sm">
                    <p class="font-semibold text-slate-800">เพิ่มใหม่ {{ $importPreview['insert'] }} · ปรับปรุง {{ $importPreview['update'] }} · ข้าม {{ $importPreview['skipped_count'] }} · ไม่มียอด (ไม่นำเข้า) {{ $importPreview['empty'] }} · ข้อผิดพลาด {{ $importPreview['error_count'] }}</p>
                    @if($importPreview['errors'] !== [])
                        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-rose-800" role="alert">
                            <p class="font-semibold">แก้ไขไฟล์ก่อนนำเข้า</p>
                            <ul class="mt-1 list-disc space-y-1 pl-5">@foreach($importPreview['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @if($importPreview['skipped'] !== [])
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-900">
                            <p class="font-semibold">แถวที่จะถูกข้าม @if($importPreview['skipped_count'] > count($importPreview['skipped']))(แสดง {{ count($importPreview['skipped']) }} แถวแรก)@endif</p>
                            <ul class="mt-1 list-disc space-y-1 pl-5">@foreach($importPreview['skipped'] as $skipped)<li>{{ $skipped }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @if($importPreview['error_count'] === 0 && $importPreview['insert'] + $importPreview['update'] > 0)
                        <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport" class="btn btn-primary">ยืนยันนำเข้า</button>
                    @endif
                </div>
            @endif
        </section>
    @endunless

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="compensation-list-heading">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 id="compensation-list-heading" class="font-bold text-brand-700">รายการเดือน{{ $months[$month] }} {{ $year }}</h2>
            <div class="w-full sm:w-72">
                <label for="compensation-search" class="sr-only">ค้นหาบุคลากร</label>
                <input id="compensation-search" type="search" wire:model.live.debounce.300ms="search" placeholder="ค้นหาชื่อหรือเลขประชาชน" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500"><tr>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">บุคลากร</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">ต้นสังกัด</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">รวมรายรับ</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">ภาษี</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">ประกันสังคม</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">กองทุนสำรองเลี้ยงชีพ</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">จัดการ</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr wire:key="compensation-{{ $row->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3"><p class="font-semibold text-slate-900">{{ \App\Livewire\Admin\MonthlyCompensationManagement::fullName($row) ?: '—' }}</p></td>
                            <td class="px-4 py-3">{{ $officeNames[$row->office_id] ?? $row->office_id }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $money(array_sum(array_map(fn ($field) => (float) $row->{$field}, $incomeFields))) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $money($row->withholding_tax) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $money($row->social_security) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $money($row->provident_fund) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" wire:click="edit({{ $row->id }})" onclick="window.portalModal.show('compensationFormModal')" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit" aria-hidden="true"></i> แก้ไข</button>
                                <button type="button" wire:click="confirmDelete({{ $row->id }})" onclick="window.portalModal.show('compensationDeleteModal')" class="btn btn-sm btn-secondary">ลบ</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">ยังไม่มีรายการของเดือนนี้</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $rows->links() }}</div>@endif
    </section>

    <div class="modal" id="compensationFormModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="compensation-form-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
            <div class="modal-header">
                <h2 id="compensation-form-title" class="text-lg font-bold text-slate-900">{{ $editingId ? 'แก้ไข' : 'เพิ่ม' }}ค่าตอบแทนเดือน{{ $months[$month] }} {{ $year }}</h2>
                <button type="button" wire:click="closeForm" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <form wire:submit="save">
                <div class="modal-body space-y-4">
                    <div>
                        <label for="compensation-person" class="block text-sm font-semibold text-slate-700">บุคลากร</label>
                        @if($cid !== '')
                            <p class="mt-1.5 rounded-xl bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-800">{{ $personName }}</p>
                        @else
                            <input id="compensation-person" type="search" wire:model.live.debounce.300ms="personSearch" placeholder="พิมพ์ชื่อหรือเลขประชาชนอย่างน้อย 2 ตัวอักษร" class="{{ $inputClass }}">
                            @if($personResults->isNotEmpty())
                                <ul class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200" role="listbox">
                                    @foreach($personResults as $person)
                                        <li wire:key="compensation-person-{{ $person->cid }}"><button type="button" wire:click="selectPerson('{{ $person->cid }}')" class="flex w-full justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50"><span>{{ \App\Livewire\Admin\MonthlyCompensationManagement::fullName($person) }}</span><span class="text-xs text-slate-500">{{ $person->type_name }}</span></button></li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                        @error('cid')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    @if($cid !== '')
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach($items as $field => $item)
                                @if(\App\Support\CompensationItems::allows($field, $selectedType === null ? null : (int) $selectedType))
                                    {{-- อื่นๆ takes a full row in a blue panel so its amount and name read as one item. --}}
                                    <div @class(['sm:col-span-2 grid gap-3 rounded-xl border border-sky-200 bg-sky-50 p-3 sm:grid-cols-2' => $field === 'other_amount'])>
                                        <div>
                                            <label for="compensation-{{ $field }}" class="block text-sm font-semibold text-slate-700">{{ $item['label'] }} <span class="font-normal text-slate-400">({{ $item['kind'] === 'income' ? 'รายรับ' : 'หัก' }})</span></label>
                                            <input id="compensation-{{ $field }}" type="number" inputmode="decimal" step="0.01" min="0" wire:model="amounts.{{ $field }}" class="{{ $inputClass }}">
                                        </div>
                                        @if($field === 'other_amount')
                                            <div>
                                                <label for="compensation-other-name" class="block text-sm font-semibold text-slate-700">ชื่อรายการอื่นๆ</label>
                                                <input id="compensation-other-name" type="text" wire:model="other_name" class="{{ $inputClass }}">
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    @error('amounts')<p role="alert" class="text-sm text-rose-700">{{ $message }}</p>@enderror
                    @error('amounts.*')<p role="alert" class="text-sm text-rose-700">{{ $message }}</p>@enderror
                    @error('month')<p role="alert" class="text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" wire:click="closeForm" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn btn-primary" @disabled($cid === '')>บันทึก</button>
                </div>
            </form>
        </div></div>
    </div>

    <div class="modal" id="compensationDeleteModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="compensation-delete-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document"><div class="modal-content">
            <div class="modal-header">
                <h2 id="compensation-delete-title" class="text-lg font-bold text-slate-900">ยืนยันการลบ</h2>
                <button type="button" wire:click="closeForm" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <p class="text-sm text-slate-700">ลบค่าตอบแทนเดือนนี้ของบุคลากรรายนี้หรือไม่?</p>
                @error('delete')<p role="alert" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="modal-footer">
                <button type="button" wire:click="closeForm" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                <button type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete" class="btn bg-rose-600 text-white hover:bg-rose-700">ลบ</button>
            </div>
        </div></div>
    </div>

    <div class="modal" id="compensationConfirmModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="compensation-confirm-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document"><div class="modal-content">
            <div class="modal-header">
                <h2 id="compensation-confirm-title" class="text-lg font-bold text-slate-900">ยืนยันข้อมูลปี {{ $year }}</h2>
                <button type="button" wire:click="closeForm" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <p class="text-sm leading-6 text-slate-700">ยืนยันว่าข้อมูลค่าตอบแทนปี {{ $year }} ของต้นสังกัด <strong>{{ $pendingConfirmOfficeName ?? $pendingConfirmOffice }}</strong> ครบถ้วนแล้ว? หลังยืนยันจะแก้ไขหรือนำเข้าไม่ได้จนกว่าสำนักงานสาธารณสุขจังหวัดจะปลดการยืนยัน</p>
                @error('confirm')<p role="alert" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="modal-footer">
                <button type="button" wire:click="closeForm" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                <button type="button" wire:click="confirmYear" wire:loading.attr="disabled" wire:target="confirmYear" class="btn btn-primary">ยืนยัน</button>
            </div>
        </div></div>
    </div>

    @script
    <script>
        $wire.on('compensation-form-opened', () => window.portalModal.show('compensationFormModal'));
        $wire.on('compensation-form-closed', () => window.portalModal.hide(document.getElementById('compensationFormModal')));
        $wire.on('compensation-delete-opened', () => window.portalModal.show('compensationDeleteModal'));
        $wire.on('compensation-delete-closed', () => window.portalModal.hide(document.getElementById('compensationDeleteModal')));
        $wire.on('compensation-confirm-opened', () => window.portalModal.show('compensationConfirmModal'));
        $wire.on('compensation-confirm-closed', () => window.portalModal.hide(document.getElementById('compensationConfirmModal')));
        $wire.on('compensation-saved', ({ message }) => window.portalToast.show(message));
    </script>
    @endscript
</div>
