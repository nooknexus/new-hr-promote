<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Attributes\On;

class Login extends Component
{
    #[Layout('components.layouts.guest')] 

    #[Validate('required')]
    public $email;
    #[Validate('required')]
    public $password;

    public $pid = '';  // For Provider ID registration
    public $remember = false;

    #[Title('เข้าสู่ระบบ')]
    public function render()
    {
        return view('livewire.login');
    }

    public function login()
    {

        $validated = $this->validate([
            'email' => 'required|min:6',
            'password' => 'required|min:8',
        ]);

        $credentials = [
            'email' => $this->email,
            'password' => $this->password,
            'is_active' => true,
        ];

        // dd($credentials);


        if (Auth::attempt($credentials, $this->remember)) {
            $this->dispatch('login-success');
            return $this->redirectIntended();
        }



        $this->dispatch('login-error');
        session()->flash('error', 'Invalid credentials!');
    }

    /**
     * เริ่มต้นกระบวนการเข้าสู่ระบบด้วย ThaiD
     */
    public function thaidLogin()
    {
        return redirect()->route('thaid.redirect');
    }

    /**
     * ลงทะเบียนผู้ใช้ใหม่ด้วยข้อมูล Provider ID
     */
    public function registerWithProvider()
    {
        // กำหนด context ของ modal
        session(['modal_context' => 'provider']);
        
        // ตรวจสอบข้อมูลให้ครบถ้วนก่อนดำเนินการต่อ
        $validatedData = $this->validate([
            'pid' => 'required|digits:13|unique:users,pid',
            'password' => 'required|min:6',
        ]);
        
        try {
            // ดึงข้อมูล Provider ID จาก session
            $providerData = session('provider_data');
            
            if (!$providerData) {
                $this->addError('general', 'ไม่พบข้อมูล Provider ID กรุณาเข้าสู่ระบบใหม่');
                return;
            }

            if ($this->hasDisabledIdentity($validatedData['pid'], [
                'cid_hash' => $providerData['hash_cid'] ?? null,
                'provider_id' => $providerData['provider_id'] ?? null,
            ])) {
                $this->addError('general', 'บัญชีนี้ไม่สามารถเข้าใช้งานระบบได้');
                return;
            }
            
            // Check if a user with this cid_hash already exists
            $existingUser = null;
            if (!empty($providerData['hash_cid'])) {
                $existingUser = User::where('cid_hash', $providerData['hash_cid'])->first();
            }
            
            if ($existingUser?->is_active === false) {
                $this->addError('general', 'บัญชีนี้ไม่สามารถเข้าใช้งานระบบได้');
                return;
            }

            if ($existingUser) {
                // Update existing user with Provider ID data
                Log::info('Updating existing user during Provider ID registration', ['user_id' => $existingUser->id]);
                
                $existingUser->update([
                    'name' => $providerData['name_th'] ?? $existingUser->name,
                    'name_en' => $providerData['name_eng'] ?? $existingUser->name_en,
                    'email' => $providerData['email'] ?? $existingUser->email,
                    'provider_id' => $providerData['provider_id'] ?? $existingUser->provider_id,
                    'cid_hash' => $providerData['hash_cid'] ?? $existingUser->cid_hash,
                    'hcode' => $providerData['hcode'] ?? $existingUser->hcode,
                    'hname' => $providerData['hname'] ?? $existingUser->hname,
                    'pid' => $validatedData['pid'],
                    'password' => bcrypt($validatedData['password']),
                ]);
                
                $user = $existingUser;
            } else {
                // Create new user
                $user = User::create([
                    'name' => $providerData['name_th'] ?? '',
                    'name_en' => $providerData['name_eng'] ?? '',
                    'email' => $providerData['email'] ?? '',
                    'password' => bcrypt($validatedData['password']),
                    'pid' => $validatedData['pid'],
                    'provider_id' => $providerData['provider_id'] ?? '',
                    'cid_hash' => $providerData['hash_cid'] ?? '',
                    'hcode' => $providerData['hcode'] ?? '',
                    'hname' => $providerData['hname'] ?? '',
                ]);
            }
            
            // ล็อกอินผู้ใช้
            Auth::login($user);
            
            // ล้างข้อมูล Provider ID ใน session
            $this->closeProviderModal();
            
            // ส่งข้อความสำเร็จ
            session()->flash('success', 'ลงทะเบียนและเข้าสู่ระบบสำเร็จ');
            
            // Redirect ไปยังหน้า profile
            return redirect()->route('profile.index');
        } catch (\Exception $e) {
            Log::error('Provider ID registration error', ['message' => $e->getMessage()]);
            $this->addError('general', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    /**
     * ลงทะเบียนผู้ใช้ใหม่ด้วยข้อมูล ThaID
     */
    public function registerWithThaID()
    {
        // กำหนด context ของ modal
        session(['modal_context' => 'thaid']);
        
        try {
            // เปลี่ยนการเรียกใช้ validate เพื่อเก็บผลลัพธ์ในตัวแปร
            $validatedData = $this->validate([
                'email' => 'required|email|unique:users',
                'password' => 'required|min:6',
            ]);
            
            // ดึงข้อมูล ThaID จาก session
            $thaidPid = session('thaid_pid');
            $thaidName = session('thaid_name');
            $thaidNameEn = session('thaid_name_en');
            $thaidBirthdate = session('thaid_birthdate');
            $thaidAddress = session('thaid_address');
            
            if (!$thaidPid) {
                $this->addError('general', 'ไม่พบข้อมูล ThaID กรุณาเข้าสู่ระบบใหม่');
                return;
            }

            if ($this->hasDisabledIdentity($thaidPid)) {
                $this->addError('general', 'บัญชีนี้ไม่สามารถเข้าใช้งานระบบได้');
                return;
            }
            
            // สร้างผู้ใช้ใหม่พร้อมข้อมูลจาก ThaID
            $user = User::create([
                'name' => $thaidName ?? 'ThaID User',
                'name_en' => $thaidNameEn ?? '',
                'email' => $validatedData['email'],
                'password' => bcrypt($validatedData['password']),
                'thaid_pid' => $thaidPid,
                'pid' => $thaidPid, // เพิ่มการบันทึกค่า pid ด้วย
                'birthdate' => $thaidBirthdate,
                'address' => $thaidAddress,
                'cid_hash' => $this->generateCidHash($thaidPid),
            ]);
            
            // ล็อกอินผู้ใช้
            Auth::login($user);
            
            // ล้างข้อมูล ThaID ใน session
            $this->closeThaidModal();
            
            // ส่งข้อความสำเร็จ
            $this->dispatch('thaid-login-success');
            session()->flash('success', 'ลงทะเบียนและเข้าสู่ระบบสำเร็จ');
            
            // Redirect ไปยังหน้า profile
            return redirect()->route('profile.index');
        } catch (\Exception $e) {
            // Keep the modal context in session for error display
            session(['modal_context' => 'thaid']);
            Log::error('ThaID registration error', ['message' => $e->getMessage()]);
            $this->addError('general', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    private function hasDisabledIdentity(string $cid, array $identifiers = []): bool
    {
        return User::query()->where('is_active', false)
            ->where(function ($query) use ($cid, $identifiers) {
                $query->where('pid', $cid)->orWhere('thaid_pid', $cid)
                    ->orWhere('cid_hash', hash('sha256', $cid));
                foreach (['cid_hash', 'provider_id'] as $field) {
                    if (filled($identifiers[$field] ?? null)) {
                        $query->orWhere($field, $identifiers[$field]);
                    }
                }
            })->exists();
    }
    
    /**
     * สร้าง CID Hash จาก PID ด้วย SHA-256
     * 
     * @param string $pid เลขประจำตัวประชาชน
     * @return string ค่า hash ที่ได้จากการเข้ารหัส
     */
    private function generateCidHash($pid)
    {
        if (empty($pid)) {
            return null;
        }
        
        // ใช้ SHA-256 เข้ารหัส PID 
        $sha256 = hash('sha256', $pid);
        
        
        // รวมกันในรูปแบบ SHA256:firstChar+lastChar
        return $sha256;
    }

    /**
     * ปิด Modal ลงทะเบียน Provider ID
     */
    #[On('closeProviderModal')]
    public function closeProviderModal()
    {
        Session::forget(['provider_data', 'provider_error', 'modal_context']);
    }
    
    /**
     * ปิด Modal ลงทะเบียน ThaID
     */
    #[On('closeThaidModal')]
    public function closeThaidModal()
    {
        Session::forget(['thaid_pid', 'thaid_name', 'thaid_name_en', 'thaid_error', 'modal_context']);
    }
}
