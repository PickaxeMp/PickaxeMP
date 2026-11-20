<?php
$methods = openssl_get_cipher_methods();
echo count($methods)."\n";
