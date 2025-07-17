<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class MyMOPHController extends Controller
{
    public function callback(Request $request)
    {
        try {
            // Validate state parameter
            if (!$request->has('state') || !session('mymoph_state') || $request->state !== session('mymoph_state')) {
                Log::warning('MyMOPH OAuth: Invalid state parameter', [
                    'received_state' => $request->state,
                    'session_state' => session('mymoph_state')
                ]);
                return redirect()->route('login')->with('error', 'Invalid state parameter');
            }

            // Check for authorization code
            if (!$request->has('code')) {
                Log::warning('MyMOPH OAuth: Authorization code not received');
                return redirect()->route('login')->with('error', 'Authorization code not received');
            }

            Log::info('MyMOPH OAuth: Processing callback', [
                'code' => substr($request->code, 0, 20) . '...',
                'state' => $request->state
            ]);

            // Exchange authorization code for tokens
            $tokenResponse = $this->exchangeCodeForToken($request->code);

            if (!$tokenResponse) {
                return redirect()->route('login')->with('error', 'Failed to exchange authorization code for token');
            }

            Log::info('MyMOPH OAuth: Token exchange successful', [
                'has_access_token' => isset($tokenResponse['access_token']),
                'has_id_token' => isset($tokenResponse['id_token']),
                'token_type' => $tokenResponse['token_type'] ?? 'unknown'
            ]);

            // Get user info using access token
            $userInfo = $this->getUserInfo($tokenResponse['access_token']);
            
            if (!$userInfo) {
                return redirect()->route('login')->with('error', 'Failed to get user information from MyMOPH');
            }

            Log::info('MyMOPH OAuth: User info retrieved', [
                'user_info_keys' => array_keys($userInfo)
            ]);

            // Clear state from session
            session()->forget('mymoph_state');

            // Handle user login/registration
            $user = $this->handleUserAuth($userInfo, $tokenResponse);
            
            if ($user) {
                Auth::login($user);
                Log::info('MyMOPH OAuth: User logged in successfully', [
                    'user_id' => $user->id,
                    'cid' => $userInfo['cid'] ?? 'not_provided'
                ]);
                // ล็อกอินผู้ใช้
                session()->flash('login_success', 'ยินดีต้อนรับ ' . $user->name . ' เข้าสู่ระบบด้วย MyMOPH');
                return redirect()->intended(route('home'));
            }

            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');

        } catch (\Exception $e) {
            Log::error('MyMOPH OAuth Error: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            return redirect()->route('login')->with('error', 'MyMOPH authentication failed: ' . $e->getMessage());
        }
    }

    private function exchangeCodeForToken($code)
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.mymoph.redirect_uri'),
            'client_id' => config('services.mymoph.client_id'),
            'client_secret' => config('services.mymoph.client_secret'),
        ];

        Log::info('MyMOPH Token Exchange Request', [
            'url' => config('services.mymoph.token_url'),
            'payload' => array_merge($payload, ['client_secret' => '***'])
        ]);

        $response = Http::asForm()->post(config('services.mymoph.token_url'), $payload);

        if ($response->successful()) {
            $responseData = $response->json();
            Log::info('MyMOPH Token Exchange Success', [
                'response_keys' => array_keys($responseData)
            ]);
            return $responseData;
        }

        Log::error('MyMOPH Token Exchange Failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => $response->headers()
        ]);

        return null;
    }

    private function getUserInfo($accessToken)
    {
        try {
            Log::info('MyMOPH UserInfo Request', [
                'url' => config('services.mymoph.userinfo_url'),
                'has_access_token' => !empty($accessToken)
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])->post(config('services.mymoph.userinfo_url'));

            if ($response->successful()) {
                $userInfo = $response->json();
                
                // บันทึกข้อมูล response ทั้งหมดลง log
                Log::info('MyMOPH UserInfo Response Data', [
                    'full_response' => $userInfo,
                    'response_keys' => array_keys($userInfo),
                    'has_cid' => isset($userInfo['cid']),
                    'has_email' => isset($userInfo['email']),
                    'cid_value' => $userInfo['cid'] ?? 'not_provided',
                    'email_value' => $userInfo['email'] ?? 'not_provided',
                    'fname_value' => $userInfo['fname'] ?? 'not_provided',
                    'lname_value' => $userInfo['lname'] ?? 'not_provided',
                    'hospcode_value' => $userInfo['hospcode'] ?? 'not_provided',
                    'staff_type_value' => $userInfo['staff_type'] ?? 'not_provided',
                    'sub_value' => $userInfo['sub'] ?? 'not_provided'
                ]);
                
                Log::info('MyMOPH UserInfo Success', [
                    'response_keys' => array_keys($userInfo),
                    'has_cid' => isset($userInfo['cid']),
                    'has_email' => isset($userInfo['email'])
                ]);
                return $userInfo;
            }

            Log::error('MyMOPH UserInfo Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to get MyMOPH user info: ' . $e->getMessage());
            return null;
        }
    }

    private function handleUserAuth($userInfo, $tokenData)
    {
        try {
            // Extract user data
            $cid = $userInfo['cid'] ?? null;
            $email = $userInfo['email'] ?? null;
            $fname = $userInfo['fname'] ?? '';
            $lname = $userInfo['lname'] ?? '';
            $hospcode = $userInfo['hospcode'] ?? null;
            $staffType = $userInfo['staff_type'] ?? null;

            if (!$cid) {
                Log::warning('MyMOPH user info missing CID', $userInfo);
                return null;
            }

            // Try to find existing user by PID (using CID from MyMOPH)
            $user = User::where('pid', $cid)->first();

            if ($user) {
                // Update existing user with MyMOPH data
                $user->update([
                    'name' => trim($fname . ' ' . $lname),
                    'fname' => $fname,
                    'lname' => $lname,
                    'email' => $email ?? $user->email,
                    'hospcode' => $hospcode,
                    'staff_type' => $staffType,
                    'last_login_at' => now(),
                ]);

                Log::info('Updated existing user with MyMOPH data', [
                    'user_id' => $user->id,
                    'pid' => $cid,
                    'name' => trim($fname . ' ' . $lname),
                    'email' => $email
                ]);

                return $user;
            }

            // Create new user if not exists
            if ($email) {
                $user = User::create([
                    'pid' => $cid,
                    'name' => trim($fname . ' ' . $lname),
                    'fname' => $fname,
                    'lname' => $lname,
                    'email' => $email,
                    'hospcode' => $hospcode,
                    'staff_type' => $staffType,
                    'password' => Hash::make(str()->random(32)), // Random password for OAuth users
                    'email_verified_at' => now(), // Auto-verify MyMOPH users
                    'last_login_at' => now(),
                ]);

                Log::info('Created new user from MyMOPH data', [
                    'user_id' => $user->id,
                    'pid' => $cid,
                    'name' => trim($fname . ' ' . $lname),
                    'email' => $email
                ]);

                return $user;
            }

            Log::warning('Cannot create user: missing required email', $userInfo);
            return null;

        } catch (\Exception $e) {
            Log::error('Failed to handle MyMOPH user auth: ' . $e->getMessage(), [
                'user_info' => $userInfo,
                'exception' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
