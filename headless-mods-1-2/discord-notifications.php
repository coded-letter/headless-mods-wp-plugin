<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * REST API routes
 */
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/receive-data', [
        'methods' => 'POST',
        'callback' => 'headless_discord_handle_form_submission',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('custom/v1', '/order-log', [
        'methods' => 'POST',
        'callback' => 'headless_discord_handle_order_log',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('custom/v1', '/error-log', [
        'methods' => 'POST',
        'callback' => 'headless_discord_handle_error_log',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Generic form submission handler
 */
function headless_discord_handle_form_submission($request)
{
    $data = $request->get_params();
    $form_data = $data['data'] ?? [];
    $form_name = sanitize_text_field($data['form_name'] ?? 'Unknown Form');

    // Clean up unnecessary fields
    unset($form_data['user_agent'], $form_data['referrer'], $form_data['ip']);

    $fields = [];
    foreach ($form_data as $key => $value) {
        $fields[] = [
            'name' => ucfirst($key),
            'value' => sanitize_text_field($value),
            'inline' => false,
        ];
    }

    return send_to_discord(
        'Form Submission',
        "**Form Name**: $form_name",
        $fields
    );
}

/**
 * Order log handler
 */
function headless_discord_handle_order_log(WP_REST_Request $request)
{
    $data = $request->get_json_params();
    $order_id = sanitize_text_field($data['input']['transactionId'] ?? '');

    if (empty($order_id) || get_transient('order_logged_' . $order_id)) {
        return new WP_REST_Response(['status' => 'duplicate'], 200);
    }

    $fields = format_order_fields($data);
    set_transient('order_logged_' . $order_id, true, 12 * HOUR_IN_SECONDS);

    return send_to_discord('New Order Detected', 'Order successfully paid.', $fields);
}

/**
 * Error log handler
 */
function headless_discord_handle_error_log(WP_REST_Request $request)
{
    $data = $request->get_json_params();
    $order_id = sanitize_text_field($data['input']['transactionId'] ?? '');

    if (empty($order_id) || get_transient('error_logged_' . $order_id)) {
        return new WP_REST_Response(['status' => 'duplicate'], 200);
    }

    $error_message = sanitize_text_field($data['errorMessage'] ?? 'Unknown error');
    $debug_message = sanitize_text_field($data['debugMessage'] ?? 'Unknown debug');

    $fields = format_order_fields($data);
    array_unshift($fields,
        ['name' => 'Error Message', 'value' => $error_message, 'inline' => true],
        ['name' => 'Debug Message', 'value' => $debug_message, 'inline' => true]
    );

    set_transient('error_logged_' . $order_id, true, 12 * HOUR_IN_SECONDS);

    return send_to_discord('Checkout Error Detected', 'A checkout error occurred.', $fields);
}

/**
 * Sends a payload to the configured Discord webhook
 */
function send_to_discord($title, $description, $fields)
{
    $webhook_url = get_option('discord_webhook_url');
    if (!$webhook_url) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'Discord webhook not configured'], 200);
    }

    $embed = [
        'title' => sanitize_text_field($title),
        'description' => sanitize_text_field($description),
        'fields' => $fields,
        'color' => 7506394,
        'timestamp' => current_time('mysql', true),
    ];

    $payload = json_encode(['embeds' => [$embed]]);

    $response = wp_remote_post($webhook_url, [
        'body' => $payload,
        'headers' => ['Content-Type' => 'application/json'],
    ]);

    if (is_wp_error($response)) {
        return new WP_REST_Response(['status' => 'error', 'message' => $response->get_error_message()], 200);
    }

    return new WP_REST_Response(['status' => 'success', 'message' => 'Sent to Discord'], 200);
}

/**
 * Format full order fields for Discord embed
 */
function format_order_fields($data)
{
    $billing = $data['input']['billing'] ?? [];
    $shipping = $data['input']['shipping'] ?? [];
    $products = $data['cart']['products'] ?? [];
    $additional_info = sanitize_text_field($data['additionalInfo'] ?? '');
    $payment_method = sanitize_text_field($data['input']['paymentMethod'] ?? '');
    $transaction_id = sanitize_text_field($data['input']['transactionId'] ?? '');

    $fields = [
        ['name' => 'Payment Method', 'value' => $payment_method, 'inline' => true],
        ['name' => 'Transaction ID', 'value' => $transaction_id, 'inline' => true],
        ['name' => 'Billing Info', 'value' => format_user_info($billing), 'inline' => false],
        ['name' => 'Shipping Info', 'value' => format_user_info($shipping, 'Shipping'), 'inline' => false],
    ];

    if (!empty($products)) {
        $fields[] = ['name' => 'Products', 'value' => format_product_details($products), 'inline' => false];
    }

    if (!empty($additional_info)) {
        $fields[] = ['name' => 'Additional Info', 'value' => $additional_info, 'inline' => false];
    }

    return $fields;
}

/**
 * Formats user details
 */
function format_user_info($info, $type = 'Billing')
{
    return trim(sprintf(
        "Name: %s %s\nAddress: %s, %s, %s\n%sPhone: %s",
        sanitize_text_field($info['firstName'] ?? ''),
        sanitize_text_field($info['lastName'] ?? ''),
        sanitize_text_field($info['address1'] ?? ''),
        sanitize_text_field($info['city'] ?? ''),
        sanitize_text_field($info['country'] ?? ''),
        $type === 'Billing' ? 'Email: ' . sanitize_email($info['email'] ?? '') . "\n" : '',
        sanitize_text_field($info['phone'] ?? '')
    ));
}

/**
 * Formats product info
 */
function format_product_details($products)
{
    $details = '';
    foreach ($products as $product) {
        $details .= sprintf(
            "- **%s** (Qty: %d, Price: %s)\n",
            sanitize_text_field($product['name']),
            intval($product['qty']),
            sanitize_text_field($product['price'])
        );
    }
    return trim($details);
}
