<?php

return [

    'trial_days' => 14,

    'billing' => [
        'payment_method_types' => ['card', 'sepa_debit'],
        'plans' => [
            'monthly' => [
                'key' => 'monthly',
                'name' => 'Monatlich',
                'label' => 'Monatlich flexibel',
                'price' => '19,99 €',
                'interval' => 'Monat',
                'description' => 'Für Vereine, die flexibel starten möchten.',
                'stripe_price_id' => env('STRIPE_PRICE_MONTHLY', 'price_1TMm3iLTnGBaGb0l8O7P19vr'),
            ],
            'yearly' => [
                'key' => 'yearly',
                'name' => 'Jährlich',
                'label' => 'Jährlich sparen',
                'price' => '199,00 €',
                'interval' => 'Jahr',
                'description' => 'Einmal jährlich zahlen und gegenüber monatlicher Zahlung sparen.',
                'badge' => '40,88 € sparen',
                'stripe_price_id' => env('STRIPE_PRICE_YEARLY', 'price_1U3A51LTnGBaGb0lp3MVF2it'),
            ],
        ],
    ],

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

        'clubano' => [
            'name' => 'Clubano',
            'member_limit' => null,
            'stripe_price_ids' => [
                env('STRIPE_PRICE_MONTHLY', 'price_1TMm3iLTnGBaGb0l8O7P19vr'),
                env('STRIPE_PRICE_YEARLY', 'price_1U3A51LTnGBaGb0lp3MVF2it'),
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
        'version' => env('CLUBANO_UPDATE_NOTICE_VERSION', '2026-08-25'),
        'title' => 'Clubano wurde aktualisiert',
        'summary' => 'Wir haben die Bereiche Beiträge, Rechnungen, Bankumsätze, Gutscheine, Kommunikation und Kalender spürbar verbessert. Viele Abläufe sind jetzt klarer, sicherer und schneller.',
        'items' => [
            'Beiträge: fehlende Beitragsmodelle, nächste Abrechnung und Familienzahler sind leichter erkennbar',
            'Rechnungen: Teilzahlungen, Überzahlungen und 0,00-Euro-Rechnungen werden sauberer behandelt',
            'Bankumsätze: CAMT/CSV-Import, Gegenkonto-Zuordnung, Belege, Verträge und Clubano-Rechnungen als Nachweis',
            'Gutscheine: PDF-Gutscheine mit Code, Widmung und verbesserter Darstellung',
            'Kommunikation: HTML-Mails mit Editor, Anhängen, Vorlagen und Testmail vor dem Versand',
            'Kalender und Dashboard: ruhigere Übersichten, bessere mobile Darstellung und schnelleres Laden',
        ],
    ],

    // Warnschwelle (in Prozent)
    'warn_threshold_percent' => 95,
];
