<?php

namespace App\Livewire\Provider;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class ProfileCallback extends Component
{
    public function mount()
    {
        try {
            // Check if this is a profile update
            if (!Session::has('profile_update') || !Session::has('user_id_for_update')) {
                throw new \Exception('ไม่พบข้อมูลการอัพเดทโปรไฟล์');
            }
            
            $userId = Session::get('user_id_for_update');
            $user = User::find($userId);
            
            if (!$user) {
                throw new \Exception('ไม่พบข้อมูลผู้ใช้');
            }
            
            // Use the EXACT SAME endpoints and parameters as in the working Callback component
            $response = Http::asForm()->post('https://moph.id.th/api/v1/token', [
                'grant_type' => 'authorization_code',
                'code' => request()->code,
                'client_id' => '01951dca-8519-7403-8e49-9c0ff7e570e3',
                'client_secret' => '338f14b67b6bbb3f2343739de69db648861edfe1',
                'redirect_uri' => route('provider.profile.callback'),
            ]);
            
            // Rest of the token/profile exchange logic from the working Callback component
            $provider = Http::post('https://provider.id.th/api/v1/services/token', [
                'client_id' => '606dbdd4-1ee0-4977-bcf5-ce4ce0797f78',
                'secret_key' => 'wJJ2EIbFlNIfnphZTSGcEmnab5WtfkWU',
                'token_by' => "Health ID",
                'token' => $response->json()['data']['access_token'],
            ]);
            
            $profile = Http::withHeaders([
                'Content-type' => 'application/json',
                'Authorization' => 'Bearer ' . $provider->json()['data']['access_token'],
                'client-id' => '606dbdd4-1ee0-4977-bcf5-ce4ce0797f78',
                'secret-key' => 'wJJ2EIbFlNIfnphZTSGcEmnab5WtfkWU',
            ])->get('https://provider.id.th/api/v1/services/profile');
            
            // Update user with Provider ID data
            $profileData = $profile->json()['data'];
            $hash_cid = $profileData['hash_cid'] ?? null;
            $providerId = $profileData['provider_id'] ?? null;
            $accountId = $profileData['account_id'] ?? null;
            $nameTh = $profileData['name_th'] ?? null;
            $nameEng = $profileData['name_eng'] ?? null;
            $email = $profileData['email'] ?? null;
            
            // Safely get organization data
            $hcode = null;
            $hname = null;
            if (!empty($profileData['organization']) && is_array($profileData['organization']) && count($profileData['organization']) > 0) {
                $hcode = $profileData['organization'][0]['hcode'] ?? null;
                $hname = $profileData['organization'][0]['hname_th'] ?? null;
            }
            
            // Update user with the safely extracted data
            $user->update([
                'name' => $nameTh ?? $user->name,
                'name_en' => $nameEng ?? $user->name_en,
                'email' => $email ?? $user->email,
                'cid_hash' => $hash_cid ?? $user->cid_hash,
                'account_id' => $accountId ?? $user->account_id,
                'provider_id' => $providerId ?? $user->provider_id,
                'hcode' => $hcode ?? $user->hcode,
                'hname' => $hname ?? $user->hname,
            ]);
            
            // Clear the session data
            Session::forget(['profile_update', 'user_id_for_update']);
            
            // Return to profile page with success message
            session()->flash('success', 'อัพเดทข้อมูลจาก Provider ID สำเร็จ');
            return redirect()->route('profile.index');
            
        } catch (\Exception $e) {
            Log::error('Provider ID profile update failed', ['error' => $e->getMessage()]);
            Session::forget(['profile_update', 'user_id_for_update']);
            session()->flash('error', 'ไม่สามารถอัพเดทข้อมูลจาก Provider ID ได้: ' . $e->getMessage());
            return redirect()->route('profile.index');
        }
    }

    public function render()
    {
        return view('livewire.provider.profile-callback');
    }
}
