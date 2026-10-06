<?php

namespace App\Livewire\Provider;

use App\Services\ProviderOAuthState;
use Livewire\Component;

class Redirect extends Component
{
    public $url = '';

    public function mount()
    {
        $state = ProviderOAuthState::issue(session()->driver());
        $this->url = config('services.provider.auth_url').'?'.http_build_query([
            'client_id' => config('services.provider.healthid_client_id'),
            'redirect_uri' => route('provider.callback'),
            'response_type' => 'code',
            'state' => $state,
            'is_auth' => 'yes',
        ]);
    }

    public function redirectToProvider()
    {
        return $this->redirect($this->url);
    }

    public function render()
    {
        return view('livewire.provider.redirect');
    }
}
