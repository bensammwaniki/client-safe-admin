<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Memories_Admin_Shield_Scanner {

    public function __construct() {
        add_action('admin_menu', array($this, 'discover_menus'), 9999);
        add_action('admin_bar_menu', array($this, 'discover_admin_bar_nodes'), 9999);
    }

    /**
     * Scan active sidebar menus and register new items dynamically
     */
    public function discover_menus() {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        global $menu, $submenu;
        if (empty($menu) && !empty($GLOBALS['menu'])) {
            $menu = $GLOBALS['menu'];
        }
        if (empty($submenu) && !empty($GLOBALS['submenu'])) {
            $submenu = $GLOBALS['submenu'];
        }

        if (empty($menu) || !is_array($menu)) {
            return;
        }

        $discovered = get_option('memories_admin_shield_discovered', array('menus' => array(), 'admin_bar' => array()));
        if (!is_array($discovered)) {
            $discovered = array('menus' => array(), 'admin_bar' => array());
        }
        if (!isset($discovered['menus']) || !is_array($discovered['menus'])) {
            $discovered['menus'] = array();
        }
        if (!isset($discovered['admin_bar']) || !is_array($discovered['admin_bar'])) {
            $discovered['admin_bar'] = array();
        }

        $changed = false;

        // Process sidebar menus
        foreach ($menu as $item) {
            if (empty($item[2])) continue;

            // Skip menu separators
            if (!empty($item[4]) && strpos($item[4], 'wp-menu-separator') !== false) {
                continue;
            }

            $slug = $item[2];
            $cap  = isset($item[1]) ? $item[1] : '';
            $raw_title = isset($item[0]) ? $item[0] : '';
            
            // Remove notification count spans before stripping tags
            $title = preg_replace('/<span[^>]*class="[^"]*(?:count|plugin-count|update-plugins|awaiting-mod)[^"]*"[^>]*>.*?<\/span>/is', '', $raw_title);
            $title = trim(strip_tags($title));
            if (empty($title)) {
                $title = $slug;
            }

            if (!isset($discovered['menus'][$slug])) {
                $discovered['menus'][$slug] = array(
                    'title' => $title,
                    'cap'   => $cap,
                    'submenus' => array()
                );
                $changed = true;
            } else {
                if (!isset($discovered['menus'][$slug]['title']) || $discovered['menus'][$slug]['title'] !== $title) {
                    $discovered['menus'][$slug]['title'] = $title;
                    $changed = true;
                }
                if (!isset($discovered['menus'][$slug]['cap']) || $discovered['menus'][$slug]['cap'] !== $cap) {
                    $discovered['menus'][$slug]['cap'] = $cap;
                    $changed = true;
                }
            }

            // Process submenus
            if (!empty($submenu[$slug]) && is_array($submenu[$slug])) {
                if (!isset($discovered['menus'][$slug]['submenus']) || !is_array($discovered['menus'][$slug]['submenus'])) {
                    $discovered['menus'][$slug]['submenus'] = array();
                }

                foreach ($submenu[$slug] as $sub_item) {
                    if (empty($sub_item[2])) continue;
                    $sub_slug = $sub_item[2];
                    $sub_cap  = isset($sub_item[1]) ? $sub_item[1] : '';
                    $sub_raw_title = isset($sub_item[0]) ? $sub_item[0] : '';
                    $sub_title = preg_replace('/<span[^>]*class="[^"]*(?:count|plugin-count|update-plugins|awaiting-mod)[^"]*"[^>]*>.*?<\/span>/is', '', $sub_raw_title);
                    $sub_title = trim(strip_tags($sub_title));
                    if (empty($sub_title)) {
                        $sub_title = $sub_slug;
                    }

                    $new_sub_data = array(
                        'title' => $sub_title,
                        'cap'   => $sub_cap
                    );

                    $existing_sub = isset($discovered['menus'][$slug]['submenus'][$sub_slug]) 
                        ? $discovered['menus'][$slug]['submenus'][$sub_slug] 
                        : null;

                    if (is_string($existing_sub)) {
                        $discovered['menus'][$slug]['submenus'][$sub_slug] = $new_sub_data;
                        $changed = true;
                    } else if (is_array($existing_sub)) {
                        if (!isset($existing_sub['title']) || $existing_sub['title'] !== $sub_title || !isset($existing_sub['cap']) || $existing_sub['cap'] !== $sub_cap) {
                            $discovered['menus'][$slug]['submenus'][$sub_slug] = $new_sub_data;
                            $changed = true;
                        }
                    } else {
                        $discovered['menus'][$slug]['submenus'][$sub_slug] = $new_sub_data;
                        $changed = true;
                    }
                }
            }
        }

        if ($changed) {
            update_option('memories_admin_shield_discovered', $discovered);
        }
    }

    /**
     * Scan active top bar nodes and register new items dynamically
     */
    public function discover_admin_bar_nodes($wp_admin_bar) {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        $nodes = $wp_admin_bar->get_nodes();
        if (empty($nodes)) {
            return;
        }

        $discovered = get_option('memories_admin_shield_discovered', array('menus' => array(), 'admin_bar' => array()));
        if (!is_array($discovered)) {
            $discovered = array('menus' => array(), 'admin_bar' => array());
        }

        $changed = false;

        foreach ($nodes as $node) {
            if (empty($node->id)) continue;
            $id = $node->id;
            $raw_title = isset($node->title) ? $node->title : '';
            $title = preg_replace('/<span[^>]*class="[^"]*(?:count|plugin-count|update-plugins|awaiting-mod)[^"]*"[^>]*>.*?<\/span>/is', '', $raw_title);
            $title = trim(strip_tags($title));
            if (empty($title)) {
                $title = $id;
            }

            if (!isset($discovered['admin_bar'][$id]) || $discovered['admin_bar'][$id] !== $title) {
                $discovered['admin_bar'][$id] = $title;
                $changed = true;
            }
        }

        if ($changed) {
            update_option('memories_admin_shield_discovered', $discovered);
        }
    }
}
