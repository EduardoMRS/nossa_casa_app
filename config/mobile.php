<?php

return [
    'access_token_expiration' => (int) env('MOBILE_ACCESS_TOKEN_EXPIRATION', 60),
    'refresh_token_expiration_days' => (int) env('MOBILE_REFRESH_TOKEN_EXPIRATION_DAYS', 30),
];
