<?php
/**
 * Settings for BORICA Payments
 *
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit();
}

return array(
    'enabled' => array(
        'title' => __('Enable/Disable', 'borica-payments'),
        'label' => __('Enable BORICA Payments', 'borica-payments'),
        'type' => 'checkbox',
        'default' => 'yes'
    ),
    //egeorgiev 20221209
    /*
    'title' => array(
        'title' => __('Title', 'borica-payments'),
        'description' => __('This appears as payment option at checkout page.', 'borica-payments'),
        'default' => __('BORICA Payments', 'borica-payments'),
        'type' => 'text',
        'desc_tip' => true
    ),
    'description' => array(
        'title' => __('Description', 'borica-payments'),
        'description' => __('This is optional. Description of the selected payment method.', 'borica-payments'),
        'default' => __('Pay via Credit/Debit card', 'borica-payments'),
        'type' => 'text',
        'desc_tip' => true
    ),
    */
    'debug' => array(
        'title' => __('Debugging', 'borica-payments'),
        'label' => __('Enable debugging', 'borica-payments'),
        'type' => 'checkbox',
        'default' => 'no'
    ),
    'test' => array(
        'title' => __('Test Mode', 'borica-payments'),
        'label' => __('Enable test mode', 'borica-payments'),
        'type' => 'checkbox',
        'default' => 'yes'
    ),

    'BACKREF_URL' => array(
        'title' => __('Back Reference URL (BACKREF)', 'borica-payments'),
        'description' => __('Merchant URL for posting the authorization result. Send this URL to your bank/BORICA.', 'borica-payments'),
        'type' => 'static',
        'desc_tip' => true,
        'css' => 'font-weight: bold;'
    ),

    'terminal_id' => array(
        'title' => __('Terminal Identifier (TID)', 'borica-payments'),
        'description' => __('ID of the terminal, provided by your bank/BORICA.', 'borica-payments'),
        'type' => 'text',
        'desc_tip' => true
    ),

    'merchant_id' => array(
        'title' => __('Merchant Identifier (MID)', 'borica-payments'),
        'description' => __('Your Merchant ID, provided by your bank/BORICA.', 'borica-payments'),
        'type' => 'text',
        'desc_tip' => true
    ),
    'merchant_name' => array(
        'title' => __('Merchant Name', 'borica-payments'),
        'description' => __('Merchant ID. Your customers will see this in their statement to identify the payment.', 'borica-payments'),
        'type' => 'text',
        'desc_tip' => true
    ),

    'developer_options' => array(
        'title' => __('Test environment  settings', 'borica-payments'),
        'type' => 'title',
        'description' => ''
    ),
    'developer_private_key' => array(
        'title' => __('Private Key', 'borica-payments'),
        'type' => 'file_with_preview',
        'description' => __('Upload here the private key for your terminal to access the payment gateway.<br /> Allowed file types are .zip, .key.', 'borica-payments'),
        'desc_tip' => true,
        'placeholder' => __('Private key is not set', 'borica-payments')
    ),
    'developer_private_key_password' => array(
        'title' => __('Private Key Password', 'borica-payments'),
        'type' => 'password',
        'description' => __('The password to your private key.', 'borica-payments'),
        'desc_tip' => true
    ),
    // 'developer_private_key_check' is important to be as 'developer_private_key' + '_check'
    'developer_private_key_check' => array(
        'title' => __('Check Keys', 'borica-payments'),
        'type' => 'check_keys',
        'description' => __('Upload the Public Key that corresponds to your Private Key to check consistency.<br /> Allowed file types are .zip, .cer.', 'borica-payments'),
        'desc_tip' => true
    ),
//     'developer_public_certificate' => array(
//         'title' => __('BORICA MPI APGW Public Certificate', 'borica-payments'),
//         'type' => 'textarea',
//         'description' => __('BORICA MPI APGW Public Certificate is available for download from https://3dsgate-dev.borica.bg/MPI_OW_APGW.zip. The certificate is provided by the bank/Borica', 'borica-payments'),
//         'desc_tip' => true
//     ),
    'developer_url' => array(
        'title' => __('BORICA APGW URL (Test environment)', 'borica-payments'),
        'type' => 'static',
        'default' => 'https://3dsgate-dev.borica.bg/cgi-bin/cgi_link'
    ),

    'production_options' => array(
        'title' => __('Production environment settings', 'borica-payments'),
        'type' => 'title',
        'description' => ''
    ),
    'production_private_key' => array(
        'title' => __('Private Key', 'borica-payments'),
        'type' => 'file_with_preview',
        'description' => __('Upload here the private key for your terminal to access the payment gateway.<br /> Allowed file types are .zip, .key.', 'borica-payments'),
        'desc_tip' => true,
        'placeholder' => __('Private key is not set', 'borica-payments')
    ),
    'production_private_key_password' => array(
        'title' => __('Private Key Password', 'borica-payments'),
        'type' => 'password',
        'description' => __('The password to your private key.', 'borica-payments'),
        'desc_tip' => true
    ),
    // 'production_private_key_check' is important to be as 'production_private_key' + '_check'
    'production_private_key_check' => array(
        'title' => __('Check Keys', 'borica-payments'),
        'type' => 'check_keys',
        'description' => __('Upload the Public Key that corresponds to your Private Key to check consistency.<br /> Allowed file types are .zip, .cer.', 'borica-payments'),
        'desc_tip' => true
    ),
//     'production_public_certificate' => array(
//         'title' => __('BORICA MPI APGW Public Certificate', 'borica-payments'),
//         'type' => 'textarea',
//         'description' => __('BORICA MPI APGW Public Certificate is available for download from https://.', 'borica-payments'),
//         'desc_tip' => true
//     ),
    'production_url' => array(
        'title' => __('BORICA APGW URL (Production environment)', 'borica-payments'),
        'type' => 'static',
        'default' => 'https://3dsgate.borica.bg/cgi-bin/cgi_link'
    ),
    'plugin_info' => array(
        'title' => __('Help', 'borica-payments'),
        'type' => 'title',
        'description' => '<a href="https://3dsgate-dev.borica.bg/generateCSR/" target="_blank">' . __('Generate private key and CSR', 'borica-payments') . '</a><br /><a href="https://3dsgate-dev.borica.bg/wordpressplugin" target="_blank">' . __('More information about the plugin', 'borica-payments') . '</a>',
    )
);