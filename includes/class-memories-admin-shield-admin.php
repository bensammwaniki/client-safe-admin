<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Memories_Admin_Shield_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'register_settings_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers
        add_action('wp_ajax_memories_admin_shield_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_memories_admin_shield_toggle_bypass', array($this, 'ajax_toggle_bypass'));
        add_action('wp_ajax_memories_admin_shield_toggle_bypass_redirect', array($this, 'ajax_toggle_bypass_redirect'));
    }

    /**
     * Register Settings Menu Page
     */
    public function register_settings_page() {
        add_menu_page(
            __('Admin Shield', 'memories-admin-shield'),
            __('Admin Shield', 'memories-admin-shield'),
            'manage_options',
            'memories-admin-shield',
            array($this, 'render_settings_page'),
            'dashicons-shield-alt',
            81
        );
    }

    /**
     * Enqueue styles and scripts for the settings screen
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_memories-admin-shield') {
            return;
        }

        // Outfit Typography
        wp_enqueue_style('memories-admin-shield-font', 'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap');

        // Stylesheet
        wp_enqueue_style('memories-admin-shield-css', MEMORIES_ADMIN_SHIELD_URL . 'assets/css/admin.css', array(), '2.0');

        // JavaScript logic
        wp_enqueue_script('memories-admin-shield-js', MEMORIES_ADMIN_SHIELD_URL . 'assets/js/admin.js', array(), '2.0', true);

        // Localize data for JavaScript usage
        $roles = wp_roles()->get_names();
        $user_counts = count_users();
        $role_counts = isset($user_counts['avail_roles']) ? $user_counts['avail_roles'] : array();
        $discovered = get_option('memories_admin_shield_discovered', array('menus' => array(), 'admin_bar' => array()));
        $settings = get_option('memories_admin_shield_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }
        if (!isset($settings['roles']) || empty($settings['roles'])) {
            $settings['roles'] = (object) array();
        } else {
            foreach ($settings['roles'] as $role => $role_settings) {
                if (!isset($role_settings['menus']) || empty($role_settings['menus'])) {
                    $settings['roles'][$role]['menus'] = (object) array();
                }
                if (!isset($role_settings['submenus']) || empty($role_settings['submenus'])) {
                    $settings['roles'][$role]['submenus'] = (object) array();
                } else {
                    foreach ($role_settings['submenus'] as $parent => $subs) {
                        if (empty($subs)) {
                            $settings['roles'][$role]['submenus'][$parent] = (object) array();
                        }
                    }
                }
                if (!isset($role_settings['admin_bar']) || empty($role_settings['admin_bar'])) {
                    $settings['roles'][$role]['admin_bar'] = (object) array();
                }
            }
        }

        $user_id = get_current_user_id();
        $bypass = get_user_meta($user_id, 'memories_admin_shield_bypass', true) ? 1 : 0;

        wp_localize_script('memories-admin-shield-js', 'MemoriesAdminShieldData', array(
            'roles' => $roles,
            'roleCounts' => $role_counts,
            'discovered' => $discovered,
            'settings' => $settings,
            'bypass' => $bypass,
            'nonce' => wp_create_nonce('memories_admin_shield_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'activeRole' => 'administrator'
        ));
    }

    /**
     * AJAX handler to save dashboard visibility configurations
     */
    public function ajax_save_settings() {
        check_ajax_referer('memories_admin_shield_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'memories-admin-shield')));
        }

        $raw_settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '';
        $settings = json_decode($raw_settings, true);

        if (!is_array($settings)) {
            wp_send_json_error(array('message' => __('Invalid settings format.', 'memories-admin-shield')));
        }

        $sanitized = array('roles' => array());
        if (isset($settings['roles']) && is_array($settings['roles'])) {
            foreach ($settings['roles'] as $role => $role_settings) {
                $sanitized['roles'][$role] = array(
                    'menus' => array(),
                    'submenus' => array(),
                    'admin_bar' => array()
                );

                if (isset($role_settings['menus']) && is_array($role_settings['menus'])) {
                    foreach ($role_settings['menus'] as $slug => $val) {
                        $sanitized['roles'][$role]['menus'][$slug] = (bool) $val;
                    }
                }

                if (isset($role_settings['submenus']) && is_array($role_settings['submenus'])) {
                    foreach ($role_settings['submenus'] as $parent_slug => $sub_slugs) {
                        if (is_array($sub_slugs)) {
                            $sanitized['roles'][$role]['submenus'][$parent_slug] = array();
                            foreach ($sub_slugs as $sub_slug => $val) {
                                $sanitized['roles'][$role]['submenus'][$parent_slug][$sub_slug] = (bool) $val;
                            }
                        }
                    }
                }

                if (isset($role_settings['admin_bar']) && is_array($role_settings['admin_bar'])) {
                    foreach ($role_settings['admin_bar'] as $node_id => $val) {
                        $sanitized['roles'][$role]['admin_bar'][$node_id] = (bool) $val;
                    }
                }
            }
        }

        update_option('memories_admin_shield_settings', $sanitized);
        wp_send_json_success(array('message' => __('Settings saved successfully.', 'memories-admin-shield')));
    }

    /**
     * AJAX handler to toggle user bypass/override
     */
    public function ajax_toggle_bypass() {
        check_ajax_referer('memories_admin_shield_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'memories-admin-shield')));
        }

        $user_id = get_current_user_id();
        $current_bypass = get_user_meta($user_id, 'memories_admin_shield_bypass', true);
        $new_bypass = empty($current_bypass) ? 1 : 0;
        update_user_meta($user_id, 'memories_admin_shield_bypass', $new_bypass);

        wp_send_json_success(array('bypass' => $new_bypass));
    }

    /**
     * Redirect-based bypass toggle for top bar link
     */
    public function ajax_toggle_bypass_redirect() {
        if (!current_user_can('manage_options') || !check_admin_referer('memories_admin_shield_toggle_nonce')) {
            wp_die(__('Unauthorized or invalid request.', 'memories-admin-shield'));
        }

        $user_id = get_current_user_id();
        $current_bypass = get_user_meta($user_id, 'memories_admin_shield_bypass', true);
        $new_bypass = empty($current_bypass) ? 1 : 0;
        update_user_meta($user_id, 'memories_admin_shield_bypass', $new_bypass);

        $referrer = wp_get_referer();
        if (!$referrer) {
            $referrer = admin_url('index.php');
        }
        wp_safe_redirect($referrer);
        exit;
    }

    /**
     * Render the admin page HTML template
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap memories-admin-shield-wrap">
            <header class="mas-header">
                <div class="mas-logo-area">
                    <span class="mas-logo-icon">🛡️</span>
                    <div>
                        <h1>Memories Creative Admin Shield</h1>
                        <p class="mas-subtitle">Created by <a href="https://memoriescreative.com/" target="_blank">Memories Creative</a></p>
                    </div>
                </div>
                <div class="mas-header-actions">
                    <div class="mas-bypass-status">
                        <span class="mas-badge-label">Filters Bypass:</span>
                        <span id="mas-bypass-badge" class="mas-badge">Checking...</span>
                        <button id="mas-bypass-btn" class="mas-btn mas-btn-secondary">Toggle</button>
                    </div>
                </div>
            </header>

            <div class="mas-container">
                <!-- Sidebar -->
                <aside class="mas-sidebar">
                    <h3>User Roles</h3>
                    <ul id="mas-role-list" class="mas-role-list">
                        <!-- Loaded dynamically via JavaScript -->
                    </ul>
                </aside>

                <!-- Main Panel -->
                <main class="mas-content">
                    <div class="mas-card">
                        <div class="mas-card-header">
                            <h2 id="mas-active-role-title">Select a Role</h2>
                            <div class="mas-search-wrapper">
                                <input type="text" id="mas-search-input" placeholder="Search menus/slugs..." />
                            </div>
                        </div>

                        <div class="mas-tabs-nav">
                            <button class="mas-tab-nav-btn active" data-tab="sidebar">Sidebar Menus</button>
                            <button class="mas-tab-nav-btn" data-tab="adminbar">Admin Bar Nodes</button>
                        </div>

                        <!-- Sidebar Tab Content -->
                        <div class="mas-tab-content active" id="mas-tab-sidebar">
                            <div class="mas-selection-actions">
                                <button id="mas-select-all-sidebar" class="mas-btn-link">Hide All</button>
                                <span style="color:#475569">|</span>
                                <button id="mas-deselect-all-sidebar" class="mas-btn-link">Show All</button>
                            </div>
                            <div id="mas-sidebar-menu-tree" class="mas-menu-tree">
                                <!-- Populated dynamically via JavaScript -->
                            </div>
                        </div>

                        <!-- Admin Bar Tab Content -->
                        <div class="mas-tab-content" id="mas-tab-adminbar">
                            <div class="mas-selection-actions">
                                <button id="mas-select-all-adminbar" class="mas-btn-link">Hide All</button>
                                <span style="color:#475569">|</span>
                                <button id="mas-deselect-all-adminbar" class="mas-btn-link">Show All</button>
                            </div>
                            <div id="mas-adminbar-nodes-list" class="mas-nodes-list">
                                <!-- Populated dynamically via JavaScript -->
                            </div>
                        </div>

                        <div class="mas-card-footer">
                            <div style="font-size:12px; color:#64748b;">
                                Checkboxes represent menus that will be <strong>hidden</strong>.
                            </div>
                            <button id="mas-save-btn" class="mas-btn mas-btn-primary">Save Changes</button>
                        </div>
                    </div>
                </main>
            </div>
            
            <div style="background:#ffffff; color:#2c3338; padding:15px; border:1px solid #ccd0d4; margin-top:20px;">
                <h3 style="margin-top:0;">Debug: Stored settings in DB</h3>
                <pre style="white-space:pre-wrap; margin:0; font-family:monospace; font-size:12px;"><?php echo esc_html(print_r(get_option('memories_admin_shield_settings'), true)); ?></pre>
            </div>

            <div id="mas-toast" class="mas-toast">Saved successfully!</div>
        </div>
        <?php
    }
}
