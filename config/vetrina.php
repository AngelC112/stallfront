<?php

return [
    'hmac_key' => env('BLOCK_HMAC_KEY', env('APP_KEY')),

    // manual | whatsapp | disabled
    'phone_verification_mode' => env('PHONE_VERIFICATION_MODE', 'manual'),
];
