<div>
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">ใบรับรองการหักภาษี ณ ที่จ่าย</h1>
    </div>

    <!-- Available Tax Certificates Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">รายการใบรับรองการหักภาษี</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="taxCertificateTable" width="100%" cellspacing="0">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th>ปีภาษี</th>
                            <th>เลขที่เอกสาร</th>
                            <th>รายได้ทั้งหมด</th>
                            <th>ภาษีที่ถูกหัก</th>
                            <th width="15%">การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($availableCertificates as $index => $certificate)
                        <tr>
                            <td>{{ $certificate['taxYear'] }}</td>
                            <td>{{ $certificate['certificateNo'] }}</td>
                            <td class="text-right">{{ number_format((float)$certificate['totalIncome'], 2) }} บาท</td>
                            <td class="text-right">{{ number_format((float)$certificate['taxWithheld'], 2) }} บาท</td>
                            <td>
                                <button wire:click="viewCertificate({{ $index }})" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> ดูรายละเอียด
                                </button>
                                <button wire:click="previewDocument({{ $index }})" class="btn btn-sm btn-success">
                                    <i class="fas fa-download"></i> ดาวน์โหลด
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center">ไม่พบข้อมูลใบรับรองการหักภาษี</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div wire:loading wire:target="viewCertificate, downloadPDF" class="text-center my-4">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">กำลังโหลด...</span>
        </div>
        <p class="mt-2">กำลังดำเนินการ...</p>
    </div>

    <!-- Tax Certificate Details Modal -->
    @if($selectedCertificate)
    <div class="modal fade show" id="certificateDetailModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-file-invoice mr-2"></i>รายละเอียดการหักภาษี ประจำปี {{ $selectedCertificate['taxYear'] }}
                    </h5>
                    <button type="button" class="close text-white" wire:click="closeModal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Income Summary Card -->
                    <div class="card border-left-success shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="card-title text-success font-weight-bold">
                                <i class="fas fa-money-bill-wave mr-2"></i>รายได้รวมทั้งปี
                            </h6>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="text-center p-3 bg-light rounded mb-3">
                                        <h4 class="text-primary font-weight-bold mb-0">
                                            ฿{{ number_format((float)$selectedCertificate['totalIncome'], 2) }}
                                        </h4>
                                        <div class="text-muted small">เงินได้ทั้งสิ้น</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-center p-3 bg-light rounded mb-3">
                                        <h4 class="text-danger font-weight-bold mb-0">
                                            ฿{{ number_format((float)$selectedCertificate['taxWithheld'], 2) }}
                                        </h4>
                                        <div class="text-muted small">ภาษีที่ถูกหัก</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Deductions Card -->
                    <div class="card border-left-danger shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="card-title text-danger font-weight-bold">
                                <i class="fas fa-hand-holding-usd mr-2"></i>รายการหักทั้งปี
                            </h6>
                            <hr>
                            
                            <!-- Deductions Table -->
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>รายการหัก</th>
                                            <th class="text-right">จำนวนเงิน (บาท)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>ภาษีหัก ณ ที่จ่าย</td>
                                            <td class="text-right">{{ number_format((float)$selectedCertificate['taxWithheld'], 2) }}</td>
                                        </tr>
                                        @if((float)$selectedCertificate['governmentPensionFund'] <= 0)
                                        <tr>
                                            <td>เงินประกันสังคม</td>
                                            <td class="text-right">{{ number_format((float)$selectedCertificate['socialSecurity'], 2) }}</td>
                                        </tr>
                                        @endif
                                        @if((float)$selectedCertificate['providentFund'] > 0)
                                        <tr>
                                            <td>เงินสะสมกองทุนสำรองเลี้ยงชีพ</td>
                                            <td class="text-right">{{ number_format((float)$selectedCertificate['providentFund'], 2) }}</td>
                                        </tr>
                                        @endif
                                        @if((float)$selectedCertificate['governmentPensionFund'] > 0)
                                        <tr>
                                            <td>กองทุนบำเหน็จบำนาญข้าราชการ</td>
                                            <td class="text-right">{{ number_format((float)$selectedCertificate['governmentPensionFund'], 2) }}</td>
                                        </tr>
                                        @endif
                                    </tbody>
                                    <tfoot class="bg-light font-weight-bold">
                                        <tr>
                                            <td>รวมเงินที่หักทั้งสิ้น</td>
                                            <td class="text-right">{{ number_format((float)$selectedCertificate['taxWithheld'] + (float)$selectedCertificate['socialSecurity'] + (float)$selectedCertificate['providentFund'] + (float)$selectedCertificate['governmentPensionFund'], 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>หมายเหตุ:</strong> หากต้องการเอกสารที่ใช้ในการยื่นภาษีอย่างเป็นทางการ 
                        กรุณาคลิกปุ่ม PDF ในตารางรายการ
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Document Preview Modal -->
    @if($previewCertificate)
    <div class="modal fade show" id="documentPreviewModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-file-pdf mr-2"></i>เอกสารใบรับรองการหักภาษี ประจำปี {{ $previewCertificate['taxYear'] }}
                    </h5>
                    <button type="button" class="close text-white" wire:click="closeDocumentPreview">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body bg-white p-0" id="documentPreviewContent">
                    <!-- Official Tax Certificate Format -->
                    <div class="document-container" style="
                            width: 100%; 
                            max-width: 210mm;
                            height: 297mm;
                            margin: 0 auto; 
                            position: relative;
                            background-image: url('{{ asset('img/layout.png') }}');
                            background-size: contain;
                            background-repeat: no-repeat;
                            background-position: center top;
                            font-family: 'THSarabunNew', sans-serif; 
                            font-size: 16pt; 
                            line-height: 1.2;
                            overflow: hidden;
                        ">
                        <!-- Form content with precise positioning to match layout fields -->
                        
                        <!-- Form versions - Top right corner -->
                        <div style="position: absolute; top: 30px; right: 40px; text-align: right;">
                            <div>ฉบับที่ 1 (สําหรับผู้ถูกหักภาษี ณ ที่จ่าย ใช้แนบพร้อมกับแบบแสดงรายการภาษี)</div>
                            <div>ฉบับที่ 2 (สําหรับผู้ถูกหักภาษี ณ ที่จ่าย เก็บไว้เป็นหลักฐาน)</div>
                        </div>

                        <!-- Document number -->
                        <div style="position: absolute; top: 150px; left: 40px;">
                            <div>เล่มที่</div>
                            <div>เลขที่ {{ $previewCertificate['certificateNo'] }}</div>
                        </div>

                        <!-- Withholding agent section -->
                        <div style="position: absolute; top: 190px; left: 40px; width: calc(100% - 80px);">
                            <div style="font-weight: bold;">ผู้มีหน้าที่หักภาษี ณ ที่จ่าย :-</div>
                            <div style="margin-left: 20px; margin-top: 10px;">
                                <div>เลขบัตรประจําตัวประชาชน 0-9940-00241-81-0</div>
                                <div>ชื่อ สํานักงานปลัดกระทรวงสาธารณสุข เลขประจําตัวผู้เสียภาษีอากร</div>
                                <div style="font-size: 14pt; color: #666;">( โปรดระบุว่าเป็น บุคคล นิติบุคคล บริษัท สมาคม หรือคณะบุคคล ) ( กรอกเฉพาะกรณีเป็นผู้ไม่มีเลขบัตรประจําตัวประชาชน )</div>
                                <div>ที่อยู่ ถนนติวานนท์ จังหวัดนนทบุรี 11000</div>
                                <div style="font-size: 14pt; color: #666;">( โปรดระบุ ชื่ออาคาร/หมู่บ้าน ห้องเลขที่ ชั้นที่ เลขที่ ตรอก/ซอย หมู่ที่ ถนน ตําบล/แขวง อําเภอ/เขต จังหวัด )</div>
                            </div>
                        </div>

                        <!-- Taxpayer section -->
                        <div style="position: absolute; top: 310px; left: 40px; width: calc(100% - 80px);">
                            <div style="font-weight: bold;">ผู้ถูกหักภาษี ณ ที่จ่าย :-</div>
                            <div style="margin-left: 20px; margin-top: 10px;">
                                <div>เลขบัตรประจําตัวประชาชน {{ $previewCertificate['employeeInfo']['pid'] }}</div>
                                <div>ชื่อ {{ $previewCertificate['employeeInfo']['name'] }} เลขประจําตัวผู้เสียภาษีอากร</div>
                                <div style="font-size: 14pt; color: #666;">( โปรดระบุว่าเป็น บุคคล นิติบุคคล บริษัท สมาคม หรือคณะบุคคล ) ( กรอกเฉพาะกรณีเป็นผู้ไม่มีเลขบัตรประจําตัวประชาชน )</div>
                                <div>ที่อยู่</div>
                                <div style="font-size: 14pt; color: #666;">( โปรดระบุ ชื่ออาคาร/หมู่บ้าน ห้องเลขที่ ชั้นที่ เลขที่ ตรอก/ซอย หมู่ที่ ถนน ตําบล/แขวง อําเภอ/เขต จังหวัด )</div>
                            </div>
                        </div>

                        <!-- Form reference -->
                        <div style="position: absolute; top: 430px; left: 40px; width: calc(100% - 80px);">
                            <div>สําคัญที่ {{ $previewCertificate['certificateNo'] }} ในแบบ (1) ก.ง.ต.1ก (2) ก.ง.ต.1ก พิเศษ (3) ก.ง.ต.2 (4) ก.ง.ต.2</div>
                            <div>(โปรดสามารถอ้างอิงหรือสอบถามต้นได้ระหว่างสําคัญที่ (5) ก.ง.ต.2ก (6) ก.ง.ต.3ก (7) ก.ง.ต.53</div>
                            <div>ตามหนังสือรับรองฯกับแบบอื่นรายการภาษีที่จ่าย)</div>
                        </div>

                        <!-- Payment table -->
                        <div style="position: absolute; top: 500px; left: 40px; width: calc(100% - 80px);">
                            <table style="width: 100%; border-collapse: collapse; border: 1px solid black;">
                                <tr>
                                    <th style="width: 45%; border: 1px solid black; padding: 5px;">ประเภทเงินได้พึงประเมินจ่าย</th>
                                    <th style="width: 15%; border: 1px solid black; padding: 5px;">วัน เดือน หรือปีภาษี ที่จ่าย</th>
                                    <th style="width: 20%; border: 1px solid black; padding: 5px;">จํานวนเงินที่จ่าย</th>
                                    <th style="width: 20%; border: 1px solid black; padding: 5px;">ภาษีที่หัก และนําส่งไว้</th>
                                </tr>
                                <tr>
                                    <td style="border: 1px solid black; padding: 5px;">1. เงินเดือน ค่าจ้าง เบี้ยเลี้ยง โบนัส ฯลฯ ตามมาตรา 40(1)<br>2. ค่าธรรมเนียม ค่าตอบแทน ฯลฯ ตามมาตรา 40(2)</td>
                                    <td style="border: 1px solid black; padding: 5px; text-align: center;">{{ $previewCertificate['taxYear'] }}</td>
                                    <td style="border: 1px solid black; padding: 5px; text-align: center;">{{ number_format((float)$previewCertificate['totalIncome'], 0) }}</td>
                                    <td style="border: 1px solid black; padding: 5px; text-align: center;">{{ number_format((float)$previewCertificate['taxWithheld'], 0) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="border: 1px solid black; padding: 5px;">รวมเงินที่จ่ายและภาษีที่หักนําส่ง</td>
                                    <td style="border: 1px solid black; padding: 5px; text-align: center;">{{ number_format((float)$previewCertificate['totalIncome'], 0) }}</td>
                                    <td style="border: 1px solid black; padding: 5px; text-align: center;">{{ number_format((float)$previewCertificate['taxWithheld'], 0) }}</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Total tax text -->
                        <div style="position: absolute; top: 620px; left: 40px; width: calc(100% - 80px);">
                            <div>รวมเงินภาษีที่หักนําส่ง (ตัวอักษร) <strong>{{ (float)$previewCertificate['taxWithheld'] > 0 ? 'ใส่จำนวนเงินเป็นตัวอักษร' : 'ศูนย์บาทถ้วน' }}</strong></div>
                        </div>

                        <!-- Savings information -->
                        <div style="position: absolute; top: 650px; left: 40px; width: calc(100% - 80px);">
                            <div>เงินสะสมที่จ่ายเข้า 
                                <span style="display: inline-block; width: 16px; height: 16px; border: 1px solid black; margin-right: 5px; vertical-align: middle; {{ (float)$previewCertificate['governmentPensionFund'] > 0 ? 'background-color: black;' : '' }}"></span> กบข. {{ (float)$previewCertificate['governmentPensionFund'] > 0 ? number_format((float)$previewCertificate['governmentPensionFund'], 0) : '' }} บาท 
                                <span style="display: inline-block; width: 16px; height: 16px; border: 1px solid black; margin-right: 5px; vertical-align: middle;"></span> กบจ. บาท
                            </div>
                        </div>

                        <!-- Payer information -->
                        <div style="position: absolute; top: 680px; left: 40px; width: calc(100% - 80px);">
                            <div>ผู้อํานวยเงิน 
                                <span style="display: inline-block; width: 16px; height: 16px; border: 1px solid black; margin-right: 5px; vertical-align: middle; background-color: black;"></span> หัก ณ ที่จ่าย 
                                <span style="display: inline-block; width: 16px; height: 16px; border: 1px solid black; margin-right: 5px; vertical-align: middle;"></span> ออกให้ตลอดไป 
                                <span style="display: inline-block; width: 16px; height: 16px; border: 1px solid black; margin-right: 5px; vertical-align: middle;"></span> ออกให้ครั้งเดียว 
                                <span style="display: inline-block; width: 16px; height: 16px; border: 1px solid black; margin-right: 5px; vertical-align: middle;"></span> อื่นๆ
                            </div>
                        </div>

                        <!-- Certification -->
                        <div style="position: absolute; top: 710px; left: 40px; width: calc(100% - 80px);">
                            <div>ขอรับรองว่าข้อความและตัวเลขดังกล่าวข้างต้นถูกต้องตรงกับความจริงทุกประการ</div>
                        </div>

                        <!-- Signature -->
                        <div style="position: absolute; top: 750px; right: 100px; text-align: center;">
                            <div>ผู้ที่มีหน้าที่ออกหนังสือรับรองการหักภาษี ณ ที่จ่าย</div>
                            <div style="margin-top: 40px;">ลงชื่อ <span style="display: inline-block; min-width: 150px; border-bottom: 1px solid black;"></span></div>
                            <div>(<span style="display: inline-block; min-width: 150px; border-bottom: 1px solid black;"></span>)</div>
                            <div>ฝ่าฝืนไม่ปฏิบัติตามมาตรา 50 ทวิ แห่งประมวล</div>
                            <div>รัษฎากร ต้องรับโทษทางอาญาตามมาตรา 35</div>
                            <div style="margin-top: 10px;">{{ $previewCertificate['formattedIssueDate'] }}</div>
                            <div>(วัน เดือน ปี ที่ออกหนังสือรับรอง)</div>
                        </div>

                        <!-- Footer -->
                        <div style="position: absolute; bottom: 40px; left: 40px; font-size: 14pt;">
                            <div>วันที่พิมพ์ใบรับรอง {{ date('d F Y') }}</div>
                            <div>ข้อมูล ณ {{ date('Y-m-d H:i:s') }}</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeDocumentPreview">ปิด</button>
                    <button type="button" class="btn btn-primary" wire:click="printDocument">
                        <i class="fas fa-print mr-1"></i> พิมพ์
                    </button>
                    <button type="button" class="btn btn-success" wire:click="downloadPDFFile({{ $previewIndex }})">
                        <i class="fas fa-file-pdf mr-1"></i> ดาวน์โหลด PDF
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @script
        <script>
            // Print functionality
            $wire.on('print-tax-certificate', () => {
                window.print();
            });
            
            // PDF download notification
            $wire.on('show-pdf-download-message', (data) => {
                Swal.fire({
                    title: 'กำลังดาวน์โหลด PDF',
                    text: `ใบรับรองการหักภาษี ประจำปี ${data.year} กำลังถูกดาวน์โหลด`,
                    icon: 'success',
                    timer: 3000,
                    showConfirmButton: false
                });
                
                // In a real application, you would trigger a file download here
                // For demonstration, we'll simulate a download after a delay
                setTimeout(() => {
                    const a = document.createElement('a');
                    a.href = '#';
                    a.download = `tax_certificate_${data.year}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                }, 1000);
            });
            
            // Print document functionality
            $wire.on('print-document', () => {
                const printContents = document.getElementById('documentPreviewContent').innerHTML;
                const originalContents = document.body.innerHTML;
                
                // Create a print-friendly version with precise positioning like the old project
                document.body.innerHTML = `
                    <html>
                        <head>
                            <title>ใบรับรองการหักภาษี</title>
                            <style>
                                @page {
                                    size: A4;
                                    margin: 0;
                                }
                                
                                body { 
                                    font-family: 'THSarabunNew', 'Sarabun', sans-serif;
                                    margin: 0;
                                    padding: 0;
                                }
                                
                                .print-container {
                                    position: relative;
                                    width: 210mm;
                                    height: 297mm;
                                    margin: 0 auto;
                                    overflow: hidden;
                                }
                                
                                .background-image {
                                    position: absolute;
                                    top: 0;
                                    left: 0;
                                    width: 100%;
                                    height: 100%;
                                    z-index: 1;
                                }
                                
                                .content {
                                    position: relative;
                                    z-index: 2;
                                    padding: 10mm;
                                    font-size: 9pt;
                                }
                                
                                .f12pt { font-size: 16px; }
                                .f9pt { font-size: 12px; }
                                .f7pt { font-size: 9px; }
                                
                                .text-center { text-align: center; }
                                .text-right { text-align: right; }
                                
                                .row {
                                    position: relative;
                                    width: 100%;
                                    clear: both;
                                }
                                
                                .checkbox {
                                    display: inline-block;
                                    width: 12px;
                                    height: 12px;
                                    border: 1px solid black;
                                    vertical-align: middle;
                                    text-align: center;
                                    line-height: 10px;
                                }
                                
                                .checked {
                                    background-color: black;
                                }
                                
                                .fixedbottom {
                                    position: absolute;
                                    left: 0;
                                    right: 0;
                                    bottom: 10mm;
                                    padding: 0 10mm;
                                }
                                
                                @media print {
                                    .print-container {
                                        -webkit-print-color-adjust: exact !important;
                                        color-adjust: exact !important;
                                        print-color-adjust: exact !important;
                                    }
                                }
                            </style>
                        </head>
                        <body onload="window.print()">
                            <div class="print-container">
                                <img class="background-image" src="${window.location.origin + '/img/layout.png'}" alt="form background">
                                <div class="content">
                                    <!-- Form versions -->
                                    <div style="margin-top: 4%; padding-left: 3%;">
                                        ฉบับที่ 1 <span class="f9pt">(สำหรับผู้ถูกหักภาษี ณ ที่จ่าย ใช้แนบพร้อมกับแบบแสดงรายการภาษี)</span>
                                    </div>
                                    <div style="padding-top: 5px; padding-left: 3%;">
                                        ฉบับที่ 2 <span class="f9pt">(สำหรับผู้ถูกหักภาษี ณ ที่จ่าย เก็บไว้เป็นหลักฐาน)</span>
                                    </div>
                                    
                                    <!-- Document title -->
                                    <div class="f12pt text-center" style="margin-top: 17px;">
                                        หนังสือรับรองการหักภาษี ณ ที่จ่าย
                                    </div>
                                    <div class="text-center" style="padding-top: 1%;">
                                        ตามมาตรา 50 ทวิ แห่งประมวลรัษฎากร
                                    </div>
                                    
                                    <!-- Document number -->
                                    <div style="margin-left: 85%; margin-top: -4%;">เล่มที่</div>
                                    <div style="margin-left: 85%; padding-top: 2px;">เลขที่</div>
                                    <div style="margin-left: 91%; margin-top: 8px;">${document.querySelector('.document-container [style*="left: 40px"] div:nth-child(2)').innerText}</div>
                                    
                                    <!-- Withholding agent section -->
                                    <div style="margin-top: 13px; margin-left: 4%;">ผู้มีหน้าที่หักภาษี ณ ที่จ่าย :-</div>
                                    <div style="margin-top: -2%; margin-left: 50%;">เลขบัตรประจำตัวประชาชน</div>
                                    <div style="margin-top: -2%; margin-left: 75%;">0-9940-00241-81-0</div>
                                    
                                    <div class="row" style="padding-top: 1%;">
                                        <div style="margin-left: 7%">ชื่อ</div>
                                        <div style="margin-left: 5%;">สํานักงานปลัดกระทรวงสาธารณสุข</div>
                                        <div style="margin-left: 20%;">เลขประจำตัวผู้เสียภาษีอากร</div>
                                    </div>
                                    
                                    <div class="row" style="padding-top: 1%;">
                                        <div class="f7pt" style="margin-left: 12%">(ให้ระบุว่าเป็น บุคคล นิติบุคคล บริษัท สมาคม หรือคณะบุคคล)</div>
                                        <div class="f7pt" style="margin-left: 22%">(กรอกเฉพาะกรณีเป็นผู้ไม่มีเลขบัตรประจำตัวประชาชน)</div>
                                    </div>
                                    
                                    <div class="row" style="padding-top: 2%;">
                                        <div style="margin-left: 7%">ที่อยู่</div>
                                        <div style="margin-left: 4%;">ถนนติวานนท์ จังหวัดนนทบุรี 11000</div>
                                    </div>
                                    
                                    <!-- Taxpayer section -->
                                    <div class="row" style="padding-top: 5%;">
                                        <div style="margin-left: 6%">ผู้ถูกหักภาษี ณ ที่จ่าย :-</div>
                                        <div style="margin-left: 28%;">เลขบัตรประจำตัวประชาชน</div>
                                        <div style="margin-left: 5%;">${document.querySelector('.document-container [style*="top: 310px"] div:nth-child(2) div:first-child').innerText}</div>
                                    </div>
                                    
                                    <div class="row" style="padding-top: 1%;">
                                        <div style="margin-left: 7%">ชื่อ</div>
                                        <div style="margin-left: 5%;">${document.querySelector('.document-container [style*="top: 310px"] div:nth-child(2) div:nth-child(2)').innerText}</div>
                                        <div style="margin-left: 30%">เลขประจำตัวผู้เสียภาษีอากร</div>
                                    </div>
                                    
                                    <!-- Payment table -->
                                    <div class="row" style="padding-top: 6%;">
                                        <div style="margin-left: 20%; margin-top: 2%;">ประเภทเงินได้พึงประเมินจ่าย</div>
                                        <div style="margin-left: 19%;">วัน เดือน</div>
                                        <div style="margin-left: -9%; margin-top: 3%;">หรือปีภาษี ที่จ่าย</div>
                                        <div style="margin-left: 2%; margin-top: 2%;">จำนวนเงินที่จ่าย</div>
                                        <div style="margin-left: 7%;">ภาษีที่หัก</div>
                                        <div style="margin-left: -7%; margin-top: 3%;">และนำส่งไว้</div>
                                    </div>
                                    
                                    <div class="row" style="padding-top: 2%;">
                                        <div style="margin-left: 7%">1. เงินเดือน ค่าจ้าง เบี้ยเลี้ยงโบนัส ฯลฯ ตามมาตรา 40(1)</div>
                                        <div style="margin-left: 13%;">${document.querySelector('td[style*="text-align: center;"]:nth-child(2)').innerText.trim()}</div>
                                        <div style="margin-left: 8%;">${new Intl.NumberFormat().format(parseInt(document.querySelector('td[style*="text-align: center;"]:nth-child(3)').innerText.replace(/,/g, '')))}</div>
                                        <div style="margin-left: 8%;">${new Intl.NumberFormat().format(parseInt(document.querySelector('td[style*="text-align: center;"]:nth-child(4)').innerText.replace(/,/g, '')))}</div>
                                    </div>
                                    
                                    <div class="row">
                                        <div style="margin-left: 7%">2. ค่าธรรมเนียม ค่านายหน้า ฯลฯ ตามมาตรา 40(2)</div>
                                    </div>
                                    
                                    <!-- Total -->
                                    <div class="row" style="padding-top: 13%;">
                                        <div style="margin-left: 45%">รวมเงินที่จ่ายและภาษีที่หักนำส่ง</div>
                                        <div style="margin-left: 4%;">${new Intl.NumberFormat().format(parseInt(document.querySelector('td[style*="text-align: center;"]:nth-child(3)').innerText.replace(/,/g, '')))}</div>
                                        <div style="margin-left: 8%;">${new Intl.NumberFormat().format(parseInt(document.querySelector('td[style*="text-align: center;"]:nth-child(4)').innerText.replace(/,/g, '')))}</div>
                                    </div>
                                    
                                    <!-- Checkboxes for funds -->
                                    <div class="row" style="padding-top: 5%;">
                                        <div style="margin-left: 7%; margin-top: 4px;">เงินสะสมที่จ่ายเข้า</div>
                                        <div class="checkbox ${document.querySelector('span[style*="background-color: black"]') ? 'checked' : ''}" style="margin-left: 4%;"></div>
                                        <div style="margin-left: 2%; margin-top: 4px;">กบข.</div>
                                        <div style="margin-left: 4%; margin-top: 4px;">${document.querySelector('.document-container [style*="top: 650px"]').innerText.includes('กบข.') ? document.querySelector('.document-container [style*="top: 650px"]').innerText.split('บาท')[0].split('กบข.')[1].trim() : ''}</div>
                                        <div style="margin-left: 10%; margin-top: 4px;">บาท</div>
                                    </div>
                                    
                                    <!-- Signature -->
                                    <div class="row" style="padding-top: 30px;">
                                        <div style="margin-left: 45%; margin-top: -11%; font-size: 10px;">ขอรับรองว่าข้อความและตัวเลขดังกล่าวข้างต้นถูกต้องตรงกับความจริงทุกประการ</div>
                                        <div style="margin-left: 70%; margin-top: -9%;">
                                            <div style="display: flex; justify-content: flex-end; width: 350px;">
                                                <div>ลงชื่อ</div>
                                                <div style="margin-left: 150px; border-bottom: 1px solid #000; width: 150px;"></div>
                                                <div style="margin-left: 10px;">ผู้จ่ายเงิน</div>
                                            </div>
                                        </div>
                                        <div style="margin-left: 70%; margin-top: 0%;">${document.querySelector('.document-container [style*="top: 750px"]').innerText.split('ลงชื่อ')[0].trim()}</div>
                                    </div>
                                    
                                    <!-- Footer -->
                                    <div class="fixedbottom" style="display: flex; justify-content: space-between;">
                                        <div>วันที่พิมพ์ใบรับรอง ${new Date().toLocaleDateString('th-TH')}</div>
                                        <div>ข้อมูล ณ ${new Date().toLocaleString('th-TH')}</div>
                                    </div>
                                </div>
                            </div>
                        </body>
                    </html>
                `;
                
                // Print will be triggered by onload
                
                // After printing, wait a bit then restore original content
                setTimeout(() => {
                    document.body.innerHTML = originalContents;
                    window.Livewire.rescan();
                }, 1000);
            });
        </script>
    @endscript
</div>
