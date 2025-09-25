<?php
/**
 * Plugin Name: Headless Mods
 * Plugin URI: https://github.com/coded-letter/headless-mods-wp-plugin
 * Description: Enhancements for headless WP & WooCommerce sites, including periodic Netlify rebuilds via CRON & webhooks and other practical functions as security and dependency version management.
 * Version: 1.1.2
 * Author: Coded Letter
 * Author URI: https://codedletter.com
 * Text Domain: headless-mods
 * Domain Path: /languages/
 * Requires at least: 5.4.2
 * Requires PHP: 7.3
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH'))
    exit;



// ========================
// 🛒 WooCommerce Order Prefix
// ========================
add_filter('woocommerce_order_number', 'change_woocommerce_order_number');
function change_woocommerce_order_number($order_id)
{
    global $wpdb;

    $prefix = get_option('order_prefix', '_shroom');
    $query = "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'shop_order' ORDER BY ID ASC";
    $result = $wpdb->get_results($query);

    $count = 0;
    foreach ($result as $index => $order) {
        if ($order->ID == $order_id) {
            $count = $index + 1;
            break;
        }
    }

    return $count . $prefix;
}

// ========================
// 🧭 Top Admin Menu Placement
// ========================
add_action('admin_menu', function () {
    add_menu_page(
        'Headless Mods Settings',
        'Headless Mods',
        'manage_options',
        'headless-mods',
        'headless_mods_settings_page',
        'dashicons-admin-site',
        3
    );
});

// ========================
// 🔧 Plugin Settings Page
// ========================
function headless_mods_settings_page()
{
    ?>
    <div class="wrap">
        <h1>Headless Mods Settings</h1>
        <?php if (isset($_GET['build_triggered'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>Manual Netlify rebuild triggered successfully.</p>
            </div>
        <?php endif; ?>


        <h2>Live Netlify Build Status</h2>
        <img id="netlify-status-badge"
            src="https://api.netlify.com/api/v1/badges/<?php echo esc_attr(get_option('netlify_status_badge_id')); ?>/deploy-status"
            width="120">

        <button id="manual-rebuild-btn" style="margin-left: 20px;" class="button button-primary">
            Trigger Manual Rebuild
        </button>

        <script>
            // Refresh badge every minute
            setInterval(() => {
                const badge = document.getElementById('netlify-status-badge');
                if (badge) {
                    badge.src = badge.src.split('?')[0] + '?t=' + Date.now();
                }
            }, 60000);

            // Manual rebuild button logic
            document.getElementById('manual-rebuild-btn').addEventListener('click', function () {
                const btn = this;
                btn.disabled = true;
                btn.textContent = 'Rebuild Triggered...';

                fetch(headlessMods.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'run_frontend_builds',
                        nonce: headlessMods.nonce
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Rebuild triggered successfully!');
                            // Immediately refresh badge
                            const badge = document.getElementById('netlify-status-badge');
                            if (badge) {
                                badge.src = badge.src.split('?')[0] + '?t=' + Date.now();
                            }
                        } else {
                            alert('Error triggering rebuild: ' + data.data);
                        }
                    })
                    .catch(() => alert('Network error while triggering rebuild.'))
                    .finally(() => {
                        setTimeout(() => {
                            btn.disabled = false;
                            btn.textContent = 'Trigger Manual Rebuild';
                        }, 5000);
                    });
            });
        </script>

        <hr>

        <form method="post" action="options.php">
            <?php
            settings_fields('headless_mods_settings');
            do_settings_sections('headless_mods');
            submit_button();
            ?>
        </form>


    </div>
    <?php
}

add_action('admin_init', function () {
    register_setting('headless_mods_settings', 'order_prefix');
    register_setting('headless_mods_settings', 'build_webhook_url');
    register_setting('headless_mods_settings', 'periodic_rebuild_enabled');
    register_setting('headless_mods_settings', 'periodic_rebuild_interval');
    register_setting('headless_mods_settings', 'netlify_status_badge_id');
    register_setting('headless_mods_settings', 'headless_mods_debug_log');

    register_setting('headless_mods_settings', 'headless_frontend_domain');
    register_setting('headless_mods_settings', 'discord_webhook_url');


    add_settings_section('headless_mods_section', 'General Settings', null, 'headless_mods');

    add_settings_field('order_prefix', 'Order Prefix', function () {
        echo '<input type="text" name="order_prefix" value="' . esc_attr(get_option('order_prefix', '_shroom')) . '" class="regular-text">';
    }, 'headless_mods', 'headless_mods_section');

    add_settings_field('build_webhook_url', 'Netlify Build Webhook URL', function () {
        echo '<input type="url" name="build_webhook_url" value="' . esc_attr(get_option('build_webhook_url')) . '" class="regular-text">';
    }, 'headless_mods', 'headless_mods_section');

    add_settings_field('periodic_rebuild_enabled', 'Enable Periodic Rebuild', function () {
        echo '<input type="checkbox" name="periodic_rebuild_enabled" value="1" ' . checked(1, get_option('periodic_rebuild_enabled'), false) . '>';
    }, 'headless_mods', 'headless_mods_section');

    add_settings_field('periodic_rebuild_interval', 'Rebuild Interval (hours)', function () {
        echo '<input type="number" min="1" name="periodic_rebuild_interval" value="' . esc_attr(get_option('periodic_rebuild_interval', 1)) . '" class="small-text">';
    }, 'headless_mods', 'headless_mods_section');

    add_settings_field('netlify_status_badge_id', 'Netlify Badge ID', function () {
        echo '<input type="text" name="netlify_status_badge_id" value="' . esc_attr(get_option('netlify_status_badge_id')) . '" class="regular-text">';
    }, 'headless_mods', 'headless_mods_section');

    add_settings_field('headless_mods_debug_log', 'Enable Debug Logging', function () {
        echo '<input type="checkbox" name="headless_mods_debug_log" value="1" ' . checked(1, get_option('headless_mods_debug_log'), false) . '>';
    }, 'headless_mods', 'headless_mods_section');

    add_settings_field('headless_frontend_domain', 'Frontend Domain', function () {
        echo '<input type="text" name="headless_frontend_domain" value="' . esc_attr(get_option('headless_frontend_domain', 'http://localhost:8888')) . '" class="regular-text">';
        echo '<p class="description">Used to replace localhost in emails or frontend links.</p>';
    }, 'headless_mods', 'headless_mods_section');

    add_settings_field('discord_webhook_url', 'Discord Webhook URL', function () {
        echo '<input type="url" name="discord_webhook_url" value="' . esc_attr(get_option('discord_webhook_url')) . '" class="regular-text">';
        echo '<p class="description">Used to send Discord notifications from the shop.</p>';
    }, 'headless_mods', 'headless_mods_section');


    // Handle manual form trigger
    if (isset($_POST['manual_build'])) {
        do_action('headless_mods_cron_hook', 'manual');
        wp_redirect(add_query_arg('build_triggered', '1', admin_url('admin.php?page=headless-mods')));
        exit;
    }
});

// ========================
// ⚡ AJAX Manual Build Trigger (with nonce verification)
// ========================
add_action('wp_ajax_run_frontend_builds', 'headless_mods_run_frontend_builds');
function headless_mods_run_frontend_builds()
{
    if (isset($_POST['nonce'])) {
        check_ajax_referer('headless_mods_rebuild_nonce', 'nonce');
    }

    $webhook = get_option('build_webhook_url');
    if (empty($webhook)) {
        wp_send_json_error('Build webhook URL not set.');
    }

    $response = wp_remote_post($webhook, [
        'method' => 'POST',
        'timeout' => 20,
    ]);

    if (!is_wp_error($response)) {
        $code = wp_remote_retrieve_response_code($response);
        wp_send_json_success("Rebuild triggered. HTTP $code");
    } else {
        wp_send_json_error('Request error: ' . $response->get_error_message());
    }
}


// ========================
// ⏱ Periodic Cron Rebuild
// ========================
add_action('headless_mods_cron_hook', 'headless_mods_trigger_cron_rebuild', 10, 1);
function headless_mods_trigger_cron_rebuild($source = 'cron')
{
    if (!get_option('periodic_rebuild_enabled'))
        return;

    $webhook = get_option('build_webhook_url');
    if (!$webhook)
        return;

    $response = wp_remote_post($webhook, [
        'method' => 'POST',
        'timeout' => 20,
    ]);

    $debug = get_option('headless_mods_debug_log');
    if (is_wp_error($response)) {
        if ($debug)
            error_log("[Headless Mods] $source error: " . $response->get_error_message());
    } else {
        $code = wp_remote_retrieve_response_code($response);
        if ($debug)
            error_log("[Headless Mods] $source triggered Netlify rebuild: HTTP $code");
    }
}

// ========================
// 🔁 Dynamic Cron Scheduling
// ========================
add_filter('cron_schedules', function ($schedules) {
    $interval = (int) get_option('periodic_rebuild_interval', 1);
    if ($interval < 1)
        $interval = 1;
    $schedules['headless_mods_custom'] = [
        'interval' => $interval * HOUR_IN_SECONDS,
        'display' => "Every $interval hour(s)"
    ];
    return $schedules;
});

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('headless_mods_cron_hook')) {
        wp_schedule_event(time(), 'headless_mods_custom', 'headless_mods_cron_hook');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('headless_mods_cron_hook');
});

add_action('update_option_periodic_rebuild_interval', function () {
    wp_clear_scheduled_hook('headless_mods_cron_hook');
    wp_schedule_event(time(), 'headless_mods_custom', 'headless_mods_cron_hook');
}, 10, 0);

// Add Stripe badge with links dropdown to top admin bar
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $stripe_badge_url = plugins_url('stripe.png', __FILE__);

    // Parent node with dropdown
    $wp_admin_bar->add_node([
        'id' => 'stripe-dashboard-menu',
        'title' => '<img src="' . esc_url($stripe_badge_url) . '" alt="Stripe" style="height:20px; vertical-align:middle; margin-top:2px;" />',
        'href' => 'https://dashboard.stripe.com/dashboard',
        'meta' => [
            'target' => '_blank',
            'title' => 'Go to Stripe Dashboard',
            'class' => 'menupop',
        ],
        'priority' => 1002,
    ]);

    $wp_admin_bar->add_node([
        'id' => 'stripe-transactions',
        'title' => 'Transactions log',
        'href' => 'https://dashboard.stripe.com/payments',
        'parent' => 'stripe-dashboard-menu',
        'meta' => ['class' => 'stripe-submenu-item', 'target' => '_blank'],
    ]);

    $wp_admin_bar->add_node([
        'id' => 'stripe-active-subscribers',
        'title' => 'Active subscribers',
        'href' => 'https://dashboard.stripe.com/subscriptions?status=active',
        'parent' => 'stripe-dashboard-menu',
        'meta' => ['class' => 'stripe-submenu-item', 'target' => '_blank'],
    ]);

    $wp_admin_bar->add_node([
        'id' => 'stripe-subscriptions',
        'title' => 'Subscription products',
        'href' => 'https://dashboard.stripe.com/products?active=true',
        'parent' => 'stripe-dashboard-menu',
        'meta' => ['class' => 'stripe-submenu-item', 'target' => '_blank'],
    ]);
}, 1002);

add_action('admin_enqueue_scripts', function () {
    if (!current_user_can('manage_options')) {
        return;
    }

    wp_add_inline_style('wp-admin', '
        #wp-admin-bar-stripe-dashboard-menu.menupop > .ab-item:after {
            content: " ▼";
            font-size: 10px;
            padding-left: 4px;
        }
        #wp-admin-bar-stripe-dashboard-menu:hover > .ab-submenu {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        #wp-admin-bar-stripe-dashboard-menu > .ab-submenu {
            display: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease-in-out;
            min-width: 160px;
        }
    ');
});

// Add Netlify badge and rebuild dropdown to top admin bar
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $badge_id = get_option('netlify_status_badge_id');
    $webhook = get_option('build_webhook_url');

    if (!$badge_id) {
        return;
    }

    $wp_admin_bar->add_node([
        'id' => 'headless-mods-menu',
        'title' => '<img id="netlify-admin-badge" src="https://api.netlify.com/api/v1/badges/' . esc_attr($badge_id) . '/deploy-status" style="height:20px; vertical-align:middle; cursor:pointer;" />',
        'href' => admin_url('admin.php?page=headless-mods'),
        'meta' => [
            'class' => 'menupop',
            'title' => 'Go to Mods Settings',
        ],
        'priority' => 1000,
    ]);

    if ($webhook) {
        $wp_admin_bar->add_node([
            'id' => 'headless-mods-trigger-rebuild',
            'title' => 'Trigger Frontend Rebuild',
            'parent' => 'headless-mods-menu',
            'href' => '#',
            'meta' => ['class' => 'headless-mods-dropdown-item'],
        ]);
    }

    $wp_admin_bar->add_node([
        'id' => 'headless-mods-go-netlify-dash',
        'title' => 'Netlify dashboard',
        'parent' => 'headless-mods-menu',
        'href' => 'https://app.netlify.com',
        'meta' => ['class' => 'headless-mods-dropdown-item', 'target' => '_blank'],
    ]);
}, 1000);



add_action('admin_enqueue_scripts', function () {
    if (!current_user_can('manage_options'))
        return;

    wp_add_inline_style('wp-admin', '
        #wp-admin-bar-headless-mods-menu.menupop > .ab-item:after {
            content: " ▼";
            font-size: 10px;
            padding-left: 4px;
        }
        #wp-admin-bar-headless-mods-menu:hover > .ab-submenu {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        #wp-admin-bar-headless-mods-menu > .ab-submenu {
            display: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease-in-out;
            min-width: 160px;
        }
        #netlify-admin-badge {
            padding: 0 8px;
        }
    ');
});


add_action('admin_enqueue_scripts', function () {
    if (!current_user_can('manage_options'))
        return;

    wp_enqueue_script('jquery');

    // Expose ajax URL + nonce to JS
    wp_localize_script('jquery', 'headlessMods', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('headless_mods_rebuild_nonce'),
    ]);

    wp_add_inline_script('jquery', "
        jQuery(document).ready(function($){
            var badge = $('#netlify-admin-badge');
            if(badge.length) {
                setInterval(function() {
                    var src = badge.attr('src').split('?')[0];
                    badge.attr('src', src + '?t=' + Date.now());
                }, 60000);
            }

            $('#wp-admin-bar-headless-mods-trigger-rebuild a').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Trigger a frontend rebuild now?')) return;

                $.post(headlessMods.ajaxUrl, {
                    action: 'run_frontend_builds',
                    nonce: headlessMods.nonce
                }, function(response){
                    if(response.success){
                        alert('Success: ' + response.data);
                        // Immediately refresh the badge:
                        var badge = $('#netlify-admin-badge');
                        if (badge.length) {
                            var src = badge.attr('src').split('?')[0];
                            badge.attr('src', src + '?t=' + Date.now());
                        }
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });


        });
    ");


});


add_action('update_option_periodic_rebuild_interval', function () {
    wp_clear_scheduled_hook('headless_mods_cron_hook');
    wp_schedule_event(time(), 'headless_mods_custom', 'headless_mods_cron_hook');
}, 10, 0);




add_action('template_redirect', function () {
    // Frontend domain (no trailing slash)
    $frontend_domain = rtrim(get_option('headless_frontend_domain', 'http://localhost:8888'), '/');

    // Do not redirect for admin
    if (is_admin()) {
        return;
    }

    // Do not redirect for REST API
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    // Do not redirect for previews
    if ((isset($_GET['preview']) && $_GET['preview'] === 'true') || is_preview()) {
        return;
    }

    // Get the requested URI
    $request_uri = $_SERVER['REQUEST_URI'];

    // Build the full URL
    $redirect_url = $frontend_domain . $request_uri;

    // Redirect with 301 (or 302 if temporary)
    wp_redirect($redirect_url, 301);
    exit;
});





// 🔐 Password reset: Replace with custom frontend reset link
add_filter('retrieve_password_message', 'custom_headless_reset_link', 10, 4);
function custom_headless_reset_link($message, $key, $user_login, $user_data)
{
    $frontend_domain = rtrim(get_option('headless_frontend_domain', 'http://localhost:8888'), '/');
    $reset_url = add_query_arg([
        'action' => 'rp',
        'key' => $key,
        'login' => rawurlencode($user_login),
    ], $frontend_domain . '/auth/reset-password');

    $message = "Hi $user_login,\n\n";
    $message .= "To reset your password, click the link below:\n\n";
    $message .= "$reset_url\n\n";
    $message .= "If you didn't request this, you can ignore this message.\n";

    return $message;
}


// 🎯 Replace myaccount URL with frontend /account
add_filter('woocommerce_get_myaccount_page_permalink', 'custom_headless_myaccount_permalink');
function custom_headless_myaccount_permalink($url)
{
    $frontend_domain = rtrim(get_option('headless_frontend_domain', 'http://localhost:8888'), '/');
    return $frontend_domain . '/account';
}

add_filter('woocommerce_get_endpoint_url', 'custom_headless_myaccount_endpoints', 10, 4);
function custom_headless_myaccount_endpoints($url, $endpoint, $value, $permalink)
{
    $frontend_domain = rtrim(get_option('headless_frontend_domain', 'http://localhost:8888'), '/');

    // List of endpoints to redirect to frontend /account
    $account_endpoints = [
        'orders',
        'edit-account',
        'downloads',
        'edit-address',
        'payment-methods',
        'customer-logout',
        'lost-password',
        'view-order',
        // add more if needed
    ];

    if (in_array($endpoint, $account_endpoints, true)) {
        return $frontend_domain . '/account';
    }

    return $url;
}


// 🔁 Redirect view order links to /account on the frontend
add_filter('woocommerce_get_view_order_url', 'custom_headless_view_order_url', 10, 2);
function custom_headless_view_order_url($url, $order)
{
    $frontend_domain = rtrim(get_option('headless_frontend_domain', 'http://localhost:8888'), '/');
    return $frontend_domain . '/account';
}





// 🐞 Optional: Log if emails fail to send
add_action('wp_mail_failed', function ($wp_error) {
    error_log('❌ wp_mail failed: ' . $wp_error->get_error_message());
});








//handle discord notifications
require_once plugin_dir_path(__FILE__) . 'discord-notifications.php';

//handle extra security options
require_once plugin_dir_path(__FILE__) . 'headless-security.php';






// This handles form submissions to discord also from the frontend if enabled
add_action('rest_api_init', function () {
    register_rest_route(
        'custom/v1',
        '/receive-data',
        array(
            'methods' => 'POST',
            'callback' => 'handle_post_request',
            'permission_callback' => '__return_true',
        )
    );
});

function handle_post_request($request)
{
    $data = $request->get_params();
    // Process the data as needed
    $result = process_data_and_send_to_discord($data);
    return new WP_REST_Response($result, 200);
}


function process_data_and_send_to_discord($data)
{
    // Extract the "data" object and "form_name"
    $form_data = isset($data['data']) ? $data['data'] : [];
    $form_name = isset($data['form_name']) ? $data['form_name'] : 'Unknown Form';
    error_log(print_r($form_data, true));
    // Remove the "user_agent" and "referrer" fields
    unset($form_data['user_agent']);
    unset($form_data['referrer']);
    unset($form_data['ip']);

    // Prepare fields for the embed
    $fields = [];
    foreach ($form_data as $key => $value) {
        $fields[] = [
            'name' => ucfirst($key),
            'value' => $value,
            'inline' => false
        ];
    }

    // Create the embed structure
    $embed = [
        'title' => 'Form Submission',
        'description' => "**Form Name**: $form_name",
        'fields' => $fields,
        'color' => 7506394, // Optional: color for the embed
    ];

    // Prepare the payload for Discord
    $webhook_url = get_option('discord_webhook_url');
    $payload = json_encode([
        'embeds' => [$embed]
    ]);

    // Send the data to Discord
    $response = wp_remote_post($webhook_url, [
        'body' => $payload,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        error_log(print_r($response, true));
        return ['status' => 'error', 'message' => $response->get_error_message()];
    } else {
        return ['status' => 'success', 'message' => 'Data sent to Discord'];
    }
}



/* Restrict to save from errors and let smooth processing */
function restrict_upload_size_to_690kb($file)
{
    $max_size_kb = 690; // maximum size in kilobytes
    $max_size_bytes = $max_size_kb * 1024;

    if ($file['size'] > $max_size_bytes) {
        $file['error'] = 'This file exceeds the maximum upload size of 690KB.';
    }

    return $file;
}


// Map this to choices also in the future
// add_filter('wp_handle_upload_prefilter', 'restrict_upload_size_to_690kb');


require_once __DIR__ . '/headless-manager.php';


