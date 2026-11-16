<?php

return [
    'system_admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SYSTEM_ADMIN_EMAIL', env('SUPER_ADMIN_EMAILS', '')))
    ))),
    'system_admin_initial_password' => env(
        'SYSTEM_ADMIN_INITIAL_PASSWORD',
        env('SUPER_ADMIN_INITIAL_PASSWORD', 'pokemon14')
    ),
];
