<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://borica.bg
 * @since      1.0.0
 *
 * @package    Borica_Vpos_Plugin
 * @subpackage Borica_Vpos_Plugin/includes
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit();
}

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since 1.0.0
 * @package Borica_Vpos_Plugin
 * @subpackage Borica_Vpos_Plugin/includes
 * @author Alexander Tonkin <atonkin@borica.bg>
 */
class Borica_Woo_Payment_Gateway_i18n
{

    /**
     * The ID of this plugin.
     *
     * @since 1.0.0
     * @access private
     * @var string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since 1.0.0
     * @access private
     * @var string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     * @param string $plugin_name
     *            The name of this plugin.
     * @param string $version
     *            The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since 1.0.0
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain($this->plugin_name, false, dirname(dirname(plugin_basename(__FILE__))) . '/languages/');
    }
}
