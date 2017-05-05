<?php

/*
Plugin Name: Sepa Direct Debit
Plugin URI: http://codecanyon.net/item/sepa-payment-gateway-for-woocommerce/12664657
Description: SEPA Direct Debit support for Woocommerce
Version: 1.6.8
Author: Joern Bungartz
Author URI: http://www.bl-solutions.de
License: Commercial, all rights reserved.
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


function init_sepa_direct_debit() {

	if(!class_exists('WC_Payment_Gateway')) return;

    require_once('WC_Gateway_SEPA_Direct_Debit.php');
    WC_Gateway_SEPA_Direct_Debit::init();
}

add_action( 'plugins_loaded', 'init_sepa_direct_debit', 10);

?>