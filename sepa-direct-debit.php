<?php

/*
Plugin Name: Sepa Direct Debit
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: SEPA Direct Debit support for Woocommerce
Version: 1.0
Author: Joern Bungartz
Author URI: http://www.bl-solutions.de
License: Commercial, all rights reserved.
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

$domain = 'sepa-direct-debit';

function init_sepa_direct_debit() {

	if(!class_exists('WC_Payment_Gateway')) return;

    require_once('WC_Gateway_SEPA_Direct_Debit.php');

	function add_to_payment_gateways( $methods ) {
		$methods[] = 'WC_Gateway_SEPA_Direct_Debit';
		return $methods;
	}

    function enqueue_validation_script() {
        global $domain;

        // Register the script
        wp_register_script('jquery-validate-adtl', plugin_dir_url( __FILE__ ) . 'js/additional-methods.min.js', array('jquery'), '1.10.0', true);

        // Localize the script
        $translation_array = array(
            'invalid_iban_message' => __( 'Please enter a valid IBAN.', $domain ),
            'invalid_bic_message' => __( 'Please enter a valid BIC.', $domain ),
        );
        wp_localize_script('jquery-validate-adtl', 'sepa_dd_localization', $translation_array );

        // Enqueued script with localized data.
        wp_enqueue_script('jquery-validate-adtl' );
    }

	add_filter( 'woocommerce_payment_gateways', 'add_to_payment_gateways' );
}

add_action( 'plugins_loaded', 'init_sepa_direct_debit' );
?>