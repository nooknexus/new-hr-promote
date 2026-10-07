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
                        <button type="button" wire:click="openEdit" wire:loading.attr="disabled" wire:target="openEdit" class="btn btn-warning">แก้ไข</button>
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
    <div class="modal" id="profileEditModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="profile-edit-title" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="profile-edit-title" class="text-lg font-bold text-slate-900">แก้ไขข้อมูลส่วนตัว</h2>
                    <button type="button" wire:click="closeEdit" data-modal-close aria-label="ปิดหน้าต่าง"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <form wire:submit="saveProfile">
                    <div class="modal-body space-y-4">
                        @php($profileInputClass = 'mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100')
                        <div>
                            <label for="profile-edit-name" class="block text-sm font-semibold text-slate-700">ชื่อ-สกุล <span class="text-rose-600">*</span></label>
                            <input id="profile-edit-name" type="text" wire:model="editName" autocomplete="name" maxlength="255" required class="{{ $profileInputClass }}">
                            @error('editName')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="profile-edit-name-en" class="block text-sm font-semibold text-slate-700">ชื่อ-สกุล ภาษาอังกฤษ</label>
                            <input id="profile-edit-name-en" type="text" wire:model="editNameEn" maxlength="255" class="{{ $profileInputClass }}">
                            @error('editNameEn')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="profile-edit-email" class="block text-sm font-semibold text-slate-700">อีเมล <span class="text-rose-600">*</span></label>
                            <input id="profile-edit-email" type="email" wire:model="editEmail" autocomplete="email" maxlength="255" required class="{{ $profileInputClass }}">
                            @error('editEmail')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="profile-edit-address" class="block text-sm font-semibold text-slate-700">ที่อยู่ตามบัตรประชาชน</label>
                            <textarea id="profile-edit-address" wire:model="editAddress" autocomplete="street-address" rows="3" maxlength="5000" class="{{ $profileInputClass }}"></textarea>
                            @error('editAddress')<p role="alert" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="closeEdit" data-modal-close class="btn btn-secondary">ยกเลิก</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveProfile" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@script
    <script>
        $wire.on('profile-edit-opened', () => window.portalModal.show('profileEditModal'));
        $wire.on('profile-saved', ({ message }) => {
            window.portalModal.hide(document.getElementById('profileEditModal'));
            window.portalToast.show(message);
        });
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
