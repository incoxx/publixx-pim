<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SSO Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => (bool) env('SSO_ENABLED', false),

    'auto_provision' => (bool) env('SSO_AUTO_PROVISION', true),

    'default_role' => env('SSO_DEFAULT_ROLE', 'Viewer'),

];
