<div>
    <div class="row">

        <!-- Content Column -->
        <div class="col-lg-12 mb-4">

            <!-- Project Card Example -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">ข้อมูลส่วนตัว</h6>
                    <div class="btn-group">
                        <button class="btn btn-info mr-2" wire:click="updateFromThaID" wire:loading.attr="disabled" wire:target="updateFromThaID">
                            <i class="fas fa-sync-alt mr-1"></i>
                            <span wire:loading.remove wire:target="updateFromThaID">อัพเดทข้อมูลจาก ThaID</span>
                            <span wire:loading wire:target="updateFromThaID">กำลังอัพเดท...</span>
                        </button>
                        <button class="btn btn-success mr-2" wire:click="updateFromProviderID" wire:loading.attr="disabled" wire:target="updateFromProviderID">
                            <i class="fas fa-sync-alt mr-1"></i>
                            <span wire:loading.remove wire:target="updateFromProviderID">อัพเดทข้อมูลจาก Provider ID</span>
                            <span wire:loading wire:target="updateFromProviderID">กำลังอัพเดท...</span>
                        </button>
                        <a href="{{ route('profile.edit', auth()->user()->id) }}" class="btn btn-warning" wire:navigate>แก้ไข</a>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tbody>
                          <tr>
                            <td scope="row">เลขบัตร 13 หลัก</td>
                            <td>
                                @php
                                $pid = auth()->user()->pid;
                                $formatted_pid = '';
                                
                                if (!empty($pid) && strlen($pid) == 13) {
                                    $formatted_pid = substr($pid, 0, 1) . '-' . 
                                                    substr($pid, 1, 4) . '-' . 
                                                    str_replace(substr($pid, 5, 5), 'xxxxx', substr($pid, 5, 5)) . '-' .
                                                    substr($pid, 10, 2) . '-' . 
                                                    substr($pid, 12, 1);
                                } else {
                                    $formatted_pid = $pid;
                                }
                                @endphp
                                {{ $formatted_pid }}
                            </td>
                          </tr>
                          <tr>
                            <td scope="row">ชื่อ-สกุล</td>
                            <td>{{ auth()->user()->name }}</td>
                          </tr>
                          <tr>
                            <td scope="row">ชื่อ-สกุล ภาษาอังกฤษ</td>
                            <td>{{ auth()->user()->name_en }}</td>
                          </tr>
                          <tr>
                            <td scope="row">ว/ด/ป เกิด</td>
                            <td>
                                @php
                                $birthdate = auth()->user()->birthdate;
                                $formatted_date = '';
                                
                                if (!empty($birthdate)) {
                                    $date = \Carbon\Carbon::parse($birthdate);
                                    $thai_months = [
                                        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 
                                        4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 
                                        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 
                                        10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                                    ];
                                    
                                    $day = $date->day;
                                    $month = $thai_months[$date->month];
                                    $year = $date->year + 543; // Convert to Buddhist Era
                                    
                                    $formatted_date = "{$day} {$month} {$year}";
                                }
                                @endphp
                                {{ $formatted_date }}
                            </td>
                          </tr>
                          <tr>
                            <td scope="row">ที่อยู่ตามบัตรประชาชน</td>
                            <td>{{ auth()->user()->address }}</td>
                          </tr>
                          <tr>
                            <td scope="row">อีเมลล์</td>
                            <td>{{ auth()->user()->email }}</td>
                          </tr>
                          <tr>
                            <td scope="row">เลขหน่วยบริการ</td>
                            <td>{{ auth()->user()->hcode }}</td>
                          </tr>
                          
                          
                          <tr>
                            <td scope="row">ชื่อหน่วยบริการ</td>
                            <td>{{ auth()->user()->hname }}</td>
                          </tr>
                          
                        </tbody>
                      </table>
                    
                </div>
            </div>

 

        </div>
    </div>
</div>

@script
    <script>
        $wire.on('profile-updated', (source) => {
            Swal.fire({
                position: "top-end",
                icon: "success",
                title: `อัพเดทข้อมูลจาก ${source} สำเร็จ`,
                showConfirmButton: false,
                timer: 1500
            });
        });

        $wire.on('profile-update-error', (message) => {
            Swal.fire({
                icon: "error",
                title: "เกิดข้อผิดพลาด",
                text: message,
            });
        });
    </script>
@endscript
