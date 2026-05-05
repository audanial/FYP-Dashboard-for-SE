<?php

/**
 * Header mapping for flexible CSV imports.
 *
 * Each key is the canonical database column name.
 * Each value is an ordered list of CSV header variants that should resolve to it.
 * Resolution is case-insensitive and stops at the first match found.
 */
return [
    'fields' => [
        'domain' => [
            'domain',
            'Domain',
            'Project Domain',
        ],

        'application_type' => [
            'application_type',
            'Type',
            'Application Type',
            'Platform',
        ],

        'fyp_phase' => [
            'fyp_phase',
            'phase',
            'FYP Phase',
            'FYP',
        ],
    ],
];
