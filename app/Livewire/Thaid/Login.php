<?php

namespace App\Livewire\Thaid;

use Livewire\Component;

class Login extends Component
{
    public function render()
    {
        return view('livewire.thaid.login');
    }

    /**
     * Redirect to ThaID authentication
     */
    public function redirectToThaid()
    {
        return redirect()->route('thaid.redirect');
    }
}
