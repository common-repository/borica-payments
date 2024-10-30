<?php
/**
 * Settings for BORICA Payments
 * Generation of private key and CSR
 *
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit();
}

return array(
    'section_title' => array(
        'title' => __('Generate Private Key and CSR', 'borica-payments'),
        'type' => 'title'
    ),
    'common_name' => array(
        'id' => 'commonName',
        'title' => __('Site Domain Name', 'borica-payments'),
        'type' => 'text',
        'desc' => __('Site Domain Name', 'borica-payments'),
        'desc_tip' => true,
        'placeholder' => 'merchantdomain.com',
        'required' => 'yes'
    ),
    'organizational_unit_name' => array(
        'id' => 'organizationalUnitName',
        'title' => __('Terminal ID (TID)', 'borica-payments'),
        'type' => 'text',
        'desc' => __('Terminal ID (TID)', 'borica-payments'),
        'desc_tip' => true,
        'placeholder' => 'VXXXXXXX',
        'required' => 'yes'
    ),
    'organization_name' => array(
        'id' => 'organizationName',
        'title' => __('Organization Name', 'borica-payments'),
        'type' => 'text',
        'desc' => __('Organization Name', 'borica-payments'),
        'desc_tip' => true,
        'placeholder' => 'Company',
        'required' => 'yes'
    ),
    'locality_name' => array(
        'id' => 'localityName',
        'title' => __('City Name', 'borica-payments'),
        'type' => 'text',
        'desc' => __('City Name', 'borica-payments'),
        'desc_tip' => true,
        'placeholder' => 'Sofia',
        'required' => 'yes'
    ),
    'state_or_province_name' => array(
        'id' => 'stateOrProvinceName',
        'title' => __('State or Province Name', 'borica-payments'),
        'type' => 'text',
        'desc' => __('State or Province Name', 'borica-payments'),
        'desc_tip' => true,
        'placeholder' => 'Sofia Oblast',
        'required' => 'yes'
    ),
    'country_name' => array(
        'id' => 'countryName',
        'title' => __('Country Code', 'borica-payments'),
        'type' => 'text',
        'desc' => __('Country Code', 'borica-payments'),
        'desc_tip' => true,
        'default' => 'BG',
        'placeholder' => 'BG',
        'required' => 'yes'
    ),
    'email_address' => array(
        'id' => 'emailAddress',
        'title' => __('Email Address', 'borica-payments'),
        'type' => 'text',
        'desc' => __('Email Address', 'borica-payments'),
        'desc_tip' => true,
        'placeholder' => 'user@domain.com',
        'required' => 'yes'
    ),
    'private_key_password' => array(
        'id' => 'private_key_password',
        'title' => __('Private Key Password', 'borica-payments'),
        'type' => 'text',
        'desc' => __('Private Key Password', 'borica-payments'),
        'desc_tip' => true,
        'placeholder' => __('Private key Password', 'borica-payments')
    ),
    array(
        'type' => 'sectionend'
    ),
    'plugin_info' => array(
        'title' => '',
        'type' => 'title',
        'desc' => '<a href="'. admin_url('admin.php?page=wc-settings&tab=checkout&section=' . Borica_Woo_Payment_Gateway_Impl::$PLUGIN_ID) .'">' . __('Cancel', 'borica-payments') . '</a>'
    )
);