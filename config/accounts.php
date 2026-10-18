<?php

return [
    'super_admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SUPER_ADMIN_EMAILS', ''))
    ))),
    'super_admin_initial_password' => env('SUPER_ADMIN_INITIAL_PASSWORD', 'pokemon14'),
];
