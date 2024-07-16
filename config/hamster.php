<?php

return [
    'init_data_raw' => env('HAMSTER_INIT_DATA_RAW', null),
    'fingerprint' => env('HAMSTER_FINGERPRINT', null),
    'spend_percentage' => env('HAMSTER_SPEND_PERCENTAGE', 0.20),
    'min_balance' => env('HAMSTER_MIN_BALANCE', 0),
];
