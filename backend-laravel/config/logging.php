<?php

return [
    'default'  => env('LOG_CHANNEL', 'daily'),

    'channels' => [

        // Singolo file (fallback / produzione semplice)
        'single' => [
            'driver'          => 'single',
            'path'            => storage_path('logs/laravel.log'),
            'level'           => env('LOG_LEVEL', 'debug'),
            'formatter'       => Monolog\Formatter\LineFormatter::class,
            'formatter_with'  => [
                'format'                    => "[%datetime%] %level_name% [%channel%]: %message% %context%\n",
                'dateFormat'                => 'Y-m-d H:i:s',
                'allowInlineLineBreaks'     => true,
                'ignoreEmptyContextAndExtra'=> true,
            ],
        ],

        // File rotante giornaliero — simile a DailyRollingFileAppender di log4php
        'daily' => [
            'driver'          => 'daily',
            'path'            => storage_path('logs/areariservata.log'),
            'level'           => env('LOG_LEVEL', 'debug'),
            'days'            => 30,
            'formatter'       => Monolog\Formatter\LineFormatter::class,
            'formatter_with'  => [
                'format'                    => "[%datetime%] %level_name% [%channel%]: %message% %context%\n",
                'dateFormat'                => 'Y-m-d H:i:s',
                'allowInlineLineBreaks'     => true,
                'ignoreEmptyContextAndExtra'=> true,
            ],
        ],

        // Stack: scrive su entrambi i canali
        'stack' => [
            'driver'   => 'stack',
            'channels' => ['daily', 'single'],
        ],
    ],
];
