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
        $this->supports = array(
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change'
        );

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

        add_action( 'scheduled_subscription_payment_' . self::GATEWAY_ID,  __CLASS__ . '::scheduled_subscription_payment', 10, 3);
        add_filter( 'woocommerce_payment_gateways', __CLASS__ . '::add_to_payment_gateways' );
        add_action( 'admin_menu', __CLASS__ . '::register_sepa_xml_page', 10);
        add_filter( 'woocommerce_subscriptions_renewal_order_meta_query', __CLASS__ . '::remove_renewal_order_meta', 10, 4 );
        add_filter( 'woocommerce_subscriptions_renewal_order_meta', __CLASS__ . '::add_exported_to_renewal_order_meta', 10, 4 );

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
    public static function remove_renewal_order_meta( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {
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
    public static function add_exported_to_renewal_order_meta( $order_meta, $original_order_id, $renewal_order_id, $new_order_role ) {
        $gateway = get_post_meta($original_order_id, self::PAYMENT_METHOD, true);
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
        $result = array();
        $result['account_holder'] = get_post_meta($post->ID, self::SEPA_DD_ACCOUNT_HOLDER, true);
        $result['total'] = floatval(get_post_meta($post->ID, self::ORDER_TOTAL, true));
        $result['iban'] = get_post_meta($post->ID, self::SEPA_DD_IBAN, true);
        $result['bic'] = get_post_meta($post->ID, self::SEPA_DD_BIC, true);
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
            <td><?= $payment_info['account_holder'] ?></td>
            <td><?php echo $payment_info['iban'] ?></td>
            <td><?php echo $payment_info['bic'] ?></td>
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
            $transfer = new CustomerDirectDebitTransferInformation($payment_info['total'], $payment_info['iban'], $payment_info['account_holder']);
            if ($payment_info['bic'])
                $transfer->setBic($payment_info['bic']);
            $transfer->setMandateSignDate(new \DateTime($order->post_date));
            $transfer->setMandateId($order->ID);
            $transfer->setRemittanceInformation(__(sprintf('Order %d', $order->ID), self::DOMAIN));
            $payment = new PaymentInformation($order->ID, $gateway->settings['target_iban'], $gateway->settings['target_bic'], $gateway->settings['target_account_holder']);
            $payment->setSequenceType(PaymentInformation::S_ONEOFF);
            if (class_exists('WC_Subscriptions_Renewal_Order')) {
                if (WC_Subscriptions_Renewal_Order::is_renewal($order->ID)) {
                    $payment->setSequenceType(PaymentInformation::S_RECURRING);
                } else if (WC_Subscriptions_Order::order_contains_subscription($order->ID)) {
                    $payment->setSequenceType(PaymentInformation::S_FIRST);
                }
            }
            $payment->setDueDate(new \DateTime());
            $payment->setCreditorId($gateway->settings['creditor_id']);
            $payment->setLocalInstrumentCode('COR1');
            $payment->addTransfer($transfer);
            $sepaFile->addPaymentInformation($payment);
        }
        $domBuilder = new CustomerDirectDebitTransferDomBuilder();
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


        try {
            $query = array(
                'numberposts' => -1,
                'post_type' => 'shop_order',
                'post_status' => array_keys(wc_get_order_statuses()),
                'meta_query' => array(
                    array(
                        'key' => self::PAYMENT_METHOD,
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

    /**
     * Check the given IBAN for correctness. Does not check for existence of the IBAN.
     *
     * @param $iban The IBAN to check.
     * @return bool True, in case the IBAN is valid.
     */
    private static function checkIBAN($iban)
    {
        $iban = strtolower(str_replace(' ', '', $iban));
        $iban_lengths = array('al' => 28, 'ad' => 24, 'at' => 20, 'az' => 28, 'bh' => 22, 'be' => 16, 'ba' => 20, 'br' => 29, 'bg' => 22, 'cr' => 21, 'hr' => 21,
            'cy' => 28, 'cz' => 24, 'dk' => 18, 'do' => 28, 'ee' => 20, 'fo' => 18, 'fi' => 18, 'fr' => 27, 'ge' => 22, 'de' => 22, 'gi' => 23,
            'gr' => 27, 'gl' => 18, 'gt' => 28, 'hu' => 28, 'is' => 26, 'ie' => 22, 'il' => 23, 'it' => 27, 'jo' => 30, 'kz' => 20, 'kw' => 30,
            'lv' => 21, 'lb' => 28, 'li' => 21, 'lt' => 20, 'lu' => 20, 'mk' => 19, 'mt' => 31, 'mr' => 27, 'mu' => 30, 'mc' => 27, 'md' => 24,
            'me' => 22, 'nl' => 18, 'no' => 15, 'pk' => 24, 'ps' => 29, 'pl' => 28, 'pt' => 25, 'qa' => 29, 'ro' => 24, 'sm' => 27, 'sa' => 24,
            'rs' => 22, 'sk' => 24, 'si' => 19, 'es' => 24, 'se' => 24, 'ch' => 21, 'tn' => 24, 'tr' => 26, 'ae' => 23, 'gb' => 22, 'vg' => 24);
        $char_values = array('a' => 10, 'b' => 11, 'c' => 12, 'd' => 13, 'e' => 14, 'f' => 15, 'g' => 16, 'h' => 17, 'i' => 18, 'j' => 19, 'k' => 20, 'l' => 21, 'm' => 22,
            'n' => 23, 'o' => 24, 'p' => 25, 'q' => 26, 'r' => 27, 's' => 28, 't' => 29, 'u' => 30, 'v' => 31, 'w' => 32, 'x' => 33, 'y' => 34, 'z' => 35);

        $country_code = substr($iban, 0, 2);
        // Does country even exist in list?
        if (!array_key_exists($country_code, $iban_lengths))
            return false;

        if (strlen($iban) == $iban_lengths[$country_code]) {

            // move country prefix and checksum to the end
            $MovedChar = substr($iban, 4) . substr($iban, 0, 4);
            $MovedCharArray = str_split($MovedChar);
            $expanded_string = "";

            // expand letters to 2 digit number strings.
            foreach ($MovedCharArray AS $key => $value) {
                if (!is_numeric($MovedCharArray[$key])) {
                    $MovedCharArray[$key] = $char_values[$MovedCharArray[$key]];
                }
                $expanded_string .= $MovedCharArray[$key];
            }

            if (bcmod($expanded_string, '97') == 1) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Check the given BIC for correctness. Does not check for existence of the BIC.
     *
     * @param $bic The BIC to check.
     * @return bool True, in case the BIC is valid.
     */
    private static function checkBIC($bic)
    {
        return preg_match("/^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$/", $bic);
    }

    public static function scheduled_subscription_payment($amount_to_charge, $order, $product_id) {
        WC_Subscriptions_Manager::process_subscription_payments_on_order($order->id);
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
        );
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

        update_post_meta( $order_id, self::SEPA_DD_EXPORTED, false);
        update_post_meta( $order_id, self::SEPA_DD_ACCOUNT_HOLDER, $this->get_post($this->id . '-account-holder') );
        update_post_meta( $order_id, self::SEPA_DD_IBAN, $this->get_post($this->id . '-iban') );
        if ($this->askForBIC())
            update_post_meta( $order_id, self::SEPA_DD_BIC, $this->get_post($this->id . '-bic') );

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
            if (!$this->checkIBAN($iban))
                $errors[] = __('Please enter a valid IBAN.', self::DOMAIN);
        }
        if ($this->askForBIC()) {
            $bic = $this->check_required_field($this->id . '-bic', __('BIC', self::DOMAIN), $errors);
            if ($iban != null) {
                if (!$this->checkBIC($bic))
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
}