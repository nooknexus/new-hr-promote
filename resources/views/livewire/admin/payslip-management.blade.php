<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold text-brand-700">จัดการข้อมูล</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">จัดการข้อมูลสลิปเงินเดือน</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">นำเข้า ตรวจสอบ และค้นหาข้อมูลสลิปเงินเดือนของบุคลากร</p>
    </header>

    <!-- Card 1: Import -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-lg font-bold text-emerald-800">นำเข้าสลิปเงินเดือน</h2>
                <p class="mt-2 text-sm text-slate-500">
                    รองรับไฟล์ข้อมูล <strong>.txt</strong> (จากกรมบัญชีกลางรูปแบบเดิม) หรือ <strong>.xlsx</strong> (ใช้ไฟล์ Excel ตาม template)
                </p>
            </div>
            <a href="{{ route('admin.payslips.template') }}" class="btn btn-secondary shrink-0">
                <i class="fas fa-file-download" aria-hidden="true"></i><span>ดาวน์โหลด template</span>
            </a>
        </div>

        <form wire:submit.prevent="import" class="mt-6">
            <div class="mb-2">
                <label for="fileUpload" class="block text-sm font-semibold text-slate-700">ไฟล์ข้อมูล (TXT หรือ Excel)</label>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="flex-1">
                    <input type="file" id="fileUpload" wire:model="fileUpload" accept=".txt,.xlsx"
                           class="block w-full text-sm text-slate-500
                                  file:mr-4 file:py-2.5 file:px-4
                                  file:rounded-l-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-slate-100 file:text-slate-700
                                  hover:file:bg-slate-200
                                  border border-slate-300 rounded-md focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-md border border-transparent bg-emerald-700 px-6 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50">
                    <span wire:loading.remove wire:target="import">นำเข้าข้อมูล</span>
                    <span wire:loading wire:target="import"><i class="fas fa-spinner fa-spin mr-2"></i>กำลังนำเข้า...</span>
                </button>
            </div>
            @error('fileUpload') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror

            @if($importResult)
            <div class="mt-4 rounded-md bg-emerald-50 p-4 border border-emerald-200">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-emerald-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-emerald-800">นำเข้าข้อมูลเสร็จสิ้น</h3>
                        <div class="mt-2 text-sm text-emerald-700">
                            <p>สำเร็จ: <span class="font-bold">{{ $importResult['success'] }}</span> รายการ | ไม่สำเร็จ (ข้อมูลไม่ครบ/ผิดรูปแบบ): <span class="font-bold text-rose-600">{{ $importResult['failed'] }}</span> รายการ
                                @if(($importResult['skipped'] ?? 0) > 0)
                                    | ข้าม (ไม่ใช่บุคลากรในหน่วยงาน): <span class="font-bold">{{ $importResult['skipped'] }}</span> รายการ
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </form>
    </div>

    <!-- Card 3: Table and Filters -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-bold text-emerald-800 mb-4">ทะเบียนสลิปเงินเดือน</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <select wire:model.live="searchYear" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                    <option value="">ทุกปี (พ.ศ.)</option>
                    @for($y = (date('Y') + 543) + 1; $y >= 2560; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>

                <select wire:model.live="searchMonth" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                    <option value="">ทุกเดือน</option>
                    <option value="01">มกราคม</option>
                    <option value="02">กุมภาพันธ์</option>
                    <option value="03">มีนาคม</option>
                    <option value="04">เมษายน</option>
                    <option value="05">พฤษภาคม</option>
                    <option value="06">มิถุนายน</option>
                    <option value="07">กรกฎาคม</option>
                    <option value="08">สิงหาคม</option>
                    <option value="09">กันยายน</option>
                    <option value="10">ตุลาคม</option>
                    <option value="11">พฤศจิกายน</option>
                    <option value="12">ธันวาคม</option>
                </select>

                <label for="payslip-office-filter" class="sr-only">กรองตามต้นสังกัด</label>
                <select id="payslip-office-filter" wire:model.live="officeFilter" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                    <option value="">ทุกต้นสังกัด</option>
                    @foreach($this->offices as $office)
                        <option value="{{ $office->office_id }}">{{ $office->office_id }} — {{ $office->office_name ?: 'ไม่ระบุชื่อต้นสังกัด' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="ค้นหาชื่อ, นามสกุล หรือเลขประจำตัวประชาชน" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-4 text-left text-sm font-semibold text-slate-700">ปี/เดือน</th>
                        <th scope="col" class="px-6 py-4 text-left text-sm font-semibold text-slate-700">บุคลากร</th>
                        <th scope="col" class="px-6 py-4 text-right text-sm font-semibold text-slate-700">รายรับรวม</th>
                        <th scope="col" class="px-6 py-4 text-right text-sm font-semibold text-slate-700">รายจ่ายรวม</th>
                        <th scope="col" class="px-6 py-4 text-right text-sm font-semibold text-slate-700">รับสุทธิ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($this->payslips as $payslip)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-900">{{ $payslip->yearNO }}/{{ $payslip->monthNO }}</td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <div class="text-sm font-medium text-slate-900">
                                {{ trim($payslip->pname . ' ' . $payslip->fname . ' ' . $payslip->lname) ?: 'ไม่พบข้อมูลชื่อ-สกุล' }}
                            </div>
                            <div class="text-sm text-slate-500">{{ $payslip->cid }}</div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600 text-right">{{ number_format((float)$payslip->summaryIncomeAmount, 2) }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600 text-right">{{ number_format((float)$payslip->summaryOutcomeAmount, 2) }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900 text-right">{{ number_format((float)$payslip->summaryTotalAmount, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">
                            <i class="fas fa-inbox text-4xl text-slate-300 mb-3"></i>
                            <p>ไม่มีข้อมูลสลิปเงินเดือน</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-200">
            {{ $this->payslips->links() }}
        </div>
    </div>
</div>
