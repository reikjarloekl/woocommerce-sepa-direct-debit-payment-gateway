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
	class WC_Gateway_SEPA_Direct_Debit extends WC_Payment_Gateway {

		function __construct() {
			global $domain;
			$this->id                 = 'sepa-direct-debit';
			$this->method_title       = __( 'SEPA Direct Debit', $domain );
			$this->method_description = __( 'Creates PAIN.008 XML-files for WooCommerce payments.', $domain );
			$this->has_fields = true;

			$this -> init_form_fields();
			$this -> init_settings();

			$this -> title = $this -> settings['title'];
			$this -> description = $this -> settings['description'];

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		/**
		 * Initialise Gateway Settings Form Fields
		 */
		function init_form_fields() {
			global $domain;

			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', $domain),
					'type' => 'checkbox',
					'label' => __('Enable SEPA Direct Debit.', $domain),
					'default' => 'no'),
				'title' => array(
					'title' => __( 'Title', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default' => __( 'SEPA Direct Debit', $domain )
				),
				'description' => array(
					'title' => __( 'Description', 'woocommerce' ),
					'type' => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
					'default' => __('Pay with SEPA direct debit.', $domain)
				),
				'ask_for_BIC' => array(
					'title' => __('Ask for BIC', $domain),
					'type' => 'checkbox',
					'label' => __('Check this if your customers have to enter their BIC/Swift-Number. Some banks accept IBAN-only for domestic transactions.', $domain),
					'default' => 'no'),
			);
		}

		function admin_options() {
			global $domain;
			?>
			<h2><?php _e('SEPA Direct Debit', $domain); ?></h2>
			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table> <?php
		}

		function process_payment( $order_id ) {
			global $woocommerce, $domain;
			$order = new WC_Order( $order_id );

			// Mark as on-hold (we're awaiting the Direct Debit)
			$order->update_status('on-hold', __( 'Awaiting SEPA direct debit completion.', $domain ));

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			$woocommerce->cart->empty_cart();

			// Return thank you redirect
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

		function payment_fields(){
			global $domain;
			$fields = array(
				'account-holder' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-account-holder">' . __( 'Account holder', $domain ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-account-holder" class="input-text" type="text" maxlength="30" autocomplete="off" placeholder="' . esc_attr__( 'John Doe', $domain ) . '" name="' . $this->id . '-account-holder' . '" />
			</p>'
			);

			?>
			<fieldset id="<?php echo $this->id; ?>-cc-form">
				<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
				<?php
				foreach ( $fields as $field ) {
					echo $field;
				}
				?>
				<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
				<div class="clear"></div>
			</fieldset>
			<?php
		}
	}

	function add_to_payment_gateways( $methods ) {
		$methods[] = 'WC_Gateway_SEPA_Direct_Debit';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'add_to_payment_gateways' );
}

add_action( 'plugins_loaded', 'init_sepa_direct_debit' );
?>