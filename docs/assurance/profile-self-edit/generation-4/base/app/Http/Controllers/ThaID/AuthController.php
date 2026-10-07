<?php

namespace App\Http\Controllers\Thaid;

use App\Http\Controllers\Controller;
use App\Services\SsoUserProvisioner;
use App\Services\ThaidIdTokenVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function redirect()
    {
        try {
            Session::put('thaid_state', Str::random(40));
            Session::put('thaid_nonce', Str::random(64));
            $config = $this->getWellKnownConfig();
            $query = http_build_query([
                'client_id' => config('services.thaid.client_id'),
                'redirect_uri' => config('services.thaid.redirect_uri'),
                'response_type' => 'code',
                'scope' => config('services.thaid.scope', 'pid openid name'),
                'state' => session('thaid_state'),
                'nonce' => session('thaid_nonce'),
            ]);

            return redirect($config['authorization_endpoint'].'?'.$query);
        } catch (\Throwable $exception) {
            Log::warning('ThaID redirect failed.', ['exception' => $exception::class]);

            return redirect()->route('login')->with('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อกับ ThaID');
        }
    }

    public function callback(Request $request)
    {
        try {
            $expectedState = Session::pull('thaid_state');
            $nonce = Session::pull('thaid_nonce');
            if (! is_string($expectedState) || ! is_string($request->state)
                || ! hash_equals($expectedState, $request->state)) {
                return $this->deny('การยืนยันตัวตนล้มเหลว: รหัสความปลอดภัยไม่ถูกต้อง');
            }
            if ($request->filled('error') || ! $request->filled('code') || ! is_string($nonce)) {
                return $this->deny('การยืนยันตัวตนล้มเหลว');
            }

            $config = $this->getWellKnownConfig();
            $response = Http::asForm()->post($config['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.thaid.client_id'),
                'client_secret' => config('services.thaid.client_secret'),
                'redirect_uri' => config('services.thaid.redirect_uri'),
                'code' => $request->string('code')->toString(),
            ]);
            $tokenData = $response->json();
            if (! $response->successful() || ! is_array($tokenData)
                || ! is_string($tokenData['id_token'] ?? null)
                || ! filled($config['jwks_uri'] ?? null)) {
                return $this->deny('การยืนยันตัวตนล้มเหลว: ไม่ได้รับข้อมูลยืนยันตัวตนที่ปลอดภัย');
            }

            $jwksResponse = Http::get($config['jwks_uri']);
            if (! $jwksResponse->successful()) {
                return $this->deny('การยืนยันตัวตนล้มเหลว: ไม่สามารถตรวจสอบลายเซ็นได้');
            }
            $userData = app(ThaidIdTokenVerifier::class)->verify(
                $tokenData['id_token'],
                $jwksResponse->json(),
                config('services.thaid.issuer'),
                config('services.thaid.client_id'),
                $nonce,
            );
            $cid = $userData['pid'] ?? null;
            if (! is_string($cid) || ! preg_match('/^\d{13}$/', $cid)) {
                return $this->deny('การยืนยันตัวตนล้มเหลว: ไม่ได้รับข้อมูลผู้ใช้ที่จำเป็น');
            }

            $formattedAddress = $this->formatAddress($userData['address'] ?? null);
            $user = app(SsoUserProvisioner::class)->provision($cid, [
                'name' => $userData['name'] ?? null,
                'name_en' => $userData['name_en'] ?? null,
                'birthdate' => $userData['birthdate'] ?? null,
                'address' => $formattedAddress,
                'thaid_pid' => $cid,
            ]);
            if ($user === null) {
                return $this->deny('ไม่พบสิทธิ์เข้าใช้งานระบบ');
            }

            Session::put('thaid_pid', $cid);
            Session::put('thaid_name', $userData['name'] ?? '');
            Session::put('thaid_name_en', $userData['name_en'] ?? '');
            Session::put('thaid_birthdate', $userData['birthdate'] ?? null);
            Session::put('thaid_address', $formattedAddress);

            Auth::login($user);
            $request->session()->regenerate();
            session()->flash('login_success', 'ยินดีต้อนรับ '.$user->name.' เข้าสู่ระบบด้วย ThaID');

            return redirect()->intended(route('home'));
        } catch (\Throwable $exception) {
            Log::warning('ThaID authentication failed.', ['exception' => $exception::class]);

            return $this->deny('เกิดข้อผิดพลาดในการเชื่อมต่อกับ ThaID');
        }
    }

    /** @return array<string, mixed> */
    private function getWellKnownConfig(): array
    {
        $cacheKey = 'thaid_well_known_config';
        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        try {
            $response = Http::get(config('services.thaid.well_known_url', 'https://imauth.bora.dopa.go.th/.well-known/openid-configuration'));
            if ($response->successful() && is_array($response->json())) {
                $config = $response->json();
                cache()->put($cacheKey, $config, now()->addHours(24));

                return $config;
            }
        } catch (\Throwable $exception) {
            Log::warning('ThaID discovery failed.', ['exception' => $exception::class]);
        }

        return [
            'authorization_endpoint' => 'https://imauth.bora.dopa.go.th/api/v2/oauth2/auth',
            'token_endpoint' => 'https://imauth.bora.dopa.go.th/api/v2/oauth2/token',
            'jwks_uri' => 'https://imauth.bora.dopa.go.th/api/v2/oauth2/jwks',
        ];
    }

    private function formatAddress(mixed $address): string
    {
        if (is_array($address)) {
            return trim((string) ($address['formatted'] ?? ''));
        }
        if (! is_string($address) || $address === '') {
            return '';
        }

        $decoded = json_decode($address, true);

        return is_array($decoded) ? trim((string) ($decoded['formatted'] ?? '')) : trim($address);
    }

    private function deny(string $message): mixed
    {
        Session::forget(['thaid_pid', 'thaid_name', 'thaid_name_en', 'thaid_birthdate', 'thaid_address']);

        return redirect()->route('login')->with('error', $message);
    }
}
