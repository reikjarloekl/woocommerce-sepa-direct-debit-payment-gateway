<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * User: Joern
 * Date: 23.08.2015
 * Time: 08:25
 */

define("SEPA_DD_DIR", plugin_dir_path(__FILE__));

require_once("sepa-checks.php");

/**
 * Custom token type to support storing sepa direct debit as a payment method in the users profile.
 */
class WC_Payment_Token_SepaDD extends WC_Payment_Token {

	const IBAN = 'IBAN';
	const BIC = 'BIC';
	const ACCOUNT_HOLDER = 'ACCOUNT_HOLDER';


    /** @protected string Token Type String */
    public $type = 'SepaDD';

    /**
     * Validate the token before storing it into the database.
     * 
     * @return boolean
     */		
	public function validate() {
	    if ( false === parent::validate() ) {
		       return false;
		}
		if (false === checkIBAN($this->get_iban())) {
			return false;
		}
		if (false === checkBIC($this->get_bic())) {
			return false;
		}
		return true;
	}

	/**
	 * Get the IBAN stored in the token.
	 * 
	 * @return string
	 */
	public function get_iban() {
    	return $this->get_meta( self::IBAN );
	}

	/**
	 * @param string $iban The IBAN to store in the token.
	 */
	public function set_iban( $iban ) {
    	$this->add_meta_data( self::IBAN, $iban, true );
    	$this->set_token($iban);
	}

	/**
	 * Get the BIC stored in the token.
	 * 
	 * @return string
	 */
	public function get_bic() {
    	return $this->get_meta( self::BIC );
	}

	/**
	 * @param string $bic The BIC to store in the token.
	 */
	public function set_bic( $bic ) {
    	$this->add_meta_data( self::BIC, $bic, true );
	}

	/**
	 * Get the account holder stored in the token.
	 * 
	 * @return string
	 */
	public function get_account_holder() {
    	return $this->get_meta( self::ACCOUNT_HOLDER );
	}

	/**
	 * @param string $account_holder The account holder to store in the token.
	 */
	public function set_account_holder( $account_holder ) {
    	$this->add_meta_data( self::ACCOUNT_HOLDER, $account_holder, true );
	}

	public function get_last4() {
		return substr($this->get_iban(), -4);
	}

	/**
	 * Get type to display to user.
	 *
	 * @since  2.6.0
	 * @param  string $deprecated Deprecated since WooCommerce 3.0.
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$display = sprintf(
			__( 'SEPA Direct Debit ending in %1$s', SEPA_DD_DOMAIN),
			$this->get_last4()
		);
		return $display;
	}
}

/**
 * Controls the output for credit cards on the my account page.
 *
 * @since 2.6
 * @param  array            $item         Individual list item from woocommerce_saved_payment_methods_list.
 * @param  WC_Payment_Token $payment_token The payment token associated with this method entry.
 * @return array                           Filtered item.
 */
function wc_get_account_saved_payment_methods_list_item_sepa( $item, $payment_token ) {
	if ( 'sepadd' !== strtolower( $payment_token->get_type() ) ) {
		return $item;
	}

	$item['method']['last4'] = $payment_token->get_last4();
	$item['method']['brand'] = __('SEPA Direct Debit', SEPA_DD_DOMAIN);
	return $item;
}

add_filter( 'woocommerce_payment_methods_list_item', 'wc_get_account_saved_payment_methods_list_item_sepa', 10, 2 );
