<?php

use Tgallice\FBMessenger\Model\Button\Postback;

return [
    'get_started' => 'GET_STARTED',
    'greeting_text' => [
        'default' => '',
        'localized' => [
            [
                'locale' => 'en_US',
                'text' => 'en.lang.settings.greeting'
            ],
            [
                'locale' => 'ar_AR',
                'text' => 'ar.lang.settings.greeting'
            ],
        ]
    ],
    'persistent_menu' => [
        [
            'locale' => 'default',
            'composer_input_disabled' => false,
            'call_to_actions' => [
                // new Postback('تغيير اللغة إلى English', 'CHANGE_LANGUAGE'),
                new Postback('ar.lang.settings.get_started', 'GET_STARTED'),
            ]
        ],
        [
            'locale' => 'en_US',
            'composer_input_disabled' => false,
            'call_to_actions' => [
                // new Postback('Change language to العربية', 'CHANGE_LANGUAGE'),
                new Postback('en.lang.settings.get_started', 'GET_STARTED'),
            ]
        ],
        [
            'locale' => 'ar_AR',
            'composer_input_disabled' => false,
            'call_to_actions' => [
                // new Postback('تغيير اللغة إلى English', 'CHANGE_LANGUAGE'),
                new Postback('ar.settings.get_started', 'GET_STARTED'),
            ]
        ],
    ]
];
