<?php
// Plugin manager object for Headless MU Manager
return [
    'themes' => [
        [
            'name' => 'Headless Previews',
            'slug' => 'headless-wp-1-1',
            'version' => '1.1.1',
            'author' => 'Coded Letter',
            'url' => 'https://superfunky.pro/wp-setup/themes/headless-wp-1-1.zip',
            'required' => true,
            'allow_update' => true,
        ],
    ],

    'plugins' => [
        // Core Headless Plugin
        [
            'name' => 'Headless Mods',
            'slug' => 'headless-mods-1-2',
            'version' => '1.1.2',
            'author' => 'Coded Letter',
            'url' => 'https://superfunky.pro/wp-setup/plugins/headless-mods-1-2.zip',
            'required' => true,
            'allow_update' => true,
        ],

        // Must-use plugins
        [
            'name' => 'WooCommerce',
            'slug' => 'woocommerce',
            'version' => '9.6.1',
            'author' => 'Automattic',
            'url' => 'https://superfunky.pro/wp-setup/plugins/woocommerce.zip',
            'required' => true,
            'allow_update' => true,
        ],
        [
            'name' => 'WooCommerce Stripe Gateway',
            'slug' => 'woocommerce-gateway-stripe',
            'version' => '9.1.1',
            'author' => 'Stripe',
            'url' => 'https://superfunky.pro/wp-setup/plugins/woocommerce-gateway-stripe.zip',
            'required' => true,
            'allow_update' => true,
        ],
        [
            'name' => 'Advanced Custom Fields',
            'slug' => 'advanced-custom-fields',
            'version' => '6.3.12',
            'author' => 'WP Engine',
            'url' => 'https://superfunky.pro/wp-setup/plugins/advanced-custom-fields.zip',
            'required' => true,
            'allow_update' => true,
        ],
        [
            'name' => 'WP Gatsby',
            'slug' => 'wp-gatsby',
            'version' => '2.3.3',
            'author' => 'GatsbyJS, Jason Bahl, Tyler Barnes',
            'url' => 'https://superfunky.pro/wp-setup/plugins/wp-gatsby.zip',
            'required' => true,
            'allow_update' => false,
        ],
        [
            'name' => 'WPGraphQL',
            'slug' => 'wp-graphql',
            'version' => '1.31.1',
            'author' => 'WPGraphQL',
            'url' => 'https://superfunky.pro/wp-setup/plugins/wp-graphql.zip',
            'required' => true,
            'allow_update' => false,
            'children' => [
                [
                    'name' => 'WPGraphQL for ACF',
                    'slug' => 'wpgraphql-acf',
                    'version' => '2.4.1',
                    'author' => 'WPGraphQL',
                    'url' => 'https://superfunky.pro/wp-setup/plugins/wpgraphql-acf.zip',
                    'required' => true,
                    'allow_update' => false,
                ],
                [
                    'name' => 'JWT Authentication for WPGraphQL',
                    'slug' => 'wp-graphql-jwt-authentication',
                    'version' => '0.7.0',
                    'author' => 'WPGraphQL, Jason Bahl',
                    'url' => 'https://superfunky.pro/wp-setup/plugins/wp-graphql-jwt-authentication-0.7.0.zip',
                    'required' => true,
                    'allow_update' => false,
                ],
                [
                    'name' => 'WPGraphQL WooCommerce (WooGraphQL)',
                    'slug' => 'wp-graphql-woocommerce',
                    'version' => '0.15.0',
                    'author' => 'kidunot89',
                    'url' => 'https://superfunky.pro/wp-setup/plugins/wp-graphql-woocommerce-5.zip',
                    'required' => true,
                    'allow_update' => false,
                ],
            ]
        ],

        // Recommended plugins
        [
            'name' => 'WPGraphQL CORS',
            'slug' => 'wp-graphql-cors',
            'version' => '2.1.1',
            'author' => 'WPGraphQL',
            'url' => 'https://superfunky.pro/wp-setup/plugins/wp-graphql-cors-2.1.1.zip',
            'required' => false,
            'allow_update' => false,
        ],
        [
            'name' => 'WPGraphQL Smart Cache',
            'slug' => 'wpgraphql-smart-cache',
            'version' => '1.3.3',
            'author' => 'WPGraphQL',
            'url' => 'https://superfunky.pro/wp-setup/plugins/wpgraphql-smart-cache.zip',
            'required' => false,
            'allow_update' => false,
        ],
        [
            'name' => 'Yoast SEO',
            'slug' => 'wordpress-seo',
            'author' => 'Yoast',
            'url' => 'https://superfunky.pro/wp-setup/plugins/',
            'required' => false,
            'allow_update' => true,
            'children' => [
                [
                    'name' => 'Yoast for GraphQL',
                    'slug' => 'wpgraphql-yoast-seo',
                    'url' => 'https://superfunky.pro/wp-setup/plugins/wp-graphql-polylang-0.7.0.zip',
                    'required' => false,
                    'allow_update' => false,
                ]
            ]
        ],
        [
            'name' => 'Polylang',
            'slug' => 'polylang',
            'url' => 'https://superfunky.pro/wp-setup/plugins/polylang.zip',
            'required' => false,
            'allow_update' => true,
            'children' => [
                [
                    'name' => 'Polylang 4 Woo',
                    'slug' => 'polylang-woo',
                    'url' => 'https://polylang.pro/downloads/polylang-for-woocommerce/',
                    'required' => false,
                    'allow_update' => false,
                ],
                [
                    'name' => 'PolylangGraphQL',
                    'slug' => 'polylang-graphql',
                    'url' => 'https://superfunky.pro/wp-setup/plugins/wp-graphql-polylang-0.7.0.zip',
                    'required' => false,
                    'allow_update' => false,
                ],
                [
                    'name' => 'ACF Options for Polylang',
                    'slug' => 'acf-options-for-polylang',
                    'url' => 'https://superfunky.pro/wp-setup/plugins/acf-options-for-polylang.zip',
                    'required' => false,
                    'allow_update' => false,
                ],

            ]
        ],
        [
            'name' => 'Mailgun',
            'slug' => 'mailgun',
            'url' => 'https://superfunky.pro/wp-setup/plugins/mailgun.zip',
            'required' => false,
            'allow_update' => true,
        ],
    ],
];

