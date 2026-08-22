<?php

return [
    'kicker' => 'Legal information',
    'title' => 'Privacy policy and terms of use',
    'meta_description' => 'Privacy policy and terms of use for the Nossa Casa platform.',
    'back_to_portal' => 'Back to portal',
    'updated_at' => 'Last updated: August 22, 2026',
    'introduction' => 'Nossa Casa is a community project created to help churches communicate, organize events and serve their members. This page explains how the platform may be used and how information is handled.',
    'sections' => [
        [
            'title' => '1. Acceptance and purpose',
            'paragraphs' => [
                'By accessing Nossa Casa, you agree to use the platform lawfully, respectfully and consistently with the mission of the church or community that operates the space.',
                'The platform may offer public pages, private areas, events, forms, Bible content, children check-in and checkout, comments, media and live streams.',
            ],
            'items' => [],
        ],
        [
            'title' => '2. Information we handle',
            'paragraphs' => [
                'Depending on the features enabled by a church, the platform may handle account details, contact information, church membership, event registrations, form responses, comments, check-in and checkout records, media and technical logs.',
                'Churches are responsible for deciding which information they collect and for using it only for legitimate ministry, administrative and security purposes.',
            ],
            'items' => [
                'We collect only information needed for the feature being used or for the security and operation of the service.',
                'Sensitive information must not be submitted in public comments, public forms or live chat.',
                'Children check-in data must be handled by authorized church workers and used only for care, identification and safe release.',
            ],
        ],
        [
            'title' => '3. Storage, security and access',
            'paragraphs' => [
                'Data is stored by the application operator or by the church hosting its own installation. Media may be stored in S3-compatible storage such as MinIO and delivered through temporary or signed URLs.',
                'We apply access controls, authentication, encrypted secrets and operational safeguards appropriate to the installation. No internet service can guarantee absolute security, so credentials must never be shared or committed to the repository.',
            ],
            'items' => [
                'Church administrators can manage their own records within their authorization scope.',
                'System operators may access infrastructure data when necessary to provide, secure, maintain or recover the service.',
                'Data may be retained while it is needed for the church, legal obligations, backups or security investigations.',
            ],
        ],
        [
            'title' => '4. Live streams, comments and media',
            'paragraphs' => [
                'Live streams, comments and media marked as public may be viewed, copied or redistributed by other people. Do not publish personal, confidential or sensitive information in public areas.',
                'A church or authorized moderator may remove comments, media or streams that violate its rules, these terms or applicable law.',
            ],
            'items' => [],
        ],
        [
            'title' => '5. Bible and third-party content',
            'paragraphs' => [
                'Bible versions and other library materials remain subject to the rights and licenses of their respective authors and providers. Use them only within the permissions shown by the application or the source license.',
                'The project does not claim ownership of third-party content merely because it makes that content available through a feature.',
            ],
            'items' => [],
        ],
        [
            'title' => '6. Availability and changes',
            'paragraphs' => [
                'Nossa Casa is developed as a community project and may change, be interrupted for maintenance or have features enabled or disabled by each installation. No availability or data retention period is guaranteed unless a separate agreement says otherwise.',
                'This policy and these terms may be updated when the project, laws or operational practices change. The localized page is generated from the current source content and may be translated by the project translation workflow.',
            ],
            'items' => [],
        ],
        [
            'title' => '7. Contact and requests',
            'paragraphs' => [
                'For questions, correction requests, deletion requests or concerns about a church installation, contact the church responsible for that installation. For the public project, use the repository issue tracker or the contact channel published by the project maintainers.',
            ],
            'items' => [],
        ],
    ],
];
