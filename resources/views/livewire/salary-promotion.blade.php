<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold text-brand-700">ข้อมูลส่วนบุคคล</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">การเลื่อนขั้นเงินเดือน</h1>
        <p class="mt-2 text-sm text-slate-500">เลือกปีงบประมาณเพื่อดูผลการเลื่อนขั้นเงินเดือนและดาวน์โหลดหนังสือแจ้งผล</p>
    </header>

    <div class="row">
        <!-- Fiscal Year Selection Column -->
        <div class="col-lg-3 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        เลือกปีงบประมาณ
                    </h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        @foreach($years as $year)
                        <button type="button"
                            class="list-group-item list-group-item-action {{ $selectedYear == $year ? 'active' : '' }}"
                            wire:click="selectYear({{ $year }})">
                            ปี{{ $year }}
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Promotion History Display Column -->
        <div class="col-lg-9 mb-4">
            <!-- Promotion Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 font-weight-bold text-primary">ข้อมูลการเลื่อนขั้นเงินเดือน (ปีงบประมาณ {{ $selectedYear }})</h6>
                    </div>
                </div>
                <div class="card-body">
                    @if(count($promotions) > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>รอบการเลื่อนขั้น</th>
                                    <th>ครั้งที่</th>
                                    <th>ร้อยละ</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($promotions as $promotion)
                                <tr>
                                    <td>{{ $promotion['round'] }}</td>
                                    <td class="text-center">
                                        @if(strpos($promotion['round_type'], 'พิเศษ') === false)
                                            <span class="badge 
                                                @if(strpos($promotion['round_type'], 'รอบ1') !== false)
                                                    badge-primary
                                                @elseif(strpos($promotion['round_type'], 'รอบ2') !== false)
                                                    badge-success
                                                @else
                                                    badge-warning
                                                @endif">
                                                ครั้งที่ {{ $promotion['round_number'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if(strpos($promotion['round_type'], 'พิเศษ') === false && is_numeric($promotion['increase_percent']))
                                            {{ $promotion['increase_percent'] }}%
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="viewPromotionDetail('{{ $promotion['id'] }}')">
                                                <i class="fas fa-eye" aria-hidden="true"></i> ดูรายละเอียด
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="downloadPromotion('{{ $promotion['id'] }}')">
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
                        <i class="fas fa-info-circle mr-1"></i> ไม่พบข้อมูลการเลื่อนขั้นเงินเดือนในปี {{ $selectedYear }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Promotion Detail Modal -->
    <div class="modal fade" id="promotionDetailModal" tabindex="-1" role="dialog" aria-labelledby="promotionDetailModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="promotionDetailModalLabel">
                        รายละเอียดการเลื่อนขั้นเงินเดือน {{ $selectedPromotion ? $selectedPromotion['round'] : '' }}
                    </h5>
                    <button type="button" class="close" data-modal-close aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if($selectedPromotion)
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-left-primary shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">ข้อมูลเงินเดือน</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <td width="40%"><strong>เงินเดือนเดิม:</strong></td>
                                            <td class="text-right">
                                                @if(is_numeric($selectedPromotion['old_salary']))
                                                    {{ number_format($selectedPromotion['old_salary'], 2) }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td width="40%"><strong>ฐานเงินเดือน:</strong></td>
                                            <td class="text-right">
                                                @if(is_numeric($selectedPromotion['base'] ?? null))
                                                    {{ number_format($selectedPromotion['base'], 2) }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>ร้อยละ:</strong></td>
                                            <td class="text-right">
                                                @if(is_numeric($selectedPromotion['increase_percent']))
                                                    {{ $selectedPromotion['increase_percent'] }}%
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>เงินพิเศษ:</strong></td>
                                            <td class="text-right">
                                                @if(is_numeric($selectedPromotion['extra'] ?? null))
                                                    {{ number_format($selectedPromotion['extra'], 2) }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>เพิ่มขึ้น:</strong></td>
                                            <td class="text-right">
                                                @if(is_numeric($selectedPromotion['increase_amount']))
                                                    {{ number_format($selectedPromotion['increase_amount'], 2) }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <td><strong>เงินเดือนใหม่:</strong></td>
                                            <td class="text-right">
                                                @if(is_numeric($selectedPromotion['new_salary']))
                                                    {{ number_format($selectedPromotion['new_salary'], 2) }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                        
                                        
                                        
                                        <tr>
                                            <td><strong>วันที่มีผล:</strong></td>
                                            <td class="text-right">{{ $selectedPromotion['effective_date'] }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card border-left-success shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-success">รายละเอียดการประเมิน</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <td width="40%"><strong>ผลการเลื่อน:</strong></td>
                                            <td>
                                                @if(isset($selectedPromotion['promote']))
                                                    @if($selectedPromotion['promote'] == 'Y')
                                                        <span class="text-success">ได้เลื่อนเงินเดือน</span>
                                                    @elseif($selectedPromotion['promote'] == 'N')
                                                        <span class="text-danger">ไม่ได้เลื่อนเงินเดือน</span>
                                                    @else
                                                        {{ $selectedPromotion['promote'] }}
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>แผนก:</strong></td>
                                            <td>{{ $selectedPromotion['department'] ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>ส่วนราชการ:</strong></td>
                                            <td>{{ $selectedPromotion['office'] ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>หมายเหตุ:</strong></td>
                                            <td>{{ $selectedPromotion['ps'] ?? '-' }}</td>
                                        </tr>
                                    </table>
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
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfPreviewModalLabel">
                        ตัวอย่างหนังสือแจ้งผลการเลื่อนเงินเดือน {{ $selectedPromotion ? $selectedPromotion['round'] : '' }}
                    </h5>
                    <button type="button" class="close" data-modal-close aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if($selectedPromotion)
                    <!-- A4 Preview Container -->
                    <div class="d-flex justify-content-center">
                        <div class="pdf-preview" style="width: 21cm; height: 29.7cm; background: white; padding: 1cm; box-shadow: 0 0 10px rgba(0,0,0,0.1); position: relative; overflow: hidden;">
                            <!-- Watermark -->
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: url('{{ asset('img/head_logo.png') }}'); background-repeat: no-repeat; background-position: center center; background-size: 80%; opacity: 0.07; pointer-events: none; z-index: 1;"></div>
                            
                            <!-- Content Container -->
                            <div style="position: relative; z-index: 2; min-height: calc(29.7cm - 3cm);">
                                <!-- Header -->
                                <div class="text-center mb-4">
                                    
                                    <h3 class="text-danger">ลับ</h3>
                                    <img src="{{ asset('img/logoMOPH.png') }}" alt="Logo" style="width: 100px; height: auto;">
                                    <h4>หนังสือแจ้งผลการเลื่อนเงินเดือน</h4>
                                   
                                </div>

                                <!-- Content -->
                                <div class="row mb-5">
                                    <div class="col-12">
                                        <!-- Additional Information Table -->
                                        <table class="table table-borderless">
                                            <tr>
                                                <td width="40%"><strong>รอบการเลื่อนขั้น:</strong></td>
                                                <td>{{ $selectedPromotion['round'] ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>ชื่อ-สกุล:</strong></td>
                                                <td>{{ Auth::user()->name }}</td>
                                            </tr>
                                           <tr>
                                                <td><strong>ตำแหน่ง:</strong></td>
                                                <td>{{ $selectedPromotion['position'] ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>ระดับ:</strong></td>
                                                <td>{{ $selectedPromotion['level'] ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>ส่วนราชการ:</strong></td>
                                                <td>{{ $selectedPromotion['office'] ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>เงินเดือนก่อนเลื่อน:</strong></td>
                                                <td>{{ number_format($selectedPromotion['old_salary'], 2)}}</td>
                                            </tr>
                                             <tr>
                                                <td><strong>ผลการเลื่อน:</strong></td>
                                                <td>
                                                    @if(isset($selectedPromotion['promote']))
                                                        @if($selectedPromotion['promote'] == 'Y')
                                                            <span>ได้เลื่อนเงินเดือน</span>
                                                        @elseif($selectedPromotion['promote'] == 'N')
                                                            <span>ไม่ได้เลื่อนเงินเดือน {{ $selectedPromotion['ps'] ?? '-' }}</span>
                                                        @else
                                                            {{ $selectedPromotion['promote'] }}
                                                        @endif
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            
                                        </table>

                                        
                                        <!-- Columnar Table for Financial Info -->
                                        <div class="mb-4">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center">ฐานในการคำนวณ</th>
                                                        <th class="text-center">ร้อยละที่ได้เลื่อน</th>
                                                        <th class="text-center">เงินเดือนที่ได้เลื่อน</th>
                                                        <th class="text-center">ค่าตอบแทนพิเศษ</th>
                                                        <th class="text-center">เงินเดือนที่ได้รับ</th>
                                                        
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="text-center">
                                                            @if(is_numeric($selectedPromotion['base'] ?? null))
                                                                {{ number_format($selectedPromotion['base'], 2) }}
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            @if(is_numeric($selectedPromotion['increase_percent']))
                                                                {{ number_format($selectedPromotion['increase_percent'], 2) }}
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            @if(is_numeric($selectedPromotion['increase_amount']))
                                                                {{ number_format($selectedPromotion['increase_amount'], 2) }}
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            @if(is_numeric($selectedPromotion['extra'] ?? null))
                                                                {{ number_format($selectedPromotion['extra'], 2) }}
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        
                                                        <td class="text-center">
                                                            @if(is_numeric($selectedPromotion['new_salary']))
                                                                {{ number_format($selectedPromotion['new_salary'], 2) }}
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        
                                                        
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        
                                    </div>
                                    
                                    
                                </div>

                                <!-- Footer - Positioned at Bottom of A4 Container -->
                                <div style="position: absolute; bottom: 0.5cm; left: 1cm; right: 1cm; border-top: 1px solid #ddd; padding-top: 0.3cm; z-index: 100;">
                                     <p class="mb-0 text-left small text-muted">ข้อมูล ณ: 
                                         <!-- Debug info to check update_date -->
                                         @if(isset($selectedPromotion['update_date']))
                                             
                                             @php
                                                 try {
                                                     $updateDate = Carbon\Carbon::parse($selectedPromotion['update_date']);
                                                     $thaiMonths = [
                                                         'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
                                                         'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
                                                     ];
                                                     $day = $updateDate->day;
                                                     $month = $thaiMonths[$updateDate->month - 1];
                                                     $year = $updateDate->year + 543; // Convert to Buddhist Era
                                                     $hours = $updateDate->format('H');
                                                     $minutes = $updateDate->format('i');
                                                     $formattedDate = "{$day} {$month} {$year} เวลา {$hours}:{$minutes}น.";
                                                     echo "{$formattedDate}";
                                                 } catch(\Exception $e) {
                                                     echo " [Error: {$e->getMessage()}]";
                                                 }
                                             @endphp
                                         @else
                                             -
                                         @endif
                                     </p>
                                     <div class="d-flex justify-content-between">
                                        <p class="mb-0 text-left small text-muted">วันที่พิมพ์: <span id="pdf-print-timestamp"></span></p>
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
                    @if($selectedPromotion)
                        <a href="{{ route('profile.salary-promotion.download', ['promotion' => $selectedPromotion['id']]) }}" class="btn btn-success" id="download-pdf-btn">
                            <i class="fas fa-file-pdf mr-1"></i> ดาวน์โหลด PDF
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    $wire.on('show-promotion-detail', () => {
        window.portalModal.show('promotionDetailModal');
    });

    $wire.on('show-pdf-preview', () => {
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
            }
        } catch (e) {
            console.error('Error updating timestamp:', e);
        }
    }
</script>
@endscript
