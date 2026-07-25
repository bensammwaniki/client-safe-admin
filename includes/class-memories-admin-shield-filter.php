<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Memories_Admin_Shield_Filter {

    public function __construct() {
        add_action('admin_menu', array($this, 'filter_admin_menu'), 99999);
        add_action('admin_bar_menu', array($this, 'filter_admin_bar'), 99999);
        add_action('admin_bar_menu', array($this, 'add_bypass_admin_bar_item'), 100);
    }

    /**
     * Remove restricted sidebar menus based on current user's roles
     */
    public function filter_admin_menu() {
        // Skip filtering if current user has the maintenance bypass enabled
        if (get_user_meta(get_current_user_id(), 'memories_admin_shield_bypass', true)) {
            return;
        }

        $current_user = wp_get_current_user();
        if (empty($current_user->roles)) {
            return;
        }

        $settings = get_option('memories_admin_shield_settings', array());
        if (empty($settings['roles'])) {
            return;
        }

        $user_roles = $current_user->roles;
        $total_user_roles = count($user_roles);
        
        $hide_menus = array();
        $hide_submenus = array();

        foreach ($user_roles as $role) {
            if (!isset($settings['roles'][$role])) {
                continue;
            }

            $role_settings = $settings['roles'][$role];

            if (!empty($role_settings['menus'])) {
                foreach ($role_settings['menus'] as $slug => $hidden) {
                    if ($hidden) {
                        $hide_menus[$slug] = isset($hide_menus[$slug]) ? $hide_menus[$slug] + 1 : 1;
                    }
                }
            }

            if (!empty($role_settings['submenus'])) {
                foreach ($role_settings['submenus'] as $parent_slug => $sub_slugs) {
                    foreach ($sub_slugs as $sub_slug => $hidden) {
                        if ($hidden) {
                            $hide_submenus[$parent_slug][$sub_slug] = isset($hide_submenus[$parent_slug][$sub_slug]) ? $hide_submenus[$parent_slug][$sub_slug] + 1 : 1;
                        }
                    }
                }
            }
        }

        // Apply sidebar parent menu removal (Only if hidden in ALL roles the user holds)
        foreach ($hide_menus as $slug => $count) {
            // Safety Lock: Administrators must always have access to the Admin Shield options page
            if (in_array('administrator', $user_roles) && ($slug === 'memories-admin-shield' || $slug === 'client-safe-admin.php' || $slug === 'client-safe-admin')) {
                continue;
            }

            if ($count === $total_user_roles) {
                remove_menu_page($slug);
            }
        }

        // Apply submenu page removal
        foreach ($hide_submenus as $parent_slug => $sub_slugs) {
            // If parent is already removed, skip removing submenus individually
            if (isset($hide_menus[$parent_slug]) && $hide_menus[$parent_slug] === $total_user_roles) {
                continue;
            }

            foreach ($sub_slugs as $sub_slug => $count) {
                if (in_array('administrator', $user_roles) && ($sub_slug === 'memories-admin-shield' || $sub_slug === 'client-safe-admin.php' || $sub_slug === 'client-safe-admin')) {
                    continue;
                }

                if ($count === $total_user_roles) {
                    remove_submenu_page($parent_slug, $sub_slug);
                }
            }
        }
    }

    /**
     * Remove restricted top bar (admin bar) nodes based on current user's roles
     */
    public function filter_admin_bar($wp_admin_bar) {
        if (get_user_meta(get_current_user_id(), 'memories_admin_shield_bypass', true)) {
            return;
        }

        $current_user = wp_get_current_user();
        if (empty($current_user->roles)) {
            return;
        }

        $settings = get_option('memories_admin_shield_settings', array());
        if (empty($settings['roles'])) {
            return;
        }

        $user_roles = $current_user->roles;
        $total_user_roles = count($user_roles);
        
        $hide_nodes = array();
        foreach ($user_roles as $role) {
            if (!isset($settings['roles'][$role])) {
                continue;
            }

            $role_settings = $settings['roles'][$role];
            if (!empty($role_settings['admin_bar'])) {
                foreach ($role_settings['admin_bar'] as $node_id => $hidden) {
                    if ($hidden) {
                        $hide_nodes[$node_id] = isset($hide_nodes[$node_id]) ? $hide_nodes[$node_id] + 1 : 1;
                    }
                }
            }
        }

        foreach ($hide_nodes as $node_id => $count) {
            if ($count === $total_user_roles) {
                $wp_admin_bar->remove_node($node_id);
            }
        }
    }

    /**
     * Inject a quick bypass toggle pill in the Admin Bar (admins only)
     */
    public function add_bypass_admin_bar_item($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $user_id = get_current_user_id();
        $bypass = get_user_meta($user_id, 'memories_admin_shield_bypass', true);

        $title = $bypass 
            ? '<span style="color: #ffb703; font-weight: bold;">🛡️ Shield: OFF</span>' 
            : '<span style="color: #8ac926; font-weight: bold;">🛡️ Shield: ON</span>';

        $wp_admin_bar->add_node(array(
            'id'    => 'memories-admin-shield-toggle',
            'title' => $title,
            'href'  => wp_nonce_url(admin_url('admin-ajax.php?action=memories_admin_shield_toggle_bypass_redirect'), 'memories_admin_shield_toggle_nonce'),
            'meta'  => array(
                'title' => __('Toggle Admin Shield Filtering (Maintenance Mode)', 'memories-admin-shield'),
            )
        ));
    }
}
