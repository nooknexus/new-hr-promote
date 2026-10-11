@php
    $inputClass = 'mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100';
    $money = static fn ($value): string => number_format((float) $value, 2);
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="issuance-heading">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 id="issuance-heading" class="font-bold text-brand-700">ออกหนังสือรับรองการหักภาษี ณ ที่จ่าย ในนามสำนักงานสาธารณสุขจังหวัด</h2>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">รวมค่าตอบแทนทุกเดือนที่ต้นสังกัดบันทึกไว้ในปีภาษี ออกให้ทุกคนที่มีเงินได้รวมมากกว่า 0 แล้วล็อกปี ถ้าต้องแก้ไขให้ปลดล็อก เอกสารเดิมยังดาวน์โหลดได้จนกว่าจะออกใหม่</p>
            </div>
            <div class="w-full sm:w-40">
                <label for="issuance-year" class="block text-sm font-semibold text-slate-700">ปีภาษี</label>
                <select id="issuance-year" wire:model.live="year" class="{{ $inputClass }}">@foreach($years as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700" role="status">
            @if($state?->locked)
                <span class="font-semibold text-emerald-700">ออกหนังสือรับรองแล้ว {{ number_format($issuedCount) }} ฉบับ</span>
                @if($state->issued_at) เมื่อ {{ \Illuminate\Support\Carbon::parse($state->issued_at)->format('j/n/') }}{{ \Illuminate\Support\Carbon::parse($state->issued_at)->year + 543 }} @endif — ปีนี้ถูกล็อก
            @elseif($issuedCount > 0)
                <span class="font-semibold text-amber-700">ปลดล็อกแล้ว</span> บุคลากรยังเห็นหนังสือรับรองเดิม {{ number_format($issuedCount) }} ฉบับ จนกว่าจะออกใหม่
            @else
                ยังไม่ได้ออกหนังสือรับรองปี {{ $year }}
            @endif
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500"><tr>
                    <th scope="col" class="px-4 py-3">ต้นสังกัด</th><th scope="col" class="px-4 py-3 text-right">เดือนที่บันทึก</th><th scope="col" class="px-4 py-3 text-right">บุคลากร</th>
                    <th scope="col" class="px-4 py-3 text-right">รวมรายรับ</th><th scope="col" class="px-4 py-3 text-right">ภาษี</th><th scope="col" class="px-4 py-3">การยืนยัน</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($summary as $office)
                        <tr wire:key="issuance-office-{{ $office->office_id }}">
                            <td class="px-4 py-3">{{ $office->office_name ?? $office->office_id }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $office->months }}/12</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($office->people) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $money($office->income) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $money($office->tax) }}</td>
                            <td class="px-4 py-3">
                                @if($office->confirmed_at)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">ยืนยันแล้ว</span>
                                    @unless($state?->locked)
                                        <button type="button" wire:click="prepareUnconfirm('{{ $office->office_id }}')" onclick="window.portalModal.show('issuanceUnconfirmModal')" class="btn btn-sm btn-secondary ml-2">ปลดการยืนยัน</button>
                                    @endunless
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">ยังไม่ยืนยัน</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">ยังไม่มีต้นสังกัดบันทึกข้อมูลปี {{ $year }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            @if($state?->locked)
                <button type="button" wire:click="prepareReopen" onclick="window.portalModal.show('issuanceReopenModal')" class="btn btn-secondary"><i class="fas fa-lock-open" aria-hidden="true"></i> ปลดล็อกปี {{ $year }}</button>
            @elseif($summary->isNotEmpty())
                <button type="button" wire:click="prepareIssue" onclick="window.portalModal.show('issuanceConfirmModal')" class="btn btn-primary" @disabled(! $hasPayer)><i class="fas fa-file-signature" aria-hidden="true"></i> ออกหนังสือรับรองปี {{ $year }}</button>
                @unless($hasPayer)<span class="text-sm text-amber-700">ตั้งค่าข้อมูลผู้จ่ายเงินด้านล่างก่อน</span>@endunless
            @endif
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="payer-heading">
        <h2 id="payer-heading" class="font-bold text-brand-700">ข้อมูลผู้จ่ายเงิน</h2>
        <p class="mt-1 text-sm text-slate-500">พิมพ์ลงหนังสือรับรองตอนกดออก หนังสือรับรองที่ออกไปแล้วไม่เปลี่ยนตาม ที่อยู่นี้ใช้แทนที่อยู่ของบุคลากรที่ไม่มีที่อยู่ในบัญชีผู้ใช้ด้วย</p>
        <form wire:submit="savePayer" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="payer-name" class="block text-sm font-semibold text-slate-700">ชื่อผู้จ่ายเงิน</label>
                <input id="payer-name" type="text" wire:model="payerName" maxlength="50" class="{{ $inputClass }}">
                @error('payerName')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="payer-tax-id" class="block text-sm font-semibold text-slate-700">เลขประจำตัวผู้เสียภาษีอากร</label>
                <input id="payer-tax-id" type="text" inputmode="numeric" wire:model="payerTaxId" maxlength="13" class="{{ $inputClass }}">
                @error('payerTaxId')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="payer-address" class="block text-sm font-semibold text-slate-700">ที่อยู่</label>
                <input id="payer-address" type="text" wire:model="payerAddress" maxlength="255" class="{{ $inputClass }}">
                @error('payerAddress')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2"><button type="submit" wire:loading.attr="disabled" wire:target="savePayer" class="btn btn-primary">บันทึกข้อมูลผู้จ่ายเงิน</button></div>
        </form>
    </section>

    <div class="modal" id="issuanceConfirmModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="issuance-confirm-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document"><div class="modal-content">
            <div class="modal-header">
                <h2 id="issuance-confirm-title" class="text-lg font-bold text-slate-900">ออกหนังสือรับรองปี {{ $year }}</h2>
                <button type="button" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body space-y-3">
                @if($unconfirmed > 0)
                    <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900" role="alert">ยังมี {{ $unconfirmed }} ต้นสังกัดที่ไม่ยืนยันข้อมูล ยอดที่บันทึกไว้ของต้นสังกัดเหล่านั้นจะถูกนับเข้าหนังสือรับรองด้วย ต้องการออกหนังสือรับรองต่อหรือไม่?</p>
                @endif
                <p class="text-sm text-slate-700">ระบบจะรวมยอดทุกเดือนของปี {{ $year }} ออกหนังสือรับรองให้บุคลากร แล้วล็อกการแก้ไขทั้งปี</p>
                <div>
                    <label for="issuance-date" class="block text-sm font-semibold text-slate-700">วันที่ออกหนังสือรับรอง</label>
                    <input id="issuance-date" type="date" wire:model="dateReport" class="{{ $inputClass }}">
                    @error('dateReport')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                @error('issue')<p role="alert" class="text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="modal-footer">
                <button type="button" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                <button type="button" wire:click="issue" wire:loading.attr="disabled" wire:target="issue" class="btn btn-primary">{{ $unconfirmed > 0 ? 'ยืนยันออกโดยข้ามการยืนยัน' : 'ออกหนังสือรับรอง' }}</button>
            </div>
        </div></div>
    </div>

    <div class="modal" id="issuanceReopenModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="issuance-reopen-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document"><div class="modal-content">
            <div class="modal-header">
                <h2 id="issuance-reopen-title" class="text-lg font-bold text-slate-900">ปลดล็อกปี {{ $year }}</h2>
                <button type="button" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body"><p class="text-sm leading-6 text-slate-700">การยืนยันของทุกต้นสังกัดจะถูกล้าง ทุกต้นสังกัดแก้ไขได้และต้องยืนยันใหม่ หนังสือรับรองเดิมยังดาวน์โหลดได้จนกว่าจะออกใหม่ ต้องการปลดล็อกหรือไม่?</p></div>
            <div class="modal-footer">
                <button type="button" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                <button type="button" wire:click="reopen" wire:loading.attr="disabled" wire:target="reopen" class="btn btn-primary">ปลดล็อก</button>
            </div>
        </div></div>
    </div>

    <div class="modal" id="issuanceUnconfirmModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="issuance-unconfirm-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document"><div class="modal-content">
            <div class="modal-header">
                <h2 id="issuance-unconfirm-title" class="text-lg font-bold text-slate-900">ปลดการยืนยัน</h2>
                <button type="button" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <p class="text-sm text-slate-700">ให้ <strong>{{ $pendingUnconfirmOfficeName ?? $pendingUnconfirmOffice }}</strong> แก้ไขข้อมูลปี {{ $year }} ได้อีกครั้งหรือไม่?</p>
                @error('unconfirm')<p role="alert" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="modal-footer">
                <button type="button" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                <button type="button" wire:click="unconfirm" wire:loading.attr="disabled" wire:target="unconfirm" class="btn btn-primary">ปลดการยืนยัน</button>
            </div>
        </div></div>
    </div>

    @script
    <script>
        const hide = (id) => window.portalModal.hide(document.getElementById(id));
        $wire.on('issuance-confirm-opened', () => window.portalModal.show('issuanceConfirmModal'));
        $wire.on('issuance-confirm-closed', () => hide('issuanceConfirmModal'));
        $wire.on('issuance-reopen-opened', () => window.portalModal.show('issuanceReopenModal'));
        $wire.on('issuance-reopen-closed', () => hide('issuanceReopenModal'));
        $wire.on('issuance-unconfirm-opened', () => window.portalModal.show('issuanceUnconfirmModal'));
        $wire.on('issuance-unconfirm-closed', () => hide('issuanceUnconfirmModal'));
        $wire.on('issuance-saved', ({ message }) => window.portalToast.show(message));
    </script>
    @endscript
</div>
