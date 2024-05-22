<?php
/*
Plugin Name: Bkash PGW Live API
Plugin URI:  https://t.me/ADNANiTUNE
Description: Integrate bKash payment gateway with WordPress and WooCommerce.
Version:     1.0
Author:      ADNANiTUNE
Author URI:  https://t.me/Adnan8368
License:     GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'init_custom_bkash_gateway');

function init_custom_bkash_gateway() {
    if (class_exists('WC_Payment_Gateway')) {

        class WC_Gateway_Custom_bKash extends WC_Payment_Gateway {
            
            public function __construct() {
                $this->id = 'custom_bkash';
                $this->method_title = __('Bkash Sendbox Live API', 'custom-bkash');
                $this->method_description = __('Bkash Sendbox Live API for WooCommerce', 'custom-bkash');
                $this->has_fields = true;

                // Load settings.
                $this->init_form_fields();
                $this->init_settings();

                // Define user settings.
                $this->title = $this->get_option('gateway_title');
                $this->api_key = $this->get_option('api_key');
                $this->api_secret = $this->get_option('api_secret');
                $this->api_username = $this->get_option('api_username');
                $this->api_password = $this->get_option('api_password');
                $this->api_token = $this->get_option('api_token');
                $this->create_payment = $this->get_option('create_payment');
                $this->execute_payment = $this->get_option('execute_payment');

                // Add hooks.
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_api_custom_bkash', array($this, 'callback_handler'));
            }

            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => __('Enable/Disable', 'custom-bkash'),
                        'type'    => 'checkbox',
                        'label'   => __('Enable Bkash Sendbox Live API', 'custom-bkash'),
                        'default' => 'yes',
                    ),
                    'gateway_title' => array(
                        'title'       => __('Title', 'custom-bkash'),
                        'type'        => 'text',
                        'description' => __('Title shown at checkout.', 'custom-bkash'),
                        'default'     => __('Bkash Sendbox Live API', 'custom-bkash'),
                        'desc_tip'    => true,
                    ),
                    'api_username' => array(
                        'title'       => __('API Username', 'custom-bkash'),
                        'type'        => 'text',
                        'description' => __('Username for API access.', 'custom-bkash'),
                        'default'     => '',
                    ),
                    'api_password' => array(
                        'title'       => __('API Password', 'custom-bkash'),
                        'type'        => 'password',
                        'description' => __('Password for API access.', 'custom-bkash'),
                        'default'     => '',
                    ),
                    'api_key' => array(
                        'title'       => __('API Key', 'custom-bkash'),
                        'type'        => 'text',
                        'description' => __('API key for authentication.', 'custom-bkash'),
                        'default'     => '',
                    ),
                    'api_secret' => array(
                        'title'       => __('API Secret', 'custom-bkash'),
                        'type'        => 'text',
                        'description' => __('API secret for authentication.', 'custom-bkash'),
                        'default'     => '',
                    ),
                    'api_token' => array(
                        'title'       => __('API Token', 'custom-bkash'),
                        'type'        => 'text',
                        'description' => __('Token for API calls.', 'custom-bkash'),
                        'default'     => '',
                    ),
                    'create_payment' => array(
                        'title'       => __('Payment Creation', 'custom-bkash'),
                        'type'        => 'text',
                        'description' => __('Payment creation endpoint.', 'custom-bkash'),
                        'default'     => '',
                    ),
                    'execute_payment' => array(
                        'title'       => __('Payment Execution', 'custom-bkash'),
                        'type'        => 'text',
                        'description' => __('Payment execution endpoint.', 'custom-bkash'),
                        'default'     => '',
                    ),
                );
            }

            public function process_payment($order_id) {
                $order = wc_get_order($order_id);
                $payment_url = $this->create_payment_url($order, $order_id);

                if (!$payment_url) {
                    wc_add_notice(__('Error processing checkout. Please try again.', 'custom-bkash'), 'error');
                    return;
                }

                $order->update_status('pending', __('Awaiting payment', 'custom-bkash'));
                $order->add_meta_data('custom_bkash_payment_url', $payment_url);
                $order->save();

                return array(
                    'result' => 'success',
                    'redirect' => $payment_url
                );
            }

            public function create_payment_url($order, $order_id) {
                $username = $this->get_option('api_username');
                $password = $this->get_option('api_password');
                $api_key = $this->get_option('api_key');
                $api_secret = $this->get_option('api_secret');

                $headers = array(
                    'Content-Type' => 'application/json',
                    'username' => $username,
                    'password' => $password
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
                    error_log('Error granting token: ' . $response->get_error_message());
                    return false;
                } else {
                    $response_body = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($response_body['id_token'])) {
                        $api_token = $response_body['id_token'];
                    } else {
                        error_log('Error: id_token not found in response');
                        return false;
                    }

                    $this->update_option('api_token', $api_token);

                    $headers = array(
                        'Authorization' => 'Bearer ' . $api_token,
                        'X-APP-Key' => $api_key,
                        'Content-Type' => 'application/json'
                    );

                    $order_data = $order->get_data();
                    $data = array(
                        'callbackURL' => get_option('siteurl') . '/wc-api/custom_bkash?order_id=' . $order_id,
                        'mode' => '0011',
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
                        error_log('Error creating payment: ' . $response->get_error_message());
                        return false;
                    } else {
                        $response_body = json_decode(wp_remote_retrieve_body($response), true);
                        if (isset($response_body['bkashURL'])) {
                            $this->update_option('create_payment', wp_remote_retrieve_body($response));
                            return $response_body['bkashURL'];
                        } else {
                            error_log('Error: bkashURL not found in response');
                            return false;
                        }
                    }
                }
            }

            public function callback_handler() {
                $api_key = $this->get_option('api_key');
                $order_id = $_GET['order_id'];
                $order = wc_get_order($order_id);

                $api_token = $this->get_option('api_token');
                if (!$order) {
                    wp_die(__('Invalid order.', 'custom-bkash'));
                }

                if (!$this->verify_callback($_GET)) {
                    wp_die(__('Invalid callback.', 'custom-bkash'));
                }

                if ($_GET['status'] == 'success') {
                    $headers = array(
                        'Authorization' => $api_token,
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

                    $this->update_option('execute_payment', wp_remote_retrieve_body($response));
                    if (is_wp_error($response)) {
                        error_log('Error executing payment: ' . $response->get_error_message());
                        wp_die(__('Payment execution failed.', 'custom-bkash'));
                    } else {
                        $response_body = json_decode(wp_remote_retrieve_body($response), true);
                        $order->payment_complete();
                        $order->add_order_note(__('Payment completed via Bkash Sendbox Live API. Transaction ID: ' . $response_body['trxID'], 'custom-bkash'));
                        wp_safe_redirect(site_url("/my-account/orders"));
                        exit;
                    }
                }
            }

            public function verify_callback($data) {
                // Implement any additional callback verification logic here
                return true;
            }
        }

        function add_custom_bkash_gateway_class($methods) {
            $methods[] = 'WC_Gateway_Custom_bKash';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'add_custom_bkash_gateway_class');
    }
}
?>
