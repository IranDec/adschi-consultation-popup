<?php
require_once 'wp-load.php';
// Helper script
$settings = get_option('acp_settings');
$forms = get_option('acp_forms');
echo "Forms:\n";
print_r($forms);
echo "Settings:\n";
print_r($settings);
