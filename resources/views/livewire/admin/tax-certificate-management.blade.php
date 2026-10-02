@php
    $inputClass = 'mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100 disabled:bg-slate-100';
    $money = static fn ($value): string => is_numeric($value) ? number_format((float) $value, 2) : '—';
    $thaiDate = static function ($date): string {
        $date = (string) $date;

        return preg_match('/^\d{8}$/', $date) === 1
            ? substr($date, 0, 2).'/'.substr($date, 2, 2).'/'.substr($date, 4, 4)
            : '—';
    };
@endphp

<div class="space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">จัดการข้อมูลการเงิน</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">จัดการใบรับรองภาษี</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">ค้นหา เพิ่ม แก้ไข หรือนำเข้าใบรับรองการหักภาษีจากไฟล์ TXT โดยระบบใช้ CID และปีภาษีเป็นรายการอ้างอิงเดียวกัน</p>
        </div>
        <button type="button" wire:click="openCreateModal" onclick="window.portalModal.show('taxCertificateFormModal')" class="btn btn-primary">
            <i class="fas fa-plus" aria-hidden="true"></i><span>เพิ่มใบรับรองภาษี</span>
        </button>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="tax-certificate-import-heading">
        <div>
            <h2 id="tax-certificate-import-heading" class="font-bold text-brand-700">นำเข้าใบรับรองจาก TXT</h2>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">เลือกรหัสอักขระให้ตรงกับไฟล์ก่อนตรวจสอบตัวอย่าง ระบบจะแสดงจำนวนรายการและข้อผิดพลาดก่อนให้ยืนยันนำเข้า โดยไม่มีการเก็บไฟล์ต้นฉบับถาวร</p>
        </div>

        <form wire:submit="previewImport" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 lg:grid-cols-[minmax(0,1fr)_13rem_auto] lg:items-end">
            <div>
                <label for="tax-certificate-import-file" class="block text-sm font-semibold text-slate-700">ไฟล์ TXT</label>
                <input id="tax-certificate-import-file" type="file" accept=".txt,text/plain" wire:model="importFile" class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                @error('importFile')<p role="alert" class="mt-1.5 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="tax-certificate-import-encoding" class="block text-sm font-semibold text-slate-700">รหัสอักขระ</label>
                <select id="tax-certificate-import-encoding" wire:model="importEncoding" class="{{ $inputClass }}">
                    <option value="tis-620">TIS-620 (ค่าเริ่มต้น)</option>
                    <option value="utf-8">UTF-8</option>
                </select>
                @error('importEncoding')<p role="alert" class="mt-1.5 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="importFile,previewImport" class="btn btn-primary">
                <i wire:loading.remove wire:target="previewImport" class="fas fa-search" aria-hidden="true"></i>
                <i wire:loading wire:target="previewImport" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                <span>ตรวจสอบตัวอย่าง</span>
            </button>
        </form>

        <p wire:loading wire:target="importFile,previewImport,confirmImport" class="mt-3 text-sm text-slate-500" role="status">กำลังอ่านไฟล์…</p>

        @if($importPreview !== [])
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <p class="font-semibold text-slate-800">ผลการตรวจสอบตัวอย่าง</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                    <p class="rounded-lg bg-white px-3 py-2 text-slate-700">อ่านได้ <strong class="tabular-nums">{{ number_format($importPreview['read']) }}</strong> แถว</p>
                    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-emerald-800">พร้อมนำเข้า <strong class="tabular-nums">{{ number_format($importPreview['valid']) }}</strong> แถว</p>
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-800">ข้าม <strong class="tabular-nums">{{ number_format($importPreview['skipped']) }}</strong> แถว</p>
                </div>
            </div>
        @endif

        @if(($importPreview['errors'] ?? []) !== [])
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
                <p class="font-semibold">ข้อผิดพลาดจากไฟล์</p>
                <p class="mt-1">ระบบปกปิดเลขประจำตัวประชาชนในรายละเอียดข้อผิดพลาด</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($importPreview['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($importPreviewReady)
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" onclick="window.portalModal.show('taxCertificateImportConfirmModal')" class="btn btn-primary">ยืนยันนำเข้ารายการที่ถูกต้อง</button>
                <button type="button" wire:click="closeModal" class="btn btn-secondary">ยกเลิก</button>
            </div>
        @endif

        @if($importResult !== [])
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm" aria-live="polite">
                <p class="font-semibold text-slate-800">สรุปผลการนำเข้า</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-5">
                    <p class="rounded-lg bg-white px-3 py-2 text-slate-700">อ่าน {{ number_format($importResult['read']) }}</p>
                    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-emerald-800">เพิ่ม {{ number_format($importResult['inserted']) }}</p>
                    <p class="rounded-lg bg-sky-50 px-3 py-2 text-sky-800">ปรับปรุง {{ number_format($importResult['updated']) }}</p>
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-800">ข้าม {{ number_format($importResult['skipped']) }}</p>
                    <p class="rounded-lg bg-rose-50 px-3 py-2 text-rose-800">ไม่สำเร็จ {{ number_format($importResult['failed']) }}</p>
                </div>
                @if(($importResult['errors'] ?? []) !== [])
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-amber-900">
                        @foreach($importResult['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="tax-certificate-list-heading">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4">
            <h2 id="tax-certificate-list-heading" class="font-bold text-brand-700">รายการใบรับรองภาษี</h2>
            <div class="grid gap-2 lg:grid-cols-3">
                <div>
                    <label for="tax-certificate-year-filter" class="sr-only">กรองตามปีภาษี</label>
                    <select id="tax-certificate-year-filter" wire:model.live="yearFilter" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                        <option value="">ทุกปีภาษี</option>
                        @foreach($years as $year)
                            <option value="{{ $year }}">ปีภาษี {{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative">
                    <i class="fas fa-id-card pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <label for="tax-certificate-cid-filter" class="sr-only">ค้นหาตาม CID</label>
                    <input id="tax-certificate-cid-filter" type="search" inputmode="numeric" wire:model.live.debounce.300ms="cidFilter" placeholder="ค้นหา CID" class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                </div>
                <div class="relative">
                    <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <label for="tax-certificate-name-filter" class="sr-only">ค้นหาตามชื่อ</label>
                    <input id="tax-certificate-name-filter" type="search" wire:model.live.debounce.300ms="nameFilter" placeholder="ค้นหาชื่อผู้ถูกหักภาษี" class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">ปี / เลขที่ใบรับรอง</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">ผู้ถูกหักภาษี</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">เงินได้</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">ภาษีหัก ณ ที่จ่าย</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">กองทุน / วันออก</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($certificates as $certificate)
                        @php($personName = trim(implode(' ', array_filter([$certificate->person_pname, $certificate->person_fname, $certificate->person_lname]))))
                        <tr wire:key="tax-certificate-{{ $certificate->cid }}-{{ $certificate->income_year }}" class="hover:bg-slate-50/80">
                            <td class="whitespace-nowrap px-4 py-3.5">
                                <p class="font-semibold text-slate-800">{{ $certificate->income_year }}</p>
                                <p class="font-mono text-xs text-slate-500">{{ $certificate->running_code1 ?: 'ไม่ระบุเลขที่' }}</p>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="font-semibold text-slate-800">{{ $certificate->fullname ?: ($personName ?: 'ไม่ระบุชื่อ') }}</p>
                                <p class="font-mono text-xs text-slate-500">{{ $certificate->cid }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-slate-700">{{ $money($certificate->income_money) }}</td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-slate-700">{{ $money($certificate->withholding_tax) }}</td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-slate-600">
                                <p>{{ $certificate->outcomeName ?: 'ไม่ระบุ' }} @if($certificate->outcomeAmount !== null)<span class="tabular-nums">({{ $money($certificate->outcomeAmount) }})</span>@endif</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $thaiDate($certificate->date_report) }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                <button type="button" wire:click="edit('{{ $certificate->cid }}', {{ $certificate->income_year }})" onclick="window.portalModal.show('taxCertificateFormModal')" class="btn btn-info btn-sm"><i class="fas fa-pen" aria-hidden="true"></i><span>แก้ไข</span></button>
                                <button type="button" wire:click="confirmDelete('{{ $certificate->cid }}', {{ $certificate->income_year }})" onclick="window.portalModal.show('taxCertificateDeleteModal')" class="btn btn-sm ml-1 bg-rose-600 text-white hover:bg-rose-700"><i class="fas fa-trash" aria-hidden="true"></i><span>ลบ</span></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">ไม่พบรายการใบรับรองภาษี</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($certificates->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $certificates->links() }}</div>
        @endif
    </section>

    <div class="modal" id="taxCertificateFormModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="tax-certificate-form-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="tax-certificate-form-title" class="text-lg font-bold text-slate-900">{{ $editingCid ? 'แก้ไขใบรับรองภาษี' : 'เพิ่มใบรับรองภาษี' }}</h2>
                    <button type="button" wire:click="closeModal" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body space-y-6">
                        <fieldset>
                            <legend class="text-sm font-bold text-brand-700">ผู้จ่ายเงิน</legend>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="tax-running-code-1" class="block text-sm font-semibold text-slate-700">เลขที่ใบรับรอง</label>
                                    <input id="tax-running-code-1" type="text" wire:model="running_code1" maxlength="255" class="{{ $inputClass }}">
                                    @error('running_code1')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tax-department-name" class="block text-sm font-semibold text-slate-700">ชื่อหน่วยงาน</label>
                                    <input id="tax-department-name" type="text" wire:model="department_name" maxlength="255" class="{{ $inputClass }}">
                                    @error('department_name')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tax-department-tax-id" class="block text-sm font-semibold text-slate-700">เลขประจำตัวผู้เสียภาษีหน่วยงาน</label>
                                    <input id="tax-department-tax-id" type="text" wire:model="department_tax_id" maxlength="255" class="{{ $inputClass }}">
                                    @error('department_tax_id')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tax-department-address" class="block text-sm font-semibold text-slate-700">ที่อยู่หน่วยงาน</label>
                                    <input id="tax-department-address" type="text" wire:model="department_addr" maxlength="255" class="{{ $inputClass }}">
                                    @error('department_addr')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="text-sm font-bold text-brand-700">ผู้ถูกหักภาษีและปีภาษี</legend>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="tax-cid" class="block text-sm font-semibold text-slate-700">เลขประจำตัวประชาชน</label>
                                    <input id="tax-cid" type="text" inputmode="numeric" maxlength="13" wire:model="cid" @disabled($editingCid) class="{{ $inputClass }}">
                                    @error('cid')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tax-income-year" class="block text-sm font-semibold text-slate-700">ปีภาษี (พ.ศ.)</label>
                                    <input id="tax-income-year" type="text" inputmode="numeric" maxlength="4" wire:model="income_year" @disabled($editingCid) class="{{ $inputClass }}">
                                    @error('income_year')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tax-id" class="block text-sm font-semibold text-slate-700">เลขประจำตัวผู้เสียภาษีผู้รับเงิน</label>
                                    <input id="tax-id" type="text" wire:model="tax_id" maxlength="255" class="{{ $inputClass }}">
                                    @error('tax_id')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tax-fullname" class="block text-sm font-semibold text-slate-700">ชื่อผู้ถูกหักภาษี</label>
                                    <input id="tax-fullname" type="text" wire:model="fullname" maxlength="255" class="{{ $inputClass }}">
                                    @error('fullname')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="tax-address" class="block text-sm font-semibold text-slate-700">ที่อยู่ผู้ถูกหักภาษี</label>
                                    <textarea id="tax-address" rows="2" wire:model="addr" maxlength="255" class="{{ $inputClass }}"></textarea>
                                    @error('addr')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tax-running-code-2" class="block text-sm font-semibold text-slate-700">ลำดับผู้ถูกหักภาษี</label>
                                    <input id="tax-running-code-2" type="text" wire:model="running_code2" maxlength="255" class="{{ $inputClass }}">
                                    @error('running_code2')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="text-sm font-bold text-brand-700">ประเภทเอกสารและยอดเงิน</legend>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="tax-income-type" class="block text-sm font-semibold text-slate-700">ประเภทแบบ ภ.ง.ด.</label>
                                    <select id="tax-income-type" wire:model="income_type_id" class="{{ $inputClass }}">
                                        @foreach($incomeTypes as $id => $name)
                                            <option value="{{ $id }}">{{ $id }} — {{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('income_type_id')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tax-date-report" class="block text-sm font-semibold text-slate-700">วันที่ออกใบรับรอง (DDMMYYYY พ.ศ.)</label>
                                    <input id="tax-date-report" type="text" inputmode="numeric" maxlength="8" wire:model="date_report" placeholder="29012568" class="{{ $inputClass }}">
                                    @error('date_report')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                @foreach(['income_money' => 'เงินได้', 'withholding_tax' => 'ภาษีหัก ณ ที่จ่าย', 'total_money' => 'ยอดรวมเงินได้', 'sumTaxAmount' => 'ยอดรวมภาษี'] as $field => $label)
                                    <div>
                                        <label for="tax-{{ $field }}" class="block text-sm font-semibold text-slate-700">{{ $label }}</label>
                                        <input id="tax-{{ $field }}" type="number" inputmode="decimal" min="0" step="0.01" wire:model="{{ $field }}" class="{{ $inputClass }}">
                                        @error($field)<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                    </div>
                                @endforeach
                                <div class="sm:col-span-2">
                                    <label for="tax-sum-tax-text" class="block text-sm font-semibold text-slate-700">ยอดภาษีเป็นตัวอักษร</label>
                                    <textarea id="tax-sum-tax-text" rows="2" wire:model="sumTaxText" maxlength="255" class="{{ $inputClass }}"></textarea>
                                    @error('sumTaxText')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="text-sm font-bold text-brand-700">กองทุนและวิธีออกหนังสือรับรอง</legend>
                            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label for="tax-outcome-type" class="block text-sm font-semibold text-slate-700">ประเภทกองทุน</label>
                                    <select id="tax-outcome-type" wire:model="outcomeType" class="{{ $inputClass }}">
                                        @foreach($outcomeTypes as $id => $name)
                                            <option value="{{ $id }}">{{ $id }} — {{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('outcomeType')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tax-outcome-amount" class="block text-sm font-semibold text-slate-700">ยอดเงินกองทุน</label>
                                    <input id="tax-outcome-amount" type="number" inputmode="decimal" min="0" step="0.01" wire:model="outcomeAmount" class="{{ $inputClass }}">
                                    @error('outcomeAmount')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tax-type" class="block text-sm font-semibold text-slate-700">วิธีออกหนังสือรับรอง</label>
                                    <select id="tax-type" wire:model="tax_type_id" class="{{ $inputClass }}">
                                        @foreach($taxTypes as $id => $name)
                                            <option value="{{ $id }}">{{ $id }} — {{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('tax_type_id')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="closeModal" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                        @if($editingCid)
                            <button type="button" wire:click="confirmDelete" onclick="window.portalModal.show('taxCertificateDeleteModal')" class="btn bg-rose-600 text-white hover:bg-rose-700">ลบรายการ</button>
                        @endif
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn btn-primary">
                            <i wire:loading wire:target="save" class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>บันทึก</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="taxCertificateDeleteModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="tax-certificate-delete-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document"><div class="modal-content">
            <div class="modal-header">
                <h2 id="tax-certificate-delete-title" class="text-lg font-bold text-slate-900">ยืนยันการลบใบรับรองภาษี</h2>
                <button type="button" wire:click="closeModal" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <p class="text-sm leading-6 text-slate-700">ยืนยันการลบใบรับรองของ CID {{ $pendingDeletionCid }} ปีภาษี {{ $pendingDeletionIncomeYear }} หรือไม่? การลบนี้ไม่สามารถกู้คืนจากหน้านี้ได้</p>
            </div>
            <div class="modal-footer">
                <button type="button" wire:click="closeModal" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                <button type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete" class="btn bg-rose-600 text-white hover:bg-rose-700">ลบใบรับรอง</button>
            </div>
        </div></div>
    </div>

    <div class="modal" id="taxCertificateImportConfirmModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="tax-certificate-import-confirm-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document"><div class="modal-content">
            <div class="modal-header">
                <h2 id="tax-certificate-import-confirm-title" class="text-lg font-bold text-slate-900">ยืนยันนำเข้าใบรับรองภาษี</h2>
                <button type="button" wire:click="closeModal" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <p class="text-sm leading-6 text-slate-700">ระบบจะบันทึกรายการที่ผ่านการตรวจสอบ โดยรายการที่มี CID และปีภาษีตรงกับข้อมูลเดิมจะถูกปรับปรุง ข้อผิดพลาดในไฟล์จะถูกข้าม</p>
                @if($importPreview !== [])
                    <p class="mt-3 text-sm font-semibold text-slate-800">พร้อมนำเข้า {{ number_format($importPreview['valid']) }} แถว · ข้าม {{ number_format($importPreview['skipped']) }} แถว</p>
                @endif
                @error('importFile')<p role="alert" class="mt-3 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="modal-footer">
                <button type="button" wire:click="closeModal" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport" class="btn btn-primary">ยืนยันนำเข้า</button>
            </div>
        </div></div>
    </div>

    @script
        $wire.on('tax-certificate-form-opened', () => window.portalModal.show('taxCertificateFormModal'));
        $wire.on('tax-certificate-form-closed', () => window.portalModal.hide(document.getElementById('taxCertificateFormModal')));
        $wire.on('tax-certificate-delete-confirm-opened', () => window.portalModal.show('taxCertificateDeleteModal'));
        $wire.on('tax-certificate-delete-confirm-closed', () => window.portalModal.hide(document.getElementById('taxCertificateDeleteModal')));
        $wire.on('tax-certificate-import-confirm-closed', () => window.portalModal.hide(document.getElementById('taxCertificateImportConfirmModal')));
        $wire.on('tax-certificate-saved', ({ message }) => window.portalToast.show(message));
        $wire.on('tax-certificate-deleted', ({ message }) => window.portalToast.show(message));
        $wire.on('tax-certificate-import-saved', ({ message }) => window.portalToast.show(message));
    @endscript
</div>
