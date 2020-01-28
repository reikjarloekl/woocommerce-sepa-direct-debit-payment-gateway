<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * User: Joern
 * Date: 23.08.2015
 * Time: 08:25
 */

spl_autoload_register(function ($className) {
    // Make sure the class included is in this plugins namespace
    if (substr($className, 0, 8) === "Digitick") {
        // Remove myplugin namespace from the className
        // Replace \ with / which works as directory separator for further namespaces
        $classNameEscaped = str_replace("\\", "/", $className);
        include_once SEPA_DD_DIR . "lib/$classNameEscaped.php";
    }
});

/**
 * Uses the php-sepa-xml library 1.0.0 licensed under LGPL 1.0
 * https://github.com/php-sepa-xml/php-sepa-xml
 */
use Digitick\Sepa\DomBuilder\CustomerCreditTransferDomBuilder;
use Digitick\Sepa\DomBuilder\CustomerDirectDebitTransferDomBuilder;
use Digitick\Sepa\Exception\InvalidTransferFileConfiguration;
use Digitick\Sepa\GroupHeader;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\CustomerCreditTransferFile;
use Digitick\Sepa\TransferFile\CustomerDirectDebitTransferFile;
use Digitick\Sepa\TransferInformation\CustomerCreditTransferInformation;
use Digitick\Sepa\TransferInformation\CustomerDirectDebitTransferInformation;

require_once("sepa-checks.php");
require_once("WC_Payment_Token_SepaDD.php");

const SEPA_DD_DOMAIN = 'sepa-direct-debit';


class WC_Gateway_SEPA_Direct_Debit extends WC_Payment_Gateway
{
    const GATEWAY_ID = 'sepa-direct-debit';
    const SEPA_DD_EXPORTED = '_sepa_dd_exported';
    const SEPA_REFUND_EXPORTED = '_sepa_refund_exported';
    const SEPA_REFUND_OK_TO_EXPORT = '_sepa_refund_ok_to_export';
    const SEPA_DD_ACCOUNT_HOLDER = '_sepa_dd_account_holder';
    const SEPA_DD_IBAN = '_sepa_dd_iban';
    const SEPA_DD_BIC = '_sepa_dd_bic';
    const DOMAIN = SEPA_DD_DOMAIN;
    const ORDER_TOTAL = '_order_total';
    const SHIPPING_FIRST_NAME = '_shipping_first_name';
    const SHIPPING_LAST_NAME = '_shipping_last_name';

    const PAYMENT_METHOD = '_payment_method';

    /**
     * C'tor, registering with WooCommerce.
     */
    function __construct()
    {
        $this->id = self::GATEWAY_ID;
        $this->supports = array(
            'products', 
            'tokenization',
            'refunds'
        );
        if (!self::isSubscriptions1x()) {
            $this->supports = array_merge($this->supports, array(
                'subscriptions',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change_customer',
                'subscription_payment_method_change_admin',
                'multiple_subscriptions'
            ));
        }

        $this->method_title = __('SEPA Direct Debit', self::DOMAIN);
        $this->method_description = __('Creates PAIN.008 XML-files for WooCommerce payments.', self::DOMAIN);
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];

        if (is_admin())
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Allow store managers to manually set SEPA Direct Debit as the payment method on a subscription
        add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
        add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
    }

    /**
     * Initializes actions and filters; load translation files.
     */
    public static function init() {
        load_plugin_textdomain( 'sepa-direct-debit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        add_action( 'woocommerce_scheduled_subscription_payment_' . self::GATEWAY_ID,  __CLASS__ . '::scheduled_subscription_payment', 10, 3);
        add_filter( 'woocommerce_payment_gateways', __CLASS__ . '::add_to_payment_gateways' );
        add_action( 'admin_menu', __CLASS__ . '::register_sepa_xml_page', 10);
        add_filter( 'wcs_renewal_order_meta_query', __CLASS__ . '::remove_renewal_order_meta', 10, 4 );
        add_filter( 'wcs_renewal_order_meta', __CLASS__ . '::add_exported_to_renewal_order_meta', 10, 4 );
        add_filter( 'woocommerce_email_order_meta', __CLASS__ . '::custom_woocommerce_email_order_meta', 10, 3 );

        add_action( 'add_meta_boxes', __CLASS__ . '::sepa_dd_add_meta_box' );

    }

    public static function get_parent_order( $order_id ) {
        $subscriptions = array();
        if ( function_exists( 'wcs_is_subscription') && wcs_is_subscription( $order_id ) ) {
            $subscriptions[] = wcs_get_subscription( $order_id );
        } elseif ( function_exists( 'wcs_order_contains_subscription') && wcs_order_contains_subscription( $order_id, array( 'parent', 'renewal' ) ) ) {
            $subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => array( 'parent', 'renewal' ) ) );
        }
        if ( 1 == count( $subscriptions ) ) {
            foreach ($subscriptions as $subscription) {
                if (false !== $subscription->get_parent()) {
                    $orders[] = $subscription->get_parent();
                }
            }
        }
        if (empty($orders)) return null;
        return $orders[0]->get_id();
    }

    // Add a meta box to the order page to show IBAN and BIC.
    public static function sepa_dd_add_meta_box()
    {
        global $post;

        if(empty($post)) return;
        $post_type = get_post_type($post);
        if (($post_type != 'shop_order')
            && ($post_type != 'shop_subscription')) return;

        $info = WC_Gateway_SEPA_Direct_Debit::get_payment_info($post);
        if (empty($info['account_holder'])) return;

        add_meta_box(
            self::GATEWAY_ID,
            __('SEPA Direct Debit', self::DOMAIN),
            __CLASS__ . '::sepa_dd_meta_box_callback',
            'shop_order',
            'side'
        );

        add_meta_box(
            self::GATEWAY_ID,
            __('SEPA Direct Debit', self::DOMAIN),
            __CLASS__ . '::sepa_dd_meta_box_callback',
            'shop_subscription',
            'side'
        );
    }

    /**
     * Include the payment meta data required to process automatic recurring payments so that store managers can
     * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
     *
     * @since 2.4
     * @param array $payment_meta associative array of meta data required for automatic payments
     * @param WC_Subscription $subscription An instance of a subscription object
     * @return array
     */
    public function add_subscription_payment_meta( $payment_meta, $subscription ) {
        $payment_meta[ $this->id ] = array(
            'post_meta' => array(
                self::SEPA_DD_ACCOUNT_HOLDER => array(
                    'value' => get_post_meta( $subscription->get_id(), self::SEPA_DD_ACCOUNT_HOLDER, true ),
                    'label' => __('Account holder', self::DOMAIN),
                ),
                self::SEPA_DD_IBAN => array(
                    'value' => get_post_meta( $subscription->get_id(), self::SEPA_DD_IBAN, true ),
                    'label' => __('IBAN', self::DOMAIN),
                ),
                self::SEPA_DD_BIC => array(
                    'value' => get_post_meta( $subscription->get_id(), self::SEPA_DD_BIC, true ),
                    'label' => __('BIC', self::DOMAIN),
                ),
            ),
        );
        return $payment_meta;
    }

    /**
     * Validate the payment meta data required to process automatic recurring payments so that store managers can
     * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions 2.0+.
     *
     * @since 2.4
     * @param string $payment_method_id The ID of the payment method to validate
     * @param array $payment_meta associative array of meta data required for automatic payments
     * @return array
     */
    public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
        if ( $this->id === $payment_method_id ) {
            if ( ! isset( $payment_meta['post_meta'][self::SEPA_DD_ACCOUNT_HOLDER]['value'] ) 
                || empty( $payment_meta['post_meta'][self::SEPA_DD_ACCOUNT_HOLDER]['value'] ) ) {
                throw new Exception( __('Account holder', self::DOMAIN) . " " . __('is a required field.', 'woocommerce'));
            }
            if ( ! isset( $payment_meta['post_meta'][self::SEPA_DD_IBAN]['value'] ) 
                || empty( $payment_meta['post_meta'][self::SEPA_DD_IBAN]['value'] ) ) {
                throw new Exception( __('IBAN', self::DOMAIN) . " " . __('is a required field.', 'woocommerce') );
            } else {
                $iban = $payment_meta['post_meta'][self::SEPA_DD_IBAN]['value'];
                if (!checkIBAN($iban)) throw new Exception( __('Please enter a valid IBAN.', self::DOMAIN) );
            }
            if ( ! isset( $payment_meta['post_meta'][self::SEPA_DD_BIC]['value'] ) 
                || empty( $payment_meta['post_meta'][self::SEPA_DD_BIC]['value'] ) ) {
                if ($this->askForBIC()) throw new Exception( __('BIC', self::DOMAIN) . " " . __('is a required field.', 'woocommerce') );
            } else {
                $bic = $payment_meta['post_meta'][self::SEPA_DD_BIC]['value'];
                if (!checkBIC($bic)) throw new Exception( __('Please enter a valid BIC.', self::DOMAIN) );
            }               
        }
    }

    /**
     * Prints the meta box on the order page showing IBAN and BIC.
     *
     * @param WP_Post $post The object for the current post/page.
     */
    public static function sepa_dd_meta_box_callback( $post ) {

        $info = WC_Gateway_SEPA_Direct_Debit::get_payment_info($post);

        echo "<p>";
        _e('Account holder', self::DOMAIN);
        echo ": " . $info['account_holder'];
        if ($info['is_from_parent']) {
            echo " ";
            _e( '(from parent order)', self::DOMAIN);
        }
        echo "</p>";

        echo "<p>";
        _e('IBAN', self::DOMAIN);
        echo ": " . $info['iban'];
        if ($info['is_from_parent']) {
            echo " ";
            _e( '(from parent order)', self::DOMAIN);
        }
        echo "</p>";

        echo "<p>";
        _e('BIC', self::DOMAIN);
        echo ": " . $info['bic'];
        if ($info['is_from_parent']) {
            echo " ";
            _e( '(from parent order)', self::DOMAIN);
        }
        echo "</p>";

    }

    /**
     * Register WooCommerce-Settings page "SEPA XML"
     */
    public static function register_sepa_xml_page() {
        add_submenu_page( 'woocommerce', __("SEPA XML", self::DOMAIN), __("SEPA XML", self::DOMAIN), 'manage_options', 'sepa-dd-export-xml', __CLASS__ . '::sepa_dd_export_xml_page' );
    }

    /**
     * Action method to register payment gateway with WooCommerce.
     *
     * @param $methods
     * @return array
     */
    public static function add_to_payment_gateways( $methods ) {
        $methods[] = 'WC_Gateway_SEPA_Direct_Debit';
        return $methods;
    }

    /**
     * Do not copy 'exported' flag over to renewal order.
     *
     * @param $order_meta_query
     * @param $original_order_id
     * @param $renewal_order_id
     * @param $new_order_role
     * @return string
     */
    public static function remove_renewal_order_meta( $order_meta_query, $original_order, $renewal_order ) {
        $order_meta_query .= " AND `meta_key` NOT IN ("
            .       "'" . self::SEPA_DD_EXPORTED ."')";
        return $order_meta_query;
    }

    /**
     * Add exported flag (=false) to renewal orders that we are responsible for.
     *
     * @param $order_meta
     * @param $original_order_id
     * @param $renewal_order_id
     * @param $new_order_role
     * @return string
     * @internal param $order_meta_query
     */
    public static function add_exported_to_renewal_order_meta( $order_meta, $renewal_order, $original_order) {
        $gateway = get_post_meta($original_order->id, self::PAYMENT_METHOD, true);
        if ($gateway === self::GATEWAY_ID) {
            $order_meta[] = array(
                'meta_key' => self::SEPA_DD_EXPORTED,
                'meta_value' => false
            );
        }
        return $order_meta;
    }

    /**
     * Returns the target output directory for SEPA-XML-files.
     *
     * @return string The output dir for SEPA-XML files.
     */
    private static function get_xml_dir() {
        return md5(wp_salt() . 'sepa-dd-plugin');
    }
    private static function get_refund_xml_dir() {
        return md5(wp_salt() . 'sepa-dd-plugin-refunds');
    }

    /**
     * Returns the full path within wp-upload to export SEPA-XML-files to.
     * @return string The full output path.
     * @throws Exception in case, output path could not be created.
     */
    private static function get_xml_path() {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/' . self::get_xml_dir();
        if (false === wp_mkdir_p( $target_dir )) {
            throw new Exception(sprintf(__('Could not create output path %s', self::DOMAIN), $target_dir));
        }
        return $target_dir;
    }

    private static function get_refund_xml_path() {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/' . self::get_refund_xml_dir();
        if (false === wp_mkdir_p( $target_dir )) {
            throw new Exception(sprintf(__('Could not create output path %s', self::DOMAIN), $target_dir));
        }
        return $target_dir;
    }

    /**
     * Get payment info from post.
     *
     * @param $post The post to get payment info from.
     * @return array Contains payment info.
     */
    private static function get_payment_info($post) {
        if (is_object($post)) {
            $post = $post->ID;
        }
        $order = wc_get_order( $post );

        $result = array();
        $result['total'] = wc_format_decimal( $order->get_total() - $order->get_total_refunded(), wc_get_price_decimals() ); 
        $result['account_holder'] = get_post_meta($post, self::SEPA_DD_ACCOUNT_HOLDER, true);
        $result['is_from_parent'] = false;
        if (empty($result['account_holder'])) {
            $post = WC_Gateway_SEPA_Direct_Debit::get_parent_order($post);
            $result['account_holder'] = get_post_meta($post, self::SEPA_DD_ACCOUNT_HOLDER, true);
            $result['is_from_parent'] = true;
        }
        $result['iban'] = get_post_meta($post, self::SEPA_DD_IBAN, true);
        $result['bic'] = get_post_meta($post, self::SEPA_DD_BIC, true);
        return $result;
    }


    private static function get_refund_info($post) {
        if (is_object($post)) {
            $post = $post->ID;
        }
        $order = wc_get_order( $post );

        $result = array();
        $result['total'] = wc_format_decimal( $order->get_total_refunded(), wc_get_price_decimals() ); 
        $result['account_holder'] = get_post_meta($post, self::SEPA_DD_ACCOUNT_HOLDER, true);
        $result['is_from_parent'] = false;
        if (empty($result['account_holder'])) {
            $post = WC_Gateway_SEPA_Direct_Debit::get_parent_order($post);
            $result['account_holder'] = get_post_meta($post, self::SEPA_DD_ACCOUNT_HOLDER, true);
            $result['is_from_parent'] = true;
        }
        $result['iban'] = get_post_meta($post, self::SEPA_DD_IBAN, true);
        $result['bic'] = get_post_meta($post, self::SEPA_DD_BIC, true);
        return $result;
    }

    /**
     * Output widefat striped table containing overview of all the orders including payment information.
     *
     * @param $orders The orders to output.
     */
    private static function output_orders_to_be_exported($orders) {
        ?>
        <h1><?php esc_attr_e( 'Direct Debit payments', self::DOMAIN ); ?></h1>
        <table class="widefat striped">
        <thead>
        <tr>
            <th class="row-title"><?php esc_attr_e( 'Order', self::DOMAIN); ?></th>
            <th><?php esc_attr_e( 'Amount', self::DOMAIN ); ?></th>
            <th><?php esc_attr_e( 'Shipping Name', self::DOMAIN ); ?></th>
            <th><?php esc_attr_e( 'Account Holder', self::DOMAIN ); ?></th>
            <th><?php esc_attr_e( 'IBAN', self::DOMAIN ); ?></th>
            <th><?php esc_attr_e( 'BIC', self::DOMAIN ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $all_names_match = true;
        foreach ($orders as $order) {
            
            $payment_info = self::get_payment_info($order);
            $is_from_parent = $payment_info['is_from_parent'];
            $shipping_name = get_post_meta($order->ID, self::SHIPPING_FIRST_NAME, true) . ' ' . get_post_meta($order->ID, self::SHIPPING_LAST_NAME, true);
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
            <td><?= $payment_info['account_holder'] ?> <?php if ($is_from_parent) echo esc_attr_e( '(from parent order)', self::DOMAIN); ?></td>
            <td><?php echo $payment_info['iban']  ?> <?php if ($is_from_parent) echo esc_attr_e( '(from parent order)', self::DOMAIN); ?></td>
            <td><?php echo $payment_info['bic'] ?> <?php if ($is_from_parent) echo esc_attr_e( '(from parent order)', self::DOMAIN); ?></td>
            <?php
        }
        echo '</tbody></table>';
        if (!$all_names_match)
            echo '<div class="error"><p>' . __("For some orders, name of account holder does not match name in shipping address.", self::DOMAIN) . '</p></div>';
        $gateway = new WC_Gateway_SEPA_Direct_Debit();
        $all_target_info_set =
            !empty($gateway->settings['target_bic'])
            && !empty($gateway->settings['target_iban'])
            && !empty($gateway->settings['target_account_holder'])
            && !empty($gateway->settings['creditor_id']);
        if ($all_target_info_set) {
            echo '<form method="post" action=""><p class="submit"><input class="button-primary" type="submit" name="export_orders" value="' . __("Export Orders to SEPA XML", self::DOMAIN) . '"></p></form>';
        } else {
            echo '<div class="error"><p>' . __("Please setup the payment target information first in WooCommerce/Settings/Checkout/SEPA Direct Debit.", self::DOMAIN) . '</p></div>';
            echo '<p class="submit"><input class="button-primary" type="submit" disabled value="' . __("Export Orders to SEPA XML", self::DOMAIN) . '"></p>';
        }
    }


    private static function output_refunds_to_be_exported($orders) {
        ?>
        <hr />
        <h1><?php esc_attr_e( 'Refunds', self::DOMAIN ); ?></h1>
        <table class="widefat striped">
        <thead>
        <tr>
            <th class="row-title"><?php esc_attr_e( 'Order', self::DOMAIN); ?></th>
            <th><?php esc_attr_e( 'Amount', self::DOMAIN ); ?></th>
            <th><?php esc_attr_e( 'Shipping Name', self::DOMAIN ); ?></th>
            <th><?php esc_attr_e( 'Account Holder', self::DOMAIN ); ?></th>
            <th><?php esc_attr_e( 'IBAN', self::DOMAIN ); ?></th>
            <th><?php esc_attr_e( 'BIC', self::DOMAIN ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $all_names_match = true;
        foreach ($orders as $order) {
            $parent_info = self::get_refund_info($order->ID);
            $refund_info = wc_get_order( $order->ID );
            // if(sizeof( $refund_info->get_refunds() ) > 0 ) return;
            if($refund_info->get_total_refunded() > 0 ) {
                update_post_meta($order->ID, self::SEPA_REFUND_OK_TO_EXPORT, true);
            } else {
                update_post_meta($order->ID, self::SEPA_REFUND_OK_TO_EXPORT, false);

            }

            $is_from_parent = $parent_info['is_from_parent'];
            $shipping_name = get_post_meta($order->post_parent, self::SHIPPING_FIRST_NAME, true) . ' ' . get_post_meta($order->post_parent, self::SHIPPING_LAST_NAME, true);
            $row_class = "";
            if ($shipping_name != $parent_info['account_holder']) {
                $row_class = "suspicious";
                $all_names_match = false;
            }
            ?>
            <tr class="<?= $row_class ?>">
                <td class="row-title"><a href="<?php echo get_edit_post_link($order->ID); ?>">#<?= $order->ID ?></a></td>
                <td><?php echo $parent_info['total'] ?> <?php //echo $refund_info->get_total_refunded() ?></td>
                <td><?= $shipping_name ?></td>
                <td><?= $parent_info['account_holder'] ?> <?php if ($is_from_parent) echo esc_attr_e( '(from parent order)', self::DOMAIN); ?></td>
                <td><?php echo $parent_info['iban']  ?> <?php if ($is_from_parent) echo esc_attr_e( '(from parent order)', self::DOMAIN); ?></td>
                <td><?php echo $parent_info['bic'] ?> <?php if ($is_from_parent) echo esc_attr_e( '(from parent order)', self::DOMAIN); ?></td>
            </tr>
        <?php
        }
        echo '</tbody></table>';
        if (!$all_names_match)
            echo '<div class="error"><p>' . __("For some orders, name of account holder does not match name in shipping address.", self::DOMAIN) . '</p></div>';
        $gateway = new WC_Gateway_SEPA_Direct_Debit();
        $all_target_info_set =
            !empty($gateway->settings['target_bic'])
            && !empty($gateway->settings['target_iban'])
            && !empty($gateway->settings['target_account_holder'])
            && !empty($gateway->settings['creditor_id']);
        if ($all_target_info_set) {
            echo '<form method="post" action=""><p class="submit"><input class="button-primary" type="submit" name="export_refunds" value="' . __("Export Refunds to SEPA XML", self::DOMAIN) . '"></p></form>';
        } else {
            echo '<div class="error"><p>' . __("Please setup the payment target information first in WooCommerce/Settings/Checkout/SEPA Direct Debit.", self::DOMAIN) . '</p></div>';
            echo '<p class="submit"><input class="button-primary" type="submit" disabled value="' . __("Export Refunds to SEPA XML", self::DOMAIN) . '"></p>';
        }
    }

    /**
     * Scan the given dir for files and sort them according to time (youngest first).
     *
     * @param $dir The directory to scan
     * @return array|bool The sorted list of files. In case no files are found, returns false.
     */
    private static function sorted_dir($dir) {
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

    /**
     * Scan output path and list all SEPA-XML-files previously generated.
     */
    private static function list_xml_files() {
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $ffs = self::sorted_dir(self::get_xml_path());
        if (empty($ffs)) return;
        echo '<h3>'. __("SEPA Orders XML-Files", self::DOMAIN) . '</h3>';
        echo '<div class="ui-state-highlight"><p>'. __("Please use right-click and 'save-link-as' to download the XML-files.", self::DOMAIN) . '</p></div>';
        echo '<ul>';
        foreach($ffs as $ff){
            echo '<li><a href="' . $base_url .'/' . self::get_xml_dir() . '/' .$ff . '" target="_blank">'. $ff . '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }
    /**
     * Scan output path and list all SEPA-XML-files previously generated.
     */
    private static function list_refund_xml_files() {
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $ffs = self::sorted_dir(self::get_refund_xml_path());
        if (empty($ffs)) return;
        echo '<h3>'. __("SEPA Refunds XML-Files", self::DOMAIN) . '</h3>';
        echo '<div class="ui-state-highlight"><p>'. __("Please use right-click and 'save-link-as' to download the XML-files.", self::DOMAIN) . '</p></div>';
        echo '<ul>';
        foreach($ffs as $ff){
            echo '<li><a href="' . $base_url .'/' . self::get_refund_xml_dir() . '/' .$ff . '" target="_blank">'. $ff . '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Create a new SEPA direct debit payment info structure.
     *
     * @param $id ID to use for the payment info structure.
     * @param $sequence Sequence code to set for the payment info structure.
     * @param $painFormat The output format of the XML to be created.
     * @return string The new SEPA direct debit payment info object filled according to the plugin settings.
     */
    private static function get_sepa_payment_info($id, $sequence, $painFormat) {
        $gateway = new WC_Gateway_SEPA_Direct_Debit();
        $iban = strtoupper($gateway->settings['target_iban']);
        $bic = strtoupper($gateway->settings['target_bic']);
        $payment = new PaymentInformation($id, $iban, $bic, $gateway->settings['target_account_holder']);
        $payment->setSequenceType($sequence);
        $payment->setDueDate(new \DateTime('tomorrow'));
        $payment->setCreditorId(strtoupper($gateway->settings['creditor_id']));
        // COR1 no longer supported in pain.008.001.02
        $cor1_enabled = ($gateway->settings['export_as_COR1'] === 'yes') && ($painFormat != 'pain.008.001.02');
        $payment->setLocalInstrumentCode($cor1_enabled ? 'COR1' : 'CORE');
        $payment = apply_filters('wc_gateway_sepa_direct_debit:get_sepa_payment_info', $payment, $id, $sequence, $painFormat);
        return $payment;
    }

    /**
     * Get the SEPA direct debit sequence code for the given order.
     *
     * @param $id ID of the order to get the correct SEPA direct debit sequence code for.
     * @return string The sequence code corresponding to the order payment due.
     */
    private static function get_sequence_for_order($id) {
        $sequence = PaymentInformation::S_ONEOFF;
        if (function_exists( 'wcs_order_contains_renewal' )
            && function_exists( 'wcs_order_contains_resubscribe' )
            && function_exists( 'wcs_order_contains_subscription' )
        ) {
            $isRenewal = wcs_order_contains_renewal($id);
            $isResubscription = wcs_order_contains_resubscribe($id);
            if ($isRenewal || $isResubscription) {
                $sequence = PaymentInformation::S_RECURRING;
            } else if (wcs_order_contains_subscription($id)) {
                $sequence = PaymentInformation::S_FIRST;
            }
        }
        return $sequence;
    }

    /**
     * Export the given orders into a PAIN XML-file (format version configured via setting).
     *
     * @param $orders The orders to export.
     * @return string The filename of the generated XML-file.
     * @throws Exception in case output file cannot be created.
     */
    private static function export_xml($orders) {
        $gateway = new WC_Gateway_SEPA_Direct_Debit();
        $groupHeader = new GroupHeader($gateway->settings['target_bic'] . $orders[0]->ID, $gateway->settings['target_account_holder']);
        $sepaFile = new CustomerDirectDebitTransferFile($groupHeader);
        $painFormat = 'pain.008.003.02';
        if (array_key_exists('pain_format', $gateway->settings)) {
            $painFormat = $gateway->settings['pain_format'];
        }
        $singlePaymentInfo = false;
        if (array_key_exists('single_payment_info', $gateway->settings)) {
            $singlePaymentInfo = ($gateway->settings['single_payment_info'] === 'yes');    
        }
        $payment = null;
        $sequence = '';

        if ($singlePaymentInfo) {
            $payment = self::get_sepa_payment_info("paymentInfo", PaymentInformation::S_ONEOFF, $painFormat);
        }
        foreach($orders as &$order) {
            $payment_info = self::get_payment_info($order);
            $parts = preg_split('/\./', $payment_info['total']);
            $amount = strval($parts[0]) * 100 + strval($parts[1]);
            $transfer = new CustomerDirectDebitTransferInformation($amount, $payment_info['iban'], $payment_info['account_holder']);
            if ($payment_info['bic'])
                $transfer->setBic($payment_info['bic']);
            $transfer->setMandateSignDate(new \DateTime($order->post_date));
            $transfer->setMandateId($order->ID);
            $remittance_info = "";
            if (array_key_exists('remittance_info', $gateway->settings)) {
                $remittance_info = $gateway->settings['remittance_info'] . " ";
            }
            $wc_order = new WC_Order($order->ID);
            $order_number = trim(str_replace('#', '', $wc_order->get_order_number()));
            $transfer->setRemittanceInformation($remittance_info . sprintf(__('Order %d', self::DOMAIN), $order_number));
            if ($singlePaymentInfo) {
                // try and aggregate sequence infos - if all orders have the same, then use that, otherwise use One-Off
                $sequence_for_this_order = self::get_sequence_for_order($order->ID);
                if ($sequence === '') $sequence = $sequence_for_this_order;
                if ($sequence !== $sequence_for_this_order) $sequence = PaymentInformation::S_ONEOFF; 
            } else {
                $payment = self::get_sepa_payment_info($order->ID, self::get_sequence_for_order($order->ID), $painFormat);
            }
            $transfer = apply_filters('wc_gateway_sepa_direct_debit:export_xml:transfer', $transfer, $order);
            $payment->addTransfer($transfer);
            if (!$singlePaymentInfo) {
                $sepaFile->addPaymentInformation($payment);
            }
        }
        if ($singlePaymentInfo) {
            $payment->setSequenceType($sequence);
            $sepaFile->addPaymentInformation($payment);
        }
        $domBuilder = new CustomerDirectDebitTransferDomBuilder($painFormat);
        $sepaFile->accept($domBuilder);
        $xml = $domBuilder->asXml();
        $now = new DateTime();
        $filename = $now->format('Y-m-d-H-i-s') . '-SEPA-DD-'. $orders[0]->ID . '.xml';
        if (false === file_put_contents(self::get_xml_path() . "/" . $filename, $xml)) {
            throw new Exception(sprintf(__('Could not create output file %s', self::DOMAIN), $filename));
        }
        return $filename;
    }


    private static function export_refund_xml($orders) {
        $gateway = new WC_Gateway_SEPA_Direct_Debit();
        $groupHeader = new GroupHeader($gateway->settings['target_bic'] . $orders[0]->ID, $gateway->settings['target_account_holder']);
        $sepaFile = new CustomerCreditTransferFile($groupHeader);
        $painFormatRefunds = 'pain.001.002.03';
        if (array_key_exists('pain_format_refunds', $gateway->settings)) {
            $painFormatRefunds = $gateway->settings['pain_format_refunds'];
        }
        $singlePaymentInfo = false;
        if (array_key_exists('single_payment_info', $gateway->settings)) {
            $singlePaymentInfo = ($gateway->settings['single_payment_info'] === 'yes');    
        }
        $payment = null;
        $sequence = '';

        if ($singlePaymentInfo) {
            $payment = self::get_sepa_payment_info("paymentInfo", PaymentInformation::S_ONEOFF, $painFormatRefunds);
        }
        foreach($orders as &$order) {
            $payment_info = self::get_refund_info($order);
            $parts = preg_split('/\./', $payment_info['total']);
            $amount = strval($parts[0]) * 100 + strval($parts[1]);
            $transfer = new CustomerCreditTransferInformation($amount, $payment_info['iban'], $payment_info['account_holder']);
            if ($payment_info['bic'])
                $transfer->setBic($payment_info['bic']);
            $remittance_info = "";
            if (array_key_exists('remittance_info', $gateway->settings)) {
                $remittance_info = $gateway->settings['remittance_info'] . " ";
            }
            $wc_order = new WC_Order($order->ID);
            $order_number = trim(str_replace('#', '', $wc_order->get_order_number()));
            $transfer->setRemittanceInformation($remittance_info . sprintf(__('Order %d', self::DOMAIN), $order_number));
            if ($singlePaymentInfo) {
                // try and aggregate sequence infos - if all orders have the same, then use that, otherwise use One-Off
                $sequence_for_this_order = self::get_sequence_for_order($order->ID);
                if ($sequence === '') $sequence = $sequence_for_this_order;
                if ($sequence !== $sequence_for_this_order) $sequence = PaymentInformation::S_ONEOFF; 
            } else {
                $payment = self::get_sepa_payment_info($order->ID, self::get_sequence_for_order($order->ID), $painFormatRefunds);
            }
            $transfer = apply_filters('wc_gateway_sepa_direct_debit:export_xml:transfer', $transfer, $order);
            $payment->addTransfer($transfer);
            if (!$singlePaymentInfo) {
                $sepaFile->addPaymentInformation($payment);
            }
        }
        if ($singlePaymentInfo) {
            $payment->setSequenceType($sequence);
            $sepaFile->addPaymentInformation($payment);
        }
        $domBuilder = new CustomerCreditTransferDomBuilder($painFormatRefunds);
        $sepaFile->accept($domBuilder);
        $xml = $domBuilder->asXml();
        $now = new DateTime();
        $filename = $now->format('Y-m-d-H-i-s') . '-SEPA-Refund-'. $orders[0]->ID . '.xml';
        if (false === file_put_contents(self::get_refund_xml_path() . "/" . $filename, $xml)) {
            throw new Exception(sprintf(__('Could not create output file %s', self::DOMAIN), $filename));
        }
        return $filename;
    }

    /**
     * Output the WooCommerce Settings page to list outstanding orders and previously generated SEPA-XML-files.
     */
    public static function sepa_dd_export_xml_page()
    {

        if (!current_user_can('manage_options')) {
            wp_die(__("You do not have permission to access this page!", self::DOMAIN));
        }

        wp_enqueue_style('sepa-dd', plugin_dir_url(__FILE__) . 'css/sepa-dd.css', array(), '1.0');

        echo '<div class="wrap"><div id="icon-tools" class="icon32"></div>';
        echo '<h2>' . __("Export SEPA XML", self::DOMAIN) . '</h2>';
        echo '</div>';

        if (self::isSubscriptions1x()) {
            echo '<div class="error"><p>' . __("Only WooCommerce Subscriptions version 2.0 and higher is supported.", self::DOMAIN) . '</p></div>';
        }

        try {

            $orderStatus = array_keys(wc_get_order_statuses());

            // do not export cancelled orders.
            $key = array_search('wc-cancelled', $orderStatus);
            unset($orderStatus[$key]);
            
            // do not export refunded orders.
            $ref = array_search('wc-refunded', $orderStatus);
            unset($orderStatus[$ref]);

            $query = array(
                'numberposts' => -1,
                'post_type' => 'shop_order',
                'post_status' => $orderStatus,
                'meta_query' => array(
                    array(
                        'key' => self::PAYMENT_METHOD,
                        'value' => 'sepa-direct-debit',
                    ),
                    array(
                        'key' => '_sepa_dd_exported',
                        'value' => false,
                    )
                ),
            );
            $to_be_exported = get_posts($query);
            $count = count($to_be_exported);

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_orders'])) {
                $filename = self::export_xml($to_be_exported);
                foreach ($to_be_exported as $order) {
                    update_post_meta($order->ID, '_sepa_dd_exported', true);
                    delete_post_meta($order->ID, '_sepa_refund_amount');
                }
                echo '<div class="updated"><p>' . sprintf(__("Exported %d payments to new SEPA XML: %s", self::DOMAIN), $count, $filename) . '</p></div>';
            } else {
                if ($to_be_exported) {
                    self::output_orders_to_be_exported($to_be_exported);
                } else {
                    echo '<div class="notice"><p>' . __("No new payments to export.", self::DOMAIN) . '</p></div>';
                }
            }
            self::list_xml_files();
        } catch (Throwable $e) {
            $msg = "Exception: ". $e->getMessage() . " (Code: " . $e->getCode() . ")";
            echo "<div class=\"error notice\"><p>$msg</p></div>";
        }



        // output the refunds
        $gateway = new WC_Gateway_SEPA_Direct_Debit();
        if($gateway->settings['enabled_refunds'] === 'yes') { 
            try {

                $orderStatus = array_keys(wc_get_order_statuses());
                
                // do not export cancelled orders.
                $key = array_search('wc-cancelled', $orderStatus);
                unset($orderStatus[$key]);

                $query_refunds = array(
                    'numberposts' => -1,
                    'post_type' => 'shop_order',
                    'post_status' => $orderStatus,
                    'meta_query' => array(
                        array(
                            'key' => self::PAYMENT_METHOD,
                            'value' => 'sepa-direct-debit',
                        ),
                        array(
                            'key' => '_sepa_dd_exported',
                            'value' => true,
                        ),
                        array(
                            'key' => '_sepa_refund_amount',
                            'value' => 0,
                            'compare' => '>',
                            'type' => 'DECIMAL'
                        ),
                    ),
                );
                $to_be_exported_refund = get_posts($query_refunds);
                
                    // print_r($to_be_exported_refund);
                
                $count = count($to_be_exported_refund);

                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_refunds'])) {
                    $filename = self::export_refund_xml($to_be_exported_refund);
                    foreach ($to_be_exported_refund as $order) {
                        update_post_meta($order->ID, '_sepa_refund_exported', true);
                        delete_post_meta($order->ID, '_sepa_refund_amount');
                    }
                    echo '<div class="updated"><p>' . sprintf(__("Exported %d refunds to new SEPA XML: %s", self::DOMAIN), $count, $filename) . '</p></div>';
                } else {
                    if ($to_be_exported_refund) {
                        self::output_refunds_to_be_exported($to_be_exported_refund);
                    } else {
                        echo '<div class="notice"><p>' . __("No new refunds to export.", self::DOMAIN) . '</p></div>';
                    }
                }
                self::list_refund_xml_files();
            } catch (Throwable $e) {
                $msg = "Exception: ". $e->getMessage() . " (Code: " . $e->getCode() . ")";
                echo "<div class=\"error notice\"><p>$msg</p></div>";
            }
        }
        
    }


    public static function scheduled_subscription_payment($amount_to_charge, $order) {
        $order->payment_complete('');
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', self::DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable SEPA Direct Debit.', self::DOMAIN),
                'default' => 'no'),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('SEPA Direct Debit', self::DOMAIN)
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default' => __('Pay with SEPA direct debit.', self::DOMAIN)
            ),
            'ask_for_BIC' => array(
                'title' => __('Ask for BIC', self::DOMAIN),
                'type' => 'checkbox',
                'label' => __('Check this if your customers have to enter their BIC/Swift-Number. Some banks accept IBAN-only for domestic transactions.', self::DOMAIN),
                'default' => 'yes'),
            'remittance_info' => array(
                'title' => __('Remittance information', self::DOMAIN),
                'type' => 'text',
                'description' => __('The text that will show on the account statement of the customer as remittance information.', self::DOMAIN),
            ),
            'target_account_holder' => array(
                'title' => __('Target account holder', self::DOMAIN),
                'type' => 'text',
                'description' => __('The account holder of the account that shall receive the payments.', self::DOMAIN),
            ),
            'target_iban' => array(
                'title' => __('Target IBAN', self::DOMAIN),
                'type' => 'text',
                'description' => __('The IBAN of the account that shall receive the payments.', self::DOMAIN),
            ),
            'target_bic' => array(
                'title' => __('Target BIC', self::DOMAIN),
                'type' => 'text',
                'description' => __('The BIC of the account that shall receive the payments.', self::DOMAIN),
            ),
            'creditor_id' => array(
                'title' => __('Creditor ID', self::DOMAIN),
                'type' => 'text',
                'description' => __('The creditor ID to be used in SEPA debits.', self::DOMAIN),
            ),
            'pain_format' => array(
                'title' => __('PAIN file format', self::DOMAIN),
                'type' => 'select',
                'description' => __('The PAIN XML version to create. If you don\'t know what this is, leave unchanged.', self::DOMAIN),
                'options' => array(
                    'pain.008.003.02' => __('pain.008.003.02 (SEPA DK 2.7 to 2.9)', self::DOMAIN),
                    'pain.008.001.02' => __('pain.008.001.02 (SEPA DK from 3.0)', self::DOMAIN)
                ),
                'default' => 'pain.008.003.02'
            ),
            'enabled_refunds' => array(
                'title' => __('Enable/Disable refunds', self::DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable the export of credit transfer files for refunds', self::DOMAIN),
                'default' => 'no'),
            'pain_format_refunds' => array(
                'title' => __('PAIN file format for refunds', self::DOMAIN),
                'type' => 'select',
                'description' => __('The PAIN XML version to create. If you don\'t know what this is, leave unchanged.', self::DOMAIN),
                'options' => array(
                    'pain.001.002.03' => __('pain.001.002.03', self::DOMAIN),
                    'pain.001.001.03' => __('pain.001.001.03', self::DOMAIN)
                ),
                'default' => 'pain.008.003.02'
            ),
            'export_as_COR1' => array(
                'title' => __('Export payments as express debits (COR1)', self::DOMAIN),
                'type' => 'checkbox',
                'label' => __('Check this to export debits as express or COR1 debits. This reduces the debit delay from 5 to 1 business day but is not supported by all banks. Please check with your bank before enabling this setting. Is ignored for pain.008.001.02 file format.', self::DOMAIN),
                'default' => 'no'
            ),
            'single_payment_info' => array(
                'title' => __('Export all transfers in single payment info segment', self::DOMAIN),
                'type' => 'checkbox',
                'label' => __('Check this to export all payments in a single payment info segment within the XML file. This is required by some banks (e.g., German Commerzbank) and may reduce costs with other banks. The sequence information will be set to "one-off" in this case for all payments. If this setting is disabled, each transfer is exported in a separate payment info segment having the sequence type set correctly ("one-off", "first of a series of recurring payments", "recurring payment").', self::DOMAIN),
                'default' => 'no'
            ),
            'payment_info_in_email' => array(
                'title' => __('Include payment information in admin emails', self::DOMAIN),
                'type' => 'checkbox',
                'label' => __('Check this to include account holder, IBAN and BIC (if requested, see setting above) in order emails sent to the shop admin.', self::DOMAIN),
                'default' => 'no'
            ),
            'set_to_processing' => array(
                'title' => __('Set order status to "Processing"', self::DOMAIN),
                'type' => 'checkbox',
                'label' => __('Check this to set the order status to "Processing" immediately. Use this option if you want to start processing the order and trust the direct debit to be fulfilled later. The payment does not need to be entered manually in this case after the money has been transferred.', self::DOMAIN),
                'default' => 'no'
            ),
        );
    }

    /**
     * @return bool
     */
    private static function isSubscriptions1x()
    {
        return class_exists('WC_Subscriptions_Order') && !function_exists('wcs_order_contains_renewal');
    }

    /**
     * Returns, if BIC is required
     */
    public function askForBIC() {
        return isset( $this->settings['ask_for_BIC'] ) && ($this->settings['ask_for_BIC'] === 'yes');
    }

    /**
     * Output payment gateway options.
     */
    function admin_options()
    {
        ?>
        <h2><?php _e('SEPA Direct Debit', self::DOMAIN); ?></h2>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table> <?php
    }

    function remove_white_space($string) {
        return preg_replace('/\s+/', '', $string);
    }

    /**
     * Process the payment for the order.
     *
     * @param int $order_id The order id.
     * @return array
     */
    function process_payment($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);

        $accountHolder = $this->get_post($this->id . '-account-holder');
        $iban = $this->remove_white_space($this->get_post($this->id . '-iban'));
        $iban = strtoupper($iban);
        $bic = '';
        if ($this->askForBIC()) {
            $bic = $this->remove_white_space($this->get_post($this->id . '-bic'));
            $bic = strtoupper($bic);
        }

        // check for use of stored payment method - if it is set, use payment info stored in 
        // token to overwrite the one read from post data (should be NULL in this case).
        $payment_token = $this->get_post('wc-' . $this->id . '-payment-token');
        if ( isset($payment_token ) && 'new' !== $payment_token ) {
            $token_id = wc_clean( $payment_token );
            $token    = WC_Payment_Tokens::get( $token_id );
            // Token user ID does not match the current user... bail out of payment processing.
            if ( $token->get_user_id() !== get_current_user_id() ) {
                wc_add_notice( __( 'There was a problem retrieving the sepa payment information.', self::DOMAIN ), 'error' );
                return;
            }
            $accountHolder = $token->get_account_holder();
            $iban = $token->get_iban();
            $bic = $token->get_bic();
        }

        // store new payment method if checkbox is selected
        $store_new = $this->get_post('wc-' . $this->id . '-new-payment-method');
        if ($store_new) {
            if ( !isset($payment_token ) || 'new' === $payment_token ) {
                $this->store_payment_token_from_post_data();
            }
        }

        $this->setSepaMetaData($order_id, $accountHolder, $iban, $bic);

        if (function_exists('wcs_get_subscriptions_for_order')) {
            foreach (wcs_get_subscriptions_for_order($order_id, array('order_type' => 'any')) as $subscription) {
                $this->setSepaMetaData($subscription->id, $accountHolder, $iban, $bic);
            }
        }

        $is_change_payment = false;
        if (function_exists('wcs_is_subscription')) {
            $is_change_payment = wcs_is_subscription($order_id);
        }
        if (isset($this->settings['set_to_processing']) and ($this->settings['set_to_processing'] === "yes")) {
            $order->payment_complete();
            $order->add_order_note( __('Automatically marking payment complete due to payment gateway settings.', self::DOMAIN) );

        } if ($is_change_payment) {
            $order->payment_complete();
            $order->add_order_note( __('Automatically marking payment complete due to payment change by customer.', self::DOMAIN) );
        } else {
            // Mark as on-hold (we're awaiting the Direct Debit)
            $order->update_status('on-hold', __('Awaiting SEPA direct debit completion.', self::DOMAIN));

            // Reduce stock levels
            $order->reduce_order_stock();
        }

        // Remove cart
        $woocommerce->cart->empty_cart();

        // Return thank you redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

    /**
     * Output the script for online validation of IBAN and BIC in the checkout page.
     */
    private static function enqueue_validation_script() {

        wp_enqueue_script('jquery-validate', plugin_dir_url(__FILE__) . 'js/jquery.validate.min.js', array('jquery'), '1.10.0', true);
        wp_enqueue_script('sepa-dd', plugin_dir_url(__FILE__) . 'js/sepa-dd.js', array('jquery'), '1.10.0', true);

        // Register the script
        wp_register_script('jquery-validate-adtl', plugin_dir_url( __FILE__ ) . 'js/additional-methods.min.js', array('jquery'), '1.10.0', true);

        // Localize the script
        $translation_array = array(
            'invalid_iban_message' => __( 'Please enter a valid IBAN.', self::DOMAIN ),
            'invalid_bic_message' => __( 'Please enter a valid BIC.', self::DOMAIN ),
        );
        wp_localize_script('jquery-validate-adtl', 'sepa_dd_localization', $translation_array );

        // Enqueued script with localized data.
        wp_enqueue_script('jquery-validate-adtl' );
        wp_enqueue_style('sepa-dd', plugin_dir_url(__FILE__) . 'css/sepa-dd.css', array(), '1.0');
    }

    /**
     * Output the payment fields as part of the checkout page.
     */
        public function payment_fields() {
        if ( $this->supports( 'tokenization' ) && is_checkout() ) {
            $this->tokenization_script();
            $this->saved_payment_methods();
            $this->form();
            $this->save_payment_method_checkbox();
        } else {
            $this->form();
        }
    }
    /**
     * Outputs fields for entering Sepa information.
     *
     */
    public function form() {
        self::enqueue_validation_script();

        if ( $this->description ) {
            echo wpautop( wptexturize( trim( $this->description ) ) );
        }

        $fields = array(
            'account-holder' => '<p class="form-row form-row-wide">
                    <label for="' . esc_attr($this->id) . '-account-holder">' . __('Account holder', self::DOMAIN) . ' <span class="required">*</span></label>
                    <input id="' . esc_attr($this->id) . '-account-holder" class="input-text sepa-direct-debit-payment-field"
                        type="text" maxlength="30" autocomplete="off" placeholder="' . esc_attr__('John Doe', self::DOMAIN) . '" name="' . $this->id . '-account-holder' . '" />
                    </p>',
            'iban' => '<p class="form-row form-row-wide">
                    <label for="' . esc_attr($this->id) . '-iban">' . __('IBAN', self::DOMAIN) . ' <span class="required">*</span></label>
                    <input id="' . esc_attr($this->id) . '-iban" class="input-text sepa-direct-debit-payment-field"
                        type="text" maxlength="31" autocomplete="off" placeholder="' . esc_attr__('DE11222222223333333333', self::DOMAIN) . '" name="' . $this->id . '-iban' . '" />
                    </p>'
        );
        if ($this->askForBIC()) {
            $fields['bic'] = '<p class="form-row form-row-wide">
                        <label for="' . esc_attr($this->id) . '-bic">' . __('BIC', self::DOMAIN) . ' <span class="required">*</span></label>
                        <input id="' . esc_attr($this->id) . '-bic" class="input-text sepa-direct-debit-payment-field"
                            type="text" maxlength="11" autocomplete="off" placeholder="' . esc_attr__('XXXXDEYYZZZ', self::DOMAIN) . '" name="' . $this->id . '-bic' . '" />
                        </p>';
        }

        ?>
        <fieldset id="<?php echo $this->id; ?>-cc-form">
            <?php
            foreach ($fields as $field) {
                echo $field;
            }
            ?>
            <div class="clear"></div>
        </fieldset>
        <?php
    }




    /**
     * Convenience function to get the post parameter or null in case it doesn't exist.
     * @param $name The requested POST parameter.
     * @return null That parameters value or null if does not exist.
     */
    private function get_post($name)
    {
        if (isset($_POST[$name]))
            return $_POST[$name];
        else
            return NULL;
    }

    /**
     * Convenience function to check for required field and output corresponding error messages for missing fields.
     *
     * @param $fieldname
     * @param $label
     * @param $errors
     * @return null
     */
    function check_required_field($fieldname, $label, &$errors)
    {
        $value = $this->get_post($fieldname);
        if (!$value)
            $errors[] = '<strong>' . $label . '</strong> ' . __('is a required field.', 'woocommerce');
        return $value;
    }

    /**
     * Validate Frontend Fields
     **/
    function validate_fields()
    {
        $errors = array();
        $payment_token = $this->get_post('wc-' . $this->id . '-payment-token');
        if ( isset($payment_token) && 'new' !== $payment_token ) {
            // if using stored payment method, do not check fields because they may remain empty/ 
            // contain faulty data.
            return true;
        }

        $this->check_required_field($this->id . '-account-holder', __('Account holder', self::DOMAIN), $errors);
        $iban = $this->check_required_field($this->id . '-iban', __('IBAN', self::DOMAIN), $errors);
        if ($iban != null) {
            if (!checkIBAN($iban))
                $errors[] = __('Please enter a valid IBAN.', self::DOMAIN);
        }
        if ($this->askForBIC()) {
            $bic = $this->check_required_field($this->id . '-bic', __('BIC', self::DOMAIN), $errors);
            if ($bic != null) {
                if (!checkBIC($bic))
                    $errors[] = __('Please enter a valid BIC.', self::DOMAIN);
            }
        }

        if ($errors) {
            $size = count($errors);
            for ($i = 0; $i < $size; $i++)
                wc_add_notice($errors[$i], 'error');
        } else {
            return true;
        }
    }

    /**
     * @param $order_id
     * @param $accountHolder
     * @param $iban
     * @param $bic
     */
    public function setSepaMetaData($order_id, $accountHolder, $iban, $bic)
    {
        update_post_meta($order_id, self::SEPA_DD_EXPORTED, false);
        update_post_meta($order_id, self::SEPA_REFUND_EXPORTED, false);
        update_post_meta($order_id, self::SEPA_REFUND_OK_TO_EXPORT, false);
        update_post_meta($order_id, self::SEPA_DD_ACCOUNT_HOLDER, $accountHolder);
        update_post_meta($order_id, self::SEPA_DD_IBAN, $iban);
        update_post_meta($order_id, self::SEPA_DD_BIC, $bic);
    }

    /**
     * Read payment data from post data and store in new payment token for current user.
     * 
     * @return boolean
     */
    public function store_payment_token_from_post_data() {
        $token = new WC_Payment_Token_SepaDD();
        $token->set_gateway_id( $this->id );
 
        $account_holder = $this->get_post($this->id . '-account-holder');
        $iban = $this->remove_white_space($this->get_post($this->id . '-iban'));
        $iban = strtoupper($iban);
        $bic = '';
        if ($this->askForBIC()) {
            $bic = $this->remove_white_space($this->get_post($this->id . '-bic'));
            $bic = strtoupper($bic);
        }

        $token->set_iban( $iban );
        $token->set_bic( $bic );
        $token->set_account_holder( $account_holder );

        $token->set_user_id( get_current_user_id() );
        if (!$token->save()) {
            wc_add_notice( __( 'There was a problem storing the sepa payment information.', self::DOMAIN ), 'error' );
            return false;
        }
        return true;
    }

    /**
     * Adds payment information to admin emails if requested in the settings
     * @param  Order $order         The order in question
     * @param  boolean $sent_to_admin If the email is sent to a shop admin
     * @param  boolean $is_plain_text plain text email?
     * @return void
     */
    public static function custom_woocommerce_email_order_meta( $order, $sent_to_admin, $is_plain_text ) {
        $gateway = new WC_Gateway_SEPA_Direct_Debit();
        if ($sent_to_admin
            && isset($gateway->settings['payment_info_in_email']) 
            && $gateway->settings['payment_info_in_email'] === 'yes') { 

            $iban = get_post_meta( $order->get_id(), self::SEPA_DD_IBAN, true );
            $bic = get_post_meta( $order->get_id(), self::SEPA_DD_BIC, true );
            $account_holder = get_post_meta( $order->get_id(), self::SEPA_DD_ACCOUNT_HOLDER, true );

            if ( $is_plain_text ) {
                echo __( 'PAYMENT INFORMATION', self::DOMAIN ) . "\n";
                echo __( 'IBAN', self::DOMAIN ) . ": " . $iban . "\n";
                if ($gateway->askForBIC()) {
                    echo __( 'BIC', self::DOMAIN ) . ": " . $bic . "\n";
                }
                echo __( 'Account Holder', self::DOMAIN ) . ": " . $account_holder . "\n";
            } else {         
                // you shouldn't have to worry about inline styles, WooCommerce adds them itself depending on the theme you use
                echo '<h2>' . __( 'Payment information', self::DOMAIN ) . '</h2>';
                echo '<ul>';
                echo '<li><strong>' . __( 'IBAN', self::DOMAIN ) . ':</strong>&nbsp;' . $iban . '</li>';
                if ($gateway->askForBIC()) {
                    echo '<li><strong>' . __( 'BIC', self::DOMAIN ) . ':</strong>&nbsp;' . $bic . '</li>';
                }
                echo '<li><strong>' . __( 'Account Holder', self::DOMAIN ) . ':</strong>&nbsp;' . $account_holder . '</li>';
                echo '</ul>';
            }
        }
    }    

    /**
    * @Store payment method in a WooCommerce token
    */
    public function add_payment_method() {
        $success = $this->store_payment_token_from_post_data();

        return array(
            'result'   => $success ? 'success' : 'failure',
            'redirect' => wc_get_endpoint_url( 'payment-methods' ),
        );
    }

    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        global $woocommerce;
        // Refund $amount for the order with ID $order_id
        // Store refund amount in new post meta
        update_post_meta($order_id, '_sepa_refund_amount', $amount);
        return true;
      }
}