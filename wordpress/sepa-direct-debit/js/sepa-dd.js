/**
 * Created by Joern on 01.11.2015.
 */

jQuery(document).ready(function () {
    jQuery("form[name='checkout']").validate({
        rules: {
            "sepa-direct-debit-iban": "iban",
            "sepa-direct-debit-bic": "bic",
        }
    })
});
