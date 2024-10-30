<?php
/**
 * The file with BORICA Payments Implementation
 *
 * A class definition that overrides WooCommerce implementation.
 *
 * @link       https://borica.bg
 * @since      1.0.0
 *
 * @package    Borica_Woo_Payment_Gateway
 * @subpackage Borica_Woo_Payment_Gateway/includes
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit();
}
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

class Borica_Woo_Payment_Gateway_Impl extends WC_Payment_Gateway
{

    public static $PLUGIN_ID = "borica_3ds_mpi";

    public static $CHECK_PAYMENT_STATUS_THRESHOLD_IN_HOURS = 24;

    public static $CAN_REFUND_THRESHOLD_IN_DAYS = 30;

    /** @var boolean Whether or not logging is enabled */
    public static $log_enabled = false;

    /** @var WC_Logger Logger instance */
    public static $log = false;

    public static $TEST_MODE_TEXT = "TEST MODE";

    protected static $DEVELOPER_URL = "https://3dsgate-dev.borica.bg/cgi-bin/cgi_link";

    protected static $DEVELOPER_PUBLIC_CERTIFICATE = <<<EOF
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAya0nWBwWR19j/B8STchu
oADV295eP0nd0I3KWIeiiiPV4+xfzqOVguKOt086BrIRLAfTU46dURtwX3PaqiJw
fXa8lpr1kQWCqQH6q/nl6t9A5OOBWF34pFvxgRL64QaQgUTwP+l4sx4p6JFKV41y
itFrgnWaz9X/Y6SXGDTFKcRfDy1FrRTY6g+UTAJtPTUOA8yi53kSK2lO8P3+Bzr1
paBVLjvsSt+uj4Jbz1ssY2IeHqaZm3vW4he6A20Z/ZGE/n1+YQoEqP4NIXVAjrlJ
W+/Z5hvokGWEdf6Fmyz+gA3G+pgVIbiTovW2SgPBy0H6runURtYS6oM3FhPRGJ2Q
uQIDAQAB
-----END PUBLIC KEY-----
EOF;

    protected static $PRODUCTION_URL = "https://3dsgate.borica.bg/cgi-bin/cgi_link";

    protected static $PRODUCTION_PUBLIC_CERTIFICATE = <<<EOF
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA8oqRwrBQKZdO+VPoDHFf
5giPRQkObyvXM8wDDm+kIPhC4gIR8Ch9sFZlQxa8ZE3cCDMsAviub6+RvTtkqy1p
C5abVJQhAIpmIX3NDf82+aD+kGuxIe6JpcFAfKhV0zEr5LzqDYNzhn2huDpv7W+Z
5zUjtwxP5Ob9/Lmw0ckF6XE3drzt0pK26p3ZKRicUh/cGBWQC7bGHpnSnNmvF5Fq
b6PLu6Gzq5RjtSnJG7q8T7DWL5iFVpSFMN0tLbfuCM0ZSc5xodrk84esRm36KMV+
lx3t6HQ1kvs7aQKbGq0TtBAbfQRlYBlgV2DamyOQfH6vMiD179bol4Ss0XvaYWzq
fwIDAQAB
-----END PUBLIC KEY-----
EOF;

    protected static $GATEWAY_ORDER_NUMBER_NAME = 'gateway_order_number';

    /**
     * Logging method
     *
     * @param string $message
     */
    public static function log($message)
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = new WC_Logger();
            }
            self::$log->add(self::$PLUGIN_ID, $message);
        }
    }

    public function __construct()
    {
        self::$TEST_MODE_TEXT = __('TEST MODE', 'borica-payments');
        $this->id = self::$PLUGIN_ID;
        $this->icon = "";
        $this->has_fields = false;
        $this->method_title = __('BORICA Payments', 'borica-payments');
        $this->method_description = __('BORICA Payments Checkout works by redirecting customers to BORICA payment page where they enter their card details. To use this payment option you need to have a virtual POS.', 'borica-payments');
        $this->supports = array(
            'products',
            'refunds'
        );
        $this->version = '1.0';

        $this->notify_url = home_url('?wc-api=' . strtolower(__CLASS__));
        // $this->log("Notify url: " . $this->notify_url);

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->test = 'yes' === $this->get_option('test', 'no');
        $this->debug = 'yes' === $this->get_option('debug', 'no');
        //egeorgiev 20221209
        //$this->title = $this->get_option('title');
        $this->title = __('Pay via Credit/Debit card', 'borica-payments');
        if ($this->test) {
            $this->title = sprintf("%s (%s)", $this->title, self::$TEST_MODE_TEXT);
        }
        //egeorgiev 20221209
        //$this->description = $this->get_option('description');
        $this->description = __('Processed by BORICA', 'borica-payments');
        $this->merchantId = $this->get_option('merchant_id');
        $this->merchantName = $this->get_option('merchant_name');
        $this->terminalId = $this->get_option('terminal_id');

        self::$log_enabled = $this->debug;

        if (! $this->test) {
            // Production settings
            $this->url = self::$PRODUCTION_URL;
            $this->privateKey = $this->get_option('production_private_key');
            $this->privateKeyPassword = $this->get_option('production_private_key_password');
            $this->publicKey = self::$PRODUCTION_PUBLIC_CERTIFICATE;
        } else {
            // Test settings
            $this->url = self::$DEVELOPER_URL;
            $this->privateKey = $this->get_option('developer_private_key');
            $this->privateKeyPassword = $this->get_option('developer_private_key_password');
            $this->publicKey = self::$DEVELOPER_PUBLIC_CERTIFICATE;
        }

        $locale = get_locale();
        $language = substr($locale, 0, 2);
        $this->boricaMpi = new BoricaMpi($language, $this->url, $this->merchantId, $this->merchantName, $this->terminalId, $this->privateKey, $this->privateKeyPassword, $this->publicKey);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'process_admin_options'
        ));

        add_action('woocommerce_api_' . strtolower(__CLASS__), array(
            $this,
            'check_apgw_response'
        ));

        add_action('woocommerce_order_item_add_action_buttons', array(
            $this,
            'check_payment_status_view'
        ));

        add_action('save_post', array(
            $this,
            'check_payment_status_post'
        ));

        add_action('check_keys_post', array(
            $this,
            'check_keys_post'
        ));

        add_action('woocommerce_thankyou_' . self::$PLUGIN_ID, array(
            $this,
            'add_content_thankyou'
        ));        

        if (! $this->is_valid_for_use()) {
            $this->enabled = 'no';
        } else {
            add_action('woocommerce_receipt_' . $this->id, array(
                $this,
                'receipt_page'
            ));
        }
    }
 
    /**
     * Admin Panel Options
     *
     */
    public function admin_options()
    {
        do_action('check_keys_post');
        if ($this->is_available()) {
            if($this->is_in_test_mode()){
                ?>
                <div class="inline error">
                    <p>
                        <strong><?php _e( 'TEST MODE', 'borica-payments' ); ?></strong>: <?php _e('ATTENTION, SYSTEM IS RUNNING IN TEST MODE.', 'borica-payments'); ?>
                    </p>
                </div>
                <?php
            }
        } else {
            ?>
            <div class="inline error">
                <p>
                    <strong><?php _e( 'Gateway Disabled', 'borica-payments' ); ?></strong>: <?php _e('BORICA Payments is disabled and cannot be used.', 'borica-payments'); ?>
                </p>
            </div>
            <?php
        }
        parent::admin_options();
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $form_fields = include ('settings-gateway.php');
        // Set value for back reference URL
        $form_fields['BACKREF_URL']['value'] = $this->notify_url;
        $form_fields['developer_url']['value'] = self::$DEVELOPER_URL;
        $form_fields['production_url']['value'] = self::$PRODUCTION_URL;
        $this->form_fields = $form_fields;
    }   

    /**
     * Get gateway icon.
     *
     * @return string
     */
    public function get_icon()
    {
        $image_name = 'brand-grid.png';

        $icon = WC_HTTPS::force_https_url(plugin_dir_url( __DIR__ ).'assets/public/images/' . $image_name);
        $icon_html = '<img src="' . $icon . '" alt="BORICA Woo Payment Gateway" title="Powered by BORICA AD" />';

        return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @return bool
     */
    public function is_valid_for_use()
    {
        return in_array(get_woocommerce_currency(), apply_filters('woocommerce_apgw_supported_currencies', array(
            'BGN', 'EUR', 'USD'
        )));
    }

    public function is_available()
    {
        return $this->is_valid_for_use() && parent::is_available();
    }

    public function is_in_test_mode()
    {
        if($this->get_option('test')==='yes'){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);

        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    public function can_refund($order) {
        $order_date_created = $order->get_date_created();
        $now = new DateTime();
        $order_created_days_ago = $now->diff($order_date_created)->d;
        $order_status = $order->get_status();
        $this->log(sprintf('Can refund Order: %s - payment method: %s, order status: %s, order created days ago: %s', $order->id, $order->get_payment_method(), $order_status, $order_created_days_ago));
        if ($this->is_available()
            && $order->get_payment_method() == self::$PLUGIN_ID
            && $order_status == 'processing'
            && $order_created_days_ago < self::$CAN_REFUND_THRESHOLD_IN_DAYS){
            return true;
        } else {
            return false;
        }
    }

    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param  int        $order_id Order ID.
     * @param  float|null $amount Refund amount.
     * @param  string     $reason Refund reason.
     * @return boolean True or false based on success, or a WP_Error object.
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = new WC_Order($order_id);
        if($this->can_refund($order)){
            $intRef =  $this->get_order_meta_value($order, 'INT_REF');
            $rrn =  $this->get_order_meta_value($order, 'RRN');

            $boricaMpi = $this->boricaMpi;
            $orderId = $this->getGatewayOrderNumber($order);
            $currency = $order->get_currency();
            $postDataReversal = $boricaMpi->generateReversalFields($orderId, $intRef, $rrn, $amount, $reason, $currency);
            $this->log('Refund request POST: ' . var_export($postDataReversal, true) );
            if(isset($postDataReversal) && !empty($postDataReversal)){
                $checkReversalResponse = $boricaMpi->makePOSTrequest($postDataReversal);
                $this->log('Refund response: ' . var_export($checkReversalResponse, true) );
                if(isset($checkReversalResponse) && !empty($checkReversalResponse)){
                    $signedData = $boricaMpi->getSignedDataFromOrderResponseFields($checkReversalResponse);
                    $signature = $boricaMpi->getSignatureFromOrderResponseFields($checkReversalResponse);
                    $signatureStatus = $boricaMpi->validateSignature($signedData, $signature);
                    if ($signatureStatus == 'VALID') {
                        $testModeText = ($this->test) ? sprintf("(<font color='red'>%s</font>) ", self::$TEST_MODE_TEXT) : "";
                        if ($checkReversalResponse['RC'] == '00' && $checkReversalResponse['ACTION'] == '0') {
                            $this->log("Reversal process success: " . $order->get_order_number() . " with code: " . $checkReversalResponse['RC'] . "\r\nMessage: " . $checkReversalResponse['STATUSMSG']);
                            //$order->update_status('refunded'); // No need when return true
                            $this->log($order->get_total());                       
                            if((strval($checkReversalResponse['AMOUNT'])) == (strval($order->get_total()))){
                                $realNetPaymentAmount = '0.00';
                            }else{
                                $realNetPaymentAmount = $checkReversalResponse['AMOUNT'];
                            }                           
                            $order->add_order_note($testModeText . __('Payment refunded<br/>Approval: ', 'borica-payments') . $checkReversalResponse['APPROVAL'] . __('<br/>Transaction Number: ', 'borica-payments') . $checkReversalResponse['RRN'] . __('<br/>Gateway Order: ', 'borica-payments') . $checkReversalResponse['ORDER'] . __('<br/>Net card payment: ', 'borica-payments') . $realNetPaymentAmount . __('<br/>Internal Reference: ', 'borica-payments') . $checkReversalResponse['INT_REF'] . __('<br/>Reason: ', 'borica-payments') . $checkReversalResponse['STATUSMSG']);
                            $this->add_card_holder_info_note($order, $checkReversalResponse['CARDHOLDERINFO'], $testModeText);
                            return true;
                        } else {
                            $this->log("Failed request for order: " . $order->get_order_number() . " with code: " . $checkReversalResponse['RC'] . "\r\nMessage: " . $checkReversalResponse['STATUSMSG']);
                            //$order->update_status('failed'); // No need to update order status when refund failed
                            $order->add_order_note($testModeText . __('Payment refund declined with code: ', 'borica-payments') . $checkReversalResponse['RC'] . __('<br/>Reason: ', 'borica-payments') . $checkReversalResponse['STATUSMSG']);
                            $this->add_card_holder_info_note($order, $checkReversalResponse['CARDHOLDERINFO'], $testModeText);
                            return false;
                        }
                    } else if ($signatureStatus == 'INVALID') {
                        $this->log("Signature of APGW response is invalid");
                        header('HTTP/1.1 500 Internal Server Error');
                        return false;
                    } else {
                        $this->log("Signature check error: " . $signatureStatus);
                        header('HTTP/1.1 500 Internal Server Error');
                        return false;
                    }
                } else {
                    $this->log("Response from APGW: " . $checkReversalResponse);
                    header('HTTP/1.1 500 Internal Server Error');
                    return false;
                }
            }
        }else{
            return false;
        }
    }

    /**
     * Generate Receipt Page
     *
     * @param int $order_id
     */
    public function receipt_page($order_id)
    {
        global $woocommerce;

        $order = new WC_Order($order_id);
        $this->log("Create post data for orderId: " . $order->get_id() . ", orderNumber: " . $order->get_order_number());
        $boricaMpi = $this->boricaMpi;
        $apgw_RC_value = $this->get_order_meta_value($order, 'RC');
        //egeorgiev 20221207  
        //if ($apgw_RC_value != '' && $apgw_RC_value != '00') {
        //    $this->setGatewayOrderNumber($order, $this->getNextGatewayOrderNumber());
        //}
        if ($apgw_RC_value == '00') {
            $redirect_url = $order->get_checkout_order_received_url();
            wp_redirect($redirect_url);
            exit();
        }
        $this->setGatewayOrderNumber($order, $this->getNextGatewayOrderNumber());
        $orderId = $this->getGatewayOrderNumber($order);
        $description = 'Order Number: ' . $order->get_order_number();
        $amount = $order->get_total();
        $currency = $order->get_currency();
        $country = $order->get_billing_country();
        $custBorOrderId = $order->get_order_number();
        $backReferenceUrl = $this->notify_url;
        $post = $boricaMpi->generateOrderRequestFields($orderId, $description, $amount, $currency, $country, $custBorOrderId, $backReferenceUrl);
        ?>
        <form id="borica_mpi_post" style="display:none" action="<?php echo esc_url($this->url)?>" method="post" name="borica_mpi">
        <?php
        $this->log(esc_url($this->url));
        foreach ($post as $key => $value) {
            $value = htmlspecialchars($value, ENT_QUOTES)
            ?>
         <input type="hidden" name="<?php echo esc_attr($key);?>" value="<?php echo esc_attr($value);?>"/>
        <?php
        $this->log(esc_attr($key).' => '.esc_attr($value));
        } ?>
        </form>
        <button type="submit" class="button wp-element-button" form="borica_mpi_post"><?php echo __('Pay', 'borica-payments'); ?></button>
        <?php
    }

    private function copy_cart($order)
    {
        //error_reporting(E_ALL);
        //ini_set('display_errors', 1);

        if (WC()->cart->get_cart_contents_count()) {
            WC()->cart->empty_cart();
        }

        foreach ($order->get_items() as $product_info) {
            $product_id = (int) apply_filters('woocommerce_add_to_cart_product_id', $product_info['product_id']);
            $qty = (int) $product_info['qty'];
            $all_variations = array();
            $variation_id = (int) $product_info['variation_id'];

            $cart_product_data = apply_filters('woocommerce_order_again_cart_item_data', array(), $product_info, $order);
            foreach ($product_info['item_meta'] as $product_meta_name => $product_meta_value) {

                if (taxonomy_is_product_attribute($product_meta_name)) {
                    $all_variations[$product_meta_name] = ucfirst($product_meta_value);
                } else {
                    if (meta_is_product_attribute($product_meta_name, $product_meta_value, $product_id)) {
                        $all_variations[$product_meta_name] = ucfirst($product_meta_value);
                    }
                }
            }

            // Add to cart validationn
            if (! apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $qty, $variation_id, $all_variations, $cart_product_data)) {
                continue;
            }

            // Checks availability of products
            $array = wc_get_product($product_id);

            // Add to cart order products
            $add_to_cart = WC()->cart->add_to_cart($product_id, $qty, $variation_id, $all_variations, $cart_product_data);
        }
    }

    /**
     * Check for valid ipc response
     */
    public function check_apgw_response()
    {
        $this->log("Notify url request");

        /**
         *
         * @var WooCommerce $woocommerce
         */
        global $woocommerce;

        //$post = $_POST;
        $this->log('POST: ' . var_export($_POST, true));

        $boricaMpi = $this->boricaMpi;
        $signedData = $boricaMpi->getSignedDataFromOrderResponseFields($_POST);
        $signature = $boricaMpi->getSignatureFromOrderResponseFields($_POST);

        $signatureStatus = $boricaMpi->validateSignature($signedData, $signature);
        if ($signatureStatus == 'VALID') {
            $testModeText = ($this->test) ? sprintf("(<font color='red'>%s</font>) ", self::$TEST_MODE_TEXT) : "";
            //egeorgiev 20221206
            //$order_id = ltrim($_POST['ORDER'], "0");
            $order_id = ltrim(hex2bin(substr($_POST['NONCE'],0,24)), "0");
            $order = new WC_Order($order_id);
            $apgw_RC_value = $this->get_order_meta_value($order, 'RC');

            if (sanitize_text_field($_POST['RC']) == '00') {
                $this->log("Successful approval/completion request for order: " . $order->get_order_number());
                //egeorgiev
                $order_status = $order->get_status();

                if ($apgw_RC_value != '00' && $order_status <> 'refunded') { //and ORDER status is not refunded????

                    $this->set_order_meta_value($order, 'RC', sanitize_text_field($_POST['RC']));
                    $this->set_order_meta_value($order, 'RRN', sanitize_text_field($_POST['RRN']));
                    $this->set_order_meta_value($order, 'INT_REF', sanitize_text_field($_POST['INT_REF']));
                    $this->set_order_meta_value($order, 'CARDHOLDERINFO', sanitize_text_field($_POST['CARDHOLDERINFO']));
                    $order->save();

                    $order->payment_complete(sanitize_text_field($_POST['RRN']));

                    $gateway_order_id = ltrim($_POST['ORDER'], "0");
                    $this->setGatewayOrderNumber($order, $gateway_order_id);
                    $order->add_order_note($testModeText . __('Payment approved<br/>Approval: ', 'borica-payments') . sanitize_text_field($_POST['APPROVAL']) . __('<br/>Gateway Order: ', 'borica-payments') . sanitize_text_field($_POST['ORDER']) .__('<br/>Custom Order: ', 'borica-payments') . sanitize_text_field($_POST['ORDER']) . 'ORD@' . $order->get_order_number() . __('<br/>Transaction Number: ', 'borica-payments') . sanitize_text_field($_POST['RRN']) . __('<br/>Internal Reference: ', 'borica-payments') . sanitize_text_field($_POST['INT_REF']) . __('<br/>Reason: ', 'borica-payments') . sanitize_text_field($_POST['STATUSMSG']));
                    $this->add_card_holder_info_note($order, sanitize_text_field($_POST['CARDHOLDERINFO']), $testModeText);
                    $woocommerce->cart->empty_cart();
                }

                $redirect_url = $order->get_checkout_order_received_url();
                wp_redirect($redirect_url);
                exit();
// egeorgiev 20221207
            } else if (sanitize_text_field($_POST['RC']) == '-25') {
                $this->log("Payment cancelled for order: " . $order->get_order_number() . " with code: " . sanitize_text_field($_POST['RC']) . "\r\nMessage: " . sanitize_text_field($_POST['STATUSMSG']));
                //$order->update_status('cancelled');

                $order->add_order_note($testModeText . __('Payment cancelled by the client', 'borica-payments'));
                $this->add_card_holder_info_note($order, sanitize_text_field($_POST['CARDHOLDERINFO']), $testModeText);
                $this->set_order_meta_value($order, 'CARDHOLDERINFO', sanitize_text_field($_POST['CARDHOLDERINFO']));
                $redirect_url = $order->get_cancel_order_url();

                wp_redirect($redirect_url);
                exit();
//
            } else {
                //egeorgiev 20221206
                //$order_status  = $order->get_status();
                $apgw_RC_value = $this->get_order_meta_value($order, 'RC');
                $this->set_order_meta_value($order, 'CARDHOLDERINFO', sanitize_text_field($_POST['CARDHOLDERINFO']));
                if ($apgw_RC_value != '00') { 
                    $gateway_order_id = ltrim($_POST['ORDER'], "0");
                    $this->setGatewayOrderNumber($order, $gateway_order_id);
                    $this->set_order_meta_value($order, 'RC', sanitize_text_field($_POST['RC']));
                    $order->save();
                    if (sanitize_text_field($_POST['RC']) == '-25') {
                        $this->log("Payment cancelled for order: " . $order->get_order_number() . " with code: " . sanitize_text_field($_POST['RC']) . "\r\nMessage: " . sanitize_text_field($_POST['STATUSMSG']));
                        $order->add_order_note($testModeText . __('Payment cancelled by the client', 'borica-payments'));
                        $redirect_url = $order->get_cancel_order_url();
                    } else {
                        $this->log("Failed payment for order: " . $order->get_order_number() . " with code: " . sanitize_text_field($_POST['RC']) . "\r\Gateway Order: " . sanitize_text_field($_POST['ORDER']) . "\r\nMessage: " . sanitize_text_field($_POST['STATUSMSG']));
                        $order->add_order_note($testModeText . __('Payment declined with code: ', 'borica-payments') . sanitize_text_field($_POST['RC']) . __('<br/>Gateway Order: ', 'borica-payments') . sanitize_text_field($_POST['ORDER']) . __('<br/>Reason: ', 'borica-payments') . sanitize_text_field($_POST['STATUSMSG']));
                         $redirect_url = add_query_arg('utm_nooverride', '1', $this->get_return_url($order));
                            }
                    $order->update_status('failed');
                    $this->add_card_holder_info_note($order, sanitize_text_field($_POST['CARDHOLDERINFO']), $testModeText);                   
                }
                wp_redirect($redirect_url);
                exit();
            }

        } else if ($signatureStatus == 'INVALID') {
            $this->log("Signature of APGW response is invalid");

            header('HTTP/1.1 500 Internal Server Error');
            die('Signature of APGW response is invalid');
        } else {
            $this->log("Signature check error: " . $signatureStatus);

            header('HTTP/1.1 500 Internal Server Error');
            die($signatureStatus);
        }
    }

    public function can_check_payment_status($order) {
        $order_date_created = $order->get_date_created();
        $now = new DateTime();
        $order_created_hours_ago = $now->diff($order_date_created)->h;

        $apgw_RC_value = $this->get_order_meta_value($order, 'RC');

        $this->log(sprintf('Can check payment Order: %s - payment method: %s, RC: %s, order created hours ago: %s', $order->id, $order->get_payment_method(), $apgw_RC_value, $order_created_hours_ago) );
        return $this->is_available()
            && $order->get_payment_method() == self::$PLUGIN_ID
            && $apgw_RC_value != '00'
            && $order_created_hours_ago < self::$CHECK_PAYMENT_STATUS_THRESHOLD_IN_HOURS;
    }

    public function check_payment_status_view($order)
    {
        if ($this->can_check_payment_status($order)) {
            ?>
                <button type="submit" name="borica-check-payment-status" class="button borica-check-payment-status" value="1">
                <?php echo __('Update Payment Status', 'borica-payments'); ?>
                </button>
            <?php
        }
    }

    public function check_payment_status_post($order_id) {
        if (array_key_exists('borica-check-payment-status', $_POST) && sanitize_text_field($_POST['borica-check-payment-status'])) {
            $order = new WC_Order($order_id);
            if ($this->can_check_payment_status($order)) {
                $this->check_payment_status($order_id);
            }
        }
    }

    public function check_payment_status($order_id) {
        $this->log('Check status POST: ' . var_export($order_id, true));

        $order = new WC_Order($order_id);
        $boricaMpi = $this->boricaMpi;
        $orderId = $this->getGatewayOrderNumber($order);
        $postDataCheckStatus = $boricaMpi->generateStatusCheckFields($orderId);
        $this->log('Check status POST: ' . var_export($postDataCheckStatus, true));
        if(isset($postDataCheckStatus) && !empty($postDataCheckStatus)){
            $checkStatusResponse = $boricaMpi->makePOSTrequest($postDataCheckStatus);
            $this->log('Check status response: ' . var_export($checkStatusResponse, true));
            if(isset($checkStatusResponse) && !empty($checkStatusResponse)){
                 $signedData = $boricaMpi->getSignedDataFromOrderResponseFields($checkStatusResponse);
                 $signature = $boricaMpi->getSignatureFromOrderResponseFields($checkStatusResponse);
                 $signatureStatus = $boricaMpi->validateSignature($signedData, $signature);
                 if ($signatureStatus == 'VALID') {
                     $testModeText = ($this->test) ? sprintf("(<font color='red'>%s</font>) ", self::$TEST_MODE_TEXT) : "";
                     $currentOrderRCvalue = $this->get_order_meta_value($order, 'RC');
                     $this->log('Current order RC: ' . $currentOrderRCvalue);
                     if ($currentOrderRCvalue == '' || ($currentOrderRCvalue != '00' && intval($checkStatusResponse['RC']) > intval($currentOrderRCvalue))) {
                         $this->set_order_meta_value($order, 'RC', $checkStatusResponse['RC']);
                     }
                     $this->set_order_meta_value($order, 'RRN', $checkStatusResponse['RRN']);
                     $this->set_order_meta_value($order, 'INT_REF', $checkStatusResponse['INT_REF']);
                     $this->set_order_meta_value($order, 'CARDHOLDERINFO', $checkStatusResponse['CARDHOLDERINFO']);
                     $order->save();
                     if ($checkStatusResponse['RC'] == '00') {
                         $this->log("Successful approval/completion request for order: " . $order->get_order_number());
                         $order->payment_complete($checkStatusResponse['RRN']);
                         //egeorgiev 20221209
                         $order->add_order_note($testModeText . __('Payment approved<br/>Approval: ', 'borica-payments') . $checkStatusResponse['APPROVAL'] . __('<br/>Gateway Order: ', 'borica-payments') . $checkStatusResponse['ORDER'] . __('<br/>Custom Order: ', 'borica-payments') . $checkStatusResponse['ORDER'] . 'ORD@' . $order->get_order_number() . __('<br/>Transaction Number: ', 'borica-payments') . $checkStatusResponse['RRN'] . __('<br/>Internal Reference: ', 'borica-payments') . $checkStatusResponse['INT_REF'] . __('<br/>Reason: ', 'borica-payments') . $checkStatusResponse['STATUSMSG']);
                         //$order->add_order_note($testModeText . __('Payment approved<br/>Approval: ', 'borica-payments') . $post['APPROVAL'] . __('<br/>Gateway Order: ', 'borica-payments') . $post['ORDER'] .__('<br/>Custom Order: ', 'borica-payments') .$post['ORDER'] . 'ORD@' . $order->get_order_number() . __('<br/>Transaction Number: ', 'borica-payments') . $post['RRN'] . __('<br/>Internal Reference: ', 'borica-payments') . $post['INT_REF'] . __('<br/>Reason: ', 'borica-payments') . $post['STATUSMSG']);
                     } else if ($checkStatusResponse['RC'] == '-25') {
                         $this->log("Cancelled request for order: " . $order->get_order_number() . " with code: " . $checkStatusResponse['RC'] . "\r\nMessage: " . $checkStatusResponse['STATUSMSG']);
                         //$order->update_status('cancelled');
                         $order->add_order_note($testModeText . __('Payment cancelled by the client', 'borica-payments'));
                     } else if ($checkStatusResponse['RC'] == '-24') {
                         $this->log("The order is not found: " . $order->get_order_number() . " with code: " . $checkStatusResponse['RC'] . "\r\nMessage: " . $checkStatusResponse['STATUSMSG']);
                         $order->add_order_note(__('The order is not found!', 'borica-payments'));
                     } else {
                         $this->log("Failed request for order: " . $order->get_order_number() . " with code: " . $checkStatusResponse['RC'] . "\r\nMessage: " . $checkStatusResponse['STATUSMSG']);
                         $order->update_status('failed');
                         $order->add_order_note($testModeText . __('Payment declined with code: ', 'borica-payments') . $checkStatusResponse['RC'] . __('<br/>Reason: ', 'borica-payments') . $checkStatusResponse['STATUSMSG']);
                     }
                     $this->add_card_holder_info_note($order, $checkStatusResponse['CARDHOLDERINFO'], $testModeText);
                } else if ($signatureStatus == 'INVALID') {
                    $this->log("Signature of APGW response is invalid");
                    header('HTTP/1.1 500 Internal Server Error');
                    die('Signature of APGW response is invalid');
                } else {
                    $this->log("Signature check error: " . $signatureStatus);
                    header('HTTP/1.1 500 Internal Server Error');
                    die($signatureStatus);
                }
            } else {
                $this->log("Response from APGW: " . $checkStatusResponse);
                header('HTTP/1.1 500 Internal Server Error');
                die('Transaction status data is unavailable!');
            }
        }
    }

    protected function add_card_holder_info_note($order, $cardHolderInfo, $testModeText) {
        if (trim($cardHolderInfo) != '') {
            $order->add_order_note($testModeText . __('Cardholder info: ', 'borica-payments') . $cardHolderInfo);
        }
    }

    /**
     * Add meta data to order. Metadata keys starting with underscore are hidden.
     * So construction of $internal_key is important!
     *
     * NB!!! Use $order->save(); to persist metadata
     *
     * @param WC_Order $order
     * @param string $key
     * @param string $value
     * @param boolean $unique
     * @param boolean $writeBlankValue
     */
    protected function set_order_meta_value($order, $key, $value, $unique = true, $writeBlankValue = false) {
        if (!$writeBlankValue && trim($value) == '') {
            return;
        }
        $internal_key = '_' . self::$PLUGIN_ID . '_' . $key;
        $order->add_meta_data($internal_key, $value, $unique);
    }

    /**
     * Get meta data from order. Metadata keys starting with underscore are hidden.
     * So construction of $internal_key is important!
     *
     * @param WC_Order $order
     * @param string $key
     * @return string
     */
    protected function get_order_meta_value($order, $key) {
        $internal_key = '_' . self::$PLUGIN_ID . '_' . $key;
        return $order->get_meta($internal_key);
    }

    public function process_admin_options() {
        parent::process_admin_options();

       // parent::display_errors();
    }

    public function add_content_thankyou($order_id) {

        $order = new WC_Order($order_id);
        $label = __('Cardholder info: ', 'borica-payments');
        $cardHolderInfo = $this->get_order_meta_value($order, 'CARDHOLDERINFO');        
        $this->log("cardHolderInfo:" . $cardHolderInfo);
        if (trim($cardHolderInfo) != '') {
            echo "<p>" . esc_html($label." ".$cardHolderInfo) . "</p>";            
        }
        
    }

    /**
     * Generate HTML for static type in settings
     * @param unknown $key
     * @param unknown $data
     * @return unknown
     */
    public function generate_static_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'static',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args( $data, $defaults );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp">
                <span
                    class="<?php echo esc_attr($data['class']); ?>"
                    id="<?php echo esc_attr($field_key); ?>"
                    style="<?php echo esc_attr($data['css']); ?>"
                    <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?> >
                    <?php echo esc_attr($data['value']); ?>
                </span>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Generate HTML for file type in settings
     * @param unknown $key
     * @param unknown $data
     * @return unknown
     */
    public function generate_file_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'file',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <input
                        class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>"
                        type="<?php echo esc_attr( $data['type'] ); ?>"
                        name="<?php echo esc_attr( $field_key ); ?>"
                        id="<?php echo esc_attr( $field_key ); ?>"
                        style="<?php echo esc_attr( $data['css'] ); ?>"
                        placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>"
                        <?php disabled( $data['disabled'], true ); ?>
                        <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
                    <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Validate and get value from file type in settings
     * @param unknown $key
     * @param unknown $value
     * @param unknown $mimes
     * @throws Exception
     * @return string[]|unknown
     */
    public function validate_file_field($key, $value, $mimes = null) {
//         print_r($_FILES); exit;
        $uploaded_file_key = $this->get_field_key($key);
        if ( ! function_exists('wp_handle_upload') ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        // Get uploaded file from specific field by $key
        $uploaded_file = $_FILES[$uploaded_file_key];
//         print_r($uploaded_file); exit;
        $moved_file = array(
            'file' => '',
            'url'  => '',
            'type' => '',
        );
        if (UPLOAD_ERR_NO_FILE == $uploaded_file['error']) {
            return $moved_file;
        }
        if ($uploaded_file['size'] == 0) {
            return $moved_file;
        }

        $upload_overrides = array(
            'test_form' => false,
            'mimes' => $mimes
        );

        $moved_file = wp_handle_upload($uploaded_file, $upload_overrides);
//         print_r($movefile); exit;
        if ($moved_file && !isset($moved_file['error'])) {
            return $moved_file;
        } else {
            throw new Exception($moved_file['error']);
        }
    }

    /**
     * Generate HTML for file_with_preview type in settings
     * @param unknown $key
     * @param unknown $data
     * @return unknown
     */
    public function generate_file_with_preview_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'file',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <input
                        class="input-text <?php echo esc_attr($data['class']); ?>"
                        type="file"
                        name="<?php echo esc_attr($field_key); ?>"
                        id="<?php echo esc_attr($field_key); ?>"
                        style="<?php echo esc_attr($data['css']); ?>"
                        placeholder="<?php echo esc_attr($data['placeholder']); ?>"
                        <?php disabled($data['disabled'], true); ?>
                        <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?> />
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok. ?>
                </fieldset>
                <textarea rows="3" cols="20"
                    class="input-text wide-input <?php echo esc_attr($data['class']); ?>"
                    type="textarea"
                    name="<?php echo esc_attr($field_key) . '_preview'; ?>"
                    id="<?php echo esc_attr($field_key) . '_preview'; ?>"
                    style="<?php echo esc_attr($data['css']); ?>"
                    placeholder="<?php echo esc_attr($data['placeholder']); ?>"
                    disabled="disabled"><?php echo esc_textarea($this->get_option($key)); ?></textarea>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Validate and get value from file_with_preview type in settings
     * @param unknown $key
     * @param unknown $value
     * @throws Exception
     * @return unknown
     */
    public function validate_file_with_preview_field($key, $value) {
        $mimes = array(
            'key' => 'text/plain',
            'zip' => 'application/zip'
        );

        $moved_file = $this->validate_file_field($key, $value, $mimes);
        if ($moved_file['file'] == '') {
            return $this->get_option($key);
        }
        $file_content = '';
        if ($mimes['key'] == $moved_file['type']) {
           // $file_content = @file_get_contents($moved_file['file']); //nrogleva           
           //$file_content= WP_Filesystem_Direct::get_contents($moved_file['url']);
           $file_content = new WP_Filesystem_Direct( false );
           $file_content = $file_content->get_contents ( $moved_file['file'] );

        } else {
            // Handle ZIP file. Search first entry with extension of 'text/plain' $mimes
            if (class_exists('ZipArchive', false)) {
                $za = new ZipArchive();
                $za->open($moved_file['file']);
                for ($i=0; $i<$za->numFiles;$i++) {
                    $stat = $za->statIndex($i);
                    $file_extension = pathinfo($stat['name'], PATHINFO_EXTENSION);
                    $mime_extensions = array_keys($mimes);
                    array_pop($mime_extensions);
                    if (in_array($file_extension, $mime_extensions)) {
                        $file_content = $za->getFromIndex($i);
                        break;
                    }
                }
                $za->close();
            } else {
                throw new Exception(__('No ZipArchive class available.', 'borica-payments'));
            }
        }
        $file_content = wp_kses_post(trim(stripslashes($file_content)));
        wp_delete_file($moved_file['file']);
        return $file_content;
    }

    /**
     * Generate HTML for check_keys type in settings
     * @param unknown $key
     * @param unknown $data
     * @return unknown
     */
    public function generate_check_keys_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'file',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        $private_key_id = str_replace('_check', '', $key);
        if ($this->get_option($private_key_id) == '') {
            $data['disabled'] = true;
        }

        $button_key = $field_key . '_button';

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <input
                        class="input-text <?php echo esc_attr($data['class']); ?>"
                        type="file"
                        name="<?php echo esc_attr($field_key); ?>"
                        id="<?php echo esc_attr($field_key); ?>"
                        style="<?php echo esc_attr($data['css']); ?>"
                        placeholder="<?php echo esc_attr($data['placeholder']); ?>"
                        <?php disabled($data['disabled'], true); ?>
                        <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?> />
                    <button
                        type="submit"
                        class="button button-primary"
                        name="<?php echo esc_attr($button_key); ?>"
                        id="<?php echo esc_attr($button_key); ?>"
                        value="1"
                        onclick="return <?php echo esc_attr($button_key) . '_click()'; ?>;"
                        <?php disabled($data['disabled'], true); ?>>
                        <?php echo __('Check certificate', 'borica-payments'); ?>
                    </button>
                    <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <script>
            function <?php echo esc_attr($button_key) . '_click()'; ?> {
                var check_key_file_input = document.getElementById("<?php echo esc_attr($field_key); ?>");
                var do_submit = false;
                if (check_key_file_input.files.length > 0) {
                    window.onbeforeunload = function() {};
                    //check_key_file_input.form.submit();
                    do_submit = true;
                }
                return do_submit;
            }
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Check public to private key correspondence
     */
    public function check_keys_post() {
        $developer_key = 'developer_private_key_check';
        $developer_field_key = $this->get_field_key($developer_key);
        $developer_button_key = $developer_field_key . '_button';
        $production_key = 'production_private_key_check';
        $production_field_key = $this->get_field_key('production_private_key_check');
        $production_button_key = $production_field_key . '_button';

        $key = '';
        $value = '';
        $field_key = '';
        $private_key = '';
        $private_key_password = '';
        if (array_key_exists($developer_button_key, $_POST) && sanitize_text_field($_POST[$developer_button_key])) {
            $key = $developer_key;
            $field_key = $developer_field_key;
            $private_key = $this->get_option('developer_private_key');
            $private_key_password = $this->get_option('developer_private_key_password');
        }
        if (array_key_exists($production_button_key, $_POST) && sanitize_text_field($_POST[$production_button_key])) {
            $key = $production_key;
            $field_key = $production_field_key;
            $private_key = $this->get_option('production_private_key');
            $private_key_password = $this->get_option('production_private_key_password');
        }

        if ($private_key != '') {          

            $mimes = array(
                'cer' => 'text/plain',
                'zip' => 'application/zip'
            );

            try {
                $moved_file = $this->validate_file_field($key, $value, $mimes);
            } catch (Exception $ex) {
                $this->sample_admin_notice__error($ex->getMessage()); 
            }
            if ($moved_file['file'] == '') {
                return;
            }
            $file_content = '';
            if ($mimes['cer'] == $moved_file['type']) {               
                $file_content = new WP_Filesystem_Direct( false );
                $file_content = $file_content->get_contents ( $moved_file['file'] );              
            } else {
                // Handle ZIP file. Search first entry with extension of 'text/plain' $mimes
                if (class_exists('ZipArchive', false)) {
                    $za = new ZipArchive();
                    $za->open($moved_file['file']);
                    for ($i=0; $i<$za->numFiles;$i++) {
                        $stat = $za->statIndex($i);
                        $file_extension = pathinfo($stat['name'], PATHINFO_EXTENSION);
                        $mime_extensions = array_keys($mimes);
                        array_pop($mime_extensions);
                        if (in_array($file_extension, $mime_extensions)) {
                            $file_content = $za->getFromIndex($i);
                            break;
                        }
                    }
                    $za->close();
                } else {
                    $this->sample_admin_notice__error(__('No ZipArchive class available.', 'borica-payments'));                    
                    return;
                }
            }
            $file_content = wp_kses_post(trim(stripslashes($file_content)));
            wp_delete_file($moved_file['file']);

            // Check keys
            $private_key_id = @openssl_pkey_get_private($private_key, $private_key_password);
            if ($private_key_id === false) {
                $this->sample_admin_notice__warning(__('Cannot read private key!', 'borica-payments'));
            }
            $public_key_id = @openssl_pkey_get_public($file_content);
            if ($public_key_id === false) {
                $this->sample_admin_notice__warning(__('Cannot read uploaded public key!', 'borica-payments'));
            }
            if (strcmp(@openssl_pkey_get_details($private_key_id)["key"], @openssl_pkey_get_details($public_key_id)["key"]) !== 0) {              
                $this->sample_admin_notice__error( __('The uploaded public key does not correspond to the private key in settings!', 'borica-payments'));
            } else {
                $this->sample_admin_notice__success( __('The uploaded public key corresponds to the private key in settings.', 'borica-payments'));
            }
        }
    }

    protected function getNextGatewayOrderNumber() {
        
        $order_number = intval($this->get_option(self::$GATEWAY_ORDER_NUMBER_NAME));        

        if ($order_number < 999999) {
            $order_number += 1;
        } else {
            //egeorgiev 20221206
            //$order_number = 100000;
            $order_number = 0;
        }

        $this->update_option(self::$GATEWAY_ORDER_NUMBER_NAME, $order_number);

        return $order_number;
    }

    public function getGatewayOrderNumber($order) {
        //egeorgiev 20221209
        $order_number = intval($this->get_order_meta_value($order, self::$GATEWAY_ORDER_NUMBER_NAME));
        $this->log('getGatewayOrderNumber for WC order: '.$order->id.', GATEWAY_ORDER_NUMBER_NAME: '.$order_number);
        if ("" == $order_number) {
            //egeorgiev 20221206
            //$order_number = $order->get_id();
            $this->setGatewayOrderNumber($order, $this->getNextGatewayOrderNumber());
            $order_number = $this->get_order_meta_value($order, self::$GATEWAY_ORDER_NUMBER_NAME);
        }

        return $order_number;
    }

    public function setGatewayOrderNumber($order, $order_number) {
        $this->set_order_meta_value($order, self::$GATEWAY_ORDER_NUMBER_NAME, $order_number);
        //egeorgiev 20221206
        $order->save();
    }

    public function sample_admin_notice__error($message_notice) {
        $class = 'notice notice-error';
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message_notice ) );
     }

     public function sample_admin_notice__warning($message_notice) {
        $class = 'notice notice-warning';       
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message_notice ) );
     }

     public function sample_admin_notice__success($message_notice) {
        $class = 'notice notice-success';        
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message_notice ) );
     }

}
