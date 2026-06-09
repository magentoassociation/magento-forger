<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

/*
 * Content configuration for the contributor call-to-action homepage.
 *
 * `paths` and `areas` reference GitHub issue labels by their exact name. Live open
 * counts are looked up from the same OpenSearch data the Forger backend ingests, and
 * outbound GitHub filter URLs are generated from the label string (see GitHubLinkHelper),
 * so there is a single source of truth and nothing to hand-encode.
 */
return [
    // Section 3 — contributor entry path(s). Each path maps to one workflow label.
    // Only "Ready to code" is offered today. Re-enable the commented paths below to turn
    // this back into a "Choose how you want to help" multi-path choice (the view adapts
    // automatically: heading, copy, and layout switch on the number of active paths).
    'paths' => [
        [
            'icon' => '🛠',
            'title' => 'Ready to code',
            'blurb' => 'Confirmed, prioritized issues waiting for a developer.',
            'cta' => 'Browse Ready for Work',
            'label' => 'Issue: Ready for Work',
        ],
        // [
        //     'icon' => '🔍',
        //     'title' => 'Help us triage',
        //     'blurb' => 'Reported issues that need someone to reproduce and confirm. '
        //         .'No fix required — a great first step.',
        //     'cta' => 'Browse issues awaiting confirmation',
        //     'label' => 'Issue: ready for confirmation',
        // ],
        // [
        //     'icon' => '💬',
        //     'title' => 'Move issues forward',
        //     'blurb' => 'Stalled issues waiting on more detail.',
        //     'cta' => 'Browse issues needing an update',
        //     'label' => 'Issue: needs update',
        // ],
    ],

    // Section 4 — "Pick your area". Curated allowlist of component labels; counts are live.
    // Any label that resolves to zero open issues is skipped at render time.
    'areas' => [
        'Area: Framework',
        'Area: Catalog',
        'Area: Product',
        'Area: Cart & Checkout',
        'Area: Order',
        'Area: Admin UI',
        'Area: Content',
        'Area: APIs',
        'Area: UI Framework',
        'Area: Performance',
    ],

    // Section 6 — "First time contributing?" onboarding links.
    'links' => [
        'contributing' => 'https://github.com/magento/magento2/blob/2.4-develop/.github/CONTRIBUTING.md',
        'dev_setup' => 'https://developer.adobe.com/commerce/contributor/guides/',
    ],
];
