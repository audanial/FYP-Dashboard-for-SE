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
        'student_name' => [
            'student_name',
            'Student Name',
            'Name',
            'Student',
        ],

        'student_id' => [
            'student_id',
            'Student ID',
            'ID',
            'Student No',
            'Matric No',
            'Matric',
        ],

        'title' => [
            'title',
            'Title',
            'Project Title',
            'Project',
        ],

        'supervisor_name' => [
            'supervisor_name',
            'Supervisor Name',
            'Supervisor',
            'SV',
        ],

        'assessor_name' => [
            'assessor_name',
            'Assessor Name',
            'Assessor',
            'Examiner',
        ],

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
