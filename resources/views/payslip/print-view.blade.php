<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบรับรองการจ่ายค่าตอบแทน - {{ $payslip['month'] }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 1cm;
        }
        
        body {
            font-family: 'THSarabunNew', 'Sarabun', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        
        .container {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            padding: 1cm;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            box-sizing: border-box;
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
            min-height: calc(297mm - 2cm - 2cm);
        }
        
        .footer {
            position: absolute;
            margin-top: 6rem!important;
            /* Changed from 1cm to 0.5cm to move it lower */
            left: 1cm;
            right: 1cm;
            z-index: 2;
            font-size: 12px;
            color: #6c757d;
        }
        
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            
            html, body {
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 0;
                background-color: white;
            }
            
            .container {
                width: 100% !important;
                height: 100% !important;
                margin: 0 auto !important;
                padding: 1cm !important;
                box-shadow: none;
                max-width: none !important;
                box-sizing: border-box !important;
                page-break-after: always;
            }
            
            .content {
                margin: 0 auto !important;
                width: 100% !important;
            }
            
            /* Ensure tables use full width */
            .table {
                width: 100% !important;
            }
            
            /* Fix Bootstrap grid for printing */
            .row {
                display: flex !important;
                width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            
            .col-6 {
                width: 50% !important;
                padding-right: 15px !important;
                padding-left: 15px !important;
                box-sizing: border-box !important;
            }
            
            .col-12 {
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
                margin-top: 6rem!important;
                bottom: 0.5cm !important;
                left: 1cm !important;
                right: 1cm !important;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">พิมพ์เอกสาร</button>
    
    <div class="container">
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
            <div class="row mt-5">
                <div class="col-12">
                    <!-- Signature Block - Right Aligned -->
                    <div class="text-end" style="padding-right: 1cm;">
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
