<?php

return [
    'default_rate_limit_per_minute' => (int) env('API_KEY_DEFAULT_RATE_LIMIT_PER_MINUTE', 60),
    'max_rate_limit_per_minute' => (int) env('API_KEY_MAX_RATE_LIMIT_PER_MINUTE', 120),
];
