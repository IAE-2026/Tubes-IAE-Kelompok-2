<?php

return [

    'default' => 'default',

    'documentations' => [

        'default' => [

            'api' => [
                'title' => 'Invoice-Winner Service API',
            ],

            'routes' => [
                'api' => 'api/documentation',
            ],

            'paths' => [

                'use_absolute_path' => true,

                'swagger_ui_assets_path' => 'vendor/swagger-api/swagger-ui/dist/',

                'docs_json' => 'api-docs.json',

                'docs_yaml' => 'api-docs.yaml',

                'format_to_use_for_docs' => 'json',

                'annotations' => [
                    base_path('app'),
                ],
            ],
        ],
    ],

    'defaults' => [

        'routes' => [

            'docs' => 'docs',

            'oauth2_callback' => 'api/oauth2-callback',

            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [],
                'oauth2' => [],
            ],

            'group_options' => [],
        ],

        'paths' => [

            'docs' => storage_path('api-docs'),

            'views' => resource_path('views/vendor/l5-swagger'),

            'base' => null,

            'excludes' => [],
        ],

        'scanOptions' => [
            'default_processors_configuration' => [],
            'analyser' => null,
            'analysis' => null,
            'processors' => [],
            'pattern' => null,
            'exclude' => [],
        ],

        'securityDefinitions' => [
            'securitySchemes' => [],
            'security' => [],
        ],

        'generate_always' => true,

        'generate_yaml_copy' => false,

        'proxy' => false,

        'additional_config_url' => null,

        'operations_sort' => null,

        'validator_url' => null,

        'ui' => [
            'display' => [],
            'authorization' => [],
        ],

        'constants' => [],
    ],
];