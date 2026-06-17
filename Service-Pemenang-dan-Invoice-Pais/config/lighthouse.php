<?php

return [

    'route' => [
        'uri'        => '/graphql',
        'name'       => 'graphql',
        'middleware' => [],
    ],

    'schema' => [
        'register' => base_path('graphql/schema.graphql'),
    ],

    'schema_cache' => [
        'enable' => env('LIGHTHOUSE_CACHE_ENABLE', false),
        'key'    => env('LIGHTHOUSE_CACHE_KEY', 'lighthouse-schema'),
    ],

    'query_cache' => [
        'enable' => env('LIGHTHOUSE_QUERY_CACHE_ENABLE', true),
        'ttl'    => env('LIGHTHOUSE_QUERY_CACHE_TTL', null),
    ],

    'namespaces' => [
        'models'        => ['App', 'Models'],
        'queries'       => 'App\\GraphQL\\Queries',
        'mutations'     => 'App\\GraphQL\\Mutations',
        'subscriptions' => 'App\\GraphQL\\Subscriptions',
        'interfaces'    => 'App\\GraphQL\\Interfaces',
        'unions'        => 'App\\GraphQL\\Unions',
        'scalars'       => 'App\\GraphQL\\Scalars',
        'directives'    => ['App\\GraphQL\\Directives'],
        'validators'    => 'App\\GraphQL\\Validators',
    ],

    'security' => [
        'max_query_complexity' => 0,
        'max_query_depth'      => 0,
    ],

    'pagination' => [
        'default_count' => 10,
        'max_count'     => 100,
    ],

    'debug' => env(
        'LIGHTHOUSE_DEBUG',
        \GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE
    ),

    'error_handlers' => [
        \Nuwave\Lighthouse\Execution\AuthenticationErrorHandler::class,
        \Nuwave\Lighthouse\Execution\AuthorizationErrorHandler::class,
        \Nuwave\Lighthouse\Execution\ValidationErrorHandler::class,
        \Nuwave\Lighthouse\Execution\ReportingErrorHandler::class,
    ],

    'field_middleware' => [
        \Nuwave\Lighthouse\Schema\Directives\TrimDirective::class,
        \Nuwave\Lighthouse\Schema\Directives\ConvertEmptyStringsToNullDirective::class,
        \Nuwave\Lighthouse\Schema\Directives\SanitizeDirective::class,
        \Nuwave\Lighthouse\Validation\ValidateDirective::class,
        \Nuwave\Lighthouse\Schema\Directives\TransformArgsDirective::class,
        \Nuwave\Lighthouse\Schema\Directives\SpreadDirective::class,
        \Nuwave\Lighthouse\Schema\Directives\RenameArgsDirective::class,
    ],

    'global_id_field' => 'id',

    'transactional_mutations' => true,

    'force_fill' => true,

    'batchload_relations' => true,

    'batch_loading' => true,
];