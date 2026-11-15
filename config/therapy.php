<?php

return [
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'summary_model' => env('GEMINI_SUMMARY_MODEL', 'gemini-3.6-flash'),
        'transcribe_model' => env('GEMINI_TRANSCRIBE_MODEL', 'gemini-3.6-flash'),
        'max_chunk_bytes' => (int) env('GEMINI_MAX_AUDIO_CHUNK_BYTES', 4_000_000),
    ],

    'updater' => [
        // Javni URL do update.json (priporočeno za nepodpisane builde)
        'manifest_url' => env('UPDATE_MANIFEST_URL'),
        // Osnova za URL-je v update.json, npr. https://example.com/sebi-updates
        'public_base_url' => env('UPDATE_PUBLIC_BASE_URL'),
        'download_dir' => env('UPDATE_DOWNLOAD_DIR'),
        // GitHub Releases (privzeto ta repo)
        'github_owner' => env('GITHUB_OWNER', 'aureliusm'),
        'github_repo' => env('GITHUB_REPO', 'therapy-app'),
        'github_token' => env('GITHUB_TOKEN'),
    ],

    'therapy_types' => [
        'Transakcijska analiza (TA)',
        'Geštalt terapija',
        'Telesna terapija',
        'Integrativna',
        'Drugo',
    ],
];
