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

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class BkashPGW {
    private $baseUrl = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/';
    private $appKey = 'YOUR_APP_KEY';
    private $appSecret = 'YOUR_APP_SECRET';
    private $username = 'YOUR_USERNAME';
    private $password = 'YOUR_PASSWORD';
    private $token;

    public function __construct() {
        add_action('admin_menu', array($this, 'create_admin_menu'));
        $this->grantToken();
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

    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>bKash PGW Settings</h1>';
        // Add your settings form here
        echo '</div>';
    }

    private function request($url, $method = 'POST', $data = null) {
        $ch = curl_init($this->baseUrl . $url);
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

        $ch = curl_init($this->baseUrl . 'token/grant');
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
        $this->token = $responseData['id_token'];
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

        $ch = curl_init($this->baseUrl . 'token/refresh');
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
        $this->token = $responseData['id_token'];
    }
}

// Initialize the plugin
$bkashPGW = new BkashPGW();
?>
