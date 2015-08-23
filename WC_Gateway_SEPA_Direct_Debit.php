<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * User: Joern
 * Date: 23.08.2015
 * Time: 08:25
 */
class WC_Gateway_SEPA_Direct_Debit extends WC_Payment_Gateway
{
    function __construct()
    {
        global $domain;
        $this->id = 'sepa-direct-debit';
        $this->method_title = __('SEPA Direct Debit', $domain);
        $this->method_description = __('Creates PAIN.008 XML-files for WooCommerce payments.', $domain);
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        if (is_admin())
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    function checkIBAN($iban)
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

    function checkBIC($bic)
    {
        return preg_match("/^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$/", $bic);
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields()
    {
        global $domain;

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', $domain),
                'type' => 'checkbox',
                'label' => __('Enable SEPA Direct Debit.', $domain),
                'default' => 'no'),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('SEPA Direct Debit', $domain)
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default' => __('Pay with SEPA direct debit.', $domain)
            ),
            'ask_for_BIC' => array(
                'title' => __('Ask for BIC', $domain),
                'type' => 'checkbox',
                'label' => __('Check this if your customers have to enter their BIC/Swift-Number. Some banks accept IBAN-only for domestic transactions.', $domain),
                'default' => 'yes'),
        );
    }

    function admin_options()
    {
        global $domain;
        ?>
        <h2><?php _e('SEPA Direct Debit', $domain); ?></h2>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table> <?php
    }

    function process_payment($order_id)
    {
        global $woocommerce, $domain;
        $order = new WC_Order($order_id);

        update_post_meta( $order_id, '_sepa-dd-exported', false);
        update_post_meta( $order_id, '_sepa-dd-account-holder', $this->get_post($this->id . '-account-holder') );
        update_post_meta( $order_id, '_sepa-dd-iban', $this->get_post($this->id . '-iban') );
        if ($this->settings['ask_for_BIC'])
            update_post_meta( $order_id, '_sepa-dd-bic', $this->get_post($this->id . '-bic') );

        // Mark as on-hold (we're awaiting the Direct Debit)
        $order->update_status('on-hold', __('Awaiting SEPA direct debit completion.', $domain));

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

    function payment_fields()
    {
        global $domain;

        wp_enqueue_script('jquery-validate', plugin_dir_url(__FILE__) . 'js/jquery.validate.min.js', array('jquery'), '1.10.0', true);
        enqueue_validation_script();
        wp_enqueue_style('sepa-dd', plugin_dir_url(__FILE__) . 'css/sepa-dd.css', array(), '1.0');

        $fields = array(
            'account-holder' => '<p class="form-row form-row-wide">
					<label for="' . esc_attr($this->id) . '-account-holder">' . __('Account holder', $domain) . ' <span class="required">*</span></label>
					<input id="' . esc_attr($this->id) . '-account-holder" class="input-text wc-credit-card-form-card-number"
						type="text" maxlength="30" autocomplete="off" placeholder="' . esc_attr__('John Doe', $domain) . '" name="' . $this->id . '-account-holder' . '" />
					</p>',
            'iban' => '<p class="form-row form-row-wide">
					<label for="' . esc_attr($this->id) . '-iban">' . __('IBAN', $domain) . ' <span class="required">*</span></label>
					<input id="' . esc_attr($this->id) . '-iban" class="input-text wc-credit-card-form-card-number"
						type="text" maxlength="31" autocomplete="off" placeholder="' . esc_attr__('DE11222222223333333333', $domain) . '" name="' . $this->id . '-iban' . '" />
					</p>'
        );
        if ($this->settings['ask_for_BIC']) {
            $fields['bic'] = '<p class="form-row form-row-wide">
						<label for="' . esc_attr($this->id) . '-bic">' . __('BIC', $domain) . ' <span class="required">*</span></label>
						<input id="' . esc_attr($this->id) . '-bic" class="input-text wc-credit-card-form-card-number"
							type="text" maxlength="11" autocomplete="off" placeholder="' . esc_attr__('XXXXDEYYZZZ', $domain) . '" name="' . $this->id . '-bic' . '" />
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
        <script>
            jQuery(document).ready(function () {
                jQuery("form[name='checkout']").validate({
                    rules: {
                        "<?php echo $this->id; ?>-iban": "iban",
                        "<?php echo $this->id; ?>-bic": "bic",
                    }
                })
            });
        </script>
        <?php
    }

    private function get_post($name)
    {
        if (isset($_POST[$name]))
            return $_POST[$name];
        else
            return NULL;
    }

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

        global $domain, $woocommerce;

        $errors = array();

        $this->check_required_field($this->id . '-account-holder', __('Account holder', $domain), $errors);
        $iban = $this->check_required_field($this->id . '-iban', __('IBAN', $domain), $errors);
        if ($iban != null) {
            if (!$this->checkIBAN($iban))
                $errors[] = __('Please enter a valid IBAN.', $domain);
        }
        if ($this->settings['ask_for_BIC']) {
            $bic = $this->check_required_field($this->id . '-bic', __('BIC', $domain), $errors);
            if ($iban != null) {
                if (!$this->checkBIC($bic))
                    $errors[] = __('Please enter a valid BIC.', $domain);
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