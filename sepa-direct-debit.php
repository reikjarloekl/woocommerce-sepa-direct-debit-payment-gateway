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

define("SEPA_DD_DIR", plugin_dir_path(__FILE__));
$domain = 'sepa-direct-debit';

spl_autoload_register(function ($className) {
    // Make sure the class included is in this plugins namespace
    if (substr($className, 0, 8) === "Digitick") {
        // Remove myplugin namespace from the className
        // Replace \ with / which works as directory separator for further namespaces
        $classNameEscaped = str_replace("\\", "/", $className);
        include_once SEPA_DD_DIR . "lib/$classNameEscaped.php";
    }
});

use Digitick\Sepa\DomBuilder\CustomerCreditTransferDomBuilder;
use Digitick\Sepa\DomBuilder\CustomerDirectDebitTransferDomBuilder;
use Digitick\Sepa\Exception\InvalidTransferFileConfiguration;
use Digitick\Sepa\GroupHeader;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\CustomerDirectDebitTransferFile;
use Digitick\Sepa\TransferInformation\CustomerDirectDebitTransferInformation;

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

function register_sepa_xml_page() {
    global $domain;
    add_submenu_page( 'woocommerce', __("SEPA XML", $domain), __("SEPA XML", $domain), 'manage_options', 'sepa-dd-export-xml', 'sepa_dd_export_xml_page' );
}

function sepa_dd_export_xml_page() {
    global $domain;

    if (!current_user_can('manage_options')) {
        wp_die(__("You do not have permission to access this page!", $domain));
    }

    echo '<div class="wrap"><div id="icon-tools" class="icon32"></div>';
    echo '<h2>'.__("Export SEPA XML", $domain).'</h2>';
    echo '</div>';

    $query = array(
        'numberposts' => -1,
        'post_type' => 'shop_order',
        'post_status' => array_keys( wc_get_order_statuses() ),
        'meta_query' => array(
            array(
                '_payment_method' => 'sepa-direct-debit',
                '_sepa_dd_exported' => 'false',
            )
        ),
    );
    $to_be_exported = get_posts($query);
    $count = count($to_be_exported);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        export_xml();
        echo '<div class="updated"><p>'.sprintf(__("Exported %d payments to new SEPA XML: ", $domain), $count).'</p></div>';
    } else {
        if ($to_be_exported) { ?>
            <table class="widefat striped">
            <thead>
            <tr>
                <th class="row-title"><?php esc_attr_e( 'Order', $domain); ?></th>
                <th><?php esc_attr_e( 'Amount', 'wp_admin_style' ); ?></th>
                <th><?php esc_attr_e( 'Shipping Name', 'wp_admin_style' ); ?></th>
                <th><?php esc_attr_e( 'Account Holder', 'wp_admin_style' ); ?></th>
                <th><?php esc_attr_e( 'IBAN', 'wp_admin_style' ); ?></th>
                <th><?php esc_attr_e( 'BIC', 'wp_admin_style' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            $all_names_match = true;
            foreach ($to_be_exported as $order) {
                $shipping_name = get_post_meta($order->ID, '_shipping_first_name', true) . ' ' . get_post_meta($order->ID, '_shipping_last_name', true);
                $account_holder = get_post_meta($order->ID, '_sepa_dd_account_holder', true);
                $row_class = "";
                if ($shipping_name != $account_holder) {
                    $row_class = "form-invalid";
                    $all_names_match = false;
                }
                ?>
                <tr class="<?= $row_class ?>">
                    <td class="row-title"><a href="<?php echo get_edit_post_link($order->ID); ?>">#<?= $order->ID ?></a></td>
                    <td><?php echo get_post_meta($order->ID, '_order_total', true); ?></td>
                    <td><?= $shipping_name ?></td>
                    <td><?= $account_holder ?></td>
                    <td><?php echo get_post_meta($order->ID, '_sepa_dd_iban', true); ?></td>
                    <td><?php echo get_post_meta($order->ID, '_sepa_dd_bic', true); ?></td>
                <?php
            }
            echo '</tbody></table>';
            if (!$all_names_match)
                echo '<div class="error"><p>' . __("For some orders, name of account holder does not match name in shipping address.", $domain) . '</p></div>';
            echo '<form method="post" action=""><p class="submit"><input class="button-primary" type="submit" value="'.__("Export to SEPA XML", $domain).'"></p></form>';
        } else {
            echo '<div class="notice"><p>'.__("No payments to export.", $domain).'</p></div>';
        }
    }

}

function export_xml() {
    $groupHeader = new GroupHeader('transferID', 'Me');
    $sepaFile = new CustomerDirectDebitTransferFile($groupHeader);
    $transfer = new CustomerDirectDebitTransferInformation('0.02', 'FI1350001540000056', 'Their Corp');
    $transfer->setBic('OKOYFIHH');
    $transfer->setMandateSignDate(new \DateTime('16.08.2013'));
    $transfer->setMandateId('ABCDE');
    $transfer->setRemittanceInformation('Transaction Description');
    $payment = new PaymentInformation('Payment Info ID', 'FR1420041010050500013M02606', 'PSSTFRPPMON', 'My Corp');
    $payment->setSequenceType(PaymentInformation::S_ONEOFF);
    $payment->setDueDate(new \DateTime('22.08.2013'));
    $payment->setCreditorId('DE21WVM1234567890');
    $payment->addTransfer($transfer);
    $sepaFile->addPaymentInformation($payment);
    $domBuilder = new CustomerDirectDebitTransferDomBuilder();
    $sepaFile->accept($domBuilder);
    $xml = $domBuilder->asXml();
    $upload_dir = wp_upload_dir();
    file_put_contents($upload_dir['path'] . "/sepa-export.xml", $xml);
}

add_action( 'admin_menu', 'register_sepa_xml_page');
add_action( 'plugins_loaded', 'init_sepa_direct_debit' );
?>