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
        @error('role')<p role="alert" class="px-5 py-3 text-sm text-rose-700">{{ $message }}</p>@enderror
        @error('office')<p role="alert" class="px-5 py-3 text-sm text-rose-700">{{ $message }}</p>@enderror
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
                                @if(!$user->is_active)<span class="mt-1 inline-block rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">ปิดการใช้งาน</span>@endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <label class="sr-only" for="user-role-{{ $user->id }}">บทบาทของ {{ $user->name }}</label>
                                <select id="user-role-{{ $user->id }}" wire:change="updateRole({{ $user->id }}, $event.target.value)" wire:loading.attr="disabled" class="{{ $inputClass }}">
                                    <option value="" @selected(!$user->role)>ผู้ใช้ทั่วไป</option>
                                    @foreach(['ADMIN', 'LEAVE', 'PROMOTE', 'FINANCE'] as $option)
                                        <option value="{{ $option }}" @selected($user->role === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <label class="sr-only" for="user-office-{{ $user->id }}">หน่วยงานของ {{ $user->name }}</label>
                                <select id="user-office-{{ $user->id }}" wire:change="updateOffice({{ $user->id }}, $event.target.value)" wire:loading.attr="disabled" class="{{ $inputClass }}">
                                    <option value="" @selected(!$user->office)>ยังไม่ระบุหน่วยงาน</option>
                                    @foreach($offices as $option)
                                        <option value="{{ $option->office_id }}" @selected($user->office === (string) $option->office_id)>{{ $option->office_name ?: $option->office_id }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" wire:click="requestStatusChange({{ $user->id }}, {{ $user->is_active ? 'false' : 'true' }})" wire:loading.attr="disabled" @disabled($user->id === auth()->id()) class="btn {{ $user->is_active ? 'bg-rose-600 text-white hover:bg-rose-700' : 'btn-success' }} btn-sm">{{ $user->is_active ? 'ปิดการใช้งาน' : 'เปิดการใช้งาน' }}</button>
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

    <div class="modal" id="userStatusModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="user-status-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="user-status-title" class="text-lg font-bold text-slate-900">{{ $pendingActive ? 'เปิดการใช้งานบัญชี' : 'ปิดการใช้งานบัญชี' }}</h2>
                    <button type="button" wire:click="cancelStatusChange" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <form wire:submit="confirmStatusChange">
                    <div class="modal-body space-y-3">
                        <p>ยืนยัน{{ $pendingActive ? 'เปิด' : 'ปิด' }}การใช้งานบัญชี <strong>{{ $pendingName }}</strong>?</p>
                        @if(!$pendingActive)<p class="text-sm text-slate-500">ผู้ใช้นี้จะไม่สามารถเข้าสู่ระบบได้ และการใช้งานที่ค้างอยู่จะถูกยกเลิก</p>@endif
                        @error('status')<p role="alert" class="text-sm text-rose-700">{{ $message }}</p>@enderror
                        @error('role')<p role="alert" class="text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="cancelStatusChange" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                        <button type="submit" wire:loading.attr="disabled" class="btn {{ $pendingActive ? 'btn-primary' : 'bg-rose-600 text-white hover:bg-rose-700' }}">ยืนยัน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@script
<script>
    $wire.on('user-status-form-opened', () => window.portalModal.show('userStatusModal'));
    $wire.on('user-status-form-closed', () => window.portalModal.hide(document.getElementById('userStatusModal')));
    $wire.on('user-access-saved', ({ message }) => window.portalToast.show(message));
</script>
@endscript
