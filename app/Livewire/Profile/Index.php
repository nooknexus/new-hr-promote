<?php

namespace App\Livewire\Profile;

use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
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
    #[Title('ข้อมูลส่วนตัว')]
    public function render()
    {
        return view('livewire.profile.index');
    }

}
