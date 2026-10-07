@php($inputClass = 'mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100')
<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold text-brand-700">จัดการข้อมูล</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">จัดการผู้ใช้</h1>
        <p class="mt-2 text-sm text-slate-500">กำหนดบทบาทและหน่วยงานของผู้ใช้ โดยไม่เปลี่ยนข้อมูลตัวตนจากระบบเข้าสู่ระบบ</p>
        <p class="mt-1 text-sm font-semibold text-amber-700">หน่วยงานเป็นข้อมูลเตรียมใช้ในอนาคต ยังไม่จำกัดสิทธิ์ตามหน่วยงาน</p>
    </header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="user-list-title">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
            <h2 id="user-list-title" class="font-bold text-brand-700">บัญชีผู้ใช้</h2>
            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                <label class="sr-only" for="user-search">ค้นหาผู้ใช้</label>
                <input id="user-search" type="search" wire:model.live.debounce.300ms="search" placeholder="ค้นหาชื่อหรืออีเมล" class="{{ $inputClass }}">
                <label class="sr-only" for="user-role-filter">กรองบทบาท</label>
                <select id="user-role-filter" wire:model.live="roleFilter" class="{{ $inputClass }}">
                    <option value="">ทุกบทบาท</option>
                    <option value="none">ผู้ใช้ทั่วไป</option>
                    @foreach(['ADMIN', 'LEAVE', 'PROMOTE', 'FINANCE'] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="user-office-filter">กรองหน่วยงาน</label>
                <select id="user-office-filter" wire:model.live="officeFilter" class="{{ $inputClass }}">
                    <option value="">ทุกหน่วยงาน</option>
                    <option value="none">ยังไม่ระบุหน่วยงาน</option>
                    @foreach($offices as $option)
                        <option value="{{ $option->office_id }}">{{ $option->office_name ?: $option->office_id }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">ผู้ใช้</th>
                        <th scope="col" class="px-4 py-3">บทบาท</th>
                        <th scope="col" class="px-4 py-3">หน่วยงาน</th>
                        <th scope="col" class="px-4 py-3 text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        <tr wire:key="access-user-{{ $user->id }}" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                                <p class="text-xs text-slate-500">{{ $user->email }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $user->role ?: 'ผู้ใช้ทั่วไป' }}</td>
                            <td class="px-4 py-3">{{ $offices->firstWhere('office_id', $user->office)?->office_name ?: ($user->office ?: '—') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" wire:click="edit({{ $user->id }})" onclick="window.portalModal.show('userAccessModal')" class="btn btn-info btn-sm">แก้ไขสิทธิ์</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">ไม่พบผู้ใช้</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $users->links() }}</div>@endif
    </section>

    <div class="modal" id="userAccessModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="user-access-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="user-access-title" class="text-lg font-bold text-slate-900">แก้ไขสิทธิ์ {{ $editingName }}</h2>
                    <button type="button" wire:click="closeForm" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body space-y-4">
                        <div>
                            <label for="user-role" class="block text-sm font-semibold text-slate-700">บทบาท</label>
                            <select id="user-role" wire:model="role" class="{{ $inputClass }}">
                                <option value="">ผู้ใช้ทั่วไป</option>
                                @foreach(['ADMIN', 'LEAVE', 'PROMOTE', 'FINANCE'] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('role')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="user-office" class="block text-sm font-semibold text-slate-700">หน่วยงาน</label>
                            <select id="user-office" wire:model="office" class="{{ $inputClass }}">
                                <option value="">ยังไม่ระบุ</option>
                                @foreach($offices as $option)
                                    <option value="{{ $option->office_id }}">{{ $option->office_name ?: $option->office_id }}</option>
                                @endforeach
                            </select>
                            @error('office')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
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
</div>

@script
<script>
    $wire.on('user-access-form-opened', () => window.portalModal.show('userAccessModal'));
    $wire.on('user-access-form-closed', () => window.portalModal.hide(document.getElementById('userAccessModal')));
    $wire.on('user-access-saved', ({ message }) => window.portalToast.show(message));
</script>
@endscript
