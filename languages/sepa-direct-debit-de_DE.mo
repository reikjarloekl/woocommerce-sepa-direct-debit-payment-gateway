��    J      l  e   �      P     Q     e     t     �     �  G   �  &   �       y   	  �  �  �   [	  |   Q
    �
     �     �  4        S     _     v     �     �     �  3   �  (   �       (   +  P   T     �  +   �     �     �     �  C   	     M     S     \     m     �     �     �     �  e   �  D   L     �     �      �  )   �                     /     P  
   ^     i     u  7   �  8   �  P   �  B   M  *   �  [   �  <     9   T  B   �  <   �            /      H   P     �     �  "   �  $   �  �       �     �     �     �     �  I   �  3   B     v  �   z  [    l  w  �   �  <  ~  &   �  !   �  9        >     L     c     p     �     �  I   �  0        5  -   O  f   }     �  /   �           (   !   7   H   Y   
   �      �      �      �      �       !  &   !  '   ;!  s   c!  i   �!     A"     R"      c"  0   �"     �"     �"     �"  &   �"     #     #  	   &#     0#  ;   B#  <   ~#  d   �#  =    $  o   ^$  O   �$  D   %  H   c%  Y   �%  R   &     Y&     _&  3   k&  H   �&     �&     '      '  %   9'     2              )   1   E      8   *         (   
                 =                 F      3   /   7          <   $   &           A   G         ;           D                         9       	   !   C   J               B       6       #   I             H   5   -                      '   ,         @       %         >              :      ?       4      0   +   .   "        (from parent order) Account Holder Account holder Amount Ask for BIC Automatically marking payment complete due to payment gateway settings. Awaiting SEPA direct debit completion. BIC Check this if your customers have to enter their BIC/Swift-Number. Some banks accept IBAN-only for domestic transactions. Check this to export all payments in a single payment info segment within the XML file. This is required by some banks (e.g., German Commerzbank) and may reduce costs with other banks. The sequence information will be set to "one-off" in this case for all payments. If this setting is disabled, each transfer is exported in a separate payment info segment having the sequence type set correctly ("one-off", "first of a series of recurring payments", "recurring payment"). Check this to export debits as express or COR1 debits. This reduces the debit delay from 5 to 1 business day but is not supported by all banks. Please check with your bank before enabling this setting. Is ignored for pain.008.001.02 file format. Check this to include account holder, IBAN and BIC (if requested, see setting above) in order emails sent to the shop admin. Check this to set the order status to "Processing" immediately. Use this option if you want to start processing the order and trust the direct debit to be fulfilled later. The payment does not need to be entered manually in this case after the money has been transferred. Could not create output file %s Could not create output path %s Creates PAIN.008 XML-files for WooCommerce payments. Creditor ID DE11222222223333333333 Description Enable SEPA Direct Debit. Enable/Disable Export SEPA XML Export all transfers in single payment info segment Export payments as express debits (COR1) Export to SEPA XML Exported %d payments to new SEPA XML: %s For some orders, name of account holder does not match name in shipping address. IBAN Include payment information in admin emails Joern Bungartz John Doe No new payments to export. Only WooCommerce Subscriptions version 2.0 and higher is supported. Order Order %d PAIN file format PAYMENT INFORMATION Pay with SEPA direct debit. Payment information Please enter a valid BIC. Please enter a valid IBAN. Please setup the payment target information first in WooCommerce/Settings/Checkout/SEPA Direct Debit. Please use right-click and 'save-link-as' to download the XML-files. Remittance information SEPA Direct Debit SEPA Direct Debit ending in %1$s SEPA Direct Debit support for Woocommerce SEPA XML SEPA XML-Files Sepa Direct Debit Set order status to "Processing" Shipping Name Target BIC Target IBAN Target account holder The BIC of the account that shall receive the payments. The IBAN of the account that shall receive the payments. The PAIN XML version to create. If you don't know what this is, leave unchanged. The account holder of the account that shall receive the payments. The creditor ID to be used in SEPA debits. The text that will show on the account statement of the customer as remittance information. There was a problem retrieving the sepa payment information. There was a problem storing the sepa payment information. This controls the description which the user sees during checkout. This controls the title which the user sees during checkout. Title XXXXDEYYZZZ You do not have permission to access this page! http://codecanyon.net/item/sepa-payment-gateway-for-woocommerce/12664657 http://www.bl-solutions.de is a required field. pain.008.001.02 (SEPA DK from 3.0) pain.008.003.02 (SEPA DK 2.7 to 2.9) Project-Id-Version: Sepa Direct Debit
POT-Creation-Date: 2018-09-22 23:33+0200
PO-Revision-Date: 2018-09-22 23:34+0200
Last-Translator: 
Language-Team: 
Language: de_DE
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
X-Generator: Poedit 2.1.1
X-Poedit-Basepath: ..
X-Poedit-WPHeader: sepa-direct-debit.php
X-Poedit-SourceCharset: UTF-8
X-Poedit-KeywordsList: __;_e;_n:1,2;_x:1,2c;_ex:1,2c;_nx:4c,1,2;esc_attr__;esc_attr_e;esc_attr_x:1,2c;esc_html__;esc_html_e;esc_html_x:1,2c;_n_noop:1,2;_nx_noop:3c,1,2;__ngettext_noop:1,2
Plural-Forms: nplurals=2; plural=(n != 1);
X-Poedit-SearchPath-0: .
X-Poedit-SearchPathExcluded-0: *.js
 (aus Elternbestellung) Kontoinhaber Kontoinhaber Betrag Nach BIC fragen Automatisch als bezahlt markieren aufgrund Zahlungsgateway-Einstellungen. Warten auf die Fertigstellung der SEPA-Lastschrift. BIC Auswählen, wenn Sie die BIC/Swift-Nummer von Ihren Kunden abfragen wollen. Einige Banken akzeptieren Lastschriften mit nur der IBAN für Inlandsüberweisungen. Auswählen, um alle Zahlungen in einem einzigen Payment Information Segment innerhalb der XML-Datei zu exportieren. Dies wird von einigen Banken (z.b. der deutschen Commerzbank) verlangt und kann die Kosten bei anderen Banken senken. Die sog. Sequenz Informationen werden in diesem Fall für alle Zahlungen auf "einmalige Zahlung" gesetzt. Wenn diese Einstellung deaktiviert ist, wird jede Übertragung in einem separaten Payment Information Segment exportiert, wobei der Sequenz Typ korrekt gesetzt ist ("einmalige Zahlung", "erste einer Reihe von wiederkehrenden Zahlungen", "wiederkehrende Zahlung"). Auswählen, um Zahlungen als sog. Eil- oder COR1-Lastschriften zu exportieren. Dadurch wird die Wartezeit vor Auslösen der Lastschrift auf Seiten der Bank von 5 auf 1 Bankarbeitstag reduziert. Das Verfahren wird aber nicht von allen Banken unterstützt. Bitte sprechen Sie mit Ihrer Bank bevor Sie die Option aktivieren. Wird für pain.008.001.02 nicht verwendet. Auswählen, um IBAN, BIC (wenn abgefragt, s. Einstellung oben) und Kontoinhaber in Bestellungsmails einzufügen, die an den Shop-Admin verschickt werden. Auswählen, um Bestellung sofort nach Eingang auf den Status "Bearbeitung" zu setzen, so dass Bestellung sofort verarbeitet werden kann. Nur aktivieren, wenn Sie darauf vertrauen, dass die Zahlung über Lastschrifteinzug sicher erfolgen wird. Der Zahlungseingang braucht dann im System nicht manuell vermerkt werden. Konnte Ausgabedatei %s nicht erstellen Kann Ausgabepfad %s nicht anlegen Erstellt PAIN.008 XML-Dateien für WooCommerce Zahlungen. Gläubiger ID DE11222222223333333333 Beschreibung SEPA-Lastschrift aktivieren. Aktivieren/Deaktivieren SEPA XML exportieren Alle Zahlungen in einem einzelnen Payment Info segment im XML exportieren Zahlungen als Eillastschrift (COR1) exportieren. Nach SEPA XML exportieren %d Zahlungen in neues SEPA XML exportiert: %s Für einige Bestellungen entspricht der Name des Kontoinhabers nicht dem Namen in der Versand-Adresse. IBAN Zahlungsinformationen in Admin-Emails einfügen Joern Bungartz Max Mustermann Keine neuen Zahlungen zum Export. Nur WooCommerce Subscriptions Version 2.0 oder höher wird unterstützt. Bestellung Bestellung %d PAIN Datei-Format ZAHLUNGSINFORMATION Per SEPA-Lastschrift bezahlen. Zahlungsinformation Bitte geben Sie eine gültige BIC ein. Bitte geben Sie eine gültige IBAN ein. Bitte geben Sie erst die Informationen zum Zahlungsziel ein unter WooCommerce/Einstellungen/Kasse/SEPA Lastschrift. Bitte klicken Sie mit der rechten Maustaste und "Speichern-Link unter", um die XML-Dateien herunterladen. Verwendungszweck SEPA-Lastschrift SEPA-Lastschrift endend auf %1$s SEPA-Lastschrift-Unterstützung für Woocommerce SEPA XML SEPA XML-Dateien SEPA-Lastschrift Bestellstatus auf "Bearbeitung" setzen Name (Versandadresse) Ziel BIC Ziel IBAN Ziel-Kontoinhaber Die BIC-Nummer des Kontos, das die Zahlungen erhalten soll. Die IBAN-Nummer des Kontos, das die Zahlungen erhalten soll. Die zu erstellende PAIN-XML-Version. Wenn Sie nicht wissen, was das ist, lassen Sie es unverändert. Der Kontoinhaber des Kontos, das die Zahlungen erhalten soll. Die Gläubiger ID, die für SEPA-Lastschriften verwendet wird. Wird in Deutschland von der Bundesbank vergeben. Dieser Text wird auf dem Kontoauszug des Kunden als Verwendungszweck angezeigt. Beim Laden der SEPA Zahlungsinformation ist ein Problem aufgetreten. Beim Speichern der SEPA Zahlungsinformation ist ein Problem aufgetreten. Hier können Sie die Beschreibung eingeben den der Benutzer während des Checkouts sieht. Hier können Sie den Titel eingeben den der Benutzer während des Checkouts sieht. Titel XXXXDEYYZZZ Sie sind nicht befugt, auf diese Seite zuzugreifen! http://codecanyon.net/item/sepa-payment-gateway-for-woocommerce/12664657 http://www.bl-solutions.de ist ein Pflichtfeld. pain.008.001.02 (SEPA DK ab 3,0) pain.008.003.02 (SEPA DK 2.7 bis 2.9) 