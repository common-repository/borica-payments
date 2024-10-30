<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
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

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since 1.0.0
 * @package Borica_Woo_Payment_Gateway
 * @subpackage Borica_Woo_Payment_Gateway/includes
 * @author Alexander Tonkin <atonkin@borica.bg>
 */
class Borica_Woo_Payment_Gateway
{

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since 1.0.0
     * @access protected
     * @var Borica_Woo_Payment_Gateway_Loader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since 1.0.0
     * @access protected
     * @var string $plugin_name The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since 1.0.0
     * @access protected
     * @var string $version The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        if (defined('BORICA_WOO_PAYMENT_GATEWAY_VERSION')) {
            $this->version = BORICA_WOO_PAYMENT_GATEWAY_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'borica-payments';

        $this->load_dependencies();
        $this->set_locale();
        // $this->define_admin_hooks();
        // $this->define_public_hooks();
        $this->define_gateway();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Borica_Woo_Payment_Gateway_Loader. Orchestrates the hooks of the plugin.
     * - Borica_Woo_Payment_Gateway_i18n. Defines internationalization functionality.
     * - Borica_Woo_Payment_Gateway_Admin. Defines all hooks for the admin area.
     * - Borica_Woo_Payment_Gateway_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since 1.0.0
     * @access private
     */
    private function load_dependencies()
    {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-borica-woo-payment-gateway-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-borica-woo-payment-gateway-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        // require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-borica-woo-payment-gateway-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        // require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-borica-woo-payment-gateway-public.php';

        $this->loader = new Borica_Woo_Payment_Gateway_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Borica_Woo_Payment_Gateway_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since 1.0.0
     * @access private
     */
    private function set_locale()
    {
        $plugin_i18n = new Borica_Woo_Payment_Gateway_i18n($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Define gateway for payment processing
     *
     * Uses the Borica_Woo_Payment_Gateway_Impl class in order to provide BORICA MPI payment functionality
     *
     * @since 1.0.0
     * @access private
     */
    private function define_gateway()
    {
        // Makes sure the plugin is defined before trying to use it
        if (! function_exists('is_plugin_active_for_network')) {
            require_once (ABSPATH . '/wp-admin/includes/plugin.php');
        }

        $activate = false;

        if (is_multisite()) {
            if (is_plugin_active_for_network('woocommerce/woocommerce.php')) {
                $activate = true;
            } elseif (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                $activate = true;
            }
        } else {
            if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                $activate = true;
            }
        }

        if ($activate) {
            $this->loader->add_action('plugins_loaded', $this, 'init_your_gateway_class');
            $this->loader->add_filter('woocommerce_payment_gateways', $this, 'add_your_gateway_class');
            $this->loader->add_filter('plugin_action_links_' . plugin_basename(plugin_dir_path(dirname(__FILE__)) . '/borica-woo-payment-gateway.php'), $this, 'init_gateway_settings_link');
        }
    }

    public function init_your_gateway_class()
    {
        include_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-borica-woo-payment-gateway-impl.php';
        include_once plugin_dir_path(dirname(__FILE__)) . 'includes/libs/BoricaMpi.php';
//         include_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-borica-woo-payment-gateway-settings-page.php';
    }

    public function add_your_gateway_class($methods)
    {
        $methods[] = 'Borica_Woo_Payment_Gateway_Impl';
        return $methods;
    }

    /**
     * adds a link on the plugins page for the wgdr settings
     *
     * @param unknown $links
     * @return unknown
     */
    public function init_gateway_settings_link($links)
    {
        $pluginSettingsLink = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=checkout&section=' . Borica_Woo_Payment_Gateway_Impl::$PLUGIN_ID),
            __('Settings', 'woocommerce')
        );
        array_unshift($links, $pluginSettingsLink);
        return $links;
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since 1.0.0
     * @access private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Borica_Woo_Payment_Gateway_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since 1.0.0
     * @access private
     */
    private function define_public_hooks()
    {
        $plugin_public = new Borica_Woo_Payment_Gateway_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since 1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since 1.0.0
     * @return string The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since 1.0.0
     * @return Borica_Woo_Payment_Gateway_Loader Orchestrates the hooks of the plugin.
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since 1.0.0
     * @return string The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }
}
