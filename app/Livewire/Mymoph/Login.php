<?php

namespace App\Livewire\Mymoph;

use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class Login extends Component
{
    public function redirectToMyMOPH()
    {
        // Generate random state for security
        $state = Str::random(32);
        session(['mymoph_state' => $state]);

        // MyMOPH OAuth parameters
        $params = [
            'response_type' => 'code',
            'client_id' => config('services.mymoph.client_id'),
            'state' => $state,
            'redirect_uri' => config('services.mymoph.redirect_uri'),
            'scope' => 'cid fname lname email hospcode staff_type ial'
        ];

        // Log the OAuth parameters for debugging
        Log::info('MyMOPH OAuth Request Parameters:', [
            'config_app_url' => config('app.url'),
            'redirect_uri' => config('services.mymoph.redirect_uri'),
            'client_id' => config('services.mymoph.client_id'),
            'all_params' => $params
        ]);

        // Build authentication URL
        $authUrl = 'https://auth.moph.go.th/v1/oauth2/auth?' . http_build_query($params);

        // Log the complete URL
        Log::info('MyMOPH OAuth URL:', ['url' => $authUrl]);

        // Redirect to MyMOPH authentication
        return redirect()->away($authUrl);
    }

    public function render()
    {
        return view('livewire.mymoph.login');
    }
}
