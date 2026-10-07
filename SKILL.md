---
name: thaid-login-development
description: "Provides instructions and workflows for developing and integrating ThaiD login functionality into a Laravel application. Use when setting up, debugging, or enhancing ThaiD authentication."
---

# ThaiD Login Development

This skill provides guidance on developing and integrating the ThaiD login feature within the Laravel application.

## Quick start

To implement a basic ThaiD login button, you can use the Livewire component. This component handles the redirection to the ThaiD authentication service.

**Example Blade integration:**

```blade
<div>
    <button wire:click="redirectToThaid" class="btn btn-primary">
        Login with ThaiD
    </button>
</div>
```

**Livewire Component (`app/Livewire/Thaid/Login.php`):**

```php
<?php

namespace App\Livewire\Thaid;

use Livewire\Component;

class Login extends Component
{
    public function render()
    {
        return view('livewire.thaid.login');
    }

    /**
     * Redirect to ThaID authentication
     */
    public function redirectToThaid()
    {
        return redirect()->route('thaid.redirect');
    }
}
```

## Workflows

### ThaiD Authentication Flow

The authentication process involves a redirection to the ThaiD service and a callback to the application.

1.  **Initiate Login:**
    *   A user clicks the "Login with ThaiD" button.
    *   The `redirectToThaid` method in the `App\Livewire\Thaid\Login` component is called.
    *   The application redirects the user to the `thaid.redirect` named route.

2.  **Redirect to ThaiD:**
    *   The `thaid.redirect` route is handled by the `redirect` method in `App\Http\Controllers\ThaID\AuthController`.
    *   A unique `state` is generated for CSRF protection and stored in the session.
    *   The application constructs the authorization URL using configuration from `config/services.php` and the well-known OpenID configuration endpoint.
    *   The user is redirected to the ThaiD authorization endpoint.

3.  **User Authentication:**
    *   The user authenticates with their ThaiD credentials on the ThaiD platform.
    *   Upon successful authentication, ThaiD redirects the user back to the `redirect_uri` specified, which is the `thaid.callback` route in our application.

4.  **Handle Callback:**
    *   The `thaid.callback` route is handled by the `callback` method in `App\Http\Controllers\ThaID\AuthController`.
    *   The application verifies the `state` parameter to prevent CSRF attacks.
    *   It exchanges the authorization `code` received from ThaiD for an `access_token` and `id_token` by making a POST request to the token endpoint.
    *   The user's information is extracted from the `id_token` or by calling the `userinfo_endpoint`.

5.  **User Login/Registration:**
    *   The application searches for a user in the database with the `pid` (Personal Identification Number) received from ThaiD.
    *   **If the user exists:**
        *   The user's information (name, address, etc.) is updated with the latest data from ThaiD.
        *   The user is logged into the application.
        *   The user is redirected to the intended page or the home page.
    *   **If the user does not exist:**
        *   The user is redirected back to the login page with an error message prompting them to register first.

### Example JSON Responses

Here are examples of the JSON data involved in the ThaiD authentication flow.

**Token Endpoint Response:**

This is the response from the ThaiD token endpoint after a successful authorization code exchange.

```json
{
    "access_token": "eyJhbGciOiJFUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "id_token": "eyJhbGciOiJFUzI1NiIsImtpZCI6..."
}
```

**Decoded `id_token` Payload:**

This is the user information payload decoded from the `id_token`.

```json
{
    "sub": "user-subject-identifier",
    "pid": "1234567890123",
    "name": "นายทดสอบ ระบบ",
    "name_en": "Mr. Test System",
    "birthdate": "1990-01-01",
    "address": "{\\"formatted\\":\\"99 หมู่ 9 ต.ทดสอบ อ.ทดสอบ จ.ทดสอบ 10110\\"}",
    "iss": "https://imauth.bora.dopa.go.th",
    "aud": "your_client_id",
    "exp": 1678886400,
    "iat": 1678882800,
    "nonce": "a_random_nonce_string"
}
```

### Configuration

The ThaiD integration relies on configuration values stored in `.env` and `config/services.php`.

**`.env` file:**

```dotenv
THAID_CLIENT_ID=your_client_id
THAID_CLIENT_SECRET=your_client_secret
THAID_REDIRECT_URI="${APP_URL}/thaid/callback"
THAID_SCOPE="pid openid name_en name birthdate address"
THAID_WELL_KNOWN_URL=https://imauth.bora.dopa.go.th/.well-known/openid-configuration
THAID_ISSUER=https://imauth.bora.dopa.go.th
```

**`config/services.php`:**

```php
// ...
'thaid' => [
    'client_id' => env('THAID_CLIENT_ID'),
    'client_secret' => env('THAID_CLIENT_SECRET'),
    'redirect_uri' => env('THAID_REDIRECT_URI'),
    'well_known_url' => env('THAID_WELL_KNOWN_URL', 'https://imauth.bora.dopa.go.th/.well-known/openid-configuration'),
    'issuer' => env('THAID_ISSUER', 'https://imauth.bora.dopa.go.th'),
    'scope' => 'pid openid name name_en birthdate address',
],
// ...
```

## Advanced features

## ThaiD JWT verification (current implementation)

The callback verifies the ThaiD ID token in `App\Services\ThaidIdTokenVerifier` before creating a session. Verification requires:

- an allowed ES256 JWK from the configured `jwks_uri`;
- an exact issuer match from `config('services.thaid.issuer')` / `THAID_ISSUER`;
- an audience match for the configured client ID;
- `azp` validation when present;
- complete time claims and a constant-time nonce comparison with the session nonce.

If ThaiD reports `No allowed ThaiD signing keys are available`, check that the well-known endpoint is reachable, its `jwks_uri` returns an ES256 key set, and configuration cache has been cleared:

```bash
php artisan config:clear
php artisan cache:clear
```

Never log the ID token, access token, client secret, nonce, PID, or complete personal profile.

For more detailed information on the underlying implementation, refer to the following files:

*   **Routing:** [routes/web.php](routes/web.php)
*   **Controller Logic:** [app/Http/Controllers/ThaID/AuthController.php](app/Http/Controllers/ThaID/AuthController.php)
*   **Livewire Component:** [app/Livewire/Thaid/Login.php](app/Livewire/Thaid/Login.php)
*   **User Model:** [app/Models/User.php](app/Models/User.php)
