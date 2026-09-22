<?php

namespace App\Http\Controllers\Thaid;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\ThaidIdTokenVerifier;

class AuthController extends Controller
{
    /**
     * Redirect to ThaID authentication page
     */
    public function redirect()
    {
        try {
            // Generate state for CSRF protection
            $state = Str::random(40);
            $nonce = Str::random(64);
            Session::put('thaid_state', $state);
            Session::put('thaid_nonce', $nonce);
            
            // Get well-known configuration
            $config = $this->getWellKnownConfig();
            
            // Construct the authorization URL
            $query = http_build_query([
                'client_id' => config('services.thaid.client_id'),
                'redirect_uri' => config('services.thaid.redirect_uri'),
                'response_type' => 'code',
                'scope' => config('services.thaid.scope', 'pid openid name'),
                'state' => $state,
                'nonce' => $nonce,
            ]);
            
            $authUrl = $config['authorization_endpoint'] . '?' . $query;
            
            return redirect($authUrl);
        } catch (\Exception $e) {
            Log::error('ThaID redirect error', ['message' => $e->getMessage()]);
            return redirect()->route('login')->with('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อกับ ThaID: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle callback from ThaID
     */
    public function callback(Request $request)
    {
        try {
            // ตรวจสอบ state
            $expectedState = Session::pull('thaid_state');
            $nonce = Session::pull('thaid_nonce');

            if (!is_string($expectedState) || !is_string($request->state) || !hash_equals($expectedState, $request->state)) {
                return redirect()->route('login')->with('error', 'การยืนยันตัวตนล้มเหลว: รหัสความปลอดภัยไม่ถูกต้อง');
            }

            // ตรวจสอบหาก ThaID ส่งข้อผิดพลาดกลับมา
            if ($request->has('error')) {
                return redirect()->route('login')->with('error', 'การยืนยันตัวตนล้มเหลว: ' . ($request->error_description ?? $request->error));
            }

            // ตรวจสอบว่ามี code กลับมาหรือไม่
            if (!$request->has('code')) {
                return redirect()->route('login')->with('error', 'การยืนยันตัวตนล้มเหลว: ไม่ได้รับรหัสอนุญาต');
            }

            $config = $this->getWellKnownConfig();
            
            // ขอ token จาก code ที่ได้รับ
            $response = Http::asForm()->post($config['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.thaid.client_id'),
                'client_secret' => config('services.thaid.client_secret'),
                'redirect_uri' => config('services.thaid.redirect_uri'),
                'code' => $request->code,
            ]);
            
            if (!$response->successful()) {
                return redirect()->route('login')->with('error', 'การยืนยันตัวตนล้มเหลว: ไม่สามารถขอ token ได้');
            }
            
            $tokenData = $response->json();
            
            if (empty($tokenData['id_token']) || empty($config['jwks_uri']) || !is_string($nonce)) {
                return redirect()->route('login')->with('error', 'การยืนยันตัวตนล้มเหลว: ไม่ได้รับข้อมูลยืนยันตัวตนที่ปลอดภัย');
            }

            $jwksResponse = Http::get($config['jwks_uri']);
            if (! $jwksResponse->successful()) {
                return redirect()->route('login')->with('error', 'การยืนยันตัวตนล้มเหลว: ไม่สามารถตรวจสอบลายเซ็นได้');
            }

            $userData = app(ThaidIdTokenVerifier::class)->verify(
                $tokenData['id_token'],
                $jwksResponse->json(),
                config('services.thaid.issuer'),
                config('services.thaid.client_id'),
                $nonce,
            );

            Session::put('thaid_access_token', $tokenData['access_token'] ?? null);
            
            // ถ้าไม่มีข้อมูลผู้ใช้ให้แจ้งข้อผิดพลาด
            if (empty($userData) || !isset($userData['pid'])) {
                return redirect()->route('login')->with('error', 'การยืนยันตัวตนล้มเหลว: ไม่ได้รับข้อมูลผู้ใช้ที่จำเป็น');
            }
            
            // เมื่อได้รับข้อมูลจาก ThaID
            if (isset($userData['pid'])) {
                $pid = $userData['pid'];
                
                // แปลงข้อมูลที่อยู่จาก JSON เป็นข้อความ
                $formattedAddress = '';
                if (isset($userData['address'])) {
                    if (is_string($userData['address'])) {
                        // ถ้าเป็น JSON string ให้แปลงเป็น array
                        $addressData = json_decode($userData['address'], true);
                        if (isset($addressData['formatted'])) {
                            $formattedAddress = $addressData['formatted'];
                        } else {
                            $formattedAddress = $userData['address'];
                        }
                    } elseif (is_array($userData['address']) && isset($userData['address']['formatted'])) {
                        // ถ้าเป็น array อยู่แล้ว
                        $formattedAddress = $userData['address']['formatted'];
                    }
                }
                
                // แปลง Unicode escape sequences เป็นข้อความปกติ
                $formattedAddress = json_decode('"' . str_replace('"', '\"', $formattedAddress) . '"');
                
                // เก็บข้อมูลเพิ่มเติมในเซสชัน
                Session::put('thaid_pid', $pid);
                Session::put('thaid_name', $userData['name'] ?? '');
                Session::put('thaid_name_en', $userData['name_en'] ?? '');
                Session::put('thaid_birthdate', $userData['birthdate'] ?? null);
                Session::put('thaid_address', $formattedAddress);
                
                // บันทึกข้อมูลที่ได้รับลงใน log เพื่อตรวจสอบ
                Log::info('ThaID user data received', ['has_address' => isset($userData['address'])]);
                
                // ตรวจสอบผู้ใช้ทั้งจากฟิลด์ thaid_pid และ pid (สำหรับผู้ใช้เก่า)
                $user = User::where('thaid_pid', $pid)
                    ->orWhere('pid', $pid)
                    ->first();
                
                if ($user) {
                    // ผู้ใช้มีอยู่แล้ว
                    
                    // อัพเดทฟิลด์ thaid_pid และ pid ถ้ายังไม่มี
                    if (empty($user->thaid_pid)) {
                        $user->thaid_pid = $pid;
                    }
                    if (empty($user->pid)) {
                        $user->pid = $pid; // เพิ่มการบันทึกค่า pid
                    }
                    
                    // อัพเดทข้อมูลล่าสุดจาก ThaID
                    $user->update([
                        'name' => $userData['name'] ?? $user->name,
                        'name_en' => $userData['name_en'] ?? $user->name_en,
                        'birthdate' => $userData['birthdate'] ?? $user->birthdate,
                        'address' => $formattedAddress,
                        'thaid_pid' => $pid,
                        'pid' => $pid, // เพิ่มการบันทึกค่า pid ด้วย
                        'cid_hash' => hash('sha256', $pid),
                    ]);
                    
                    // ล็อกอินผู้ใช้
                    Auth::login($user);
                    
                    // Update to use 'login_success' key and include user's name
                    session()->flash('login_success', 'ยินดีต้อนรับ ' . $user->name . ' เข้าสู่ระบบด้วย ThaID');
                    
                    return redirect()->intended(route('home'));
                } else {
                    // ไม่พบผู้ใช้ในระบบ นำไปสู่หน้าลงทะเบียน
                    return redirect()->route('login')->with('thaid_error', 'ไม่พบบัญชีผู้ใช้ในระบบ กรุณาลงทะเบียนบัญชีก่อนใช้งาน');
                }
            }
        } catch (\Exception $e) {
            Log::error('ThaID callback error', ['message' => $e->getMessage()]);
            return redirect()->route('login')->with('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อกับ ThaID: ' . $e->getMessage());
        }
    }
    
    /**
     * Get OpenID Connect well-known configuration
     */
    private function getWellKnownConfig()
    {
        try {
            $cacheKey = 'thaid_well_known_config';
            
            if (cache()->has($cacheKey)) {
                return cache()->get($cacheKey);
            }
            
            $response = Http::get(config('services.thaid.well_known_url', 'https://imauth.bora.dopa.go.th/.well-known/openid-configuration'));
            
            if ($response->successful()) {
                $config = $response->json();
                cache()->put($cacheKey, $config, now()->addHours(24));
                return $config;
            }
        } catch (\Exception $e) {
            Log::error('ThaID: Failed to get well-known config', ['message' => $e->getMessage()]);
        }
        
        // Return default values if failed to get well-known config
        return [
            'authorization_endpoint' => 'https://imauth.bora.dopa.go.th/api/v2/oauth2/auth',
            'token_endpoint' => 'https://imauth.bora.dopa.go.th/api/v2/oauth2/token',
            'jwks_uri' => 'https://imauth.bora.dopa.go.th/api/v2/oauth2/jwks',
            'revocation_endpoint' => 'https://imauth.bora.dopa.go.th/api/v2/oauth2/revoke',
            'userinfo_endpoint' => 'https://imauth.bora.dopa.go.th/api/v2/oauth2/userinfo',
        ];
    }
    
    /**
     * สร้าง CID Hash จาก PID
     * 
     * @param string $pid เลขประจำตัวประชาชน
     * @return string ค่า hash ที่ได้จากการเข้ารหัส
     */
    private function generateCidHash($pid)
    {
        if (empty($pid)) {
            return null;
        }
        
        // ใช้ MD5 เข้ารหัส PID และแปลงเป็นตัวพิมพ์ใหญ่
        $md5 = strtoupper(md5($pid));
        
        // ดึงตัวอักษรตัวแรกและตัวสุดท้ายของ PID
        $firstChar = substr($pid, 0, 1);
        $lastChar = substr($pid, -1);
        
        // รวมกันในรูปแบบ MD5:firstChar+lastChar
        return $md5 . ':' . $firstChar . $lastChar;
    }
}
