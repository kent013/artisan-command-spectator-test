<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SpectatorTest Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for spectatortest class maker.
    |
    */

    'openapi_path' => env('SPECTATORTEST_OPENAPI_PATH', null),
    'namespace' => env('SPECTATORTEST_NAMESPACE', 'Feature'),
];
