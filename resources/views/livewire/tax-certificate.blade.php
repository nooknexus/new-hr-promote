<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold text-brand-700">เอกสารภาษี</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">ใบรับรองการหักภาษี ณ ที่จ่าย</h1>
        <p class="mt-2 text-sm text-slate-500">เลือกปีภาษีเพื่อดูและดาวน์โหลดรายการใบรับรอง</p>
    </header>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-3" aria-labelledby="tax-year-heading">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                <h2 id="tax-year-heading" class="text-sm font-bold text-brand-700">เลือกปีภาษี</h2>
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
                        ไม่พบข้อมูลใบรับรองการหักภาษีที่เชื่อมโยงกับบัญชีนี้
                    </p>
                @endif
            </div>
        </section>

        <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-9" aria-labelledby="tax-certificates-heading">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                <h2 id="tax-certificates-heading" class="text-sm font-bold text-brand-700">
                    @if($selectedYear)
                        รายการใบรับรองการหักภาษี ปี{{ $selectedYear }}
                    @else
                        รายการใบรับรองการหักภาษี
                    @endif
                </h2>
                <span wire:loading wire:target="selectYear" class="text-xs font-medium text-slate-500">กำลังโหลดรายการ…</span>
            </div>

            <div class="p-5">
                @if(!$selectedYear)
                    <div class="flex min-h-56 flex-col items-center justify-center text-center">
                        <span class="flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-2xl text-brand-600" aria-hidden="true"><i class="fas fa-calendar-alt"></i></span>
                        <p class="mt-4 font-semibold text-slate-700">เลือกปีภาษี</p>
                        <p class="mt-1 text-sm text-slate-500">เลือกรายการทางด้านซ้ายเพื่อแสดงใบรับรอง</p>
                    </div>
                @elseif($availableCertificates === [])
                    <div class="flex min-h-56 flex-col items-center justify-center text-center">
                        <span class="flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400" aria-hidden="true"><i class="fas fa-file-invoice"></i></span>
                        <p class="mt-4 font-semibold text-slate-700">ไม่พบใบรับรองการหักภาษี</p>
                        <p class="mt-1 text-sm text-slate-500">ไม่พบรายการในปี{{ $selectedYear }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm" id="taxCertificateTable">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th scope="col" class="whitespace-nowrap px-4 py-3">เลขที่เอกสาร</th>
                                    <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">รายได้ทั้งหมด</th>
                                    <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">ภาษีที่ถูกหัก</th>
                                    <th scope="col" class="whitespace-nowrap px-4 py-3">การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach($availableCertificates as $index => $certificate)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-800">{{ $certificate['certificateNo'] }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format((float)$certificate['totalIncome'], 2) }} บาท</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format((float)$certificate['taxWithheld'], 2) }} บาท</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" wire:click="viewCertificate({{ $index }})" class="btn btn-secondary btn-sm">
                                                    <i class="fas fa-eye" aria-hidden="true"></i> ดูรายละเอียด
                                                </button>
                                                <button type="button" wire:click="previewDocument({{ $index }})" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-download" aria-hidden="true"></i> ดาวน์โหลด
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <div wire:loading wire:target="selectYear, viewCertificate, previewDocument" class="text-center text-sm font-medium text-slate-500">
        กำลังดำเนินการ…
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
                <div class="modal-body p-0" id="documentPreviewContent">
                    <div class="tax-certificate-preview-stage">
                        <article class="tax-certificate-document" aria-label="หนังสือรับรองการหักภาษี ณ ที่จ่าย">
                            <div class="tax-certificate-copies">
                                <div>ฉบับที่ 1 (สําหรับผู้ถูกหักภาษี ณ ที่จ่าย ใช้แนบพร้อมกับแบบแสดงรายการภาษี)</div>
                                <div>ฉบับที่ 2 (สําหรับผู้ถูกหักภาษี ณ ที่จ่าย เก็บไว้เป็นหลักฐาน)</div>
                            </div>
                            <header class="tax-certificate-title"><strong>หนังสือรับรองการหักภาษี ณ ที่จ่าย</strong><span>ตามมาตรา 50 ทวิ แห่งประมวลรัษฎากร</span></header>
                            <div class="tax-certificate-number"><div><span>เล่มที่</span><span class="tax-certificate-number-field"></span></div><div><span>เลขที่</span><span class="tax-certificate-number-field">{{ $previewCertificate['certificateNo'] }}</span></div></div>

                            <section class="tax-certificate-party tax-certificate-agent">
                                <strong>ผู้มีหน้าที่หักภาษี ณ ที่จ่าย :-</strong>
                                <p class="tax-certificate-party-id">เลขประจำตัวประชาชน <span>{{ \App\Support\ThaiCitizenId::display((string) ($previewCertificate['payerTaxId'] ?? '')) }}</span></p>
                                <p class="tax-certificate-party-name">ชื่อ <span class="tax-certificate-value">{{ $previewCertificate['payerName'] ?? '' }}</span> <span class="tax-certificate-tax-id">เลขประจำตัวผู้เสียภาษีอากร {{ \App\Support\ThaiCitizenId::display((string) ($previewCertificate['payerTaxId'] ?? '')) }}</span></p>
                                <p class="tax-certificate-party-hint tax-certificate-hint">( ให้ระบุว่าเป็น บุคคล นิติบุคคล บริษัท สมาคม หรือคณะบุคคล ) <span>( กรอกเฉพาะกรณีเป็นผู้ไม่มีเลขบัตรประจำตัวประชาชน )</span></p>
                                <p class="tax-certificate-party-address">ที่อยู่ <span class="tax-certificate-value">{{ $previewCertificate['payerAddress'] ?? '' }}</span></p>
                                <p class="tax-certificate-party-address-hint tax-certificate-hint">( ให้ระบุ ชื่ออาคาร/หมู่บ้าน ห้องเลขที่ ชั้นที่ เลขที่ ตรอก/ซอย หมู่ที่ ถนน ตำบล/แขวง อำเภอ/เขต จังหวัด )</p>
                            </section>

                            <section class="tax-certificate-party tax-certificate-recipient">
                                <strong>ผู้ถูกหักภาษี ณ ที่จ่าย :-</strong>
                                <p class="tax-certificate-party-id">เลขประจำตัวประชาชน <span>{{ \App\Support\ThaiCitizenId::display((string) ($previewCertificate['recipientCid'] ?? $previewCertificate['employeeInfo']['pid'] ?? '')) }}</span></p>
                                <p class="tax-certificate-party-name">ชื่อ <span class="tax-certificate-value">{{ $previewCertificate['recipientName'] ?? $previewCertificate['employeeInfo']['name'] ?? '' }}</span> <span class="tax-certificate-tax-id">เลขประจำตัวผู้เสียภาษีอากร {{ \App\Support\ThaiCitizenId::display((string) ($previewCertificate['recipientTaxId'] ?? '')) }}</span></p>
                                <p class="tax-certificate-party-hint tax-certificate-hint">( ให้ระบุว่าเป็น บุคคล นิติบุคคล บริษัท สมาคม หรือคณะบุคคล ) <span>( กรอกเฉพาะกรณีเป็นผู้ไม่มีเลขบัตรประจำตัวประชาชน )</span></p>
                                <p class="tax-certificate-party-address">ที่อยู่ <span class="tax-certificate-value">{{ $previewCertificate['recipientAddress'] ?? $previewCertificate['employeeInfo']['department'] ?? '' }}</span></p>
                                <p class="tax-certificate-party-address-hint tax-certificate-hint">( ให้ระบุ ชื่ออาคาร/หมู่บ้าน ห้องเลขที่ ชั้นที่ เลขที่ ตรอก/ซอย หมู่ที่ ถนน ตำบล/แขวง อำเภอ/เขต จังหวัด )</p>
                            </section>

                            @php
                                $previewIncomeTypeOptions = $previewCertificate['incomeTypeOptions'] ?? \App\Services\TaxCertificatePdfService::incomeTypeOptions($previewCertificate);
                                $previewFundOptions = $previewCertificate['fundOptions'] ?? \App\Services\TaxCertificatePdfService::fundOptions($previewCertificate);
                                $previewPaymentOptions = $previewCertificate['paymentOptions'] ?? \App\Services\TaxCertificatePdfService::paymentOptions($previewCertificate);
                            @endphp
                            <div class="tax-certificate-form-reference">
                                <div class="tax-certificate-sequence"><span>ลำดับที่</span><strong>{{ $previewCertificate['certificateNo'] }}</strong><span>ในแบบ</span></div>
                                <div class="tax-certificate-options">
                                    @foreach($previewIncomeTypeOptions as $option)
                                        <span><i class="tax-certificate-box {{ $option['selected'] ? 'is-checked' : '' }}"></i>({{ $option['id'] }}) {{ $option['label'] }}</span>
                                    @endforeach
                                </div>
                            </div>

                            @php
                                $previewIncomeParts = explode('.', number_format((float) $previewCertificate['totalIncome'], 2));
                                $previewTaxParts = explode('.', number_format((float) $previewCertificate['taxWithheld'], 2));
                            @endphp
                            <table class="tax-certificate-income-table">
                                <colgroup><col style="width:53.45%"><col style="width:14.65%"><col style="width:12.07%"><col style="width:4.31%"><col style="width:11.21%"><col style="width:4.31%"></colgroup>
                                <thead><tr><th>ประเภทเงินได้พึงประเมินที่จ่าย</th><th>วัน เดือน<br>หรือปีภาษี ที่จ่าย</th><th colspan="2">จำนวนเงินที่จ่าย</th><th colspan="2">ภาษีที่หัก<br>และนำส่งไว้</th></tr></thead>
                                <tbody><tr><td>1. เงินเดือน ค่าจ้าง เบี้ยเลี้ยง โบนัส ฯลฯ ตามมาตรา 40(1)<br>2. ค่าธรรมเนียม ค่าตอบแทน ฯลฯ ตามมาตรา 40(2)</td><td>{{ $previewCertificate['taxYear'] }}</td><td>{{ $previewIncomeParts[0] }}</td><td>{{ $previewIncomeParts[1] ?? '00' }}</td><td>{{ $previewTaxParts[0] }}</td><td>{{ $previewTaxParts[1] ?? '00' }}</td></tr></tbody>
                                <tfoot>
                                    <tr class="tax-certificate-total-row"><td colspan="2">รวมเงินที่จ่ายและภาษีที่หักนำส่ง</td><td>{{ $previewIncomeParts[0] }}</td><td>{{ $previewIncomeParts[1] ?? '00' }}</td><td>{{ $previewTaxParts[0] }}</td><td>{{ $previewTaxParts[1] ?? '00' }}</td></tr>
                                    <tr class="tax-certificate-words-row"><td colspan="6"><span>รวมเงินภาษีที่หักนำส่ง (ตัวอักษร)</span><strong>{{ array_key_exists('taxText', $previewCertificate) ? ($previewCertificate['taxText'] ?? '') : ((float) $previewCertificate['taxWithheld'] > 0 ? 'ใส่จำนวนเงินเป็นตัวอักษร' : 'ศูนย์บาทถ้วน') }}</strong></td></tr>
                                </tfoot>
                            </table>

                            <div class="tax-certificate-fund-row">
                                <span>เงินสะสมที่จ่ายเข้า</span>@foreach($previewFundOptions as $option)<span class="tax-certificate-fund-option"><i class="tax-certificate-box {{ $option['selected'] ? 'is-checked' : '' }}"></i>{{ $option['label'] }} {{ $option['selected'] ? number_format((float) $option['amount'], 2) : '' }} บาท</span>@endforeach
                            </div>
                            <div class="tax-certificate-payment-row">
                                <span>ผู้จ่ายเงิน</span>@foreach($previewPaymentOptions as $option)<span><i class="tax-certificate-box {{ $option['selected'] ? 'is-checked' : '' }}"></i>({{ $option['id'] }}) {{ $option['label'] }}{{ $option['id'] === '4' ? ' (ระบุ) ....................' : '' }}</span>@endforeach
                            </div>
                            <div class="tax-certificate-warning"><span>คำเตือน</span><p>ผู้ที่มีหน้าที่ออกหนังสือรับรองการหักภาษี ณ ที่จ่าย<br>ฝ่าฝืนไม่ปฏิบัติตามมาตรา 50 ทวิ แห่งประมวลรัษฎากร<br>ต้องรับโทษทางอาญาตามมาตรา 35</p></div>
                            <div class="tax-certificate-declaration"><p>ขอรับรองว่าข้อความและตัวเลขดังกล่าวข้างต้นถูกต้องตรงกับความจริงทุกประการ</p>@if(! empty($previewCertificate['signatureDataUri']))<img class="tax-certificate-signature-image" src="{{ $previewCertificate['signatureDataUri'] }}" alt="ลายเซ็นผู้จ่ายเงิน">@endif<div class="tax-certificate-signature"><p>ลงชื่อ <span></span> ผู้จ่ายเงิน</p><p>{{ $previewCertificate['formattedIssueDate'] }}</p><p>(วัน เดือน ปี ที่ออกหนังสือรับรอง)</p></div></div>
                        </article>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeDocumentPreview">ปิด</button>
                    <button type="button" class="btn btn-primary" wire:click="printDocument">
                        <i class="fas fa-print mr-1"></i> พิมพ์
                    </button>
                    <a href="{{ route('tax.certificate.download', ['taxYear' => $previewCertificate['taxYear'], 'certificateNo' => $previewCertificate['certificateNo']]) }}" class="btn btn-success">
                        <i class="fas fa-file-pdf mr-1"></i> ดาวน์โหลด PDF
                    </a>
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
            
            // Print document functionality
            $wire.on('print-document', () => {
                const documentElement = document.querySelector('.tax-certificate-document');
                if (!documentElement) return;

                const printWindow = window.open('', '_blank');
                if (!printWindow) return;

                const stylesheets = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
                    .map((link) => `<link rel="stylesheet" href="${link.href}">`)
                    .join('');

                printWindow.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>ใบรับรองการหักภาษี</title>${stylesheets}<style>
                    @page { size: A4; margin: 0; }
                    html, body { margin: 0; padding: 0; background: #fff; }
                    .tax-certificate-preview-stage { padding: 0 !important; background: #fff !important; }
                    .tax-certificate-document { width: 210mm !important; min-width: 0 !important; height: 297mm !important; }
                    @media print { body { width: 210mm; height: 297mm; } }
                </style></head><body>${documentElement.outerHTML}</body></html>`);
                printWindow.document.close();
                printWindow.addEventListener('load', () => {
                    printWindow.focus();
                    printWindow.print();
                    printWindow.close();
                }, { once: true });
            });
        </script>
    @endscript
</div>
