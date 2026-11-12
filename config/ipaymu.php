<?php

return [
    /*
    |--------------------------------------------------------------------------
    | iPaymu Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for iPaymu API v2 (Sandbox and Production).
    |
    */
    'env' => env('IPAYMU_ENV', 'sandbox'),
    'va' => env('IPAYMU_VA', '0000007882798449'),
    'api_key' => env('IPAYMU_API_KEY', 'SANDBOX7B6D8133-D6FF-4443-93E0-95E60DA70F29'),
    'base_url' => env('IPAYMU_URL', 'https://sandbox.ipaymu.com/api/v2'),
];
