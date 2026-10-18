<?php

return [
    'amount' => env('MEMBERSHIP_AMOUNT'),
    'currency_symbol' => env('MEMBERSHIP_CURRENCY_SYMBOL', '$'),
    'period' => env('MEMBERSHIP_PERIOD', 'year'),
];
