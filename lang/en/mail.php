<?php

return [
    'welcome' => [
        'subject' => 'Welcome to :brand',
        'preheader' => 'Your account at :brand is ready.',
        'heading' => 'Welcome, :name!',
        'introduction' => 'Your account at :brand has been created successfully.',
        'lines' => [
            'You can now follow news and events, access Bible resources, and participate in the spaces available to your community.',
            'Your optional profile information can be updated at any time from your account settings.',
        ],
        'action' => 'Open :brand',
        'outro' => 'We are glad to have you with us.',
    ],
    'reset_password' => [
        'subject' => 'Reset your password at :brand',
        'preheader' => 'Use the secure link to create a new password.',
        'heading' => 'Reset your password',
        'introduction' => 'We received a request to create a new password for your account.',
        'expiration' => 'For your security, this link expires in :count minutes.',
        'action' => 'Create new password',
        'outro' => 'If you did not request a password reset, you can safely ignore this email.',
    ],
    'church_registration_request' => [
        'subject' => 'New church registration request: :church',
        'preheader' => 'A new church registration request is waiting for review.',
        'heading' => 'New registration request',
        'introduction' => ':requester requested the registration of :church.',
        'community' => 'Community: :community',
        'parent_church' => 'Requested parent church: :church',
        'contact' => 'Contact email: :email',
        'address' => 'Address: :address',
        'language' => 'Default language: :language',
        'languages' => [
            'pt' => 'Portuguese',
            'en' => 'English',
        ],
        'action' => 'Review request',
        'outro' => 'Open the portal to review the information and approve or reject this request.',
    ],
    'footer' => [
        'application' => 'Nossa Casa · Technology serving communities of faith.',
        'church' => ':brand uses Nossa Casa to stay closer to its community.',
        'legal' => 'Privacy policy and terms of use',
    ],
];
