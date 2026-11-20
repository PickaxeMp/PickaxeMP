<?php
$key = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'secp384r1',
]);
if ($key === false) {
    while ($msg = openssl_error_string()) {
        echo $msg . PHP_EOL;
    }
} else {
    echo "OK";
}
