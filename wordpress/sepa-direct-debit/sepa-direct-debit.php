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

    global $domain;
    load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    function get_xml_dir() {
        return md5(wp_salt() . 'sepa-dd-plugin');
    }

    function get_xml_path() {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/' . get_xml_dir();
        wp_mkdir_p( $target_dir );
        return $target_dir;
    }

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

function get_payment_info($post) {
    $result = array();
    $result['account_holder'] = get_post_meta($post->ID, '_sepa_dd_account_holder', true);
    $result['total'] = get_post_meta($post->ID, '_order_total', true);
    $result['iban'] = get_post_meta($post->ID, '_sepa_dd_iban', true);
    $result['bic'] = get_post_meta($post->ID, '_sepa_dd_bic', true);
    return $result;
}

function output_orders_to_be_exported($orders) {
    global $domain;
    ?>
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
    foreach ($orders as $order) {
        $payment_info = get_payment_info($order);
        $shipping_name = get_post_meta($order->ID, '_shipping_first_name', true) . ' ' . get_post_meta($order->ID, '_shipping_last_name', true);
        $row_class = "";
        if ($shipping_name != $payment_info['account_holder']) {
            $row_class = "suspicious";
            $all_names_match = false;
        }
        ?>
        <tr class="<?= $row_class ?>">
        <td class="row-title"><a href="<?php echo get_edit_post_link($order->ID); ?>">#<?= $order->ID ?></a></td>
        <td><?php echo $payment_info['total'] ?></td>
        <td><?= $shipping_name ?></td>
        <td><?= $payment_info['account_holder'] ?></td>
        <td><?php echo $payment_info['iban'] ?></td>
        <td><?php echo $payment_info['bic'] ?></td>
        <?php
    }
    echo '</tbody></table>';
    if (!$all_names_match)
        echo '<div class="error"><p>' . __("For some orders, name of account holder does not match name in shipping address.", $domain) . '</p></div>';
    echo '<form method="post" action=""><p class="submit"><input class="button-primary" type="submit" value="'.__("Export to SEPA XML", $domain).'"></p></form>';
}

function sorted_dir($dir) {
    $ignored = array('.', '..');

    $files = array();
    foreach (scandir($dir) as $file) {
        if (in_array($file, $ignored)) continue;
        $files[$file] = filemtime($dir . '/' . $file);
    }

    arsort($files);
    $files = array_keys($files);

    return ($files) ? $files : false;
}

function list_xml_files() {
    global $domain;
    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'];
    $ffs = sorted_dir(get_xml_path());
    echo '<h3>'. __("SEPA XML-Files", $domain) . '</h3>';
    echo '<div class="ui-state-highlight"><p>'. __("Please use right-click and 'save-link-as' to download the XML-files.", $domain) . '</p></div>';
    echo '<ul>';
    foreach($ffs as $ff){
        echo '<li><a href="' . $base_url .'/' . get_xml_dir() . '/' .$ff . '" target="_blank">'. $ff . '</a>';
        echo '</li>';
    }
    echo '</ul>';
}

function sepa_dd_export_xml_page() {
    global $domain;

    if (!current_user_can('manage_options')) {
        wp_die(__("You do not have permission to access this page!", $domain));
    }

    wp_enqueue_style('sepa-dd', plugin_dir_url(__FILE__) . 'css/sepa-dd.css', array(), '1.0');

    echo '<div class="wrap"><div id="icon-tools" class="icon32"></div>';
    echo '<h2>'.__("Export SEPA XML", $domain).'</h2>';
    echo '</div>';

    $query = array(
        'numberposts' => -1,
        'post_type' => 'shop_order',
        'post_status' => array_keys( wc_get_order_statuses() ),
        'meta_query' => array(
            array(
                'key' => '_payment_method',
                'value' => 'sepa-direct-debit',
            ),
            array(
                'key' => '_sepa_dd_exported',
                'value' => false,
            ),
        ),
    );
    $to_be_exported = get_posts($query);
    $count = count($to_be_exported);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $filename = export_xml($to_be_exported);
        foreach($to_be_exported as $order) {
            update_post_meta($order->ID, '_sepa_dd_exported', true);
        }
        echo '<div class="updated"><p>'.sprintf(__("Exported %d payments to new SEPA XML: %s", $domain), $count, $filename).'</p></div>';
    } else {
        if ($to_be_exported) {
            output_orders_to_be_exported($to_be_exported);
        } else {
            echo '<div class="notice"><p>'.__("No new payments to export.", $domain).'</p></div>';
        }
    }
    list_xml_files();
}

function export_xml($orders) {
    global $domain;
    $gateway = new WC_Gateway_SEPA_Direct_Debit();
    $groupHeader = new GroupHeader($gateway->settings['target_bic'] . $orders[0]->ID, $gateway->settings['target_account_holder']);
    $sepaFile = new CustomerDirectDebitTransferFile($groupHeader);

    foreach($orders as $order) {
        $payment_info = get_payment_info($order);
        $transfer = new CustomerDirectDebitTransferInformation($payment_info['total'], $payment_info['iban'], $payment_info['account_holder']);
        if ($payment_info['bic'])
            $transfer->setBic($payment_info['bic']);
        $transfer->setMandateSignDate(new \DateTime($order->post_date));
        $transfer->setMandateId($order->ID);
        $transfer->setRemittanceInformation(__(sprintf('Order %d', $order->ID), $domain));
        $payment = new PaymentInformation($order->ID, $gateway->settings['target_iban'], $gateway->settings['target_bic'], $gateway->settings['target_account_holder']);
        $payment->setSequenceType(PaymentInformation::S_ONEOFF);
        $payment->setDueDate(new \DateTime());
        $payment->setCreditorId($gateway->settings['creditor_id']);
        $payment->addTransfer($transfer);
        $sepaFile->addPaymentInformation($payment);
    }
    $domBuilder = new CustomerDirectDebitTransferDomBuilder();
    $sepaFile->accept($domBuilder);
    $xml = $domBuilder->asXml();
    $now = new DateTime();
    $filename = $now->format('Y-m-d-H-i-s') . '-SEPA-DD-'. $orders[0]->ID . '.xml';
    file_put_contents(get_xml_path() . "/" . $filename, $xml);
    return $filename;
}

add_action( 'admin_menu', 'register_sepa_xml_page');
add_action( 'plugins_loaded', 'init_sepa_direct_debit' );
?>