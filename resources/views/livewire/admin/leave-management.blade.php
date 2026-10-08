@php
    $inputClass = 'mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100 disabled:bg-slate-100';
    $statusStyles = [
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'step1_approved' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'rejected' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'cancelled' => 'bg-slate-100 text-slate-500 ring-slate-200',
    ];
    $thaiDate = static fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('j/n/') . (\Illuminate\Support\Carbon::parse($date)->year + 543) : '—';
    $formatDays = static fn ($days) => rtrim(rtrim(number_format((float) $days, 1), '0'), '.');
@endphp
<div class="space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">จัดการข้อมูล</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">จัดการข้อมูลการลา</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">ทะเบียนรายการลาทั้งหมดในระบบ เพิ่มรายการลา แก้ไข หรือยกเลิกวันลาได้ รายการที่ยกเลิกจะยังคงอยู่ในทะเบียนแต่ไม่นับเป็นวันลา</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row">
            <button type="button" wire:click="openCreate" onclick="window.portalModal.show('leaveFormModal')" class="btn btn-primary">
                <i class="fas fa-calendar-plus" aria-hidden="true"></i><span>เพิ่มข้อมูลลา</span>
            </button>
        </div>
    </header>

    @if(\App\Support\OfficeScope::officeFor(auth()->user()) !== '')
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="leave-import-heading">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 id="leave-import-heading" class="font-bold text-slate-900">นำเข้าข้อมูลจาก Excel</h2>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">วันที่ใช้ ค.ศ. YYYY-MM-DD หรือวันที่ Excel ระบบคำนวณวันทำการหักวันหยุดและบันทึกเป็นอนุมัติแล้ว ข้ามรายการซ้ำและนอกต้นสังกัด หากข้อมูลผิดหรือวันลาทับกันจะไม่บันทึกทั้งไฟล์</p>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">A เลขบัตร 13 หลัก · B ประเภทการลา · C วันที่เริ่ม · D วันที่สิ้นสุด · E เต็มวัน/ครึ่งวัน · F หมายเหตุ — รองรับ .xlsx ไม่เกิน 5MB และ 5,000 รายการ</p>
                </div>
                <div class="flex shrink-0 flex-col gap-2">
                    <a href="{{ route('admin.leaves.template') }}" class="btn btn-secondary">
                        <i class="fas fa-file-download" aria-hidden="true"></i><span>ดาวน์โหลดเทมเพลท</span>
                    </a>
                    @if(\App\Support\AdminAccess::allows(auth()->user(), 'leave-sync'))
                        <a href="{{ route('admin.leave-sheet-sync') }}" wire:navigate class="btn btn-info">
                            <i class="fas fa-cloud-download-alt" aria-hidden="true"></i><span>ดึงประวัติการลา</span>
                        </a>
                    @endif
                </div>
            </div>
            <form wire:submit="importExcel" class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-start">
                <div class="min-w-0 flex-1">
                    <label for="leave-import-file" class="sr-only">ไฟล์ Excel ข้อมูลการลา</label>
                    <input id="leave-import-file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" wire:model="importFile" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                    @error('importFile') <p class="mt-2 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                    <p wire:loading wire:target="importFile" class="mt-2 text-sm text-slate-500">กำลังเตรียมไฟล์...</p>
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="importExcel,importFile" class="btn btn-primary shrink-0">
                    <i wire:loading.remove wire:target="importExcel" class="fas fa-file-import" aria-hidden="true"></i>
                    <i wire:loading wire:target="importExcel" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                    <span>นำเข้าข้อมูล</span>
                </button>
            </form>
        </section>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="leave-list-heading">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4">
            <h2 id="leave-list-heading" class="font-bold text-brand-700">ทะเบียนการลา</h2>
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                <div class="sm:w-48">
                    <label for="leave-office-filter" class="sr-only">กรองตามต้นสังกัด</label>
                    <select id="leave-office-filter" wire:model.live="officeFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                        <option value="">ทุกต้นสังกัด</option>
                        @foreach($offices as $office)
                            <option value="{{ $office->office_id }}">{{ $office->office_id }} — {{ $office->office_name ?: 'ไม่ระบุชื่อต้นสังกัด' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-36">
                    <label for="leave-year-filter" class="sr-only">กรองตามปี</label>
                    <select id="leave-year-filter" wire:model.live="yearFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                        <option value="">ทุกปี (พ.ศ.)</option>
                        @foreach($years as $year)
                            <option value="{{ $year }}">{{ $year + 543 }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-36">
                    <label for="leave-month-filter" class="sr-only">กรองตามเดือน</label>
                    <select id="leave-month-filter" wire:model.live="monthFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                        <option value="">ทุกเดือน</option>
                        @foreach($months as $number => $monthName)
                            <option value="{{ $number }}">{{ $monthName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-44">
                    <label for="leave-type-filter" class="sr-only">กรองตามประเภทการลา</label>
                    <select id="leave-type-filter" wire:model.live="leaveTypeFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                        <option value="">ทุกประเภทการลา</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-40">
                    <label for="leave-status-filter" class="sr-only">กรองตามสถานะ</label>
                    <select id="leave-status-filter" wire:model.live="statusFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                        <option value="">ทุกสถานะ</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative w-full sm:w-64">
                    <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <label for="leave-search" class="sr-only">ค้นหารายการลา</label>
                    <input id="leave-search" type="search" wire:model.live.debounce.300ms="search" placeholder="ค้นหาชื่อหรือ CID" class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">ผู้ลา</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">ประเภทการลา</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">ช่วงวันที่</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">จำนวนวัน</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">สถานะ</th>
                        <th scope="col" class="min-w-48 px-4 py-3">หมายเหตุ</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($leaves as $leave)
                        @php($isCancelled = $leave->status === 'cancelled')
                        <tr wire:key="leave-{{ $leave->id }}" @class(['hover:bg-slate-50/80', 'text-slate-400' => $isCancelled])>
                            <td class="whitespace-nowrap px-4 py-3.5">
                                <p @class(['font-semibold', 'text-slate-800' => !$isCancelled, 'line-through' => $isCancelled])>{{ $leave->external_name ?: 'ไม่ระบุชื่อ' }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ $leave->cid }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5">{{ $leave->leave_type ?: 'ไม่ระบุประเภท' }}</td>
                            <td class="whitespace-nowrap px-4 py-3.5 tabular-nums">
                                {{ $thaiDate($leave->start_date) }}@if($leave->end_date !== $leave->start_date) – {{ $thaiDate($leave->end_date) }}@endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums">
                                {{ (float) $leave->total_days === 0.5 ? 'ครึ่งวัน' : $formatDays($leave->total_days) . ' วัน' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5">
                                <span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', $statusStyles[$leave->status] ?? $statusStyles['cancelled']])>{{ $statuses[$leave->status] ?? 'ไม่ระบุสถานะ' }}</span>
                            </td>
                            <td class="max-w-xs px-4 py-3.5 text-slate-600"><p class="line-clamp-2">{{ $leave->reason }}</p></td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                @unless($isCancelled)
                                    <button type="button" wire:click="edit({{ $leave->id }})" onclick="window.portalModal.show('leaveFormModal')" class="btn btn-info btn-sm"><i class="fas fa-pen" aria-hidden="true"></i><span>แก้ไข</span></button>
                                    <button type="button" wire:click="prepareCancel({{ $leave->id }})" onclick="window.portalModal.show('leaveCancelModal')" class="btn btn-sm ml-1 bg-rose-600 text-white hover:bg-rose-700"><i class="fas fa-ban" aria-hidden="true"></i><span>ยกเลิกวันลา</span></button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">ไม่พบรายการลา</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($leaves->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $leaves->links() }}</div>
        @endif
    </section>

    <div class="modal" id="leaveFormModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="leave-form-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="leave-form-title" class="text-lg font-bold text-slate-900">{{ $editingId ? 'แก้ไขรายการลา' : 'เพิ่มข้อมูลลา' }}</h2>
                    <button type="button" wire:click="closeForm" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="leave-person-search" class="block text-sm font-semibold text-slate-700">รายชื่อบุคลากร</label>
                            @if($cid !== '')
                                <div class="mt-1.5 flex items-center justify-between gap-3 rounded-xl border border-brand-100 bg-brand-50 px-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800">{{ $personName ?: 'ไม่ระบุชื่อ' }}</p>
                                        <p class="font-mono text-xs text-slate-500">{{ $cid }}</p>
                                    </div>
                                    @unless($editingId)
                                        <button type="button" wire:click="clearPerson" class="shrink-0 text-sm font-semibold text-brand-700 hover:underline">เปลี่ยน</button>
                                    @endunless
                                </div>
                            @else
                                <div class="relative">
                                    <input id="leave-person-search" type="search" wire:model.live.debounce.300ms="personSearch" autocomplete="off" placeholder="พิมพ์ชื่อ นามสกุล หรือ CID อย่างน้อย 2 ตัวอักษร" aria-controls="leave-person-results" class="{{ $inputClass }}">
                                    @if(mb_strlen(trim($personSearch)) >= 2)
                                        <ul id="leave-person-results" role="listbox" class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                                            @forelse($personResults as $person)
                                                <li role="option" aria-selected="false" wire:key="person-option-{{ $person->cid }}">
                                                    <button type="button" wire:click="selectPerson('{{ $person->cid }}')" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50 focus:bg-slate-50">
                                                        <span class="font-medium text-slate-800">{{ trim(implode(' ', array_filter([$person->pname, $person->fname, $person->lname]))) }}</span>
                                                        <span class="shrink-0 text-xs text-slate-500">{{ $person->office_name }}</span>
                                                    </button>
                                                </li>
                                            @empty
                                                <li class="px-3 py-2 text-sm text-slate-500">ไม่พบรายชื่อ</li>
                                            @endforelse
                                        </ul>
                                    @endif
                                </div>
                            @endif
                            @error('cid') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="leave-type" class="block text-sm font-semibold text-slate-700">ประเภทการลา</label>
                            <select id="leave-type" wire:model="leave_type_id" class="{{ $inputClass }}">
                                <option value="">เลือกประเภทการลา</option>
                                @foreach($leaveTypes as $type)
                                    @if($type->is_active || $type->id === $leave_type_id)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('leave_type_id') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="leave-start" class="block text-sm font-semibold text-slate-700">วันที่เริ่มต้น</label>
                            <input id="leave-start" type="date" wire:model.live="start_date" class="{{ $inputClass }}">
                            @error('start_date') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="leave-end" class="block text-sm font-semibold text-slate-700">วันที่สิ้นสุด</label>
                            <input id="leave-end" type="date" wire:model.live="end_date" min="{{ $start_date }}" class="{{ $inputClass }}">
                            @error('end_date') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3" aria-live="polite">
                            @if($start_date !== '' && $end_date !== '')
                                @if($workingDays > 0)
                                    <p class="text-sm text-slate-600">จำนวนวันลา
                                        <span class="ml-1 text-lg font-bold tabular-nums text-slate-900">{{ $workingDays === 1 && $duration === 'half' ? '0.5' : $workingDays }}</span> วัน
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-500">นับเฉพาะวันทำการจันทร์–ศุกร์ และหักวันหยุดนักขัตฤกษ์ของประเทศไทยแล้ว</p>
                                    @if($workingDays === 1)
                                        <fieldset class="mt-3">
                                            <legend class="text-sm font-semibold text-slate-700">ระยะเวลาการลา</legend>
                                            <div class="mt-2 flex gap-4">
                                                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="radio" wire:model.live="duration" value="full" class="accent-brand-600"> เต็มวัน</label>
                                                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="radio" wire:model.live="duration" value="half" class="accent-brand-600"> ครึ่งวัน</label>
                                            </div>
                                        </fieldset>
                                    @endif
                                @else
                                    <p class="text-sm text-amber-700">ช่วงวันที่เลือกไม่มีวันทำการ</p>
                                @endif
                            @else
                                <p class="text-sm text-slate-500">เลือกวันที่เริ่มต้นและสิ้นสุดเพื่อคำนวณจำนวนวันลา</p>
                            @endif
                        </div>

                        <div class="sm:col-span-2">
                            <label for="leave-reason" class="block text-sm font-semibold text-slate-700">หมายเหตุ</label>
                            <textarea id="leave-reason" wire:model="reason" rows="3" maxlength="1000" class="{{ $inputClass }}"></textarea>
                            @error('reason') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
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

    <div class="modal" id="leaveCancelModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="leave-cancel-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header"><h2 id="leave-cancel-title" class="text-lg font-bold text-slate-900">ยืนยันการยกเลิกวันลา</h2><button type="button" wire:click="dismissCancel" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button></div>
                <div class="modal-body"><p class="text-sm leading-6 text-slate-600">ยืนยันการยกเลิกรายการลานี้หรือไม่? รายการจะยังอยู่ในทะเบียนโดยมีสถานะ "ยกเลิก" และไม่นับเป็นวันลา</p></div>
                <div class="modal-footer"><button type="button" wire:click="dismissCancel" data-modal-close class="btn btn-secondary">ปิด</button><button type="button" wire:click="cancelLeave" wire:loading.attr="disabled" wire:target="cancelLeave" data-modal-close class="btn bg-rose-600 text-white hover:bg-rose-700">ยกเลิกวันลา</button></div>
            </div>
        </div>
    </div>

    @script
        $wire.on('leave-form-opened', () => window.portalModal.show('leaveFormModal'));
        $wire.on('leave-form-closed', () => window.portalModal.hide(document.getElementById('leaveFormModal')));
        $wire.on('leave-cancel-opened', () => window.portalModal.show('leaveCancelModal'));
        $wire.on('leave-cancel-closed', () => window.portalModal.hide(document.getElementById('leaveCancelModal')));
        $wire.on('leave-saved', ({ message }) => window.portalToast.show(message));
    @endscript
</div>
