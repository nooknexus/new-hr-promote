@php($inputClass = 'mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100 disabled:bg-slate-100')
<div class="space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-700">จัดการข้อมูล</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">จัดการต้นสังกัด</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">ทะเบียนต้นสังกัดที่ใช้กำหนดหน่วยงานของบุคลากรและสิทธิ์ของผู้ใช้ ต้นสังกัดที่เลิกใช้ให้ปิดใช้งานแทนการลบ บุคลากรและผู้ใช้เดิมจะยังแสดงผลได้</p>
        </div>
        <button type="button" wire:click="openCreate" onclick="window.portalModal.show('officeFormModal')" class="btn btn-primary">
            <i class="fas fa-plus" aria-hidden="true"></i><span>เพิ่มต้นสังกัด</span>
        </button>
    </header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="office-list-heading">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 id="office-list-heading" class="font-bold text-brand-700">ทะเบียนต้นสังกัด</h2>
            <div class="relative w-full sm:w-72">
                <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                <label for="office-search" class="sr-only">ค้นหาต้นสังกัด</label>
                <input id="office-search" type="search" wire:model.live.debounce.300ms="search" placeholder="ค้นหารหัสหรือชื่อต้นสังกัด" class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">รหัส</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">ชื่อต้นสังกัด</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">บุคลากร</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">ผู้ใช้</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">สถานะ</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3">การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($offices as $office)
                        <tr wire:key="office-{{ $office->code }}" @class(['hover:bg-slate-50', 'text-slate-400' => ! $office->is_active])>
                            <td class="whitespace-nowrap px-4 py-3 font-mono font-semibold">{{ $office->code }}</td>
                            <td class="px-4 py-3">{{ $office->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format($personnelCounts[$office->code] ?? 0) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format($userCounts[$office->code] ?? 0) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if($office->is_active)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">ใช้งาน</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 ring-1 ring-inset ring-slate-200">ปิดใช้งาน</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" wire:click="edit('{{ $office->code }}')" onclick="window.portalModal.show('officeFormModal')" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit" aria-hidden="true"></i> แก้ไข
                                    </button>
                                    @if($office->code !== \App\Support\OfficeScope::ALL_OFFICES)
                                        <button type="button" wire:click="prepareToggle('{{ $office->code }}')" onclick="window.portalModal.show('officeToggleModal')" class="btn btn-sm btn-secondary">
                                            <i class="fas {{ $office->is_active ? 'fa-ban' : 'fa-check' }}" aria-hidden="true"></i> {{ $office->is_active ? 'ปิดใช้งาน' : 'เปิดใช้งาน' }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">ไม่พบต้นสังกัด</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="modal" id="officeFormModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="office-form-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="office-form-title" class="text-lg font-bold text-slate-900">{{ $editingCode ? 'แก้ไขต้นสังกัด' : 'เพิ่มต้นสังกัด' }}</h2>
                    <button type="button" wire:click="closeForm" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body grid gap-4">
                        <div>
                            <label for="office-code" class="block text-sm font-semibold text-slate-700">รหัสต้นสังกัด (ตัวเลข 2 หลัก)</label>
                            <input id="office-code" type="text" inputmode="numeric" maxlength="2" wire:model="code" @disabled($editingCode) class="{{ $inputClass }}">
                            @if($editingCode)<p class="mt-1.5 text-xs text-slate-500">รหัสเปลี่ยนไม่ได้ เพราะใช้อ้างอิงบุคลากรและผู้ใช้</p>@endif
                            @error('code') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="office-name" class="block text-sm font-semibold text-slate-700">ชื่อต้นสังกัด</label>
                            <input id="office-name" type="text" wire:model="name" class="{{ $inputClass }}">
                            @if($editingCode)<p class="mt-1.5 text-xs text-slate-500">เมื่อบันทึก ชื่อต้นสังกัดของบุคลากรในรหัสนี้จะเปลี่ยนตามด้วย</p>@endif
                            @error('name') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="office-sort" class="block text-sm font-semibold text-slate-700">ลำดับการแสดง</label>
                            <input id="office-sort" type="number" min="0" wire:model="sort_order" class="{{ $inputClass }}">
                            @error('sort_order') <p class="mt-1.5 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="closeForm" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="officeToggleModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="office-toggle-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="office-toggle-title" class="text-lg font-bold text-slate-900">{{ $pendingOffice?->is_active ? 'ยืนยันการปิดใช้งาน' : 'ยืนยันการเปิดใช้งาน' }}</h2>
                    <button type="button" wire:click="cancelToggle" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <div class="modal-body">
                    @if($pendingOffice)
                        <p class="text-sm leading-6 text-slate-600">
                            {{ $pendingOffice->is_active ? 'ปิดใช้งาน' : 'เปิดใช้งาน' }}ต้นสังกัด
                            <span class="font-semibold text-slate-800">{{ $pendingOffice->code }} — {{ $pendingOffice->name }}</span> หรือไม่?
                            @if($pendingOffice->is_active)
                                ต้นสังกัดนี้จะไม่แสดงให้เลือกในฟอร์ม แต่บุคลากรและผู้ใช้เดิมยังอยู่ในต้นสังกัดนี้และค้นหาได้ตามปกติ
                            @endif
                        </p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" wire:click="cancelToggle" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                    <button type="button" wire:click="toggleActive" wire:loading.attr="disabled" wire:target="toggleActive" class="btn btn-primary">ยืนยัน</button>
                </div>
            </div>
        </div>
    </div>

    @script
        $wire.on('office-form-opened', () => window.portalModal.show('officeFormModal'));
        $wire.on('office-toggle-opened', () => window.portalModal.show('officeToggleModal'));
        $wire.on('office-saved', ({ message }) => {
            ['officeFormModal', 'officeToggleModal'].forEach((id) => {
                const modal = document.getElementById(id);
                if (modal) window.portalModal.hide(modal);
            });
            window.portalToast.show(message);
        });
    @endscript
</div>
