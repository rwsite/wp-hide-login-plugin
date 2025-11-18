<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @license   GPL-2.0+
 */

namespace hide_login;

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('whl_page');
delete_option('whl_redirect_admin');

// Flush rewrite rules
flush_rewrite_rules();
