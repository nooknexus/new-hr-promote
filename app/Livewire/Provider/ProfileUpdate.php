<?php

namespace App\Livewire\Provider;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ProfileUpdate extends Component
{
    public function mount()
    {
        try {
            // Force a direct JavaScript redirect instead of using Livewire
            return response()->view('livewire.provider.profile-update', [
                'redirectUrl' => 'https://moph.id.th/oauth2/auth?' . http_build_query([
                    'response_type' => 'code',
                    'client_id' => '01951dca-8519-7403-8e49-9c0ff7e570e3',
                    'redirect_uri' => route('provider.profile.callback')
                ])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Provider auth URL', ['error' => $e->getMessage()]);
            session()->flash('error', 'ไม่สามารถเชื่อมต่อกับระบบ Provider ID ได้: ' . $e->getMessage());
            return $this->redirect(route('profile.index'));
        }
    }

    public function render()
    {
        return view('livewire.provider.profile-update');
    }
}
