@php
    $inputClass = 'mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100';
    $thaiDate = static fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('j/n/') . (\Illuminate\Support\Carbon::parse($date)->year + 543) : '—';
    $money = static fn ($value) => is_numeric($value) ? number_format((float) $value, 2) : '—';
@endphp
<div class="space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">จัดการข้อมูล</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">จัดการข้อมูลการเลื่อนขั้นเงินเดือน</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">เพิ่มและแก้ไขรายการเลื่อนขั้นเงินเดือน รายการที่ยกเลิกจะเก็บไว้ในทะเบียนและสามารถกู้คืนได้</p>
        </div>
        @if($hasTypes)
            <button type="button" wire:click="openCreate" onclick="window.portalModal.show('promotionFormModal')" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i><span>เพิ่มรายการ</span>
            </button>
        @endif
    </header>

    @if($hasTypes)
        <livewire:admin.salary-promotion-import />
    @else
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
            ยังไม่ได้กำหนดประเภทการจ้างที่รับผิดชอบ กรุณาติดต่อผู้ดูแลระบบ
        </div>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="promotion-list-heading">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4">
            <h2 id="promotion-list-heading" class="font-bold text-brand-700">ทะเบียนการเลื่อนขั้นเงินเดือน</h2>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div>
                    <label for="promotion-year-filter" class="sr-only">ปีงบประมาณ</label>
                    <select id="promotion-year-filter" wire:model.live="yearFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">ทุกปีงบประมาณ</option>@foreach($years as $year)<option value="{{ $year }}">ปี {{ $year }}</option>@endforeach</select>
                </div>
                <div>
                    <label for="promotion-office-filter" class="sr-only">กรองตามต้นสังกัด</label>
                    <select id="promotion-office-filter" wire:model.live="officeFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">ทุกต้นสังกัด</option>@foreach($offices as $officeOption)<option value="{{ $officeOption->office_id }}">{{ $officeOption->office_id }} — {{ $officeOption->office_name ?: 'ไม่ระบุชื่อต้นสังกัด' }}</option>@endforeach @unless($typeRestricted)<option value="unmatched">ไม่พบบุคลากร</option>@endunless</select>
                </div>
                <div>
                    <label for="promotion-type-filter" class="sr-only">กรองตามประเภทการจ้าง</label>
                    <select id="promotion-type-filter" wire:model.live="typeFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">ทุกประเภทการจ้าง</option>@foreach($personnelTypes as $typeId => $typeName)<option value="{{ $typeId }}">{{ $typeName }}</option>@endforeach</select>
                </div>
                <div>
                    <label for="promotion-result-filter" class="sr-only">ผลการเลื่อนขั้น</label>
                    <select id="promotion-result-filter" wire:model.live="promoteFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">ทุกผลการเลื่อนขั้น</option><option value="Y">ได้เลื่อนขั้น</option><option value="N">ไม่ได้เลื่อนขั้น</option></select>
                </div>
                <div>
                    <label for="promotion-status-filter" class="sr-only">สถานะรายการ</label>
                    <select id="promotion-status-filter" wire:model.live="statusFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"><option value="active">ใช้งาน</option><option value="archived">ยกเลิกแล้ว</option><option value="all">ทั้งหมด</option></select>
                </div>
                <div>
                    <label for="promotion-search" class="sr-only">ค้นหาบุคลากร</label>
                    <input id="promotion-search" type="search" wire:model.live.debounce.300ms="search" placeholder="ค้นหาชื่อหรือเลขประชาชน" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">บุคลากร</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">วันที่ประเมิน</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">หน่วยงาน</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">เงินเดือนเดิม</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">เพิ่มขึ้น</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">เงินเดือนใหม่</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">ผล</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($promotions as $promotion)
                        @php($displayName = trim(implode(' ', array_filter([$promotion->pname, $promotion->fname, $promotion->lname]))))
                        <tr wire:key="promotion-{{ $promotion->id }}" @class(['hover:bg-slate-50', 'text-slate-400' => $promotion->deleted_at])>
                            <td class="whitespace-nowrap px-4 py-3">
                                <p class="font-semibold text-slate-800">{{ $displayName ?: 'ไม่พบบุคลากร' }}</p>
                                <p class="font-mono text-xs text-slate-500">{{ $promotion->CID }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums">{{ $thaiDate($promotion->evaluation_date) }}</td>
                            <td class="max-w-48 px-4 py-3">{{ $promotion->office_name ?: ($promotion->office ?: '—') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $money($promotion->before_salary) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $money($promotion->sum) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $money($promotion->new_salary) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">@if($promotion->deleted_at)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-500">ยกเลิกแล้ว</span>@elseif($promotion->promote === 'Y')<span class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700">ได้เลื่อนขั้น</span>@elseif($promotion->promote === 'N')<span class="rounded-full bg-amber-50 px-2 py-1 text-xs text-amber-700">ไม่ได้เลื่อนขั้น</span>@else<span>ไม่ระบุ</span>@endif</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @if($promotion->deleted_at)
                                    <button type="button" wire:click="prepareRestore({{ $promotion->id }})" onclick="window.portalModal.show('promotionConfirmModal')" class="btn btn-info btn-sm">กู้คืน</button>
                                @else
                                    <button type="button" wire:click="edit({{ $promotion->id }})" onclick="window.portalModal.show('promotionFormModal')" class="btn btn-info btn-sm">แก้ไข</button>
                                    <button type="button" wire:click="prepareArchive({{ $promotion->id }})" onclick="window.portalModal.show('promotionConfirmModal')" class="btn btn-sm ml-1 bg-rose-600 text-white hover:bg-rose-700">ยกเลิก</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">ไม่พบรายการเลื่อนขั้นเงินเดือน</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($promotions->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $promotions->links() }}</div>@endif
    </section>

    <div class="modal" id="promotionFormModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="promotion-form-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header"><h2 id="promotion-form-title" class="text-lg font-bold text-slate-900">{{ $editingId ? 'แก้ไขรายการเลื่อนขั้นเงินเดือน' : 'เพิ่มรายการเลื่อนขั้นเงินเดือน' }}</h2><button type="button" wire:click="closeForm" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button></div>
                <form wire:submit="save">
                    <div class="modal-body grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="promotion-person-search" class="block text-sm font-semibold text-slate-700">บุคลากร</label>
                            @if($cid !== '')
                                <div class="mt-1.5 flex items-center justify-between gap-3 rounded-xl border border-brand-100 bg-brand-50 px-3 py-2.5"><span class="text-sm font-semibold text-slate-800">{{ $personName }} <span class="font-mono font-normal text-slate-500">{{ $cid }}</span></span>@unless($editingId)<button type="button" wire:click="clearPerson" class="text-sm font-semibold text-brand-700 hover:underline">เปลี่ยน</button>@endunless</div>
                            @else
                                <input id="promotion-person-search" type="search" wire:model.live.debounce.300ms="personSearch" autocomplete="off" placeholder="ค้นหาชื่อ นามสกุล หรือเลขประชาชนอย่างน้อย 2 ตัวอักษร" class="{{ $inputClass }}">
                                @if(mb_strlen(trim($personSearch)) >= 2)
                                    <ul role="listbox" class="relative z-10 mt-1 max-h-52 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                                        @forelse($personResults as $person)
                                            <li role="option" aria-selected="false" wire:key="promotion-person-{{ $person->cid }}"><button type="button" wire:click="selectPerson('{{ $person->cid }}')" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50"><span>{{ trim(implode(' ', array_filter([$person->pname, $person->fname, $person->lname]))) }}</span><span class="text-xs text-slate-500">{{ $person->office_name }}</span></button></li>
                                        @empty<li class="px-3 py-2 text-sm text-slate-500">ไม่พบรายชื่อ</li>@endforelse
                                    </ul>
                                @endif
                            @endif
                            @error('cid')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div><label for="promotion-date" class="block text-sm font-semibold text-slate-700">วันที่ประเมิน</label><input id="promotion-date" type="date" wire:model.live="evaluation_date" class="{{ $inputClass }}">@error('evaluation_date')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        <div><label for="promotion-result" class="block text-sm font-semibold text-slate-700">ผลการเลื่อนขั้น</label><select id="promotion-result" wire:model="promote" class="{{ $inputClass }}">@if($editingId && $promote === '')<option value="">ไม่ระบุ (ข้อมูลเดิม)</option>@endif<option value="Y">ได้เลื่อนขั้น</option><option value="N">ไม่ได้เลื่อนขั้น</option></select>@error('promote')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        @foreach(['before_salary' => 'เงินเดือนเดิม', 'base' => 'ฐานคำนวณ', 'percent' => 'ร้อยละ', 'extra' => 'เงินเพิ่มเติม', 'new_salary' => 'เงินเดือนใหม่'] as $field => $label)
                            <div><label for="promotion-{{ $field }}" class="block text-sm font-semibold text-slate-700">{{ $label }}</label><input id="promotion-{{ $field }}" type="{{ $field === 'percent' ? 'text' : 'number' }}" inputmode="decimal" step="0.01" min="0" wire:model{{ $field === 'percent' ? '.live.debounce.300ms' : ($field === 'base' ? '.live' : '') }}="{{ $field }}" @if(in_array($field, ['before_salary', 'new_salary'], true)) readonly aria-readonly="true" @endif class="{{ $inputClass }} {{ in_array($field, ['before_salary', 'new_salary'], true) ? 'bg-slate-50 text-slate-700' : '' }}">@error($field)<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        @endforeach
                        <div class="sm:col-span-2 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">@if($previewIncrease !== null)<strong class="text-slate-900">ยอดเพิ่มขึ้น {{ $previewIncrease }} บาท</strong><br>@endif เงินเดือนใหม่ = เงินเดือนเดิม + (ฐานคำนวณ × ร้อยละ ÷ 100) เงินเดือนเดิมและฐานคำนวณตั้งต้นจากรายการก่อนหน้า แต่สามารถปรับฐานคำนวณได้เมื่อมีการเลื่อนระดับ @if(!$editingId && $cid !== '' && $evaluation_date !== '' && $before_salary === '')<span class="block font-semibold text-amber-700">ไม่พบรายการก่อนหน้าสำหรับวันที่ประเมินนี้ จึงยังบันทึกรายการใหม่ไม่ได้</span>@endif</div>
                        <div><label for="promotion-office" class="block text-sm font-semibold text-slate-700">หน่วยงาน</label><input id="promotion-office" type="text" wire:model="office" maxlength="255" class="{{ $inputClass }}">@error('office')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        <div><label for="promotion-department" class="block text-sm font-semibold text-slate-700">แผนก</label><input id="promotion-department" type="text" wire:model="department" maxlength="255" class="{{ $inputClass }}">@error('department')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        <div class="sm:col-span-2"><label for="promotion-ps" class="block text-sm font-semibold text-slate-700">หมายเหตุ</label><textarea id="promotion-ps" wire:model="ps" rows="2" maxlength="255" class="{{ $inputClass }}"></textarea>@error('ps')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                    </div>
                    <div class="modal-footer"><button type="button" wire:click="closeForm" data-modal-close class="btn btn-secondary">ปิด</button><button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn btn-primary">บันทึก</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="promotionConfirmModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="promotion-confirm-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document"><div class="modal-content">
            <div class="modal-header"><h2 id="promotion-confirm-title" class="text-lg font-bold text-slate-900">ยืนยันการจัดการรายการ</h2><button type="button" wire:click="dismissAction" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button></div>
            <div class="modal-body"><p class="text-sm leading-6 text-slate-600">{{ $pendingRestore ? 'ยืนยันการกู้คืนรายการนี้หรือไม่? รายการจะกลับไปแสดงในประวัติของบุคลากร' : 'ยืนยันการยกเลิกรายการนี้หรือไม่? รายการจะไม่แสดงในประวัติของบุคลากร แต่ยังสามารถกู้คืนได้' }}</p></div>
            <div class="modal-footer"><button type="button" wire:click="dismissAction" data-modal-close class="btn btn-secondary">ปิด</button>@if($pendingRestore)<button type="button" wire:click="restore" wire:loading.attr="disabled" wire:target="restore" class="btn btn-primary">กู้คืนรายการ</button>@else<button type="button" wire:click="archive" wire:loading.attr="disabled" wire:target="archive" class="btn bg-rose-600 text-white hover:bg-rose-700">ยกเลิกรายการ</button>@endif</div>
        </div></div>
    </div>

    @script
        $wire.on('promotion-form-opened', () => window.portalModal.show('promotionFormModal'));
        $wire.on('promotion-form-closed', () => window.portalModal.hide(document.getElementById('promotionFormModal')));
        $wire.on('promotion-confirm-opened', () => window.portalModal.show('promotionConfirmModal'));
        $wire.on('promotion-confirm-closed', () => window.portalModal.hide(document.getElementById('promotionConfirmModal')));
        $wire.on('promotion-saved', ({ message }) => window.portalToast.show(message));
    @endscript
</div>
