<?php

namespace App\Livewire\Profile;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Edit extends Component
{
    public function mount(int $id): void
    {
        abort_unless(Auth::check() && (int) Auth::id() === $id, 403);
        $this->redirectRoute('profile.index');
    }

    public function render()
    {
        return view('livewire.profile.edit');
    }
}
