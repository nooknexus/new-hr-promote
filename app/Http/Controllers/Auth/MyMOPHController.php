<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SsoUserProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MyMOPHController extends Controller
{
    public function callback(Request $request)
    {
        try {
            $expectedState = session('mymoph_state');
            if (! is_string($expectedState) || ! is_string($request->state)
                || ! hash_equals($expectedState, $request->state)) {
                return redirect()->route('login')->with('error', 'การยืนยันตัวตนล้มเหลว');
            }

            if (! $request->filled('code')) {
                return redirect()->route('login')->with('error', 'การยืนยันตัวตนล้มเหลว');
            }

            $tokenData = $this->exchangeCodeForToken($request->string('code')->toString());
            $accessToken = $tokenData['access_token'] ?? null;
            if (! is_string($accessToken) || $accessToken === '') {
                return redirect()->route('login')->with('error', 'ไม่สามารถเชื่อมต่อกับ MyMOPH ได้');
            }

            $userInfo = $this->getUserInfo($accessToken);
            session()->forget('mymoph_state');
            if (! is_array($userInfo)) {
                return redirect()->route('login')->with('error', 'ไม่สามารถรับข้อมูลยืนยันตัวตนจาก MyMOPH ได้');
            }

            $user = $this->handleUserAuth($userInfo);
            if ($user === null) {
                return redirect()->route('login')->with('error', 'ไม่พบสิทธิ์เข้าใช้งานระบบ');
            }

            Auth::login($user);
            $request->session()->regenerate();
            session()->flash('login_success', 'ยินดีต้อนรับ '.$user->name.' เข้าสู่ระบบด้วย MyMOPH');

            return redirect()->intended(route('home'));
        } catch (\Throwable $exception) {
            // OAuth material and profile data are sensitive; never include them in logs.
            Log::warning('MyMOPH authentication failed.', ['exception' => $exception::class]);

            return redirect()->route('login')->with('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อกับ MyMOPH');
        }
    }

    /** @return array<string, mixed>|null */
    private function exchangeCodeForToken(string $code): ?array
    {
        $response = Http::asForm()->post(config('services.mymoph.token_url'), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.mymoph.redirect_uri'),
            'client_id' => config('services.mymoph.client_id'),
            'client_secret' => config('services.mymoph.client_secret'),
        ]);

        return $response->successful() ? $response->json() : null;
    }

    /** @return array<string, mixed>|null */
    private function getUserInfo(string $accessToken): ?array
    {
        $response = Http::withToken($accessToken)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->post(config('services.mymoph.userinfo_url'));

        return $response->successful() ? $response->json() : null;
    }

    /** @param array<string, mixed> $userInfo */
    private function handleUserAuth(array $userInfo): ?User
    {
        $cid = $userInfo['cid'] ?? null;
        if (! is_string($cid)) {
            return null;
        }

        $firstName = trim((string) ($userInfo['fname'] ?? ''));
        $lastName = trim((string) ($userInfo['lname'] ?? ''));

        return app(SsoUserProvisioner::class)->provision($cid, [
            'name' => trim($firstName.' '.$lastName),
            'email' => $userInfo['email'] ?? null,
            'hospcode' => $userInfo['hospcode'] ?? null,
        ]);
    }
}
