# SEPA Payment Gateway for WooCommerce

Seamlessly adds SEPA Direct Debit support to WooCommerce. Easily collect IBAN and BIC of your
customers during checkout and export SEPA-XML-files ready for upload to your bank.

* Dynamic real-time validation of IBAN and BIC (s. video for demonstration).
* Creates XML-files that are 100% compliant to PAIN.008.003.02 and new PAIN.008.001.02 (SEPA 3.x) standard.
* Automatic Umlaute-transformation.
* Basic fraud prevention: highlights mismatching shipping name and account holder.
* Multiple payments in one XML-file.
* See all open payments in an overview before exporting XML. Easily navigate to orders to check details.
* Supports WooCommerce Subscriptions.

## Licenses

Contains the [php-sepa-xml](https://github.com/php-sepa-xml/php-sepa-xml) library, licensed under the LGPL
Contains the [jQuery Validation library](https://github.com/jzaefferer/jqueryvalidation/), licensed under the MIT license

Accept direct debit payments in your WooCommerce shop without payment providers!
--------------------------------------------------------------------------------

All you need is a business bank account that allows for SEPA direct debit transfers. The plugin adds a new payment method "SEPA direct debit" to the WooCommerce checkout page. When selected, your customers just need to enter their bank account information. From the WooCommerce shop backend, you then download a single file containing all payments since the last download. You upload this file to the online banking of your bank and the transfers are initiated.

### Features

-   Supports WooCommerce 3.x.
-   Supports WooCommerce Subscriptions 2.x including multiple subscriptions (note that Subscriptions 1.x is no longer supported!).
-   See all open payments in an overview before exporting XML. Easily navigate to orders to check details.
-   Multiple payments in one XML-file.
-   Creates XML-files that are 100% compliant to most recent online banking standards (PAIN.008.003.02 and new PAIN.008.001.02 or SEPA 3.x).
-   Basic fraud prevention: highlights mismatching shipping name and account holder.
-   Dynamic real-time validation of IBAN and BIC.
-   Change payment method on backend for subscriptions.
-   Supports storing payment information in user account.
-   5 star top rating
-   Excellent support, frequent updates

### Updates

#### VERSION 1.14

-   Automatically activate subscriptions again when customer changes payment method.

#### VERSION 1.13

-   Calculating total for orders including refunds.

#### VERSION 1.12

-   Updated Sepa-XML creation library to latest version. Now also supports non-german special characters.

#### VERSION 1.11

-   Added option to include payment information (IBAN, BIC, account holder) in emails sent to shop admin.
-   Added option to automatically mark order as payed (order status becomes "Processing" instead of "On Hold").

#### VERSION 1.10

-   Added support for storing payment method in user account so that sepa information does not have to be entered again for subsequent order.

#### VERSION 1.9

-   Added support for exporting multiple payments in a single "Payment Information" segment within the XML-file. This is required by some banks (e.g., German Commerzbank) and can reduce costs with other banks.

#### VERSION 1.8

-   Added support for new SEPA version 3.x by optionally generating pain.008.001.02 files.

#### VERSION 1.7

-   Added support for manually changing the payment method and editing the SEPA information in the admin backend for subscriptions

#### VERSION 1.6.9

-   Added french translation provided by Nicolas Grandgirard (thanks Nicolas! =)

#### VERSION 1.6.8

-   Fixed error "Fatal error: Allowed memory size ..." when trying to export XML files with Subscriptions installed.
-   Fixed error "Notice: Undefined index remittance_info..."

#### VERSION 1.6.7

-   Using WooCommerce order number instead of post id for remittance information.

#### VERSION 1.6.6

-   Compatibility to WooCommerce 3.0.
-   Added setting for remittance information.

#### VERSION 1.6.5

-   Fixed issue with old orders that were still created with WooCommerce Subscriptions 1.x.

#### VERSION 1.6.4

-   Fixed issue where direct debit information was empty in subscriptions created with WooCommerce Subscriptions 1.x.

#### VERSION 1.6.3

-   Fixed issue with validating some BIC-numbers.

#### VERSION 1.6.2

-   Support for change of payment method by customer added.

#### VERSION 1.6.1

-   Does not export payments for cancelled orders anymore.

#### VERSION 1.6

-   Updated to fully support WooCommerce Subscriptions 2.0 including multiple Subscriptions. **Please note**: The plugin is now no longer compatible with Subscriptions 1.x!
-   Set due date for payment one day into the future to prevent banking software from rejecting XML-file.

#### VERSION 1.5.4

-   Made exporting payments as COR1 express debits optional. Default is the basic or CORE debit which has a five working day delay before the payment is fulfilled. **Please check with your bank before activating COR1 debits.**

#### VERSION 1.5.3

-   Convert IBAN, BIC and Creditor ID to uppercase. Some banks seem to reject the XML if it contains lower case letters in these fields.

#### VERSION 1.5.1

-   Removed dependency on php BC Math library, which is missing on some hosted webspaces.
-   Remove white space from IBAN and BIC before storing them in the database.

#### VERSION 1.5

-   Added info-box to order page showing account holder, iban and bic.

#### VERSION 1.4.1

-   Fixed missing description on checkout-page.

#### VERSION 1.4

-   Fixed issue when WooCommerce Subscriptions is not installed.
-   Fixed issue where javascript code was visible on the checkout page.

#### VERSION 1.3

-   Fixed incompatibility with WooCommerce Stripe gateway.

#### VERSION 1.2

-   Improvement: Added hint when trying to export payments without setting up the target account information.
-   Bugfix: Setting SEPA sequence correctly to ONE_OFF, FIRST or RECURRING depending on payment type.

#### VERSION 1.1

-   Improvement: Added support for WooCommerce Subscriptions.