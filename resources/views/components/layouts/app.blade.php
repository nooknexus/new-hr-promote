<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#056b3e">
    <title>{{ filled($title ?? null) ? $title.' | ' : '' }}MyPPH ระบบข้อมูลบุคลากร</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logoMOPH.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/logoMOPH.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bai+Jamjuree:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <div class="app-shell">
        <div class="mobile-menu-backdrop" data-mobile-menu-backdrop></div>
        <aside class="app-sidebar" aria-label="เมนูหลัก">
            <div class="mb-8 flex items-center justify-between px-2">
                <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3 text-white">
                    <img src="{{ asset('img/head_logo.png') }}" alt="ตรากระทรวงสาธารณสุข สำนักงานสาธารณสุขจังหวัดพิษณุโลก" class="size-14 shrink-0 rounded-xl bg-white object-contain p-1">
                    <span class="leading-tight">
                        <span class="block text-lg font-bold tracking-tight">MyPPH</span>
                        <span class="block text-xs font-medium text-emerald-100/80">ระบบข้อมูลบุคลากร</span>
                    </span>
                </a>
                <button type="button" class="rounded-lg p-2 text-white hover:bg-white/15 lg:hidden" data-mobile-menu-close aria-label="ปิดเมนู">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <nav class="space-y-6 overflow-y-auto pe-1">
                @php($navigation = [
                    ['home', 'fa-chart-line', 'หน้าแรก'],
                    ['payslip', 'fa-file-invoice-dollar', 'PaySlip'],
                    ['tax.certificate', 'fa-receipt', 'ใบรับรองการหักภาษี'],
                    ['salary-promotion', 'fa-level-up-alt', 'เลื่อนขั้นเงินเดือน'],
                    ['leave.history', 'fa-calendar-check', 'ประวัติการลา'],
                ])
                <div data-navigation-group="user" class="space-y-1">
                    @foreach($navigation as [$route, $icon, $label])
                        <a href="{{ route($route) }}" wire:navigate @class(['app-nav-link', 'is-active' => request()->routeIs($route)])>
                            <i class="fas {{ $icon }} w-5 text-center"></i><span>{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
                @if(\App\Support\AdminAccess::allows(auth()->user(), 'users') || \App\Support\AdminAccess::allows(auth()->user(), 'offices') || \App\Support\AdminAccess::allows(auth()->user(), 'personnel') || \App\Support\AdminAccess::allows(auth()->user(), 'leave') || \App\Support\AdminAccess::allows(auth()->user(), 'promotions') || \App\Support\AdminAccess::allows(auth()->user(), 'signatories') || \App\Support\AdminAccess::allows(auth()->user(), 'tax-certificates') || \App\Support\AdminAccess::allows(auth()->user(), 'payslip'))
                    <section data-navigation-group="admin" aria-labelledby="admin-navigation-heading" class="border-t border-white/15 pt-5">
                        <h2 id="admin-navigation-heading" class="px-3 pb-2 text-[11px] font-bold uppercase tracking-[0.16em] text-emerald-100/70">เมนูผู้ดูแลระบบ</h2>
                        <div class="space-y-1">
                            @if(\App\Support\AdminAccess::allows(auth()->user(), 'personnel'))
                            <a href="{{ route('admin.personnel.index') }}" wire:navigate @class(['app-nav-link', 'is-active' => request()->routeIs('admin.personnel.*')])>
                                <i class="fas fa-users-cog w-5 text-center"></i><span>จัดการข้อมูลบุคลากร</span>
                            </a>
                            @endif
                            @if(\App\Support\AdminAccess::allows(auth()->user(), 'offices'))
                            <a href="{{ route('admin.offices.index') }}" wire:navigate @class(['app-nav-link', 'is-active' => request()->routeIs('admin.offices.*')])>
                                <i class="fas fa-sitemap w-5 text-center"></i><span>จัดการต้นสังกัด</span>
                            </a>
                            @endif
                            @if(\App\Support\AdminAccess::allows(auth()->user(), 'users'))
                            <a href="{{ route('admin.users.index') }}" wire:navigate @class(['app-nav-link', 'is-active' => request()->routeIs('admin.users.*')])>
                                <i class="fas fa-user-shield w-5 text-center"></i><span>จัดการผู้ใช้</span>
                            </a>
                            @endif
                            @if(\App\Support\AdminAccess::allows(auth()->user(), 'payslip'))
                            <a href="{{ route('admin.payslips.index') }}" wire:navigate @class(['app-nav-link', 'is-active' => request()->routeIs('admin.payslips.*')])>
                                <i class="fas fa-file-invoice-dollar w-5 text-center"></i><span>จัดการข้อมูลเงินเดือน</span>
                            </a>
                            @endif
                            @if(\App\Support\AdminAccess::allows(auth()->user(), 'leave'))
                            <a href="{{ route('admin.leaves.index') }}" wire:navigate @class(['app-nav-link', 'is-active' => request()->routeIs('admin.leaves.*', 'admin.leave-sheet-sync')])>
                                <i class="fas fa-calendar-alt w-5 text-center"></i><span>จัดการข้อมูลการลา</span>
                            </a>
                            @endif
                            @if(\App\Support\AdminAccess::allows(auth()->user(), 'promotions'))
                            <a href="{{ route('admin.salary-promotions.index') }}" wire:navigate @class(['app-nav-link', 'is-active' => request()->routeIs('admin.salary-promotions.*')])>
                                <i class="fas fa-level-up-alt w-5 text-center"></i><span>จัดการเลื่อนขั้นเงินเดือน</span>
                            </a>
                            @endif
                            @if(\App\Support\AdminAccess::allows(auth()->user(), 'signatories'))
                            <a href="{{ route('admin.signatories') }}" wire:navigate @class(['app-nav-link', 'is-active' => request()->routeIs('admin.signatories')])>
                                <i class="fas fa-signature w-5 text-center"></i><span>ผู้ลงนามใบรับรอง</span>
                            </a>
                            @endif
                            @if(\App\Support\AdminAccess::allows(auth()->user(), 'tax-certificates'))
                            <a href="{{ route('admin.tax-certificates.index') }}" wire:navigate @class(['app-nav-link', 'is-active' => request()->routeIs('admin.tax-certificates.*')])>
                                <i class="fas fa-file-invoice-dollar w-5 text-center"></i><span>จัดการใบรับรองภาษี</span>
                            </a>
                            @endif
                        </div>
                    </section>
                @endif
            </nav>

            <p class="mt-auto px-3 pt-8 text-xs leading-5 text-emerald-100/80">สำนักงานสาธารณสุขจังหวัดพิษณุโลก</p>
        </aside>

        <div class="app-main">
            <header class="app-topbar">
                <button type="button" class="rounded-xl p-2 text-slate-600 hover:bg-slate-100 lg:hidden" data-mobile-menu-button aria-label="เปิดเมนู">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="hidden text-sm font-medium text-slate-500 lg:block">ระบบข้อมูลบุคลากร</div>
                <div class="relative ms-auto min-w-0" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="if (open) { open = false; $refs.userMenuButton.focus() }">
                    <button type="button" x-ref="userMenuButton" @click="open = !open" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="user-account-menu" class="flex max-w-full items-center gap-3 rounded-xl px-3 py-2 text-slate-600 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-600">
                        <span class="min-w-0 text-end">
                            <span class="block max-w-48 truncate text-sm font-semibold text-slate-800 sm:max-w-72">{{ auth()->user()->name }}</span>
                            <span class="hidden text-xs text-slate-500 sm:block">{{ \App\Support\AdminAccess::roleLabel(auth()->user()) }}</span>
                        </span>
                        <i class="fas fa-chevron-down text-xs" aria-hidden="true"></i>
                    </button>
                    <div id="user-account-menu" x-show="open" x-transition style="display: none;" @click="open = false" class="absolute right-0 top-full mt-2 w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
                        <a href="{{ route('profile.index') }}" wire:navigate @if(request()->routeIs('profile.index', 'profile.edit')) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-600">
                            <i class="fas fa-user w-5 text-center" aria-hidden="true"></i>
                            <span>ข้อมูลส่วนตัว</span>
                        </a>
                        <a href="{{ route('about') }}" wire:navigate @if(request()->routeIs('about')) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-600">
                            <i class="fas fa-info-circle w-5 text-center" aria-hidden="true"></i>
                            <span>เกี่ยวกับ</span>
                        </a>
                        <div class="my-1 border-t border-slate-100"></div>
                        <livewire:logout />
                    </div>
                </div>
            </header>

            <main class="page-content">{{ $slot }}</main>
            <footer class="border-t border-slate-200 bg-white px-4 py-5 text-center text-xs text-slate-500 sm:px-8">© สำนักงานสาธารณสุขจังหวัดพิษณุโลก {{ now()->year }}</footer>
        </div>
    </div>
    @livewireScripts
</body>
</html>
