<?php

return [
    'categories' => [
        'description' => 'Create and edit categories used by events, posts, media, forms, the library, and classrooms.',
        'subtitle' => 'Administration and settings',
        'title' => 'Church categories',
        'types' => [
            'classrooms' => 'Classrooms',
            'events' => 'Events',
            'forms' => 'Forms',
            'library' => 'Library',
            'media' => 'Media',
            'posts' => 'Posts',
        ],
    ],
    'media' => [
        'actions' => ['Open public gallery', 'Go to dashboard'],
        'description' => 'Track approval, edit media, and manage community publications.',
        'stats' => ['Media submitted', 'Pending', 'Approved', 'Rejected'],
        'subtitle' => 'Communication and content',
        'title' => 'Moderate gallery',
    ],
    'modules' => [
        'library' => [
            'actions' => ['Open public home', 'Review highlights'],
            'description' => 'Manage devotional materials, the digital collection, and featured verses.',
            'stats' => ['Library materials', 'Registered verses', 'Content categories'],
            'subtitle' => 'Communication and content',
            'title' => 'Library and verse',
        ],
        'prayer_requests' => [
            'actions' => ['Open my prayers', 'Go to dashboard'],
            'description' => 'Review incoming requests and organize pastoral follow-up.',
            'stats' => ['Requests received', 'Anonymous requests', 'Identified requests'],
            'subtitle' => 'Ministries and members',
            'title' => 'Intercession requests',
        ],
    ],
    'multicongregation' => [
        'stats' => ['Churches', 'Communities', 'Headquarters/branch links'],
    ],
    'prayers' => [
        'stats' => ['Requests sent', 'Church requests'],
    ],
    'users' => [
        'stats' => ['Total users', 'Administrators', 'Members'],
    ],
    'event_registrations' => [
        'filename' => 'registrations',
        'export_generated_at' => 'Generated at :date',
        'individual_export' => 'Complete participant registration',
        'pdf' => [
            'field' => 'Field',
            'value' => 'Information',
            'project_reference' => 'Nossa Casa - open source project',
        ],
        'statuses' => [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'canceled' => 'Canceled',
            'confirmed' => 'Confirmed',
        ],
        'columns' => [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'status' => 'Status',
            'registered_at' => 'Registered at',
        ],
    ],
    'wall' => [
        'stats' => ['Total comments', 'Posts with comments', 'Events with comments'],
    ],
];
