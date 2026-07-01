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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PagoFacil (pago por QR — requisito #10)
    |--------------------------------------------------------------------------
    |
    | Credenciales y parametros del servicio de cobro por QR de PagoFacil. Las
    | credenciales reales son del curso. El cobro es SIMBOLICO ('amount'); la
    | cuota se salda con su monto real. Ver proyecto/qr_pagofacil.md.
    |
    */
    'pagofacil' => [
        'base_url' => rtrim((string) env('PAGOFACIL_BASE_URL', ''), '/'),
        'token_service' => env('PAGOFACIL_TOKEN_SERVICE'),
        'token_secret' => env('PAGOFACIL_TOKEN_SECRET'),
        // Verificacion SSL. Dejar en true SIEMPRE en produccion. Solo poner false en desarrollo local
        // (Windows) si PHP no tiene el bundle de CA y da "cURL error 60". Ver php.ini curl.cainfo.
        'verify_ssl' => filter_var(env('PAGOFACIL_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'amount' => env('PAGOFACIL_AMOUNT', '0.01'),      // monto simbolico del cobro real
        'currency' => (int) env('PAGOFACIL_CURRENCY', 2), // 2 = BOB
        'document_type' => (int) env('PAGOFACIL_DOCUMENT_TYPE', 1),
        'payment_method' => env('PAGOFACIL_PAYMENT_METHOD'), // id del metodo (opcional)
        'callback_url' => env('PAGOFACIL_CALLBACK_URL', 'https://tu-dominio.com/callback'),
        'poll_seconds' => (int) env('PAGOFACIL_POLL_SECONDS', 5),
        'qr_timeout_seconds' => (int) env('PAGOFACIL_QR_TIMEOUT_SECONDS', 150),
        'paid_status' => (int) env('PAGOFACIL_PAID_STATUS', 5),
        'void_status' => (int) env('PAGOFACIL_VOID_STATUS', 4),

        // Datos fiscales del cliente que viajan en el QR. Si use_env_client=true se usan estos
        // valores por defecto; si es false, se usan los datos reales del comprador (name/ci/telefono/email).
        'use_env_client' => filter_var(env('PAGOFACIL_USE_ENV_CLIENT', true), FILTER_VALIDATE_BOOLEAN),
        'client_name' => env('PAGOFACIL_CLIENT_NAME', 'Cliente Tienda D&D'),
        'client_document' => env('PAGOFACIL_CLIENT_DOCUMENT', '0000000'),
        'client_phone' => env('PAGOFACIL_CLIENT_PHONE', '70000000'),
        'client_email' => env('PAGOFACIL_CLIENT_EMAIL', 'cliente@tiendadyd.com'),
        'client_code' => env('PAGOFACIL_CLIENT_CODE', 'TIENDADYD'),
    ],

];
