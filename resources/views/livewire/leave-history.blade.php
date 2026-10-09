<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold text-brand-700">ข้อมูลส่วนบุคคล</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">ประวัติการลา</h1>
        <p class="mt-2 text-sm text-slate-500">เลือกปีงบประมาณเพื่อดูรายการประวัติการลาของคุณ</p>
    </header>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-3" aria-labelledby="fiscal-year-heading">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                <h2 id="fiscal-year-heading" class="text-sm font-bold text-brand-700">เลือกปีงบประมาณ</h2>
            </div>
            <div class="p-4">
                @if($years !== [])
                    <div class="space-y-2">
                        @foreach($years as $year)
                            <button
                                type="button"
                                wire:click="selectYear({{ $year }})"
                                wire:loading.attr="disabled"
                                wire:target="selectYear"
                                @class([
                                    'flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left text-sm font-semibold transition',
                                    'border-brand-600 bg-brand-600 text-white shadow-sm' => $selectedYear === $year,
                                    'border-slate-200 bg-white text-slate-700 hover:border-brand-200 hover:bg-brand-50' => $selectedYear !== $year,
                                ])
                                aria-pressed="{{ $selectedYear === $year ? 'true' : 'false' }}"
                            >
                                <span>ปี{{ $year }}</span>
                                <i class="fas fa-chevron-right text-xs opacity-70" aria-hidden="true"></i>
                            </button>
                        @endforeach
                    </div>
                @else
                    <p class="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-800">
                        ไม่พบบัญชีเลขประจำตัวประชาชนที่เชื่อมโยงกับผู้ใช้นี้ จึงไม่สามารถค้นหาประวัติการลาได้ กรุณาติดต่อเจ้าหน้าที่ฝ่ายบุคคล
                    </p>
                @endif
            </div>
        </section>

        <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-9" aria-labelledby="leave-history-heading">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                <h2 id="leave-history-heading" class="text-sm font-bold text-brand-700">
                    @if($selectedYear)
                        รายการประวัติการลา ปีงบประมาณ {{ $selectedYear }}
                    @else
                        รายการประวัติการลา
                    @endif
                </h2>
                <span wire:loading wire:target="selectYear" class="text-xs font-medium text-slate-500">กำลังโหลดรายการ…</span>
            </div>

            <div class="p-5">
                @if(!$selectedYear)
                    <div class="flex min-h-56 flex-col items-center justify-center text-center">
                        <span class="flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-2xl text-brand-600" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                        <p class="mt-4 font-semibold text-slate-700">เลือกปีงบประมาณ</p>
                        <p class="mt-1 text-sm text-slate-500">เลือกรายการทางด้านซ้ายเพื่อแสดงประวัติการลา</p>
                    </div>
                @else
                    @if($annualLeaveEntitlement)
                        <section class="mb-5 overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50/40" aria-labelledby="annual-leave-entitlement-heading">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-emerald-100 bg-emerald-50 px-4 py-3">
                                <h3 id="annual-leave-entitlement-heading" class="font-bold text-emerald-900"><i class="fas fa-umbrella-beach mr-1.5" aria-hidden="true"></i>สิทธิวันลาพักผ่อน</h3>
                                @if($annualLeaveEntitlement['available'])
                                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">{{ $annualLeaveEntitlement['personnel_type'] }}</span>
                                @endif
                            </div>
                            <div class="p-4">
                                @if($annualLeaveEntitlement['available'])
                                    <p class="text-sm leading-6 text-emerald-950">
                                        วันลาสะสม {{ $annualLeaveEntitlement['carryover_actual_days'] === null ? '—' : rtrim(rtrim(number_format((float) $annualLeaveEntitlement['carryover_actual_days'], 2), '0'), '.') }} วัน,
                                        สิทธิวันลาปีปัจจุบัน {{ rtrim(rtrim(number_format((float) $annualLeaveEntitlement['current_year_days'], 2), '0'), '.') }} วัน,
                                        รวมวันลาทั้งหมด {{ rtrim(rtrim(number_format((float) $annualLeaveEntitlement['total_entitled_days'], 2), '0'), '.') }} วัน
                                    </p>
                                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <div class="rounded-xl border border-emerald-100 bg-white px-3 py-3"><p class="text-xs font-medium text-slate-500">วันลาสะสม</p><p class="mt-1 text-xl font-bold tabular-nums text-emerald-800">{{ $annualLeaveEntitlement['carryover_actual_days'] === null ? '—' : rtrim(rtrim(number_format((float) $annualLeaveEntitlement['carryover_actual_days'], 2), '0'), '.') }} <span class="text-sm">วัน</span></p><p class="mt-1 text-[11px] text-slate-500">สะสมได้สูงสุด {{ rtrim(rtrim(number_format((float) $annualLeaveEntitlement['carryover_cap_days'], 2), '0'), '.') }} วัน</p></div>
                                        <div class="rounded-xl border border-emerald-100 bg-white px-3 py-3"><p class="text-xs font-medium text-slate-500">สิทธิปีปัจจุบัน</p><p class="mt-1 text-xl font-bold tabular-nums text-emerald-800">{{ rtrim(rtrim(number_format((float) $annualLeaveEntitlement['current_year_days'], 2), '0'), '.') }} <span class="text-sm">วัน</span></p></div>
                                        <div class="rounded-xl border border-emerald-100 bg-white px-3 py-3"><p class="text-xs font-medium text-slate-500">ลาพักผ่อนใช้แล้ว</p><p class="mt-1 text-xl font-bold tabular-nums text-slate-800">{{ rtrim(rtrim(number_format((float) $annualLeaveEntitlement['used_days'], 2), '0'), '.') }} <span class="text-sm">วัน</span></p></div>
                                        <div class="rounded-xl border border-emerald-200 bg-emerald-100/60 px-3 py-3"><p class="text-xs font-medium text-emerald-700">วันลาคงเหลือ</p><p class="mt-1 text-xl font-bold tabular-nums text-emerald-900">{{ rtrim(rtrim(number_format((float) $annualLeaveEntitlement['remaining_days'], 2), '0'), '.') }} <span class="text-sm">วัน</span></p></div>
                                    </div>
                                    @if(!$annualLeaveEntitlement['completed_six_months'])
                                        <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm leading-6 text-amber-800"><i class="fas fa-clock mr-1" aria-hidden="true"></i>ยังปฏิบัติงานไม่ครบ 6 เดือนในปีงบประมาณนี้ จึงยังไม่มีสิทธิวันลาพักผ่อน</p>
                                    @endif
                                @else
                                    <p class="rounded-xl bg-amber-50 px-3 py-3 text-sm leading-6 text-amber-800"><i class="fas fa-exclamation-circle mr-1" aria-hidden="true"></i>{{ $annualLeaveEntitlement['message'] }}</p>
                                @endif
                            </div>
                        </section>
                    @endif

                    <section class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-labelledby="leave-type-summary-heading">
                        <div class="border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                            <h3 id="leave-type-summary-heading" class="font-bold text-slate-800"><i class="fas fa-chart-pie mr-1.5 text-brand-600" aria-hidden="true"></i>สรุปจำนวนวันลา</h3>
                            <p class="mt-0.5 text-xs text-slate-500">นับเฉพาะรายการที่อนุมัติแล้วในปีที่เลือก</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach($leaveTypeTotals as $leaveType => $totalDays)
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                                    <p class="truncate text-xs font-medium text-slate-600" title="{{ $leaveType }}">{{ $leaveType }}</p>
                                    <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">{{ rtrim(rtrim(number_format((float) $totalDays, 2), '0'), '.') }} <span class="text-sm font-medium text-slate-500">วัน</span></p>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    @if($leaveHistory === [])
                        <div class="flex min-h-56 flex-col items-center justify-center text-center">
                            <span class="flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400" aria-hidden="true"><i class="fas fa-calendar-times"></i></span>
                            <p class="mt-4 font-semibold text-slate-700">ไม่พบประวัติการลา</p>
                            <p class="mt-1 text-sm text-slate-500">ไม่พบรายการลาในปีงบประมาณ {{ $selectedYear }}</p>
                            <p class="mt-1 text-sm text-slate-500">หากข้อมูลไม่ถูกต้อง กรุณาติดต่อเจ้าหน้าที่ฝ่ายบุคคล</p>
                        </div>
                    @else
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th scope="col" class="whitespace-nowrap px-4 py-3">ประเภทการลา</th>
                                        <th scope="col" class="whitespace-nowrap px-4 py-3">วันที่ลา</th>
                                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-center">จำนวนวัน</th>
                                        <th scope="col" class="min-w-48 px-4 py-3">เหตุผล</th>
                                        <th scope="col" class="whitespace-nowrap px-4 py-3">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach($leaveHistory as $leave)
                                        <tr wire:key="leave-{{ $leave['id'] }}" class="hover:bg-slate-50/80">
                                            <td class="whitespace-nowrap px-4 py-3.5 font-semibold text-slate-800">{{ $leave['leave_type'] }}</td>
                                            <td class="whitespace-nowrap px-4 py-3.5 text-slate-600">
                                                {{ $leave['start_date'] }}
                                                @if($leave['end_date'] !== $leave['start_date'])
                                                    <span class="text-slate-400">–</span> {{ $leave['end_date'] }}
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3.5 text-center text-slate-700">{{ rtrim(rtrim(number_format((float) $leave['total_days'], 2), '0'), '.') }}</td>
                                            <td class="min-w-48 px-4 py-3.5 leading-6 text-slate-600">{{ $leave['reason'] ?: '—' }}</td>
                                            <td class="whitespace-nowrap px-4 py-3.5">
                                                <span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', $leave['status_style']])>{{ $leave['status'] }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </section>
    </div>
</div>
