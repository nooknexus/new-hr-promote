<?php

namespace App\Livewire\Profile;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

class Index extends Component
{
    #[Locked]
    public ?int $editingUserId = null;

    public string $editName = '';
    public string $editNameEn = '';
    public string $editEmail = '';
    public string $editAddress = '';

    public function boot(): void
    {
        abort_unless(Auth::check(), 403);
    }

    public function openEdit(): void
    {
        $user = Auth::user()->fresh();
        abort_unless($user, 403);
        $this->resetValidation();
        $this->editingUserId = (int) $user->id;
        $this->editName = $user->name;
        $this->editNameEn = $user->name_en ?? '';
        $this->editEmail = $user->email;
        $this->editAddress = $user->address ?? '';
        $this->dispatch('profile-edit-opened');
    }

    public function saveProfile(): void
    {
        abort_unless($this->editingUserId !== null && $this->editingUserId === (int) Auth::id(), 403);
        $user = Auth::user()->fresh();
        abort_unless($user, 403);

        foreach (['editName', 'editNameEn', 'editEmail', 'editAddress'] as $field) {
            $this->{$field} = trim($this->{$field});
        }
        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editNameEn' => ['nullable', 'string', 'max:255'],
            'editEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'editAddress' => ['nullable', 'string', 'max:5000'],
        ], [
            'editName.required' => 'กรุณาระบุชื่อ-สกุล',
            'editName.max' => 'ชื่อ-สกุลต้องไม่เกิน 255 ตัวอักษร',
            'editNameEn.max' => 'ชื่อภาษาอังกฤษต้องไม่เกิน 255 ตัวอักษร',
            'editEmail.required' => 'กรุณาระบุอีเมล',
            'editEmail.email' => 'กรุณาระบุอีเมลที่ถูกต้อง',
            'editEmail.unique' => 'อีเมลนี้มีผู้ใช้งานแล้ว',
            'editEmail.max' => 'อีเมลต้องไม่เกิน 255 ตัวอักษร',
            'editAddress.max' => 'ที่อยู่ต้องไม่เกิน 5,000 ตัวอักษร',
        ]);

        $user->fill([
            'name' => $this->editName,
            'name_en' => $this->editNameEn !== '' ? $this->editNameEn : null,
            'email' => $this->editEmail,
            'address' => $this->editAddress !== '' ? $this->editAddress : null,
        ]);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();
        Auth::setUser($user);
        $this->resetEditForm();
        $this->dispatch('profile-saved', message: 'บันทึกข้อมูลส่วนตัวแล้ว');
    }

    public function closeEdit(): void
    {
        $this->resetEditForm();
    }

    private function resetEditForm(): void
    {
        $this->reset(['editingUserId', 'editName', 'editNameEn', 'editEmail', 'editAddress']);
        $this->resetValidation();
    }

    #[Layout('components.layouts.app')]

    public function render()
    {
        return view('livewire.profile.index');
    }

    /**
     * อัพเดทข้อมูลผู้ใช้จาก ThaID API
     */
    public function updateFromThaID()
    {
        try {
            $user = Auth::user();
            
            // ตรวจสอบว่ามี thaid_pid หรือไม่
            if (empty($user->thaid_pid)) {
                $this->dispatch('profile-update-error', 'ไม่พบข้อมูล ThaID ในระบบ กรุณาเข้าสู่ระบบด้วย ThaID ก่อน');
                return;
            }
            
            // เรียกข้อมูลจาก ThaID API ด้วย thaid_pid
            // โค้ดจริงจะต้องใช้ ThaID API อย่างถูกต้อง
            // นี่เป็นแค่ตัวอย่าง
            $response = Http::withToken(session('thaid_access_token'))
                ->get(config('services.thaid.api_url') . '/userinfo');
            
            if (!$response->successful()) {
                throw new \Exception('ไม่สามารถเชื่อมต่อกับ ThaID API ได้');
            }
            
            $thaidData = $response->json();
            
            // อัพเดทข้อมูลผู้ใช้
            $user->update([
                'name' => $thaidData['name'] ?? $user->name,
                'name_en' => $thaidData['name_en'] ?? $user->name_en,
                'birthdate' => $thaidData['birthdate'] ?? $user->birthdate,
                'address' => $this->formatAddress($thaidData['address'] ?? null) ?? $user->address,
            ]);
            
            $this->dispatch('profile-updated', 'ThaID');
            
        } catch (\Exception $e) {
            Log::error('ThaID profile update error', ['message' => $e->getMessage()]);
            $this->dispatch('profile-update-error', $e->getMessage());
        }
    }
    
    /**
     * อัพเดทข้อมูลผู้ใช้จาก Provider ID API
     */
    public function updateFromProviderID()
    {
        try {
            // Use the controller-based approach that will find the correct URL
            return redirect()->route('provider.profile.update');
        } catch (\Exception $e) {
            Log::error('Provider ID update button error', ['message' => $e->getMessage()]);
            $this->dispatch('profile-update-error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    
    /**
     * แปลงข้อมูลที่อยู่เป็นข้อความ
     */
    private function formatAddress($address)
    {
        if (empty($address)) {
            return null;
        }
        
        // ถ้าเป็น JSON string ให้แปลงเป็น array
        if (is_string($address)) {
            $addressData = json_decode($address, true);
            if (isset($addressData['formatted'])) {
                return json_decode('"' . str_replace('"', '\"', $addressData['formatted']) . '"');
            }
            return $address;
        }
        
        // ถ้าเป็น array อยู่แล้ว
        if (is_array($address) && isset($address['formatted'])) {
            return json_decode('"' . str_replace('"', '\"', $address['formatted']) . '"');
        }
        
        return is_array($address) ? json_encode($address) : $address;
    }
}
