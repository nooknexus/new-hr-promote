<div>
    <div class="row">
        <!-- Fiscal Year Selection Column -->
        <div class="col-lg-3 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">เลือกปี</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        @foreach($years as $year)
                        <button type="button"
                            class="list-group-item list-group-item-action {{ $selectedYear == $year ? 'active' : '' }}"
                            wire:click="selectYear({{ $year }})">
                            ปี{{ $year + 543 }}
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Payslip Display Column -->
        <div class="col-lg-9 mb-4">
            <!-- Payslip Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">ใบรับรองการจ่ายค่าตอบแทน (ปี {{ $selectedYear + 543 }})</h6>
                </div>
                <div class="card-body">
                    @if(count($payslips) > 0)
                    <div class="table-responsive">
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
                                        <button class="btn btn-sm btn-primary" wire:click="viewPayslipDetail('{{ $payslip['id'] }}')">
                                            <i class="fas fa-eye"></i> ดูรายละเอียด
                                        </button>
                                        <button class="btn btn-sm btn-success" wire:click="downloadPayslip('{{ $payslip['id'] }}')">
                                            <i class="fas fa-download"></i> ดาวน์โหลด
                                        </button>
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
                    
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0" style="overflow-y: auto; flex: 1 1 auto;">
                    @if($selectedPayslip)
                    <!-- A4 Preview Container -->
                    <div class="d-flex justify-content-center py-3">
                        <div class="pdf-preview" style="width: 21cm; height: 29.7cm; background: white; padding: 1cm; box-shadow: 0 0 10px rgba(0,0,0,0.1); position: relative; overflow: hidden;">
                            <!-- Watermark -->
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: url('{{ asset('img/head_logo.png') }}'); background-repeat: no-repeat; background-position: center center; background-size: 80%; opacity: 0.07; pointer-events: none; z-index: 1;"></div>
                            
                            <!-- Content Container - ensure content is above watermark -->
                            <div style="position: relative; z-index: 2;">
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
                                <div class="row mt-5">
                                    <div class="col-12">
                                        <!-- Signature Block - Right Aligned -->
                                        <div class="text-right" style="padding-right: 1cm;">
                                            <!-- Signature Line -->
                                            <div class="d-flex justify-content-end align-items-center">
                                                <span style="padding-right: 4%;">ลงชื่อ</span>
                                                <img src="{{ asset('img/sign.png') }}" alt="Signature" height="50" class="mx-2">
                                                <span style="padding-left: 4%;">ผู้มีหน้าที่จ่ายเงิน</span>
                                            </div>
                                            
                                            <!-- Name and Position -->
                                            <div class="mt-2 text-center" style="margin-left: 37%; width: 80%;">
                                                <p class="mb-0">(นายมนัสศักต์ มากบุญ)</p>
                                                <p class="mb-0">นักวิชาการสาธารณสุขชำนาญการพิเศษ (ด้านบริการทางวิชาการ)</p>
                                                <p class="mb-0">รองนายแพทย์สาธารณสุขจังหวัดพิษณุโลก (ด้านบริหาร)</p>
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
                    
                    <!-- Only show these buttons when a payslip is selected -->
                    @if($selectedPayslip)
                        <!-- Direct link to printable version with fallback for empty ID -->
                        <a href="{{ route('payslip.print-view', ['id' => $selectedPayslip['id']]) }}" 
                           class="btn btn-info" target="_blank">
                            <i class="fas fa-external-link-alt mr-1"></i> เปิดในแท็บใหม่
                        </a>
                        
                        <button type="button" class="btn btn-success" wire:click="generatePDF('{{ $selectedPayslip['id'] }}')" id="download-pdf-btn">
                            <i class="fas fa-file-pdf mr-1"></i> ดาวน์โหลด PDF
                        </button>
                    @else
                        <!-- Disabled buttons when no payslip is selected -->
                        <button type="button" class="btn btn-info" disabled>
                            <i class="fas fa-external-link-alt mr-1"></i> เปิดในแท็บใหม่
                        </button>
                        
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
    // Global functions for direct onclick access
    function openInNewTab() {
        console.log('Open in new tab function called');
        // Get the preview content
        const previewContent = document.querySelector('.pdf-preview');
        if (!previewContent) {
            console.error('Preview content not found');
            return;
        }
        
        // Create a complete HTML document for the new tab
        const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>ใบรับรองการจ่ายค่าตอบแทน</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    font-family: 'THSarabunNew', 'Sarabun', sans-serif;
                    margin: 0;
                    padding: 20px;
                    background-color: #f8f9fa;
                }
                .container {
                    width: 21cm;
                    min-height: 29.7cm;
                    margin: 0 auto;
                    padding: 1cm;
                    background-color: white;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
                .print-button {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 10px 20px;
                    background-color: #007bff;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    z-index: 1000;
                }
                @media print {
                    body {
                        background-color: white;
                    }
                    .container {
                        width: 100%;
                        box-shadow: none;
                        padding: 0;
                    }
                    .print-button {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <button class="print-button" onclick="window.print()">พิมพ์เอกสาร</button>
            <div class="container">
                ${previewContent.outerHTML}
            </div>
        </body>
        </html>`;
        
        // Open the content in a new tab
        const newTab = window.open('', '_blank');
        if (newTab) {
            newTab.document.write(htmlContent);
            newTab.document.close();
            console.log('Content opened in new tab');
        } else {
            console.error('Failed to open new tab. Popup might be blocked.');
            alert('ไม่สามารถเปิดแท็บใหม่ได้ โปรดตรวจสอบว่าเบราว์เซอร์ไม่ได้บล็อกป๊อปอัพ');
        }
    }
    
    $wire.on('show-payslip-detail', () => {
        $('#payslipDetailModal').modal('show');
    });

    $wire.on('show-pdf-preview', () => {
        console.log('Opening PDF preview modal');
        $('#pdfPreviewModal').modal('show');
        
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
    $('#pdfPreviewModal').on('shown.bs.modal', function () {
        updatePrintTimestamp();
    });

    // Add manual trigger for testing
    document.addEventListener('DOMContentLoaded', function() {
        // Add click handler to download buttons
        document.querySelectorAll('[wire\\:click="downloadPayslip"]').forEach(button => {
            button.addEventListener('click', function() {
                // Wait for Livewire to finish processing
                setTimeout(() => {
                    if (!$('#pdfPreviewModal').hasClass('show')) {
                        console.log('Modal not shown automatically, trying manual trigger');
                        // Fallback manual trigger
                        $('#pdfPreviewModal').modal('show');
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
                            font-family: 'THSarabunNew', 'Sarabun', sans-serif; 
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
    
    // Add event listeners for PDF generation
    document.addEventListener('livewire:initialized', () => {
        console.log('Livewire initialized - Setting up Livewire event listeners');
        
        // PDF error handling
        Livewire.on('pdf-generation-error', (message) => {
            console.error('PDF Generation Error:', message);
            Swal.close(); // Close any loading dialogs
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: message || 'ไม่สามารถสร้างไฟล์ PDF ได้',
                confirmButtonText: 'ตกลง'
            });
        });
        
        // Add enhanced loading indicator when generating PDF
        document.getElementById('download-pdf-btn')?.addEventListener('click', function() {
            console.log('Download PDF button clicked');
            
            // Show loading indicator
            Swal.fire({
                title: 'กำลังสร้างไฟล์ PDF',
                text: 'กรุณารอสักครู่...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });
        
        // PDF download handler
        Livewire.on('serve-pdf-download', (url) => {
            console.log('PDF download ready:', url);
            Swal.close(); // Close loading dialog
            
            // Check if URL is valid
            if (!url || url === 'undefined') {
                console.error('Invalid URL received:', url);
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่ได้รับ URL ที่ถูกต้องสำหรับดาวน์โหลดไฟล์ PDF',
                    confirmButtonText: 'ตกลง'
                });
                return;
            }
            
            console.log('Attempting to download PDF from URL:', url);
            
            // Method 1: Try direct download using anchor element
            try {
                const downloadLink = document.createElement('a');
                downloadLink.href = url;
                downloadLink.download = 'payslip.pdf';
                downloadLink.style.display = 'none';
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
                console.log('Download initiated via anchor element');
            } catch (error) {
                console.error('Anchor download failed:', error);
            }
            
            // Method 2: Try opening in new window as fallback
            try {
                const newWindow = window.open(url, '_blank');
                if (newWindow) {
                    console.log('Opened in new window as fallback');
                } else {
                    console.error('Failed to open new window - popup blocked');
                }
            } catch (error) {
                console.error('Window.open failed:', error);
            }
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'ดาวน์โหลดสำเร็จ',
                text: 'ระบบกำลังดาวน์โหลดไฟล์ PDF ให้คุณ หากไม่ได้รับไฟล์โปรดตรวจสอบการบล็อกป๊อปอัพ',
                timer: 3000,
                showConfirmButton: false
            });
        });
    });
</script>
@endscript