<?php
$exts = get_loaded_extensions();
echo in_array('openssl', $exts) ? 'openssl loaded' : 'openssl NOT loaded';
