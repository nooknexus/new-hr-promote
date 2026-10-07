<?php

namespace App\Livewire\Provider;

use App\Services\ProviderOAuthState;
use App\Services\SsoUserProvisioner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Callback extends Component
{
    public function mount()
    {
        try {
            if (! ProviderOAuthState::consume(session()->driver(), request()->string('state')->toString())) {
                return $this->deny();
            }

            $healthClientId = config('services.provider.healthid_client_id');
            $healthSecret = config('services.provider.healthid_secret_key');
            $providerClientId = config('services.provider.provider_client_id');
            $providerSecret = config('services.provider.provider_secret_key');

            if (! filled($healthClientId) || ! filled($healthSecret)
                || ! filled($providerClientId) || ! filled($providerSecret)) {
                throw new \RuntimeException('ProviderID configuration is incomplete.');
            }

            $healthResponse = Http::post('https://moph.id.th/api/v1/token', [
                'grant_type' => 'authorization_code',
                'code' => request()->string('code')->toString(),
                'client_id' => $healthClientId,
                'client_secret' => $healthSecret,
                'redirect_uri' => config('services.provider.redirect_uri'),
            ]);
            $healthAccessToken = $this->healthAccessToken($healthResponse->json());
            $healthClaims = $healthAccessToken === null ? null : $this->decodeJwtPayload($healthAccessToken);
            $cid = data_get($healthClaims, 'scopes_detail.id_card');

            if (! $healthResponse->successful() || ! is_string($cid) || ! preg_match('/^\d{13}$/', $cid)) {
                return $this->deny();
            }

            $providerResponse = Http::post('https://provider.id.th/api/v1/services/token', [
                'client_id' => $providerClientId,
                'secret_key' => $providerSecret,
                'token_by' => 'Health ID',
                'token' => $healthAccessToken,
            ]);
            $providerAccessToken = $providerResponse->json('data.access_token');
            if (! $providerResponse->successful() || ! is_string($providerAccessToken) || $providerAccessToken === '') {
                throw new \RuntimeException('ProviderID token exchange failed.');
            }

            $profileResponse = Http::withHeaders([
                'Content-type' => 'application/json',
                'Authorization' => 'Bearer '.$providerAccessToken,
                'client-id' => $providerClientId,
                'secret-key' => $providerSecret,
            ])->get('https://provider.id.th/api/v1/services/profile');
            $profile = $profileResponse->json('data');
            if (! $profileResponse->successful() || ! is_array($profile)) {
                throw new \RuntimeException('ProviderID profile lookup failed.');
            }

            $name = trim(implode(' ', array_filter([
                data_get($profile, 'title_th'),
                data_get($profile, 'name_th'),
            ])));
            $user = app(SsoUserProvisioner::class)->provision($cid, [
                'name' => $name,
                'name_en' => data_get($profile, 'name_eng'),
                'email' => data_get($profile, 'email'),
                'provider_id' => data_get($profile, 'provider_id') ?? data_get($healthClaims, 'sub'),
                'hcode' => data_get($profile, 'organization.0.hcode'),
                'hname' => data_get($profile, 'organization.0.hname_th'),
            ]);

            if ($user === null) {
                return $this->deny();
            }

            Auth::login($user);
            request()->session()->regenerate();
            session()->flash('login_success', 'ยินดีต้อนรับ '.$user->name.' เข้าสู่ระบบ');

            return $this->redirectRoute('home', navigate: true);
        } catch (\Throwable $exception) {
            // Never log OAuth responses, tokens, or identity attributes.
            Log::warning('ProviderID authentication failed.', [
                'exception' => $exception::class,
            ]);

            return $this->deny('เกิดข้อผิดพลาดในการเชื่อมต่อกับ ProviderID');
        }
    }

    /**
     * Provider documentation has used both data.access_token and data.data.access_token.
     *
     * @param  array<string, mixed>  $payload
     */
    private function healthAccessToken(array $payload): ?string
    {
        $accessToken = data_get($payload, 'data.data.access_token') ?? data_get($payload, 'data.access_token');

        return is_string($accessToken) && $accessToken !== '' ? $accessToken : null;
    }

    /** @return array<string, mixed>|null */
    private function decodeJwtPayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }

        $claims = json_decode($decoded, true);

        return is_array($claims) ? $claims : null;
    }

    private function deny(string $message = 'ไม่พบสิทธิ์เข้าใช้งานระบบ'): mixed
    {
        session()->forget('provider_data');
        session()->flash('provider_error', $message);

        return $this->redirectRoute('login', navigate: true);
    }

    public function render()
    {
        return view('livewire.provider.callback');
    }
}
