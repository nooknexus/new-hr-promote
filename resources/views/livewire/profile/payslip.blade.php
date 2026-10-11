<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold text-brand-700">ข้อมูลส่วนบุคคล</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">สลิปเงินเดือน</h1>
        <p class="mt-2 text-sm text-slate-500">เลือกปีเพื่อดูรายละเอียดและดาวน์โหลดสลิปเงินเดือนของคุณ</p>
    </header>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
        <!-- Fiscal Year Selection Column -->
        <div class="lg:col-span-3">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="payslip-year-heading">
                <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                    <h2 id="payslip-year-heading" class="text-sm font-bold text-brand-700">เลือกปี</h2>
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
                                        'border-brand-600 bg-brand-600 text-white shadow-sm' => $selectedYear == $year,
                                        'border-slate-200 bg-white text-slate-700 hover:border-brand-200 hover:bg-brand-50' => $selectedYear != $year,
                                    ])
                                    aria-pressed="{{ $selectedYear == $year ? 'true' : 'false' }}"
                                >
                                    <span>ปี{{ $year + 543 }}</span>
                                    <i class="fas fa-chevron-right text-xs opacity-70" aria-hidden="true"></i>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500">ไม่มีปีให้เลือก</p>
                    @endif
                </div>
            </section>
        </div>

        <!-- Payslip Display Column -->
        <div class="min-w-0 lg:col-span-9">
            @if($years === [])
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-7 text-amber-900" role="status">
                    <p class="font-semibold"><i class="fas fa-info-circle mr-1" aria-hidden="true"></i> ยังไม่มีข้อมูลสลิปเงินเดือนของท่านในระบบ</p>
                    <p class="mt-1">หากเป็นพนักงานกระทรวงหรือได้รับเงินเดือนจากหน่วยบริการ กรุณาติดต่อเจ้าหน้าที่การเงินของหน่วยงาน</p>
                </div>
            @else
            <!-- Payslip Card -->
            <div id="payslip-register" class="card shadow mb-4 scroll-mt-20">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">ใบรับรองการจ่ายค่าตอบแทน (ปี {{ $selectedYear + 543 }})</h6>
                </div>
                <div class="card-body">
                    @if(count($payslips) > 0)
                    {{-- Phones get one card per month so every figure and action fits the screen. --}}
                    <ul class="space-y-3 md:hidden" id="payslipCards">
                        @foreach($payslips as $payslip)
                            <li class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" wire:key="payslip-card-{{ $payslip['id'] }}">
                                <p class="text-xs text-slate-500">งวดเดือน</p>
                                <p class="font-semibold text-slate-900">{{ $payslip['month'] }}</p>
                                <dl class="mt-3 grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                                    <div>
                                        <dt class="text-xs text-slate-500">เงินเดือน</dt>
                                        <dd class="font-semibold tabular-nums text-slate-900">{{ number_format($payslip['salary'], 2) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-slate-500">รับสุทธิ</dt>
                                        <dd class="font-semibold tabular-nums text-slate-900">{{ number_format($payslip['net'], 2) }}</dd>
                                    </div>
                                </dl>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button type="button" wire:click="viewPayslipDetail('{{ $payslip['id'] }}')" class="btn btn-outline-primary btn-sm justify-center">
                                        <i class="fas fa-eye" aria-hidden="true"></i> ดูรายละเอียด
                                    </button>
                                    <button type="button" wire:click="downloadPayslip('{{ $payslip['id'] }}')" class="btn btn-primary btn-sm justify-center">
                                        <i class="fas fa-download" aria-hidden="true"></i> ดาวน์โหลด
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="table-responsive hidden md:block">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>งวดเดือน</th>
                                    <th>เงินเดือน</th>
                                    <!-- <th>รายรับทั้งหมด</th>
                                    <th>หักทั้งหมด</th> -->
                                    <th>รับสุทธิ</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payslips as $payslip)
                                <tr>
                                    <td>{{ $payslip['month'] }}</td>
                                    <td class="text-right">{{ number_format($payslip['salary'], 2) }}</td>
                                    <!-- <td class="text-right">{{ number_format($payslip['total_income'], 2) }}</td>
                                    <td class="text-right">{{ number_format($payslip['total_outcome'], 2) }}</td> -->
                                    <td class="text-right">{{ number_format($payslip['net'], 2) }}</td>
                                    <td>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="viewPayslipDetail('{{ $payslip['id'] }}')">
                                                <i class="fas fa-eye" aria-hidden="true"></i> ดูรายละเอียด
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="downloadPayslip('{{ $payslip['id'] }}')">
                                                <i class="fas fa-download" aria-hidden="true"></i> ดาวน์โหลด
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-1"></i> ไม่พบข้อมูลใบรับรองการจ่ายค่าตอบแทนในปี {{ $selectedYear + 543 }}
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Payslip Detail Modal -->
    <div class="modal fade" id="payslipDetailModal" tabindex="-1" role="dialog" aria-labelledby="payslipDetailModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="payslipDetailModalLabel">
                        รายละเอียดใบรับรองการจ่ายค่าตอบแทน {{ $selectedPayslip ? $selectedPayslip['month'] : '' }}
                    </h5>
                    <button type="button" class="close" data-modal-close aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if($selectedPayslip)
                    <div class="row">
                        <!-- Income Details -->
                        <div class="col-md-6">
                            <div class="card border-left-success shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-success">รายรับ</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <tbody>
                                                @foreach($selectedPayslip['income_details'] as $income)
                                                <tr>
                                                    <td>{{ $income['name'] }}</td>
                                                    <td class="text-right">{{ number_format($income['amount'], 2) }}</td>
                                                </tr>
                                                @endforeach
                                                <tr class="font-weight-bold bg-light">
                                                    <td>รวมรายรับทั้งหมด</td>
                                                    <td class="text-right">{{ number_format($selectedPayslip['total_income'], 2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expense Details -->
                        <div class="col-md-6">
                            <div class="card border-left-danger shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-danger">รายจ่าย</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <tbody>
                                                @foreach($selectedPayslip['expense_details'] as $expense)
                                                <tr>
                                                    <td>{{ $expense['name'] }}</td>
                                                    <td class="text-right">{{ number_format($expense['amount'], 2) }}</td>
                                                </tr>
                                                @endforeach
                                                <tr class="font-weight-bold bg-light">
                                                    <td>รวมรายจ่ายทั้งหมด</td>
                                                    <td class="text-right">{{ number_format($selectedPayslip['total_outcome'], 2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="card border-left-primary shadow">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        รับสุทธิ: {{ number_format($selectedPayslip['net'], 2) }} บาท
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="alert alert-info">
                        กรุณารอสักครู่...
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>ปิด</button>
                    
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Preview Modal -->
    <div class="modal fade" id="pdfPreviewModal" tabindex="-1" role="dialog" aria-labelledby="pdfPreviewModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl" role="document" style="max-height: 95vh;">
            <div class="modal-content" style="max-height: 95vh; display: flex; flex-direction: column;">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfPreviewModalLabel">
                        ตัวอย่างใบรับรองการจ่ายค่าตอบแทน {{ $selectedPayslip ? $selectedPayslip['month'] : '' }}
                    </h5>
                    <button type="button" class="close" data-modal-close aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0" style="overflow-y: auto; flex: 1 1 auto;">
                    @if($selectedPayslip)
                    <!-- A4 Preview Container -->
                    <div class="payslip-preview-stage">
                        <div class="pdf-preview" style="width: 21cm; height: 29.7cm; background: white; padding: 1cm; box-shadow: 0 0 10px rgba(0,0,0,0.1); position: relative; overflow: hidden;">
                            <!-- Watermark -->
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: url('{{ asset('img/head_logo.png') }}'); background-repeat: no-repeat; background-position: center center; background-size: 80%; opacity: 0.07; pointer-events: none; z-index: 1;"></div>
                            
                            <!-- Content Container - ensure content is above watermark -->
                            <div style="position: relative; z-index: 2; height: calc(29.7cm - 2cm); overflow: hidden;">
                                <!-- Header -->
                                <div class="text-center mb-4">
                                    <img src="{{ asset('img/logoMOPH.png') }}" alt="Logo" style="width: 100px; height: auto;">
                                    <h4>ใบรับรองการจ่ายเงินเดือนและเงินอื่น</h4>
                                    @php
                                    // Extract month and year from the month string (e.g., "มกราคม 2566")
                                    $parts = explode(' ', $selectedPayslip['month']);
                                    $monthName = $parts[0] ?? '';
                                    $yearBE = $parts[1] ?? '';
                                    @endphp
                                    <h5>ประจำเดือน {{ $monthName }} ปี พ.ศ. {{ $yearBE }}</h5>
                                </div>

                                <!-- Employee Info -->
                                <div class="row mb-4">
                                    <div class="col-6">
                                        <p><strong>ชื่อ-นามสกุล:</strong> {{ Auth::user()->name }}</p>
                                    </div>
                                    <div class="col-6">
                                        <p><strong>เลขที่ตำแหน่ง:</strong> {{ $selectedPayslip['person_info']['position_number'] ?? '-' }}</p>
                                    </div>
                                    <div class="col-6">
                                        <p><strong>ประเภท:</strong> {{ $selectedPayslip['person_info']['employee_type'] ?? '-' }}</p>
                                    </div>
                                    <div class="col-6">
                                        <p><strong>กรม: {{ $selectedPayslip['person_info']['ministry']  ?? '-' }}</strong> </p>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <p><strong>สำนัก/กอง/ศูนย์:</strong> {{ $selectedPayslip['person_info']['department'] ?? '-' }}</p>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <p><strong>ส่วน:</strong> {{ $selectedPayslip['person_info']['office'] ?? '-' }}</p>
                                    </div>
                                    <div class="col-6">
                                        <p><strong>โอนเงินเข้า:</strong> {{ $selectedPayslip['person_info']['bank_name'] ?? '-' }} </p>
                                    </div>
                                    <div class="col-6">
                                        <p><strong>เลขที่บัญชี:</strong> {{ $selectedPayslip['person_info']['bank_account'] ?? '-' }}</p>
                                    </div>

                                </div>

                                <!-- Income and Expense Details -->
                                <div class="row">
                                    <div class="col-6">
                                        <h5 class="border-bottom pb-2"><strong>รายรับ</strong></h5>
                                        <table class="table table-sm">
                                            <tbody>
                                                @foreach($selectedPayslip['income_details'] as $income)
                                                <tr>
                                                    <td>{{ $income['name'] }}</td>
                                                    <td class="text-right">{{ number_format($income['amount'], 2) }}</td>
                                                </tr>
                                                @endforeach
                                                <tr class="font-weight-bold">
                                                    <td>รวมรายรับทั้งหมด</td>
                                                    <td class="text-right">{{ number_format($selectedPayslip['total_income'], 2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="col-6">
                                        <h5 class="border-bottom pb-2"><strong>รายจ่าย</strong></h5>
                                        <table class="table table-sm">
                                            <tbody>
                                                @foreach($selectedPayslip['expense_details'] as $expense)
                                                <tr>
                                                    <td>{{ $expense['name'] }}</td>
                                                    <td class="text-right">{{ number_format($expense['amount'], 2) }}</td>
                                                </tr>
                                                @endforeach
                                                <tr class="font-weight-bold">
                                                    <td>รวมรายจ่ายทั้งหมด</td>
                                                    <td class="text-right">{{ number_format($selectedPayslip['total_outcome'], 2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Summary -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="text-center">
                                            <h5 class="font-weight-bold">รับสุทธิ: <u>{{ number_format($selectedPayslip['net'], 2) }} บาท</u></h5>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Signature Section -->
                                @php
                                    // The signer changes by fiscal year and term of office, so
                                    // look up who was on record for this pay period.
                                    $signatory = isset($selectedPayslip['id']) ? \App\Models\PayslipSignatory::forPeriodId($selectedPayslip['id']) : null;
                                @endphp
                                <div class="row mt-5">
                                    <div class="col-12">
                                        <div class="payslip-signature-block">
                                            <!-- Signature Line -->
                                            <div class="payslip-signature-line d-flex align-items-center">
                                                <span style="padding-right: 4.8%;">ลงชื่อ</span>
                                                <img src="{{ $signatory && $signatory->signature_data_uri ? $signatory->signature_data_uri : asset('img/sign.png') }}" alt="Signature" class="payslip-signature mx-2">
                                                <span style="padding-left: 4.8%;">ผู้มีหน้าที่จ่ายเงิน</span>
                                            </div>

                                            <!-- Name and Position -->
                                            <div class="payslip-signature-details mt-2 text-center">
                                                @if($signatory)
                                                    <p class="mb-0">{{ $signatory->display_name }}</p>
                                                    <p class="mb-0">{{ $signatory->position_line1 }}</p>
                                                    @if($signatory->position_line2)
                                                        <p class="mb-0">{{ $signatory->position_line2 }}</p>
                                                    @endif
                                                @else
                                                    <p class="mb-0">(นายมนัสศักต์ มากบุญ)</p>
                                                    <p class="mb-0">นักวิชาการสาธารณสุขชำนาญการพิเศษ (ด้านบริการทางวิชาการ)</p>
                                                    <p class="mb-0">รองนายแพทย์สาธารณสุขจังหวัดพิษณุโลก (ด้านบริหาร)</p>
                                                @endif
                                                <p class="mt-0">
                                                    @if(isset($selectedPayslip['verify_date']))
                                                        @php
                                                            $date = \Carbon\Carbon::parse($selectedPayslip['verify_date']);
                                                            $thaiMonths = [
                                                                1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 
                                                                4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
                                                                7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
                                                                10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                                                            ];
                                                            $day = $date->format('j');
                                                            $month = $thaiMonths[$date->month];
                                                            $year = $date->year + 543; // Convert to Buddhist Era
                                                            $thaiDate = "({$day} {$month} {$year})";
                                                        @endphp
                                                        {{ $thaiDate }}
                                                    @else
                                                        @php
                                                            $date = \Carbon\Carbon::now();
                                                            $thaiMonths = [
                                                                1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 
                                                                4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
                                                                7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
                                                                10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                                                            ];
                                                            $day = $date->format('j');
                                                            $month = $thaiMonths[$date->month];
                                                            $year = $date->year + 543; // Convert to Buddhist Era
                                                            $thaiDate = "({$day} {$month} {$year})";
                                                        @endphp
                                                        {{ $thaiDate }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer - placed outside content container but with higher z-index than watermark -->
                            <div class="text-center" style="position: absolute; bottom: 0.5cm; left: 1cm; right: 1cm; z-index: 2;">
                                <div class="d-flex justify-content-between mt-1">
                                    <p class="mb-0 text-left small text-muted">วันที่พิมพ์ใบรับรอง: <span id="pdf-print-timestamp"></span></p>
                                    <p class="mb-0 text-right small text-muted">เอกสารนี้จัดทำโดยระบบอิเล็กทรอนิกส์</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="alert alert-info">
                        กรุณารอสักครู่...
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>ปิด</button>
                    
                    <!-- Only show these buttons when a payslip is selected -->
                    @if($selectedPayslip)

                        
                        <a class="btn btn-success" href="{{ route('payslip.download', ['id' => $selectedPayslip['id']]) }}">
                            <i class="fas fa-file-pdf mr-1"></i> ดาวน์โหลด PDF
                        </a>
                    @else
                        <!-- Disabled buttons when no payslip is selected -->

                        
                        <button type="button" class="btn btn-success" disabled>
                            <i class="fas fa-file-pdf mr-1"></i> ดาวน์โหลด PDF
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    $wire.on('year-selected', () => window.portalScrollToOnPhone('payslip-register'));

    $wire.on('show-payslip-detail', () => {
        window.portalModal.show('payslipDetailModal');
    });

    $wire.on('show-pdf-preview', () => {
        console.log('Opening PDF preview modal');
        window.portalModal.show('pdfPreviewModal');
        
        // Delay the timestamp update to ensure the modal is visible
        setTimeout(() => {
            updatePrintTimestamp();
        }, 300);
    });
    
    // Function to generate Thai date-time format
    function updatePrintTimestamp() {
        try {
            const now = new Date();
            const day = now.getDate();
            
            // Thai month names
            const thaiMonths = [
                'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
                'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
            ];
            const month = thaiMonths[now.getMonth()];
            
            // Convert to Buddhist Era year (CE + 543)
            const year = now.getFullYear() + 543;
            
            // Format time (HH:MM)
            let hours = now.getHours();
            let minutes = now.getMinutes().toString().padStart(2, '0');
            
            // Create formatted timestamp
            const timestamp = `${day} ${month} ${year} เวลา ${hours}:${minutes}น.`;
            
            // Update the timestamp element
            const timestampElement = document.getElementById('pdf-print-timestamp');
            if (timestampElement) {
                timestampElement.textContent = timestamp;
                console.log('Timestamp updated:', timestamp);
            } else {
                console.error('Timestamp element not found');
            }
        } catch (e) {
            console.error('Error updating timestamp:', e);
        }
    }

    // Add event listener for when modal is fully shown
    // Add manual trigger for testing
    document.addEventListener('DOMContentLoaded', function() {
        // Add click handler to download buttons
        document.querySelectorAll('[wire\\:click="downloadPayslip"]').forEach(button => {
            button.addEventListener('click', function() {
                // Wait for Livewire to finish processing
                setTimeout(() => {
                    if (!document.getElementById('pdfPreviewModal')?.classList.contains('show')) {
                        console.log('Modal not shown automatically, trying manual trigger');
                        // Fallback manual trigger
                        window.portalModal.show('pdfPreviewModal');
                    }
                }, 500);
            });
        });
    });
    
    // Function to print the payslip preview
    function printPayslipPreview() {
        // Get the preview content
        const printContents = document.querySelector('.pdf-preview').outerHTML;
        const originalContents = document.body.innerHTML;
        
        // Create a print-friendly version
        document.body.innerHTML = `
            <html>
                <head>
                    <title>ใบรับรองการจ่ายค่าตอบแทน</title>
                    <style>
                        @media print {
                            body { margin: 0; padding: 0; }
                            .pdf-preview {
                                box-shadow: none !important;
                                width: 100% !important;
                                height: auto !important;
                                padding: 1cm;
                            }
                        }
                        
                        @page {
                            size: A4;
                            margin: 0;
                        }
                        
                        body {
                            font-family: 'THSarabunNew', sans-serif;
                        }
                    </style>
                </head>
                <body>
                    ${printContents}
                </body>
            </html>
        `;
        
        window.print();
        
        // Restore original content
        setTimeout(() => {
            document.body.innerHTML = originalContents;
            // Re-initialize Livewire
            window.Livewire.rescan();
        }, 1000);
    }
    
    $wire.on('pdf-generation-error', (event) => {
        const message = event?.message ?? event?.[0] ?? 'ไม่สามารถเตรียมข้อมูลใบรับรองได้';
        window.Swal?.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: message });
    });
</script>
@endscript
