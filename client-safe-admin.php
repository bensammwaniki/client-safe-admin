<?php
/*
Plugin Name: Memories Creative Admin Shield
Description: Elegant client management and admin dashboard shielding. Selectively hide sidebar menus and top bar elements by user role.
Version: 2.0
Author: Memories Creative
Author URI: https://memoriescreative.com/
Text Domain: memories-admin-shield
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define Global Constants
define('MEMORIES_ADMIN_SHIELD_PATH', plugin_dir_path(__FILE__));
define('MEMORIES_ADMIN_SHIELD_URL', plugin_dir_url(__FILE__));

// Require Class Files
require_once MEMORIES_ADMIN_SHIELD_PATH . 'includes/class-memories-admin-shield.php';
require_once MEMORIES_ADMIN_SHIELD_PATH . 'includes/class-memories-admin-shield-scanner.php';
require_once MEMORIES_ADMIN_SHIELD_PATH . 'includes/class-memories-admin-shield-filter.php';
require_once MEMORIES_ADMIN_SHIELD_PATH . 'includes/class-memories-admin-shield-admin.php';

// Boot the main plugin instance
Memories_Admin_Shield::get_instance();
