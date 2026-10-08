<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

class About extends Component
{

    #[Title('เกี่ยวกับ')]    public function render()
    {
        return view('livewire.about');
    }
}
