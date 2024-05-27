<?php
/*
Plugin Name: BKash PGW Live API
Details URI:  https://www.bkash.com/en/page/tokenized_checkout
Description: Integrate bKash payment gateway with WordPress and WooCommerce.
Version:     1.0
Author:      ADNANiTUNE
Author URI:  https://t.me/ADNANiTUNE
License:     GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'initialize_bkash_pgw_live_api');

function initialize_bkash_pgw_live_api() {
    if (class_exists('WC_Payment_Gateway')) {

        class WC_Gateway_bKash_PGW_Live_API extends WC_Payment_Gateway {

            public function __construct() {
                $this->id = 'bkash_pgw_live_api';
                $this->method_title = __('bKash PGW Live API Gateway', 'bkash-pgw-live-api');
                $this->method_description = __('bKash PGW Live API Gateway for WooCommerce', 'bkash-pgw-live-api');
                $this->has_fields = true;

                // Load settings.
                $this->init_form_fields();
                $this->init_settings();

                // Define user settings.
                $this->title = $this->get_option('gateway_title');
                $this->api_key = $this->get_option('api_key');
                $this->api_secret = $this->get_option('api_secret');
                $this->api_user = $this->get_option('api_user');
                $this->api_pass = $this->get_option('api_pass');
                $this->token = $this->get_option('token');
                $this->payment_create = $this->get_option('payment_create');
                $this->payment_execute = $this->get_option('payment_execute');

                // Add hooks.
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_api_bkash_pgw_live_api', array($this, 'handle_callback'));
            }

            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => __('Enable/Disable', 'bkash-pgw-live-api'),
                        'type'    => 'checkbox',
                        'label'   => __('Enable bKash PGW Live API Gateway', 'bkash-pgw-live-api'),
                        'default' => 'yes',
                    ),
                    'gateway_title' => array(
                        'title'       => __('Title', 'bkash-pgw-live-api'),
                        'type'        => 'text',
                        'description' => __('Title shown at checkout.', 'bkash-pgw-live-api'),
                        'default'     => __('bKash PGW Live API Gateway', 'bkash-pgw-live-api'),
                        'desc_tip'    => true,
                    ),
                    'api_user' => array(
                        'title'       => __('API Username', 'bkash-pgw-live-api'),
                        'type'        => 'text',
                        'description' => __('Username for API access.', 'bkash-pgw-live-api'),
                        'default'     => '',
                    ),
                    'api_pass' => array(
                        'title'       => __('API Password', 'bkash-pgw-live-api'),
                        'type'        => 'password',
                        'description' => __('Password for API access.', 'bkash-pgw-live-api'),
                        'default'     => '',
                    ),
                    'api_key' => array(
                        'title'       => __('API Key', 'bkash-pgw-live-api'),
                        'type'        => 'text',
                        'description' => __('API key for authentication.', 'bkash-pgw-live-api'),
                        'default'     => '',
                    ),
                    'api_secret' => array(
                        'title'       => __('API Secret', 'bkash-pgw-live-api'),
                        'type'        => 'text',
                        'description' => __('API secret for authentication.', 'bkash-pgw-live-api'),
                        'default'     => '',
                    ),
                    'token' => array(
                        'title'       => __('API Token', 'bkash-pgw-live-api'),
                        'type'        => 'text',
                        'description' => __('Token for API calls.', 'bkash-pgw-live-api'),
                        'default'     => '',
                    ),
                    'payment_create' => array(
                        'title'       => __('Payment Creation', 'bkash-pgw-live-api'),
                        'type'        => 'text',
                        'description' => __('Payment creation endpoint.', 'bkash-pgw-live-api'),
                        'default'     => '',
                    ),
                    'payment_execute' => array(
                        'title'       => __('Payment Execution', 'bkash-pgw-live-api'),
                        'type'        => 'text',
                        'description' => __('Payment execution endpoint.', 'bkash-pgw-live-api'),
                        'default'     => '',
                    ),
                );
            }

            public function process_payment($order_id) {
                $order = wc_get_order($order_id);
                $payment_url = $this->generate_payment_url($order, $order_id);

                if (!$payment_url) {
                    wc_add_notice(__('Error processing checkout. Please try again.', 'bkash-pgw-live-api'), 'error');
                    error_log('Error generating payment URL for order ' . $order_id);
                    return array(
                        'result'   => 'failure',
                        'redirect' => ''
                    );
                }

                $order->update_status('pending', __('Awaiting payment', 'bkash-pgw-live-api'));
                $order->add_meta_data('bkash_pgw_live_api_payment_url', $payment_url);
                $order->save();

                return array(
                    'result' => 'success',
                    'redirect' => $payment_url
                );
            }

            public function generate_payment_url($order, $order_id) {
                $api_user = $this->get_option('api_user');
                $api_pass = $this->get_option('api_pass');
                $api_key = $this->get_option('api_key');
                $api_secret = $this->get_option('api_secret');

                $headers = array(
                    'Content-Type' => 'application/json',
                    'username' => $api_user,
                    'password' => $api_pass
                );

                $data = array(
                    'app_key' => $api_key,
                    'app_secret' => $api_secret
                );

                $response = wp_remote_post('https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant', array(
                    'headers' => $headers,
                    'body' => json_encode($data),
                ));

                if (is_wp_error($response)) {
                    error_log('Error granting token for order ' . $order_id . ': ' . $response->get_error_message());
                    return false;
                } else {
                    $response_body = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($response_body['id_token'])) {
                        $api_token = $response_body['id_token'];
                    } else {
                        error_log('Error: id_token not found in response for order ' . $order_id);
                        error_log('Response body: ' . wp_remote_retrieve_body($response));
                        return false;
                    }

                    $this->update_option('token', $api_token);

                    $headers = array(
                        'Authorization' => 'Bearer ' . $api_token,
                        'X-APP-Key' => $api_key,
                        'Content-Type' => 'application/json'
                    );

                    $order_data = $order->get_data();
                    $data = array(
                        'callbackURL' => get_option('siteurl') . '/wc-api/bkash_pgw_live_api?order_id=' . $order_id,
                        'mode' => '0000', // Ensure this is the correct mode
                        'amount' => $order_data['total'],
                        'currency' => 'BDT',
                        'intent' => 'sale',
                        'merchantInvoiceNumber' => $order_id,
                        'payerReference' => $order_id
                    );

                    $response = wp_remote_post('https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create', array(
                        'headers' => $headers,
                        'body' => json_encode($data),
                    ));

                    if (is_wp_error($response)) {
                        error_log('Error creating payment for order ' . $order_id . ': ' . $response->get_error_message());
                        return false;
                    } else {
                        $response_body = json_decode(wp_remote_retrieve_body($response), true);
                        if (isset($response_body['bkashURL'])) {
                            return $response_body['bkashURL'];
                        } else {
                            error_log('Error: bkashURL not found in response for order ' . $order_id);
                            error_log('Response body: ' . wp_remote_retrieve_body($response));
                            return false;
                        }
                    }
                }
            }

            public function handle_callback() {
                $api_key = $this->get_option('api_key');
                $order_id = $_GET['order_id'];
                $order = wc_get_order($order_id);

                $api_token = $this->get_option('token');
                if (!$order) {
                    wp_die(__('Invalid order.', 'bkash-pgw-live-api'));
                }

                if (!$this->verify_callback($_GET)) {
                    wp_die(__('Invalid callback.', 'bkash-pgw-live-api'));
                }

                if ($_GET['status'] == 'success') {
                    $headers = array(
                        'Authorization' => 'Bearer ' . $api_token,
                        'X-APP-Key' => $api_key,
                        'Content-Type' => 'application/json',
                    );

                    $body = array(
                        'paymentID' => $_GET['paymentID'],
                    );

                    $response = wp_remote_post(
                        'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute',
                        array(
                            'method' => 'POST',
                            'timeout' => 20,
                            'redirection' => 5,
                            'httpversion' => '1.1',
                            'blocking' => true,
                            'headers' => $headers,
                            'body' => json_encode($body),
                        )
                    );

                    if (is_wp_error($response)) {
                        error_log('Error executing payment for order ' . $order_id . ': ' . $response->get_error_message());
                        wp_die(__('Payment execution failed.', 'bkash-pgw-live-api'));
                    } else {
                        $response_body = json_decode(wp_remote_retrieve_body($response), true);
                        if (isset($response_body['trxID'])) {
                            $order->payment_complete();
                            $order->add_order_note(__('Payment completed via bKash PGW Live API Gateway. Transaction ID: ' . $response_body['trxID'], 'bkash-pgw-live-api'));
                            wp_safe_redirect($this->get_return_url($order));
                            exit;
                        } else {
                            error_log('Error: trxID not found in response for order ' . $order_id);
                            error_log('Response body: ' . wp_remote_retrieve_body($response));
                            wp_die(__('Payment execution failed.', 'bkash-pgw-live-api'));
                        }
                    }
                } else {
                    error_log('Error: payment status not success for order ' . $order_id);
                    wp_die(__('Payment failed.', 'bkash-pgw-live-api'));
                }
            }

            public function verify_callback($data) {
                // Implement any additional callback verification logic here
                return true;
            }
        }

        function add_bkash_pgw_live_api_gateway_class($methods) {
            $methods[] = 'WC_Gateway_bKash_PGW_Live_API';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'add_bkash_pgw_live_api_gateway_class');
    }
}
?>
