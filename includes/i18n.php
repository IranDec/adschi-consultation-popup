<?php
if (!defined('ABSPATH')) exit;

function acp_t($persian, $english, $german) {
    $locale = get_locale();
    if (strpos($locale, 'de_') === 0) {
        return $german;
    } elseif (strpos($locale, 'en_') === 0) {
        return $english;
    }
    return $persian;
}
