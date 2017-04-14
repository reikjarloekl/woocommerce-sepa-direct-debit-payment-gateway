<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * User: Joern
 * Date: 23.08.2015
 * Time: 08:25
 */

define("SEPA_DD_DIR", plugin_dir_path(__FILE__));

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
use Digitick\Sepa\TransferFile\CustomerDirectDebitTransferFile;
use Digitick\Sepa\TransferInformation\CustomerDirectDebitTransferInformation;

require_once("sepa-checks.php");

class WC_Gateway_SEPA_Direct_Debit extends WC_Payment_Gateway
{
    const GATEWAY_ID = 'sepa-direct-debit';
    const SEPA_DD_EXPORTED = '_sepa_dd_exported';
    const SEPA_DD_ACCOUNT_HOLDER = '_sepa_dd_account_holder';
    const SEPA_DD_IBAN = '_sepa_dd_iban';
    const SEPA_DD_BIC = '_sepa_dd_bic';
    const DOMAIN = 'sepa-direct-debit';
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
        $this->supports = array('products');
        if (!self::isSubscriptions1x()) {
            $this->supports = array_merge($this->supports, array(
                'subscriptions',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change_customer',
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

        add_action( 'add_meta_boxes', __CLASS__ . '::sepa_dd_add_meta_box' );

    }

    public static function get_parent_order( $order_id ) {
        $subscriptions = array();
        if ( wcs_is_subscription( $order_id ) ) {
            $subscriptions[] = wcs_get_subscription( $order_id );
        } elseif ( wcs_order_contains_subscription( $order_id, array( 'parent', 'renewal' ) ) ) {
            $subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => array( 'parent', 'renewal' ) ) );
        }
        if ( 1 == count( $subscriptions ) ) {
            foreach ($subscriptions as $subscription) {
                if (false !== $subscription->order) {
                    $orders[] = $subscription->order;
                }
            }
        }
        if (empty($orders)) return null;
        return $orders[0]->id;
    }

    // Add a meta box to the order page to show IBAN and BIC.
    public static function sepa_dd_add_meta_box()
    {
        global $post;

        if(empty($post)) return;

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
            .		"'" . self::SEPA_DD_EXPORTED ."')";
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

    /**
     * Returns the full path within wp-upload to export SEPA-XML-files to.
     * @return string The full output path.
     * @throws Exception in case, output path could not be created.
     */
    private static function get_xml_path() {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/' . self::get_xml_dir();
        if (false === wp_mkdir_p( $target_dir )) {
            throw new Exception(__(sprintf('Could not create output path %s', $target_dir)));
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

        $result = array();
        $result['total'] = get_post_meta($post, self::ORDER_TOTAL, true);
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
            echo '<form method="post" action=""><p class="submit"><input class="button-primary" type="submit" value="' . __("Export to SEPA XML", self::DOMAIN) . '"></p></form>';
        } else {
            echo '<div class="error"><p>' . __("Please setup the payment target information first in WooCommerce/Settings/Checkout/SEPA Direct Debit.", self::DOMAIN) . '</p></div>';
            echo '<p class="submit"><input class="button-primary" type="submit" disabled value="' . __("Export to SEPA XML", self::DOMAIN) . '"></p>';
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
        echo '<h3>'. __("SEPA XML-Files", self::DOMAIN) . '</h3>';
        echo '<div class="ui-state-highlight"><p>'. __("Please use right-click and 'save-link-as' to download the XML-files.", self::DOMAIN) . '</p></div>';
        echo '<ul>';
        foreach($ffs as $ff){
            echo '<li><a href="' . $base_url .'/' . self::get_xml_dir() . '/' .$ff . '" target="_blank">'. $ff . '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Export the given orders into a PAIN.008.003.02 XML-file.
     *
     * @param $orders The orders to export.
     * @return string The filename of the generated XML-file.
     * @throws Exception in case output file cannot be created.
     */
    private static function export_xml($orders) {
        $gateway = new WC_Gateway_SEPA_Direct_Debit();
        $groupHeader = new GroupHeader($gateway->settings['target_bic'] . $orders[0]->ID, $gateway->settings['target_account_holder']);
        $sepaFile = new CustomerDirectDebitTransferFile($groupHeader);

        foreach($orders as $order) {
            $payment_info = self::get_payment_info($order);
            $parts = preg_split('/\./', $payment_info['total']);
            $amount = strval($parts[0]) * 100 + strval($parts[1]);
            $transfer = new CustomerDirectDebitTransferInformation($amount, $payment_info['iban'], $payment_info['account_holder']);
            if ($payment_info['bic'])
                $transfer->setBic($payment_info['bic']);
            $transfer->setMandateSignDate(new \DateTime($order->post_date));
            $transfer->setMandateId($order->ID);
            $transfer->setRemittanceInformation(__(sprintf('Order %d', $order->ID), self::DOMAIN));
            $iban = strtoupper($gateway->settings['target_iban']);
            $bic = strtoupper($gateway->settings['target_bic']);
            $payment = new PaymentInformation($order->ID, $iban, $bic, $gateway->settings['target_account_holder']);
            $payment->setSequenceType(PaymentInformation::S_ONEOFF);
            if (function_exists( 'wcs_order_contains_renewal' ) && function_exists( 'wcs_order_contains_resubscribe' )) {
                $isRenewal = wcs_order_contains_renewal($order) || wcs_order_contains_resubscribe($order);
                if ($isRenewal) {
                    $payment->setSequenceType(PaymentInformation::S_RECURRING);
                } else if (WC_Subscriptions_Order::order_contains_subscription($order->ID)) {
                    $payment->setSequenceType(PaymentInformation::S_FIRST);
                }
            }
            $payment->setDueDate(new \DateTime('tomorrow'));
            $payment->setCreditorId(strtoupper($gateway->settings['creditor_id']));
            $cor1_enabled = $gateway->settings['export_as_COR1'];
            $payment->setLocalInstrumentCode($cor1_enabled ? 'COR1' : 'CORE');
            $payment->addTransfer($transfer);
            $sepaFile->addPaymentInformation($payment);
        }
        $domBuilder = new CustomerDirectDebitTransferDomBuilder('pain.008.003.02');
        $sepaFile->accept($domBuilder);
        $xml = $domBuilder->asXml();
        $now = new DateTime();
        $filename = $now->format('Y-m-d-H-i-s') . '-SEPA-DD-'. $orders[0]->ID . '.xml';
        if (false === file_put_contents(self::get_xml_path() . "/" . $filename, $xml)) {
            throw new Exception(__(sprintf('Could not create output file %s', $filename)));
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

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $filename = self::export_xml($to_be_exported);
                foreach ($to_be_exported as $order) {
                    update_post_meta($order->ID, '_sepa_dd_exported', true);
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
        } catch (Exception $e) {
            $msg = "Exception: ". $e->getMessage() . " (Code: " . $e->getCode() . ")";
            echo "<div class=\"error notice\"><p>$msg</p></div>";
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
            'export_as_COR1' => array(
                'title' => __('Export payments as express debits (COR1)', self::DOMAIN),
                'type' => 'checkbox',
                'label' => __('Check this to export debits as express or COR1 debits. This reduces the debit delay from 5 to 1 business day but is not supported by all banks. Please check with your bank before enabling this setting.', self::DOMAIN),
                'default' => 'no'),
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
    function askForBIC() {
        return isset( $this->settings['ask_for_BIC'] ) && ($this->settings['ask_for_BIC'] == 'yes');
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

        $this->setSepaMetaData($order_id, $accountHolder, $iban, $bic);

        if (function_exists('wcs_get_subscriptions_for_order')) {
            foreach (wcs_get_subscriptions_for_order($order, array('order_type' => 'any')) as $subscription) {
                $this->setSepaMetaData($subscription->id, $accountHolder, $iban, $bic);
            }
        }

        // Mark as on-hold (we're awaiting the Direct Debit)
        $order->update_status('on-hold', __('Awaiting SEPA direct debit completion.', self::DOMAIN));

        // Reduce stock levels
        $order->reduce_order_stock();

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
    function payment_fields()
    {
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

        $this->check_required_field($this->id . '-account-holder', __('Account holder', self::DOMAIN), $errors);
        $iban = $this->check_required_field($this->id . '-iban', __('IBAN', self::DOMAIN), $errors);
        if ($iban != null) {
            if (!checkIBAN($iban))
                $errors[] = __('Please enter a valid IBAN.', self::DOMAIN);
        }
        if ($this->askForBIC()) {
            $bic = $this->check_required_field($this->id . '-bic', __('BIC', self::DOMAIN), $errors);
            if ($iban != null) {
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
        update_post_meta($order_id, self::SEPA_DD_ACCOUNT_HOLDER, $accountHolder);
        update_post_meta($order_id, self::SEPA_DD_IBAN, $iban);
        update_post_meta($order_id, self::SEPA_DD_BIC, $bic);
    }
}