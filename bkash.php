<?php
/*
Plugin Name: bKash PGW
Plugin URI:  http://example.com/bkash-pgw
Description: A plugin to integrate bKash PGW with WordPress.
Version:     1.0
Author:      Your Name
Author URI:  http://example.com
License:     GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BkashPGW {
    private $sandboxBaseUrl = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/';
    private $liveBaseUrl = 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout/';
    private $appKey;
    private $appSecret;
    private $username;
    private $password;
    private $token;
    private $useSandbox;
    private $title;

    public function __construct() {
        add_action('admin_menu', array($this, 'create_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        $this->load_settings();
        if ($this->username && $this->password && $this->appKey && $this->appSecret) {
            $this->grantToken();
        }
    }

    public function create_admin_menu() {
        add_menu_page(
            'bKash PGW',
            'bKash PGW',
            'manage_options',
            'bkash-pgw',
            array($this, 'admin_page'),
            'dashicons-admin-generic'
        );
    }

    public function register_settings() {
        register_setting('bkash-pgw-settings-group', 'bkash_enable_sandbox');
        register_setting('bkash-pgw-settings-group', 'bkash_title');
        register_setting('bkash-pgw-settings-group', 'bkash_username');
        register_setting('bkash-pgw-settings-group', 'bkash_password');
        register_setting('bkash-pgw-settings-group', 'bkash_app_key');
        register_setting('bkash-pgw-settings-group', 'bkash_app_secret');
        register_setting('bkash-pgw-settings-group', 'bkash_id_token');
    }

    private function load_settings() {
        $this->useSandbox = get_option('bkash_enable_sandbox');
        $this->title = get_option('bkash_title');
        $this->username = get_option('bkash_username');
        $this->password = get_option('bkash_password');
        $this->appKey = get_option('bkash_app_key');
        $this->appSecret = get_option('bkash_app_secret');
        $this->token = get_option('bkash_id_token');
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->title); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('bkash-pgw-settings-group'); ?>
                <?php do_settings_sections('bkash-pgw-settings-group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Enable Sandbox API</th>
                        <td><input type="checkbox" name="bkash_enable_sandbox" value="1" <?php checked(1, get_option('bkash_enable_sandbox'), true); ?> /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Title</th>
                        <td><input type="text" name="bkash_title" value="<?php echo esc_attr(get_option('bkash_title')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Username</th>
                        <td><input type="text" name="bkash_username" value="<?php echo esc_attr(get_option('bkash_username')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Password</th>
                        <td><input type="password" name="bkash_password" value="<?php echo esc_attr(get_option('bkash_password')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">App Key</th>
                        <td><input type="text" name="bkash_app_key" value="<?php echo esc_attr(get_option('bkash_app_key')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">App Secret</th>
                        <td><input type="text" name="bkash_app_secret" value="<?php echo esc_attr(get_option('bkash_app_secret')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">ID Token</th>
                        <td><input type="text" name="bkash_id_token" value="<?php echo esc_attr(get_option('bkash_id_token')); ?>" readonly /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <h2>Create Payment</h2>
            <form method="post" action="">
                <input type="number" name="bkash_payment_amount" placeholder="Amount" required>
                <input type="text" name="bkash_payment_currency" placeholder="Currency" value="BDT" required>
                <button type="submit" name="bkash_create_payment">Create Payment</button>
            </form>

            <?php
            if (isset($_POST['bkash_create_payment'])) {
                $amount = sanitize_text_field($_POST['bkash_payment_amount']);
                $currency = sanitize_text_field($_POST['bkash_payment_currency']);
                $paymentResponse = $this->createPayment($amount, $currency);
                echo '<pre>' . print_r($paymentResponse, true) . '</pre>';
            }
            ?>

            <h2>Execute Payment</h2>
            <form method="post" action="">
                <input type="text" name="bkash_payment_id" placeholder="Payment ID" required>
                <button type="submit" name="bkash_execute_payment">Execute Payment</button>
            </form>

            <?php
            if (isset($_POST['bkash_execute_payment'])) {
                $paymentID = sanitize_text_field($_POST['bkash_payment_id']);
                $executeResponse = $this->executePayment($paymentID);
                echo '<pre>' . print_r($executeResponse, true) . '</pre>';
            }
            ?>
        </div>
        <?php
    }

    private function getBaseUrl() {
        return $this->useSandbox ? $this->sandboxBaseUrl : $this->liveBaseUrl;
    }

    private function request($url, $method = 'POST', $data = null) {
        if (!$this->token) {
            $this->grantToken();
        }

        $ch = curl_init($this->getBaseUrl() . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    private function grantToken() {
        $credentials = base64_encode($this->username . ':' . $this->password);

        $ch = curl_init($this->getBaseUrl() . 'token/grant');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . $credentials,
            'app_key: ' . $this->appKey,
            'app_secret: ' . $this->appSecret
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([]));

        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);
        if (isset($responseData['id_token'])) {
            $this->token = $responseData['id_token'];
            update_option('bkash_id_token', $this->token);
        } else {
            $this->token = null;
        }
    }

    public function createPayment($amount, $currency = 'BDT') {
        $data = [
            'amount' => $amount,
            'currency' => $currency,
            'intent' => 'sale'
        ];

        return $this->request('create', 'POST', $data);
    }

    public function executePayment($paymentID) {
        $data = [
            'paymentID' => $paymentID
        ];

        return $this->request('execute', 'POST', $data);
    }

    public function getPaymentStatus($paymentID) {
        return $this->request('payment/status', 'GET', ['paymentID' => $paymentID]);
    }

    public function refundPayment($paymentID, $amount, $currency = 'BDT') {
        $data = [
            'paymentID' => $paymentID,
            'amount' => $amount,
            'currency' => $currency
        ];

        return $this->request('payment/refund', 'POST', $data);
    }

    public function searchTransaction($trxID) {
        return $this->request('general/searchTransaction', 'GET', ['trxID' => $trxID]);
    }

    public function refreshToken() {
        $credentials = base64_encode($this->username . ':' . $this->password);

        $ch = curl_init($this->getBaseUrl() . 'token/refresh');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . $credentials,
            'app_key: ' . $this->appKey,
            'app_secret: ' . $this->appSecret
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([]));

        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);
        if (isset($responseData['id_token'])) {
            $this->token = $responseData['id_token'];
            update_option('bkash_id_token', $this->token);
        } else {
            $this->token = null;
        }
    }
}

// Initialize the plugin
$bkashPGW = new BkashPGW();
?>
