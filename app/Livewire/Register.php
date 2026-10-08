<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;

class Register extends Component
{
    #[Layout('components.layouts.guest')] 
    #[Validate('required', message: 'กรุณาระบุชื่อ')]
    #[Validate('min:5', message: 'ชื่อ-นามสกุลสั้นเกินไป')]
    public $name;

    // #[Validate('required|email|max:250|unique:users,email')]
    #[Validate('required', message: 'กรุณาระบุเลขบัตรประชาน')]
    #[Validate('email', message: 'รูปแบบ Email ไม่ถูกต้อง')]
    #[Validate('unique:users,email', message: 'Email นี้ถูกใช้ไปแล้ว')]
    public $email;

    #[Validate('required', message: 'กรุณาระบุรหัสผ่าน')]
    #[Validate('min:8', message: 'รหัสผ่านสั้นเกินไป')]
    #[Validate('confirmed', message: 'รหัสผ่านไม่ตรงกัน')]
    public $password;

    public $password_confirmation;

    #[Title('ลงทะเบียน')]

    public function render()
    {
        return view('livewire.register');
    }

    public function submit()
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => Str::lower($this->email),
            'password' => Hash::make($this->password),
        ]);

        $credentials = [
            //'name' => $this->name,
            'email' => Str::lower($this->email),
            'password' => Hash::make($this->password),
        ];

        //dd($credentials, $V_Person);
        $this->dispatch('register-success');
        Auth::attempt($credentials);

        //session()->flash('message', 'You have successfully registered & logged in!');

        return $this->redirectRoute('home', navigate: true);
    }

}
