<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PCGamingWiki API URL
    |--------------------------------------------------------------------------
    |
    | URL of the PCGamingWiki MediaWiki API endpoint.
    |
    */
    'api_url' => env('PCGW_API_URL', 'https://www.pcgamingwiki.com/w/api.php'),

    /*
    |--------------------------------------------------------------------------
    | User Agent
    |--------------------------------------------------------------------------
    |
    | The User-Agent header sent with each request to PCGamingWiki API.
    | Please customize in your app's .env with contact details.
    |
    */
    'user_agent' => env('PCGW_USER_AGENT', 'LaravelPCGamingWikiDb/1.0 (+https://example.com; contact@example.com)'),

    /*
    |--------------------------------------------------------------------------
    | API Format
    |--------------------------------------------------------------------------
    |
    | Response format for the MediaWiki API.
    |
    */
    'format' => 'json',

    /*
    |--------------------------------------------------------------------------
    | Query Limit
    |--------------------------------------------------------------------------
    |
    | Number of records to retrieve per API request.
    |
    */
    'limit' => 50,

    /*
    |--------------------------------------------------------------------------
    | Throttle between API requests (milliseconds)
    |--------------------------------------------------------------------------
    |
    | Global delay used by PCGamingWiki jobs to avoid hammering the API.
    | Set via env PCGW_THROTTLE_MS. Defaults to 1000 ms (1s).
    |
    */
    'throttle_milliseconds' => (int) env('PCGW_THROTTLE_MS', 1000),
];
