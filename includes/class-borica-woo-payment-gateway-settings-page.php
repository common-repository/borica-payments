<?php
/**
 * The file with BORICA Payments separate Settings Page
 *
 * A class definition that adds new tab in WooCommerce settings.
 * It is used to generate Private Key and Certificate Signing Request (CSR)
 * Code is identical to: https://woocommerce.github.io/code-reference/files/woocommerce-includes-admin-settings-class-wc-settings-page.html
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

if (! class_exists('Borica_Woo_Payment_Gateway_Impl', false)) {
    exit();
}

class Borica_Woo_Payment_Gateway_Settings_Page
{

    public static $PAGE_ID = "settings_page";

    /**
     * Setting page id.
     *
     * @var string
     */
    protected $id = '';

    /**
     * Setting page label.
     *
     * @var string
     */
    protected $label = '';

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->id = Borica_Woo_Payment_Gateway_Impl::$PLUGIN_ID . '_' . self::$PAGE_ID;
        $this->label = __('Generate CSR', 'borica-payments');

        add_filter('woocommerce_settings_tabs_array', array(
            $this,
            'add_settings_page'
        ), 99);
        // add_action('woocommerce_sections_' . $this->id, array($this, 'output_sections'));
        add_action('woocommerce_settings_' . $this->id, array(
            $this,
            'output'
        ));
        add_action('woocommerce_settings_save_' . $this->id, array(
            $this,
            'save'
        ));
    }

    /**
     * Get settings page ID.
     *
     * @return string
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * Get settings page label.
     *
     * @return string
     */
    public function get_label()
    {
        return $this->label;
    }

    /**
     * Add this page to settings.
     *
     * @param array $pages
     *            The setings array where we'll add ourselves.
     *
     * @return mixed
     */
    public function add_settings_page($pages)
    {
        $pages[$this->id] = $this->label;

        return $pages;
    }

    /**
     * Get all the settings for this plugin.
     *
     * @return array Settings array, each item being an associative array representing a setting.
     */
    public function get_settings()
    {
        $settings = include ('settings-page.php');
        $settings = apply_filters('woocommerce_get_settings_' . $this->id, $settings);

        return $settings;
    }

    /**
     * Output the HTML for the settings.
     */
    public function output()
    {
        $settings = $this->get_settings();

        WC_Admin_Settings::output_fields($settings);
    }

    /**
     * Save settings and trigger the 'woocommerce_update_options_'.id action.
     */
    public function save()
    {
        if (! isset($_POST['_wpnonce'])) {
            throw new Exception('Invalid request nonce');
        }
        // WC_Admin_Settings::add_error('Error');
        if (wp_create_nonce($_POST['_wpnonce'])) {
            $has_errors = false;
            $settings = $this->get_settings();
            $dn = array(
                "commonName" => '',
                "organizationalUnitName" => '',
                "organizationName" => '',
                "localityName" => '',
                "stateOrProvinceName" => '',
                "countryName" => '',
                "emailAddress" => ''
            );
            $config = array(
//                 'config' => plugin_dir_path(dirname(__FILE__)) . 'includes/openssl.cnf',
                'digest_alg' => 'sha256',
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            );

            foreach ($settings as $setting_key => $setting_value) {
                if ('yes' == $setting_value['required'] && (! isset($_POST[$setting_value['id']]) || empty(trim($_POST[$setting_value['id']])))) {
                    WC_Admin_Settings::add_error(sprintf("%s %s", $setting_value['title'], __('is required', 'borica-payments')));
                    $has_errors = true;
                } else {
                    if (array_key_exists($setting_value['id'], $dn)) {
                        $dn[$setting_value['id']] = intval($_POST[$setting_value['id']]);
                    }
                }
            }
            if (! $has_errors) {
//                 print_r($dn);
//                 print_r($_POST);
                $privkeypass = sanitize_text_field($_POST['private_key_password']);
                $numberofdays = 365;

                //Generate private key
                //error_reporting(E_ALL);
                $privkey = openssl_pkey_new($config);
                if (false === $privkey) {
                    WC_Admin_Settings::add_error(sprintf("%s %s", __('Error generating private key: ', 'borica-payments'), openssl_error_string()));
                    return;
                }
                //Generate certificate request
                $csr = openssl_csr_new($dn, $privkey);
                if (false === $csr) {
                    WC_Admin_Settings::add_error(sprintf("%s %s", __('Error generating CSR: ', 'borica-payments'), openssl_error_string()));
                    return;
                }

                $result = openssl_pkey_export($privkey, $privatekey, $privkeypass);
                if (false === $result) {
                    WC_Admin_Settings::add_error(sprintf("%s %s", __('Error exporting private key: ', 'borica-payments'), openssl_error_string()));
                    return;
                }
                $result = openssl_csr_export($csr, $csrStr);
                if (false === $result) {
                    WC_Admin_Settings::add_error(sprintf("%s %s", __('Error exporting CSR: ', 'borica-payments'), openssl_error_string()));
                    return;
                }

                echo esc_attr($privatekey); // Will hold the exported PriKey
                echo esc_attr($csrStr);     // Will hold the exported Certificate
                // TODO: Make and download ZIP
            }
        } else {
            throw new Exception('Invalid request nonce');
        }
    }
}

new Borica_Woo_Payment_Gateway_Settings_Page();
