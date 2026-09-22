<?php

namespace App\Livewire\Provider;

use App\Services\ProviderOAuthState;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ProfileUpdate extends Component
{
    public string $redirectUrl = '';

    public function mount()
    {
        try {
            $state = ProviderOAuthState::issue(session()->driver());
            session([
                'profile_update' => true,
                'user_id_for_update' => Auth::id(),
            ]);

            $this->redirectUrl = config('services.provider.auth_url') . '?' . http_build_query([
                'response_type' => 'code',
                'client_id' => config('services.provider.healthid_client_id'),
                'redirect_uri' => route('provider.profile.callback'),
                'state' => $state,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Provider auth URL', ['error' => $e->getMessage()]);
            session()->flash('error', 'ไม่สามารถเชื่อมต่อกับระบบ Provider ID ได้: ' . $e->getMessage());
            return $this->redirect(route('profile.index'));
        }
    }

    public function render()
    {
        return view('livewire.provider.profile-update', ['redirectUrl' => $this->redirectUrl]);
    }
}
