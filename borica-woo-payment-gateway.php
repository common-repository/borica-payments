<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://borica.bg
 * @since             1.0.0
 * @package           Borica_Woo_Payment_Gateway
 *
 * @wordpress-plugin
 * Plugin Name:       BORICA Payments
 * Plugin URI:        https://3dsgate-dev.borica.bg/wordpressplugin
 * Description:       BORICA Payments works by redirecting customers to BORICA payment page where they enter their card details. To use this payment option you need to have a virtual POS.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.2
 * Author:            BORICA AD
 * Developer URI:
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woocommerce-extension
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit();
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('BORICA_WOO_PAYMENT_GATEWAY_VERSION', '1.1.0');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-borica-woo-payment-gateway-activator.php
 */
// function activate_borica_woo_payment_gateway_plugin()
// {
//     require_once plugin_dir_path(__FILE__) . 'includes/class-borica-woo-payment-gateway-activator.php';
//     Borica_Woo_Payment_Gateway_Activator::activate();
// }

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-borica-woo-payment-gateway-deactivator.php
 */
// function deactivate_borica_woo_payment_gateway_plugin()
// {
//     require_once plugin_dir_path(__FILE__) . 'includes/class-borica-woo-payment-gateway-deactivator.php';
//     Borica_Woo_Payment_Gateway_Deactivator::deactivate();
// }

// register_activation_hook(__FILE__, 'activate_borica_woo_payment_gateway_plugin');
// register_deactivation_hook(__FILE__, 'deactivate_borica_woo_payment_gateway_plugin');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-borica-woo-payment-gateway.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 1.0.0
 */
function run_borica_woo_payment_gateway()
{
    $plugin = new Borica_Woo_Payment_Gateway();
    $plugin->run();
}
run_borica_woo_payment_gateway();
