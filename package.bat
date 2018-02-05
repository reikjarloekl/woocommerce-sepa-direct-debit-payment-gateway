del sepa-direct-debit.zip
copy "assets\SEPA Payment Gateway for WooCommerce.pdf" .
7z a sepa-direct-debit.zip "SEPA Payment Gateway for WooCommerce.pdf" css js languages lib sepa-direct-debit.php WC_Gateway_SEPA_Direct_Debit.php sepa-checks.php
del "SEPA Payment Gateway for WooCommerce.pdf"
pause