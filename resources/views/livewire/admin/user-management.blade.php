@php($inputClass = 'mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100')
<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold text-brand-700">จัดการข้อมูล</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">จัดการผู้ใช้</h1>
        <p class="mt-2 text-sm text-slate-500">กำหนดบทบาทและต้นสังกัดของผู้ใช้ โดยไม่เปลี่ยนข้อมูลตัวตนจากระบบเข้าสู่ระบบ</p>
    </header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="user-list-title">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
            <h2 id="user-list-title" class="font-bold text-brand-700">บัญชีผู้ใช้</h2>
            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                <label class="sr-only" for="user-search">ค้นหาผู้ใช้</label>
                <input id="user-search" type="search" wire:model.live.debounce.300ms="search" placeholder="ค้นหาชื่อ หน่วยบริการ หรืออีเมล" class="{{ $inputClass }}">
                <label class="sr-only" for="user-role-filter">กรองบทบาท</label>
                <select id="user-role-filter" wire:model.live="roleFilter" class="{{ $inputClass }}">
                    <option value="">ทุกบทบาท</option>
                    <option value="none">ผู้ใช้ทั่วไป</option>
                    @foreach(\App\Support\AdminAccess::ROLES as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="user-office-filter">กรองต้นสังกัด</label>
                <select id="user-office-filter" wire:model.live="officeFilter" class="{{ $inputClass }}">
                    <option value="">ทุกต้นสังกัด</option>
                    <option value="none">ยังไม่ระบุต้นสังกัด</option>
                    @foreach($offices as $option)
                        <option value="{{ $option->office_id }}">{{ $option->office_id }} — {{ $option->office_name }}{{ $option->is_active ? '' : ' (ปิดใช้งาน)' }}</option>
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
                        <th scope="col" class="px-4 py-3">ต้นสังกัด</th>
                        <th scope="col" class="px-4 py-3 text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        {{-- An ADMIN cannot change a SUPERADMIN account. --}}
                        @php($locked = $user->role === \App\Support\AdminAccess::SUPERADMIN && ! $isSuperadmin)
                        <tr wire:key="access-user-{{ $user->id }}" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                                <p class="text-xs text-slate-500">{{ $user->hname ?: 'ไม่ระบุหน่วยบริการ' }}</p>
                                @if(!$user->is_active)<span class="mt-1 inline-block rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">ปิดการใช้งาน</span>@endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <label class="sr-only" for="user-role-{{ $user->id }}">บทบาทของ {{ $user->name }}</label>
                                <select id="user-role-{{ $user->id }}" wire:change="updateRole({{ $user->id }}, $event.target.value)" wire:loading.attr="disabled" @disabled($locked) class="{{ $inputClass }}">
                                    @if($locked)
                                        <option value="{{ $user->role }}" selected>{{ $user->role }}</option>
                                    @else
                                        <option value="" @selected(!$user->role)>ผู้ใช้ทั่วไป</option>
                                        @foreach($roles as $option)
                                            <option value="{{ $option }}" @selected($user->role === $option)>{{ $option }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @if($user->role === 'PROMOTE')
                                    @php($userTypes = $assignedTypes[$user->id] ?? [])
                                    <div class="mt-2 flex items-center gap-2 text-xs">
                                        <span @class(['text-slate-600', 'font-semibold text-amber-700' => $userTypes === []])>ประเภทการจ้าง: {{ $userTypes === [] ? 'ยังไม่กำหนด' : \App\Support\PersonnelTypeScope::labels($userTypes) }}</span>
                                        <button type="button" wire:click="openPersonnelTypes({{ $user->id }})" onclick="window.portalModal.show('userTypesModal')" class="btn btn-sm btn-outline-primary">กำหนด</button>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <label class="sr-only" for="user-office-{{ $user->id }}">ต้นสังกัดของ {{ $user->name }}</label>
                                <select id="user-office-{{ $user->id }}" wire:change="updateOffice({{ $user->id }}, $event.target.value)" wire:loading.attr="disabled" @disabled($locked) class="{{ $inputClass }}">
                                    <option value="" @selected(!$user->office)>ยังไม่ระบุต้นสังกัด</option>
                                    @foreach($offices as $option)
                                        @if($option->is_active || $user->office === (string) $option->office_id)
                                        <option value="{{ $option->office_id }}" @selected($user->office === (string) $option->office_id)>{{ $option->office_id }} — {{ $option->office_name }}{{ $option->is_active ? '' : ' (ปิดใช้งาน)' }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" wire:click="requestStatusChange({{ $user->id }}, {{ $user->is_active ? 'false' : 'true' }})" wire:loading.attr="disabled" @disabled($user->id === auth()->id() || $locked) class="btn {{ $user->is_active ? 'bg-rose-600 text-white hover:bg-rose-700' : 'btn-success' }} btn-sm">{{ $user->is_active ? 'ปิดการใช้งาน' : 'เปิดการใช้งาน' }}</button>
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

    <div class="modal" id="userTypesModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="user-types-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="user-types-title" class="text-lg font-bold text-slate-900">ประเภทการจ้างที่รับผิดชอบ</h2>
                    <button type="button" wire:click="cancelPersonnelTypes" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <form wire:submit="savePersonnelTypes">
                    <div class="modal-body space-y-3">
                        <p class="text-sm text-slate-600"><strong>{{ $typesUserName }}</strong> จะเห็นและนำเข้าข้อมูลเลื่อนขั้นเงินเดือนได้เฉพาะประเภทที่เลือก ภายในต้นสังกัดของตน หากไม่เลือกเลยจะไม่เห็นข้อมูล</p>
                        <fieldset class="grid gap-2 sm:grid-cols-2">
                            <legend class="sr-only">ประเภทการจ้าง</legend>
                            @foreach($personnelTypes as $typeId => $typeName)
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    <input type="checkbox" wire:model="selectedTypes" value="{{ $typeId }}" class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                    <span>{{ $typeName }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                        @error('personnelTypes')<p role="alert" class="text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="cancelPersonnelTypes" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="savePersonnelTypes" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
    $wire.on('user-types-form-opened', () => window.portalModal.show('userTypesModal'));
    $wire.on('user-types-form-closed', () => window.portalModal.hide(document.getElementById('userTypesModal')));
    $wire.on('user-access-saved', ({ message }) => window.portalToast.show(message));
</script>
@endscript
