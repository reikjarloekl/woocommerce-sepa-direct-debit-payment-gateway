<?php
/**
 * Created by PhpStorm.
 * User: Joern
 * Date: 18.11.2015
 * Time: 09:07
 */

/**
 * my_bcmod - get modulus (substitute for bcmod)
 * string my_bcmod ( string left_operand, int modulus )
 * left_operand can be really big, but be careful with modulus :(
 * by Andrius Baranauskas and Laurynas Butkus :) Vilnius, Lithuania
 **/
function my_bcmod( $x, $y )
{
    // how many numbers to take at once? careful not to exceed (int)
    $take = 5;
    $mod = '';

    do
    {
        $a = (int)$mod.substr( $x, 0, $take );
        $x = substr( $x, $take );
        $mod = $a % $y;
    }
    while ( strlen($x) );

    return (int)$mod;
}

/**
 * Check the given IBAN for correctness. Does not check for existence of the IBAN.
 *
 * @param $iban The IBAN to check.
 * @return bool True, in case the IBAN is valid.
 */
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

        if (my_bcmod($expanded_string, '97') == 1) {
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
function checkBIC($bic)
{
    return preg_match("/^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$/", $bic);
}