<div class="space-y-6">
    <header class="space-y-4">
        <a href="{{ route('admin.leaves.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg text-sm font-semibold text-slate-600 transition hover:text-brand-700">
            <i class="fas fa-arrow-left" aria-hidden="true"></i><span>กลับไปหน้าจัดการข้อมูลการลา</span>
        </a>
        <div>
            <p class="text-sm font-semibold text-brand-700">จัดการข้อมูล</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">ซิงค์ประวัติการลาจาก Google Sheet</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">นำเข้าหรือปรับปรุงรายการลาในระบบจากชีตต้นทาง ข้อมูลที่ตรงกับรายการเดิมจะอัปเดตแทนการสร้างซ้ำ</p>
        </div>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="เลือก Google Sheet สำหรับซิงค์">
        <label for="leave-sheet-url" class="block text-sm font-semibold text-slate-800">URL ของ Google Sheets</label>
        <p id="leave-sheet-url-help" class="mt-1 text-sm leading-6 text-slate-500">วางลิงก์จากแถบที่อยู่หรือ CSV export URL ได้ ระบบจะส่งออกข้อมูลเป็น CSV ก่อนนำเข้า และบัญชีที่เปิดชีตต้องอนุญาตให้ server อ่านข้อมูลได้</p>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
            <div class="min-w-0 flex-1">
                <input
                    id="leave-sheet-url"
                    type="url"
                    inputmode="url"
                    autocomplete="url"
                    wire:model="sheetUrl"
                    aria-describedby="leave-sheet-url-help @error('sheetUrl') leave-sheet-url-error @enderror"
                    @error('sheetUrl') aria-invalid="true" @enderror
                    placeholder="https://docs.google.com/spreadsheets/d/.../edit?gid=0"
                    class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
                >
                @error('sheetUrl')
                    <p id="leave-sheet-url-error" class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <button
                type="button"
                onclick="window.portalModal.show('leaveSheetSyncConfirmModal')"
                wire:loading.attr="disabled"
                wire:target="syncFromGoogleSheet"
                aria-haspopup="dialog"
                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <i class="fas fa-sync-alt" aria-hidden="true"></i>
                <span>เริ่มซิงค์ข้อมูล</span>
            </button>
        </div>
    </section>

    <div class="modal" id="leaveSheetSyncConfirmModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="leave-sheet-sync-confirm-title" aria-describedby="leave-sheet-sync-confirm-message" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="leave-sheet-sync-confirm-title" class="text-lg font-bold text-slate-900">ยืนยันการซิงค์ข้อมูล</h2>
                    <button type="button" class="close" data-modal-close aria-label="ปิดหน้าต่างยืนยัน">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="leave-sheet-sync-confirm-message" class="text-sm leading-6 text-slate-600">ยืนยันซิงค์ประวัติการลาจาก Google Sheet หรือไม่? รายการเดิมที่ตรงกันจะถูกปรับปรุง</p>
                </div>
                <div class="modal-footer">
                    <button type="button" data-modal-close class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">ยกเลิก</button>
                    <button type="button" wire:click="syncFromGoogleSheet" wire:loading.attr="disabled" wire:target="syncFromGoogleSheet" data-modal-close class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <i wire:loading.remove wire:target="syncFromGoogleSheet" class="fas fa-sync-alt" aria-hidden="true"></i>
                        <i wire:loading wire:target="syncFromGoogleSheet" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                        <span>ยืนยันซิงค์ข้อมูล</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section
        wire:loading.block
        wire:target="syncFromGoogleSheet"
        class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm"
        aria-live="polite"
        aria-label="ความคืบหน้าการซิงค์ข้อมูล"
    >
        <div wire:stream="leave-sync-progress">
            @include('livewire.admin.partials.leave-sync-progress', ['stage' => 'starting'])
        </div>
        <p class="mt-3 text-xs text-slate-500">กรุณาอย่าปิดหรือรีเฟรชหน้านี้จนกว่าการซิงค์จะเสร็จสิ้น</p>
    </section>

    @error('sync')
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">{{ $message }}</div>
    @enderror

    @if($syncResult)
        <section class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm" aria-labelledby="sync-result-heading">
            <div class="border-b border-emerald-100 bg-emerald-50/70 px-5 py-4">
                <h2 id="sync-result-heading" class="font-bold text-emerald-900">ซิงค์เสร็จแล้ว — ครั้งที่ {{ $syncResult['run_id'] }}</h2>
            </div>
            <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3 xl:grid-cols-5">
                @foreach([
                    ['อ่านได้', 'fetched_count'],
                    ['เพิ่มใหม่', 'created_count'],
                    ['ปรับปรุง', 'updated_count'],
                    ['ข้าม', 'skipped_count'],
                    ['ผิดพลาด', 'failed_count'],
                ] as [$label, $key])
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($syncResult[$key]) }}</p>
                    </div>
                @endforeach
            </div>

            @if($syncResult['errors'] !== [])
                <div class="border-t border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-bold text-slate-800">รายการที่ต้องตรวจสอบ</h3>
                    <ul class="mt-3 max-h-64 space-y-2 overflow-y-auto text-sm text-slate-600">
                        @foreach($syncResult['errors'] as $error)
                            <li class="flex gap-3"><span class="shrink-0 font-semibold text-slate-500">แถว {{ $error['row_number'] }}</span><span>{{ $error['message'] }}</span></li>
                        @endforeach
                    </ul>
                    @if($syncResult['errors_truncated'])
                        <p class="mt-3 text-xs text-slate-500">แสดงข้อผิดพลาดไม่เกิน 100 รายการ; จำนวนที่ผิดพลาดทั้งหมดอยู่ในสรุปด้านบน</p>
                    @endif
                </div>
            @endif
        </section>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="sync-history-heading">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
            <h2 id="sync-history-heading" class="font-bold text-brand-700">ประวัติการซิงค์</h2>
        </div>
        <div class="p-5">
            @if($syncRuns->isEmpty())
                <div class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">ยังไม่มีประวัติการซิงค์</div>
            @else
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="whitespace-nowrap px-4 py-3">เวลา</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3">ผู้ดำเนินการ</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3">ผลการทำงาน</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">อ่าน / เพิ่ม / ปรับปรุง</th>
                                <th scope="col" class="min-w-52 px-4 py-3">รายละเอียด</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($syncRuns as $run)
                                <tr wire:key="leave-sync-run-{{ $run->id }}" class="hover:bg-slate-50/80">
                                    <td class="whitespace-nowrap px-4 py-3.5 text-slate-600">{{ $run->started_at?->format('d/m/Y H:i') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-slate-700">{{ $run->admin?->name ?? 'บัญชีถูกลบ' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        @if($run->status === 'completed' && $run->failed_count === 0)
                                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">สำเร็จ</span>
                                        @elseif($run->status === 'completed')
                                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">เสร็จบางส่วน</span>
                                        @elseif($run->status === 'running')
                                            <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 ring-1 ring-inset ring-sky-200">กำลังทำงาน</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-200">ไม่สำเร็จ</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-slate-600">
                                        <div>อ่าน {{ number_format($run->fetched_count) }}</div>
                                        <div class="text-xs text-slate-500">เพิ่ม {{ number_format($run->created_count) }} · ปรับปรุง {{ number_format($run->updated_count) }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600">
                                        @if($run->error_summary)
                                            {{ $run->error_summary }}
                                        @else
                                            <div>ข้าม {{ number_format($run->skipped_count) }} · ผิดพลาด {{ number_format($run->failed_count) }}</div>
                                            @if($run->failed_count > 0 && $run->errors)
                                                <details class="mt-1">
                                                    <summary class="cursor-pointer text-xs font-semibold text-brand-700">ดูรายละเอียด</summary>
                                                    <ul class="mt-2 space-y-1 text-xs">
                                                        @foreach($run->errors as $error)
                                                            <li>แถว {{ $error['row_number'] }}: {{ $error['message'] }}</li>
                                                        @endforeach
                                                    </ul>
                                                </details>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
</div>
