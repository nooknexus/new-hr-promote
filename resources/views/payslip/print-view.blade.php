<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบรับรองการจ่ายค่าตอบแทน - {{ $payslip['month'] }}</title>
    <style>
        @font-face {
            font-family: 'THSarabunNew';
            src: url('{{ asset('fonts/THSarabunNew.ttf') }}') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'THSarabunNew';
            src: url('{{ asset('fonts/THSarabunNew-Bold.ttf') }}') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'THSarabunNew';
            src: url('{{ asset('fonts/THSarabunNew-Italic.ttf') }}') format('truetype');
            font-weight: 400;
            font-style: italic;
            font-display: swap;
        }
        @font-face {
            font-family: 'THSarabunNew';
            src: url('{{ asset('fonts/THSarabunNew-BoldItalic.ttf') }}') format('truetype');
            font-weight: 700;
            font-style: italic;
            font-display: swap;
        }

        @page {
            size: A4 portrait;
            margin: 1cm;
        }

        *, *::before, *::after { box-sizing: border-box; }

        .payslip-page {
            font-family: 'THSarabunNew', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        
        .payslip-document {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            padding: 1cm;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            box-sizing: border-box;
            overflow: hidden;
            font-size: 20px;
        }

        /* Document-only equivalents for the Tailwind app shell. They preserve
           the legacy certificate geometry without loading Bootstrap. */
        .payslip-document .row { display: flex; flex-wrap: wrap; margin-right: -12px; margin-left: -12px; }
        .payslip-document .row > [class*='col-'] { padding-right: 12px; padding-left: 12px; }
        .payslip-document .col-6 { flex: 0 0 auto; width: 50%; }
        .payslip-document .col-12 { flex: 0 0 auto; width: 100%; }
        .payslip-document p { margin-top: 0; margin-bottom: 1rem; }
        .payslip-document h4 { margin-top: 0; margin-bottom: .5rem; font-size: 26px; font-weight: 500; line-height: 1.2; }
        .payslip-document h5 { margin-top: 0; margin-bottom: .5rem; font-size: 22px; font-weight: 500; line-height: 1.2; }
        .payslip-document .mb-4 { margin-bottom: 1.5rem !important; }
        .payslip-document .mt-4 { margin-top: 1.5rem !important; }
        .payslip-document .mt-5 { margin-top: 3rem !important; }
        .payslip-document .mt-2 { margin-top: .5rem !important; }
        .payslip-document .mt-1 { margin-top: .25rem !important; }
        .payslip-document .mt-0 { margin-top: 0 !important; }
        .payslip-document .mb-0 { margin-bottom: 0 !important; }
        .payslip-document .mx-2 { margin-right: .5rem !important; margin-left: .5rem !important; }
        .payslip-document .payslip-signature { width: 10mm; height: auto; object-fit: contain; }
        .payslip-document .pb-2 { padding-bottom: .5rem !important; }
        .payslip-document .border-bottom { border-bottom: 1px solid #dee2e6 !important; }
        .payslip-document .table { width: 100%; margin-bottom: 1rem; vertical-align: top; border-color: #dee2e6; border-collapse: collapse; font-size: 17px; }
        .payslip-document .table tr:last-child { font-size: 20px; }
        .payslip-document .table > tbody > tr > td { padding: .25rem; border-bottom: 1px solid #dee2e6; }
        .payslip-document .text-center { text-align: center; }
        .payslip-document .text-start, .payslip-document .text-left { text-align: left; }
        .payslip-document .text-end, .payslip-document .text-right { text-align: right; }
        .payslip-document .d-flex { display: flex; }
        .payslip-document .justify-content-between { justify-content: space-between; }
        .payslip-document .justify-content-end { justify-content: flex-end; }
        .payslip-document .align-items-center { align-items: center; }
        .payslip-document .fw-bold { font-weight: 700; }
        .payslip-document .small { font-size: 14px; }
        .payslip-document .payslip-signature-block { font-size: 17px; }
        .payslip-document .text-muted { color: #6c757d; }
        
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
        
        .watermark {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('{{ asset('img/head_logo.png') }}');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 80%;
            opacity: 0.07;
            pointer-events: none;
            z-index: 1;
        }
        
        .content {
            position: relative;
            z-index: 2;
            height: calc(297mm - 2cm);
            overflow: hidden;
        }
        
        .footer {
            position: absolute;
            bottom: 0.5cm;
            left: 0;
            right: 0;
            z-index: 2;
            font-size: 12px;
            color: #6c757d;
        }
        
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            
            html, body.payslip-page {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
                background-color: white;
            }
            
            .payslip-document {
                width: 210mm !important;
                height: 297mm !important;
                margin: 0 !important;
                padding: 1cm !important;
                box-shadow: none;
                max-width: none !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
                break-after: auto;
                page-break-after: auto;
            }
            
            .content {
                margin: 0 auto !important;
                width: 100% !important;
                height: calc(297mm - 2cm) !important;
                overflow: hidden !important;
            }
            
            /* Ensure tables use full width */
            .table {
                width: 100% !important;
            }
            
            .payslip-document .row {
                display: flex !important;
                width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            
            .payslip-document .col-6 {
                width: 50% !important;
                padding-right: 15px !important;
                padding-left: 15px !important;
                box-sizing: border-box !important;
            }
            
            .payslip-document .col-12 {
                width: 100% !important;
                padding-right: 15px !important;
                padding-left: 15px !important;
            }
            
            .print-button {
                display: none;
            }
            
            /* Ensure footer appears in the right position when printing */
            .footer {
                position: absolute !important;
                bottom: 0.5cm !important;
                left: 0 !important;
                right: 0 !important;
            }
        }
    </style>
</head>
<body class="payslip-page">
    <button class="print-button" onclick="window.print()">พิมพ์เอกสาร</button>
    
    <div class="payslip-document">
        <!-- Watermark -->
        <div class="watermark"></div>
        
        <!-- Content -->
        <div class="content">
            <!-- Header -->
            <div class="text-center mb-4">
                <img src="{{ asset('img/logoMOPH.png') }}" alt="Logo" style="width: 100px; height: auto;">
                <h4>ใบรับรองการจ่ายเงินเดือนและเงินอื่น</h4>
                @php
                // Extract month and year from the month string (e.g., "มกราคม 2566")
                $parts = explode(' ', $payslip['month']);
                $monthName = $parts[0] ?? '';
                $yearBE = $parts[1] ?? '';
                @endphp
                <h5>ประจำเดือน {{ $monthName }} ปี พ.ศ. {{ $yearBE }}</h5>
            </div>

            <!-- Employee Info -->
            <div class="row mb-4">
                <div class="col-6">
                    <p><strong>ชื่อ-นามสกุล:</strong> {{ $user->name }}</p>
                </div>
                <div class="col-6">
                    <p><strong>เลขที่ตำแหน่ง:</strong> {{ $payslip['person_info']['position_number'] ?? '-' }}</p>
                </div>
                <div class="col-6">
                    <p><strong>ประเภท:</strong> {{ $payslip['person_info']['employee_type'] ?? '-' }}</p>
                </div>
                <div class="col-6">
                    <p><strong>กรม: {{ $payslip['person_info']['ministry']  ?? '-' }}</strong> </p>
                </div>
                <div class="col-12 mb-2">
                    <p><strong>สำนัก/กอง/ศูนย์:</strong> {{ $payslip['person_info']['department'] ?? '-' }}</p>
                </div>
                <div class="col-12 mb-2">
                    <p><strong>ส่วน:</strong> {{ $payslip['person_info']['office'] ?? '-' }}</p>
                </div>
                <div class="col-6">
                    <p><strong>โอนเงินเข้า:</strong> {{ $payslip['person_info']['bank_name'] ?? '-' }} </p>
                </div>
                <div class="col-6">
                    <p><strong>เลขที่บัญชี:</strong> {{ $payslip['person_info']['bank_account'] ?? '-' }}</p>
                </div>
            </div>

            <!-- Income and Expense Details -->
            <div class="row">
                <div class="col-6">
                    <h5 class="border-bottom pb-2"><strong>รายรับ</strong></h5>
                    <table class="table table-sm">
                        <tbody>
                            @foreach($payslip['income_details'] as $income)
                            <tr>
                                <td>{{ $income['name'] }}</td>
                                <td class="text-end">{{ number_format($income['amount'], 2) }}</td>
                            </tr>
                            @endforeach
                            <tr class="fw-bold">
                                <td>รวมรายรับทั้งหมด</td>
                                <td class="text-end">{{ number_format($payslip['total_income'], 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="col-6">
                    <h5 class="border-bottom pb-2"><strong>รายจ่าย</strong></h5>
                    <table class="table table-sm">
                        <tbody>
                            @foreach($payslip['expense_details'] as $expense)
                            <tr>
                                <td>{{ $expense['name'] }}</td>
                                <td class="text-end">{{ number_format($expense['amount'], 2) }}</td>
                            </tr>
                            @endforeach
                            <tr class="fw-bold">
                                <td>รวมรายจ่ายทั้งหมด</td>
                                <td class="text-end">{{ number_format($payslip['total_outcome'], 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="text-center">
                        <h5 class="fw-bold">รับสุทธิ: <u>{{ number_format($payslip['net'], 2) }} บาท</u></h5>
                    </div>
                </div>
            </div>
            
            <!-- Signature Section -->
            @php
                // The signer changes by fiscal year and term of office, so
                // look up who was on record for this pay period.
                $signatory = isset($payslip['id']) ? \App\Models\PayslipSignatory::forPeriodId($payslip['id']) : null;
            @endphp
            <div class="row mt-5">
                <div class="col-12">
                    <!-- Signature Block - Right Aligned -->
                    <div class="text-end payslip-signature-block" style="padding-right: 1cm;">
                        <!-- Signature Line -->
                        <div class="d-flex justify-content-end align-items-center">
                            <span style="padding-right: 4%;">ลงชื่อ</span>
                            <img src="{{ $signatory && $signatory->signature_data_uri ? $signatory->signature_data_uri : asset('img/sign.png') }}" alt="Signature" class="payslip-signature mx-2">
                            <span style="padding-left: 4%;">ผู้มีหน้าที่จ่ายเงิน</span>
                        </div>

                        <!-- Name and Position -->
                        <div class="mt-2 text-center" style="margin-left: 37%; width: 80%;">
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
                                @if(isset($payslip['verify_date']))
                                    @php
                                        $date = \Carbon\Carbon::parse($payslip['verify_date']);
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
            
            <!-- Footer - updated to ensure it's at bottom of page -->
            <div class="footer">
                <div class="d-flex justify-content-between mt-1">
                    <p class="mb-0 text-start small text-muted">
                        วันที่พิมพ์ใบรับรอง: 
                        @php
                            $now = \Carbon\Carbon::now();
                            $thaiMonths = [
                                1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 
                                4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
                                7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
                                10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                            ];
                            $day = $now->format('j');
                            $month = $thaiMonths[$now->month];
                            $year = $now->year + 543;
                            $hours = $now->format('H');
                            $minutes = $now->format('i');
                            echo "{$day} {$month} {$year} เวลา {$hours}:{$minutes}น.";
                        @endphp
                    </p>
                    <p class="mb-0 text-end small text-muted">เอกสารนี้จัดทำโดยระบบอิเล็กทรอนิกส์</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
