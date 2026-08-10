<?php

return [

    'trial_days' => 14,

    /*
    |--------------------------------------------------------------------------
    | Lizenz-Pläne (über Stripe Price IDs)
    |--------------------------------------------------------------------------
    | Mappe Stripe Price IDs auf interne Pläne + Limits.
    | Enterprise = unbegrenzt (null oder sehr hoher Wert)
    */

    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'member_limit' => 25,
            'stripe_price_ids' => [
                'price_1SzzoCLp71a9zFH1jsB4cNfk',
            ],
        ],

        'basic' => [
            'name' => 'Basic',
            'member_limit' => 50,
            'stripe_price_ids' => [
                'price_1Szzp5Lp71a9zFH1LfTtPy2v',
            ],
        ],

        'enterprise' => [
            'name' => 'Enterprise',
            'member_limit' => NULL, // unbegrenzt
            'stripe_price_ids' => [
                'price_1SzzqgLp71a9zFH1xmTy6MOP',
            ],
        ],
    ],

    'registration' => [
        'min_fill_seconds' => env('REGISTRATION_MIN_FILL_SECONDS', 4),
        'email_verification_required_since' => env('EMAIL_VERIFICATION_REQUIRED_SINCE', '2026-07-06 00:00:00'),
        'blocked_email_domains' => [
            'mailinator.com',
            'tempmail.com',
            '10minutemail.com',
            'guerrillamail.com',
            'yopmail.com',
            'trashmail.com',
            'dispostable.com',
            'sharklasers.com',
        ],
        'blocked_name_fragments' => [
            'seo',
            'casino',
            'crypto',
            'loan',
            'backlink',
            'viagra',
            'porn',
            'betting',
            'forex',
        ],
    ],

    'update_notice' => [
        'version' => env('CLUBANO_UPDATE_NOTICE_VERSION', '2026-08-10'),
        'title' => 'Clubano wurde aktualisiert',
        'summary' => 'Wir haben den Finanzbereich deutlich erweitert: Kontenrahmen, Buchungsimporte und Kontostände sind jetzt besser vorbereitet und sicherer nachvollziehbar.',
        'items' => [
            'Kontenrahmen können importiert oder als einfacher Clubano-Standardrahmen angelegt werden',
            'DATEV-Buchungsstapel können importiert und automatisch den richtigen Konten zugeordnet werden',
            'Konten und Buchungskonten lassen sich schneller suchen, filtern und kompakt anzeigen',
            'Kontostände werden zuverlässiger berechnet; negative Bankbestände werden klar rot dargestellt',
            'Vor dem Import eines weiteren Kontenrahmens schützt eine deutliche Warnung vor unbeabsichtigten Änderungen',
            'Ein neuer Wartungsbefehl kann Kontensalden aus abgeschlossenen Buchungen neu berechnen',
        ],
    ],

    // Warnschwelle (in Prozent)
    'warn_threshold_percent' => 95,
];
