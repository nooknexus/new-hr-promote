<?php

namespace App\Livewire\Provider;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Callback extends Component
{
    public function mount()
    {
        try {
            // Get credentials from config with fallbacks to original hardcoded values
            $clientId = config('services.provider.client_id') ?? '01951dca-8519-7403-8e49-9c0ff7e570e3';
            $clientSecret = config('services.provider.client_secret') ?? '338f14b67b6bbb3f2343739de69db648861edfe1';
            $providerClientId = config('services.provider.provider_client_id') ?? '606dbdd4-1ee0-4977-bcf5-ce4ce0797f78';
            $secretKey = config('services.provider.secret_key') ?? 'wJJ2EIbFlNIfnphZTSGcEmnab5WtfkWU';
            
            // Log configuration values for debugging
            Log::info('Provider ID authentication attempt', [
                'client_id_from_config' => config('services.provider.client_id'),
                'using_client_id' => $clientId,
                'redirect_uri' => route('provider.callback')
            ]);
            
            // Health ID token request
            $response = Http::asForm()->post('https://moph.id.th/api/v1/token', [
                'grant_type' => 'authorization_code',
                'code' => request()->code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => route('provider.callback'),
            ]);
            
            if (!$response->successful() || !isset($response->json()['data']['access_token'])) {
                throw new \Exception('Failed to get access token from Health ID. Response: ' . $response->body());
            }
            
            // Provider ID token request
            $provider = Http::post('https://provider.id.th/api/v1/services/token', [
                'client_id' => $providerClientId,
                'secret_key' => $secretKey,
                'token_by' => "Health ID",
                'token' => $response->json()['data']['access_token'],
            ]);
            
            if (!$provider->successful() || !isset($provider->json()['data']['access_token'])) {
                throw new \Exception('Failed to get access token from Provider ID. Response: ' . $provider->body());
            }
            
            // Get provider profile
            $profile = Http::withHeaders([
                'Content-type' => 'application/json',
                'Authorization' => 'Bearer ' . $provider->json()['data']['access_token'],
                'client-id' => '606dbdd4-1ee0-4977-bcf5-ce4ce0797f78',
                'secret-key' => 'wJJ2EIbFlNIfnphZTSGcEmnab5WtfkWU',
            ])->get('https://provider.id.th/api/v1/services/profile');

            // Get the hash_cid from provider response
            $hash_cid = $profile->json()['data']['hash_cid'] ?? null;
            
            if (empty($hash_cid)) {
                throw new \Exception('Missing hash_cid from Provider ID');
            }
            
            // First, check if user exists by cid_hash (highest priority for linking accounts)
            $user = User::where('cid_hash', $hash_cid)->first();
            
            // If no user found by cid_hash, check other identifiers
            if (!$user) {
                $user = User::where('provider_id', $profile->json()['data']['provider_id'])
                    ->orWhere('account_id', $profile->json()['data']['account_id'])
                    ->first();
            }

            if ($user) {
                // User exists, update their info
                Log::info('Updating existing user with Provider ID data', ['user_id' => $user->id]);
                
                // Create update data array
                $updateData = [
                    'name_en' => $profile->json()['data']['name_eng'] ?? $user->name_en,
                    'email' => $profile->json()['data']['email'] ?? $user->email,
                    'cid_hash' => $hash_cid, // Ensure cid_hash is updated
                    'account_id' => $profile->json()['data']['account_id'] ?? $user->account_id,
                    'provider_id' => $profile->json()['data']['provider_id'] ?? $user->provider_id,
                    'hcode' => $profile->json()['data']['organization'][0]['hcode'] ?? $user->hcode,
                    'hname' => $profile->json()['data']['organization'][0]['hname_th'] ?? $user->hname,
                ];
                
                // Only update name if title_th is available
                $titleTh = $profile->json()['data']['title_th'] ?? null;
                if (!empty($titleTh)) {
                    $nameTh = $profile->json()['data']['name_th'] ?? '';
                    $updateData['name'] = $titleTh . ' ' . $nameTh;
                }
                
                $user->update($updateData);

                // Login the user
                Auth::login($user);
                
                // Add login success message to display SweetAlert
                session()->flash('login_success', 'ยินดีต้อนรับ ' . $user->name . ' เข้าสู่ระบบ');
                
                // Also dispatch a Livewire event for the alert
                $this->dispatch('login-success', 'ยินดีต้อนรับ ' . $user->name . ' เข้าสู่ระบบ');
                
                // Redirect to home page
                return $this->redirectRoute('home', navigate: true);
            } else {
                // User doesn't exist, store data in session for registration
                $titleTh = $profile->json()['data']['title_th'] ?? null;
                $nameTh = $profile->json()['data']['name_th'] ?? '';
                
                Session::put('provider_data', [
                    'account_id' => $profile->json()['data']['account_id'],
                    'hash_cid' => $hash_cid,
                    'provider_id' => $profile->json()['data']['provider_id'],
                    'name_th' => !empty($titleTh) ? $titleTh . ' ' . $nameTh : $nameTh,
                    'name_eng' => $profile->json()['data']['name_eng'] ?? null,
                    'email' => $profile->json()['data']['email'],
                    'hcode' => $profile->json()['data']['organization'][0]['hcode'] ?? null,
                    'hname' => $profile->json()['data']['organization'][0]['hname_th'] ?? null,
                    'title_th' => $titleTh, // Store title separately in case needed
                ]);

                // Set error flag to show registration modal
                session()->flash('provider_error', 'ไม่พบบัญชีผู้ใช้ในระบบ กรุณาลงทะเบียนบัญชีก่อนใช้งาน');
                return $this->redirectRoute('login', navigate: true);
            }
        } catch (\Throwable $th) {
            Log::error('Provider ID authentication error', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            session()->flash('error', 'เกิดข้อผิดพลาด: ' . $th->getMessage());
            return $this->redirectRoute('login', navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.provider.callback');
    }
}
