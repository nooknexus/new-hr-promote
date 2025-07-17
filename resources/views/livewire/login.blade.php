<div>
    <div class="container">

        <!-- Outer Row -->
        <div class="row justify-content-center">

            <div class="col-xl-10 col-lg-12 col-md-9">

                <div class="card o-hidden border-0 shadow-lg my-5">
                    <div class="card-body p-0">
                        <!-- Nested Row within Card Body -->
                        <div class="row">
                            <div class="col-lg-6 d-none d-lg-block bg-login-image" style="display: flex !important; align-items: center; justify-content: center;">
                                <img src="{{ asset('img/head_logo.png') }}" alt="Login Background" class="img-fluid" style="max-width: 80%; max-height: 80%;">
                            </div>
                            <div class="col-lg-6">
                                <div class="p-5">
                                    <div class="text-center">
                                        <h1 class="h4 text-gray-900 mb-4">เข้าสู่ระบบ/ลงทะเบียน</h1>
                                    </div>
                                    
                                    <!-- Display error messages -->
                                    @if(session('error'))
                                    <div class="alert alert-danger mb-4">
                                        {{ session('error') }}
                                    </div>
                                    @endif

                                    <!-- Display success messages -->
                                    @if(session('success'))
                                    <div class="alert alert-success mb-4">
                                        {{ session('success') }}
                                    </div>
                                    @endif
                                    
                                    <!-- ThaID Login Section -->
                                    <div class="mt-4">
                                        <div class="text-center mb-3">
                                            <livewire:provider.redirect />
                                        </div>
                                         <div class="text-center mb-3">
                                             <p style="color:black;">หรือ</p>
                                        </div>
                                        <div class="text-center mb-3">
                                             <livewire:thaid.login />
                                        </div>
                                       <div class="text-center mb-3">
                                             <livewire:mymoph.login />
                                        </div>
                                        
                                        <!-- ThaID Instructions -->
                                        <div class="mt-4 p-4 rounded-lg shadow-sm" style="background: linear-gradient(to bottom right, #f8f9fa, #e9ecef); border-left: 4px solid #01797E;">
                                            <h6 class="text-center mb-3 fw-bold" style="color: #01797E;">
                                                <i class="fas fa-info-circle me-2"></i>วิธีเข้าสู่ระบบด้วย Single Sign-on
                                            </h6>
                                            <div class="px-2">
                                                <ol class="text-gray-800 mb-3" style="padding-left: 1.5rem;">
                                                    <li class="mb-2">คลิกปุ่ม <span class="badge bg-primary" style="color:white;">เข้าสู่ระบบ</span> ที่ต้องการ</li>
                                                    <li class="mb-2">สแกน <span class="badge bg-secondary" style="color:white;">QR Code</span> ด้วยแอพพลิเคชั่นบนมือถือของคุณ</li>
                                                    <li class="mb-2">ยืนยันตัวตนในแอพพลิเคชั่น</li>
                                                    <li class="mb-2">รอระบบตรวจสอบและนำคุณเข้าสู่ระบบโดยอัตโนมัติ</li>
                                                </ol>
                                                <p class="text-gray-600 small fst-italic mb-2 border-top pt-2">หากคุณยังไม่มีบัญชีผู้ใช้ กรุณาลงทะเบียนก่อน</p>
                                                <div class="d-flex flex-column mt-3">
                                                    <a href="{{ route('provider.info') }}" class="text-decoration-none mb-2 d-flex align-items-center" style="color: #01797E;">
                                                        <i class="fas fa-question-circle me-2"></i>ProviderID คืออะไร ?
                                                    </a>
                                                    <a href="https://www.bora.dopa.go.th/app-thaid/" class="text-decoration-none mb-2 d-flex align-items-center" style="color: #01797E;">
                                                        <i class="fas fa-question-circle me-2"></i>ThaiD คืออะไร ?
                                                    </a>
                                                    <a href="https://mymoph.moph.go.th/th/main" class="text-decoration-none mb-2 d-flex align-items-center" style="color: #01797E;">
                                                        <i class="fas fa-question-circle me-2"></i>MyMOPH คืออะไร ?
                                                    </a>
                                                    
                                                    
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    @script
        <script>
            $wire.on('login-success', () => {
                Swal.fire({
                    position: "top-end",
                    icon: "success",
                    title: "เข้าสู่ระบบสำเร็จ",
                    showConfirmButton: false,
                    timer: 1500
                });
            });
            
            $wire.on('thaid-login-success', () => {
                Swal.fire({
                    position: "top-end",
                    icon: "success",
                    title: "เข้าสู่ระบบด้วย ThaID สำเร็จ",
                    showConfirmButton: false,
                    timer: 1500
                });
            });
            
            // เพิ่มโค้ดนี้เพื่อป้องกัน modal ปิดเอง
            document.addEventListener('DOMContentLoaded', function() {
                const providerModal = document.getElementById('providerRegistrationModal');
                const thaidModal = document.getElementById('thaidRegistrationModal');
                
                if (providerModal) {
                    providerModal.addEventListener('click', function(e) {
                        if (e.target === providerModal) {
                            // ป้องกันการคลิกพื้นหลัง modal แล้วปิด
                            e.preventDefault();
                            e.stopPropagation();
                            return false;
                        }
                    });
                }
                
                if (thaidModal) {
                    thaidModal.addEventListener('click', function(e) {
                        if (e.target === thaidModal) {
                            // ป้องกันการคลิกพื้นหลัง modal แล้วปิด
                            e.preventDefault();
                            e.stopPropagation();
                            return false;
                        }
                    });
                }
            });
        </script>
    @endscript

    <!-- ThaID Registration Modal -->
    @if(session('thaid_error') || (session('modal_context') === 'thaid' && $errors->any()))
    <div class="modal fade show" id="thaidRegistrationModal" tabindex="-1" role="dialog" aria-labelledby="thaidRegistrationModalLabel" style="display: block; background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="thaidRegistrationModalLabel">ไม่พบบัญชีผู้ใช้</h5>
                    <button type="button" class="close" onclick="if(confirm('คุณต้องการยกเลิกการลงทะเบียนหรือไม่?')) Livewire.dispatch('closeThaidModal')">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>ไม่พบบัญชีผู้ใช้ที่เชื่อมโยงกับบัญชี ThaID ของคุณ กรุณาลงทะเบียนบัญชีใหม่</p>
                    
                    @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    
                    <div>
                        <div class="form-group">
                            <label>หมายเลขประจำตัวประชาชน (ThaID)</label>
                            <input type="text" class="form-control" value="{{ session('thaid_pid') }}" readonly>
                        </div>
                        <div class="form-group">
                            <label>ชื่อ-นามสกุล (ThaID)</label>
                            <input type="text" class="form-control" value="{{ session('thaid_name') }}" readonly>
                        </div>
                        <div class="form-group">
                            <label>ชื่อภาษาอังกฤษ (ThaID)</label>
                            <input type="text" class="form-control" value="{{ session('thaid_name_en') }}" readonly>
                        </div>
                        <div class="form-group">
                            <label>วันเกิด (ThaID)</label>
                            <input type="text" class="form-control" value="{{ session('thaid_birthdate') }}" readonly>
                        </div>
                        <div class="form-group">
                            <label>ที่อยู่ (ThaID)</label>
                            <textarea class="form-control" readonly>{{ session('thaid_address') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label>อีเมล <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" wire:model.defer="email" required>
                            @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label>รหัสผ่าน <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" wire:model.defer="password" required>
                            @error('password') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="if(confirm('คุณต้องการยกเลิกการลงทะเบียนหรือไม่?')) Livewire.dispatch('closeThaidModal')">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" wire:click="registerWithThaID" wire:loading.attr="disabled">
                        <span wire:loading.remove>ลงทะเบียน</span>
                        <span wire:loading>กำลังดำเนินการ...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Provider ID Registration Modal -->
    @if(session('provider_error') || (session('modal_context') === 'provider' && $errors->any()))
    <div class="modal fade show" id="providerRegistrationModal" tabindex="-1" role="dialog" aria-labelledby="providerRegistrationModalLabel" style="display: block; background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="providerRegistrationModalLabel">ไม่พบบัญชีผู้ใช้</h5>
                    <button type="button" class="close" onclick="if(confirm('คุณต้องการยกเลิกการลงทะเบียนหรือไม่?')) Livewire.dispatch('closeProviderModal')">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>ไม่พบบัญชีผู้ใช้ที่เชื่อมโยงกับบัญชี Provider ID ของคุณ กรุณาลงทะเบียนบัญชีใหม่</p>
                    
                    @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    
                    <div>
                        <div class="form-group">
                            <label>ชื่อ-นามสกุล</label>
                            <input type="text" class="form-control" value="{{ session('provider_data.name_th') }}" readonly>
                        </div>
                        <div class="form-group">
                            <label>ชื่อภาษาอังกฤษ</label>
                            <input type="text" class="form-control" value="{{ session('provider_data.name_eng') }}" readonly>
                        </div>
                        <div class="form-group">
                            <label>อีเมล</label>
                            <input type="text" class="form-control" value="{{ session('provider_data.email') }}" readonly>
                        </div>
                        <div class="form-group">
                            <label>รหัสหน่วยงาน</label>
                            <input type="text" class="form-control" value="{{ session('provider_data.hcode') }}" readonly>
                        </div>
                        <div class="form-group">
                            <label>ชื่อหน่วยงาน</label>
                            <input type="text" class="form-control" value="{{ session('provider_data.hname') }}" readonly>
                        </div>
                        <div class="form-group">
                            <label>เลขบัตรประจำตัวประชาชน 13 หลัก <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" wire:model.defer="pid" required maxlength="13" inputmode="numeric" onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                            @error('pid') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label>รหัสผ่าน <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" wire:model.defer="password" required>
                            @error('password') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="if(confirm('คุณต้องการยกเลิกการลงทะเบียนหรือไม่?')) Livewire.dispatch('closeProviderModal')">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" wire:click="registerWithProvider" wire:loading.attr="disabled">
                        <span wire:loading.remove>ลงทะเบียน</span>
                        <span wire:loading>กำลังดำเนินการ...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
