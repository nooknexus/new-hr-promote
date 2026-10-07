<div>
    <main class="relative isolate flex min-h-screen w-full items-center justify-center overflow-hidden bg-slate-50 px-4 py-6 sm:px-8 sm:py-10 lg:px-12">
        <div aria-hidden="true" class="pointer-events-none absolute -left-32 -top-32 size-96 rounded-full bg-emerald-100/70 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 -right-24 size-[30rem] rounded-full bg-teal-100/70 blur-3xl"></div>

        <section class="relative grid w-full max-w-7xl overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_24px_80px_-28px_rgba(15,23,42,0.28)] lg:min-h-[720px] lg:grid-cols-[0.92fr_1.08fr] xl:min-h-[780px]">
            <aside class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-emerald-950 via-brand-700 to-emerald-700 p-10 text-white sm:p-12 lg:flex xl:p-16">
                <div aria-hidden="true" class="absolute -right-24 -top-20 size-80 rounded-full border border-white/10 bg-white/[0.04]"></div>
                <div aria-hidden="true" class="absolute -bottom-40 -left-20 size-96 rounded-full border border-white/10 bg-emerald-300/[0.07]"></div>
                <div class="relative z-10 flex items-center gap-3">
                    <img src="{{ asset('img/logoMOPH.png') }}" alt="ตรากระทรวงสาธารณสุข" class="size-14 rounded-2xl bg-white p-1.5 shadow-lg shadow-emerald-950/20">
                    <div>
                        <span class="block text-xl font-bold tracking-wide">MyPPH</span>
                        <span class="block text-xs font-medium tracking-[0.16em] text-emerald-100/80">PERSONNEL PORTAL</span>
                    </div>
                </div>

                <div class="relative z-10 my-12">
                    <p class="mb-4 text-sm font-semibold tracking-[0.2em] text-emerald-200">ระบบข้อมูลบุคลากร</p>
                    <h2 class="max-w-xl text-4xl font-bold leading-tight tracking-tight xl:text-5xl">ทุกเรื่องงานบุคลากร<br>เข้าถึงได้ในที่เดียว</h2>
                    <p class="mt-6 max-w-lg text-base leading-8 text-emerald-50/85">บริการข้อมูลสำหรับบุคลากรสาธารณสุข ทั้งข้อมูลเงินเดือน เอกสารภาษี และประวัติการลา ผ่านการยืนยันตัวตนที่ปลอดภัย</p>
                </div>

                <div class="relative z-10 rounded-3xl border border-white/15 bg-white/[0.08] p-5 backdrop-blur-sm xl:p-6">
                    <div class="flex items-center gap-4">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-xl" aria-hidden="true"><i class="fas fa-shield-alt"></i></div>
                        <div>
                            <p class="font-semibold">ปลอดภัยและเป็นส่วนตัว</p>
                            <p class="mt-1 text-sm leading-6 text-emerald-50/80">เข้าสู่ระบบด้วยผู้ให้บริการยืนยันตัวตนที่หน่วยงานรองรับ</p>
                        </div>
                    </div>
                    <img src="{{ asset('img/head_logo.png') }}" alt="" class="pointer-events-none absolute bottom-3 right-5 hidden h-12 max-w-[38%] object-contain opacity-20 xl:block">
                </div>
            </aside>

            <section class="flex flex-col justify-center px-6 py-8 sm:px-12 sm:py-12 lg:px-14 xl:px-20">
                <div class="mx-auto w-full max-w-lg">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <img src="{{ asset('img/logoMOPH.png') }}" alt="ตรากระทรวงสาธารณสุข" class="size-12 rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm">
                        <div>
                            <span class="block text-lg font-bold text-brand-700">MyPPH</span>
                            <span class="block text-xs text-slate-500">ระบบข้อมูลบุคลากร</span>
                        </div>
                    </div>

                    <div class="mb-8">
                        <p class="text-xs font-bold tracking-[0.18em] text-brand-600">MY PPH PORTAL</p>
                        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">เข้าสู่ระบบ / ลงทะเบียน</h1>
                        <p class="mt-2 text-sm leading-6 text-slate-500">เลือกช่องทางเพื่อยืนยันตัวตนและเข้าใช้งานระบบ</p>
                    </div>
                                    
                @if(session('error'))
                    <div class="alert alert-danger mb-4">{{ session('error') }}</div>
                @endif

                @if(session('success'))
                    <div class="alert alert-success mb-4">{{ session('success') }}</div>
                @endif
                                    
                <div class="space-y-3">
                    <livewire:provider.redirect />
                    <div class="flex items-center gap-3 py-1 text-xs font-medium text-slate-400" aria-hidden="true"><span class="h-px flex-1 bg-slate-200"></span><span>หรือเข้าสู่ระบบด้วย</span><span class="h-px flex-1 bg-slate-200"></span></div>
                    <livewire:thaid.login />
                    <livewire:mymoph.login />
                                        
                    <div class="mt-7 rounded-2xl border border-emerald-100 bg-emerald-50/70 p-5 sm:p-6">
                        <h2 class="text-sm font-bold text-brand-700"><i class="fas fa-info-circle me-2"></i>วิธีเข้าสู่ระบบด้วย Single Sign-on</h2>
                        <ol class="mt-3 list-decimal space-y-2 ps-5 text-sm leading-6 text-slate-600 marker:font-semibold marker:text-brand-600">
                            <li>เลือกช่องทางเข้าสู่ระบบที่ต้องการ</li>
                            <li>สแกน QR Code ด้วยแอปพลิเคชันบนมือถือ</li>
                            <li>ยืนยันตัวตน และรอระบบนำคุณเข้าสู่ระบบ</li>
                        </ol>
                        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 border-t border-emerald-100 pt-4 text-sm font-semibold text-brand-700">
                            <a href="{{ route('provider.info') }}">ProviderID คืออะไร?</a>
                            <a href="https://www.bora.dopa.go.th/app-thaid/">ThaiD คืออะไร?</a>
                            <a href="https://mymoph.moph.go.th/th/main">MyMOPH คืออะไร?</a>
                        </div>
                    </div>
                </div>
                <p class="mt-8 text-center text-xs text-slate-400">ระบบสารสนเทศบุคลากรสาธารณสุข</p>
                </div>
            </section>
        </section>
    </main>

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
