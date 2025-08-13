<?php
/**
 * @see https://github.com/artesaos/seotools
 */

return [
    'inertia' => env('SEO_TOOLS_INERTIA', false),
    'meta' => [
        /*
         * The default configurations to be used by the meta generator.
         */
        'defaults'       => [
            'title'        => false, // Don't set a default title to avoid duplication
            'titleBefore'  => false, // Put defaults.title before page title, like 'E-commerce Store - Dashboard'
            'description'  => 'Quality motorcycle LED lighting products with fast shipping and excellent customer service. Angel eye headlights, LED lights for motard and lighting accessories.', // set false to total remove
            'separator'    => ' – ',
            'keywords'     => ['led headlights', 'angel eye', 'motard', 'motorcycle led lights', 'motorcycle lights', 'motorcycle lighting', 'front headlights', 'angel eyes led', 'motard accessories', 'bike lights', 'led motorcycle'],
            'canonical'    => 'full', // Set to null or 'full' to use Url::full(), set to 'current' to use Url::current(), set false to total remove
            'robots'       => 'index,follow', // Set to 'all', 'none' or any combination of index/noindex and follow/nofollow
        ],
        /*
         * Webmaster tags are always added.
         */
        'webmaster_tags' => [
            'google'    => null,
            'bing'      => null,
            'alexa'     => null,
            'pinterest' => null,
            'yandex'    => null,
            'norton'    => null,
        ],

        'add_notranslate_class' => false,
    ],
    'opengraph' => [
        /*
         * The default configurations to be used by the opengraph generator.
         */
        'defaults' => [
            'title'       => false, // Don't set default to avoid duplication
            'description' => 'Quality motorcycle LED lighting products with fast shipping and excellent customer service. Angel eye headlights, LED lights for motard and lighting accessories.', // set false to total remove
            'url'         => null, // Set null for using Url::current(), set false to total remove
            'type'        => 'website',
            'site_name'   => env('APP_NAME', 'Laravel'),
            'images'      => [],
        ],
    ],
    'twitter' => [
        /*
         * The default values to be used by the twitter cards generator.
         */
        'defaults' => [
            'card'        => 'summary_large_image',
            'site'        => '@' . str_replace(' ', '', strtolower(env('APP_NAME', 'laravel'))),
        ],
    ],
    'json-ld' => [
        /*
         * The default configurations to be used by the json-ld generator.
         */
        'defaults' => [
            'title'       => false, // Don't set default to avoid duplication
            'description' => 'Quality products with fast shipping and excellent customer service. Browse our wide selection of electronics, clothing, home goods and more.', // set false to total remove
            'url'         => null, // Set to null or 'full' to use Url::full(), set to 'current' to use Url::current(), set false to total remove
            'type'        => 'WebPage',
            'images'      => [],
        ],
    ],
];
