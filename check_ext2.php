<?php
echo "ext dir: ".ini_get('extension_dir')."\n";
echo "openssl loaded: ".(extension_loaded('openssl') ? 'yes' : 'no')."\n";
echo "function exists: ".(function_exists('openssl_pkey_new') ? 'yes' : 'no')."\n";
