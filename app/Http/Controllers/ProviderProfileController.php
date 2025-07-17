<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ProviderProfileController extends Controller
{
    public function redirect()
    {
        // Store user information for later
        Session::put('profile_update', true);
        Session::put('user_id_for_update', Auth::id());
        
        // Log all configurations for debugging
        Log::info('Provider Profile Update Debug', [
            'available_routes' => [
                'provider.callback' => route('provider.callback'),
                'provider.profile.callback' => route('provider.profile.callback'),
            ]
        ]);
        
        // Test URLs to find which one works
        $urls = [
            'a' => 'https://moph.id.th/oauth2/auth?' . http_build_query([
                'response_type' => 'code',
                'client_id' => '01951dca-8519-7403-8e49-9c0ff7e570e3',
                'redirect_uri' => route('provider.callback')
            ]),
            'b' => 'https://moph.id.th/api/v1/oauth2/authorize?' . http_build_query([
                'response_type' => 'code',
                'client_id' => '01951dca-8519-7403-8e49-9c0ff7e570e3',
                'redirect_uri' => route('provider.callback')
            ]),
            'c' => 'https://moph.id.th/oauth2/authorize?' . http_build_query([
                'response_type' => 'code',
                'client_id' => '01951dca-8519-7403-8e49-9c0ff7e570e3',
                'redirect_uri' => route('provider.callback')
            ]),
        ];
        
        // Log all URLs we're testing
        Log::info('Testing Provider Auth URLs', $urls);
        
        // Important: Use the callback route that's already working for login
        // This is based on the fact that the profile update isn't working yet
        return redirect($urls['a']);
    }
}
