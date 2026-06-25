<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Memories_Admin_Shield {
    private static $instance = null;
    
    // Sub-class instances
    public $scanner;
    public $filter;
    public $admin;

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Enforce safety constant (Disable Theme and Plugin File Editor)
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }

        // Initialize component classes
        $this->scanner = new Memories_Admin_Shield_Scanner();
        $this->filter  = new Memories_Admin_Shield_Filter();
        $this->admin   = new Memories_Admin_Shield_Admin();

        // Register legacy cleanup hooks from Client Safe Admin v1.0
        add_action('wp_dashboard_setup', array($this, 'legacy_dashboard_widgets'));
        add_action('admin_head', array($this, 'legacy_hide_filebird'));
    }

    /**
     * Legacy Dashboard widgets removal
     */
    public function legacy_dashboard_widgets() {
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_site_health', 'dashboard', 'normal');
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
    }

    /**
     * Legacy CSS injection to hide FileBird UI in Media
     */
    public function legacy_hide_filebird() {
        echo '<style>
        .njt-filebird-sidebar,
        .filebird-folder-tree,
        .njt-fb-toolbar,
        .filebird-toolbar,
        .njt-fb-new-folder {
            display:none !important;
        }
        </style>';
    }
}
