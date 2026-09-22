<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'thaid' => [
        'client_id' => env('THAID_CLIENT_ID', ''),
        'client_secret' => env('THAID_CLIENT_SECRET', ''),
        'redirect_uri' => env('THAID_REDIRECT_URI', ''),
        'well_known_url' => env('THAID_WELL_KNOWN_URL', 'https://imauth.bora.dopa.go.th/.well-known/openid-configuration'),
        'issuer' => env('THAID_ISSUER', 'https://imauth.bora.dopa.go.th'),
        'scope' => env('THAID_SCOPE', 'pid openid name_en name birthdate address given_name middle_name family_name given_name_en middle_name_en family_name_en gender smartcard_code title title_en'),
    ],

    'provider' => [
        'healthid_client_id' => env('HEALTHID_CLIENT_ID'),
        'healthid_secret_key' => env('HEALTHID_SECRET_KEY'),
        'provider_client_id' => env('PROVIDER_CLIENT_ID'),
        'provider_secret_key' => env('PROVIDER_SECRET_KEY'),
        'auth_url' => env('PROVIDER_AUTH_URL', 'https://moph.id.th/oauth2/auth'),
    ],

    'mymoph' => [
        'client_id' => env('MYMOPH_CLIENT_ID'),
        'client_secret' => env('MYMOPH_CLIENT_SECRET'),
        'redirect_uri' => env('MYMOPH_REDIRECT_URI', env('APP_URL', 'http://localhost:8000') . '/mymoph/callback'),
        'auth_url' => 'https://auth.moph.go.th/v1/oauth2/auth',
        'token_url' => 'https://auth.moph.go.th/v1/oauth2/token',
        'userinfo_url' => 'https://auth.moph.go.th/v1/userinfo',
    ],
];
