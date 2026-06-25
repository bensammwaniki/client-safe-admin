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
        if (empty($menu)) {
            return;
        }

        $discovered = get_option('memories_admin_shield_discovered', array('menus' => array(), 'admin_bar' => array()));
        $changed = false;

        // Process sidebar menus
        foreach ($menu as $item) {
            if (empty($item[2])) continue;
            $slug = $item[2];
            $title = isset($item[0]) ? strip_tags($item[0]) : '';
            if (empty($title)) {
                $title = $slug;
            }

            if (!isset($discovered['menus'][$slug])) {
                $discovered['menus'][$slug] = array(
                    'title' => $title,
                    'submenus' => array()
                );
                $changed = true;
            } else if ($discovered['menus'][$slug]['title'] !== $title && !empty($title)) {
                $discovered['menus'][$slug]['title'] = $title;
                $changed = true;
            }

            // Process submenus
            if (!empty($submenu[$slug])) {
                foreach ($submenu[$slug] as $sub_item) {
                    if (empty($sub_item[2])) continue;
                    $sub_slug = $sub_item[2];
                    $sub_title = isset($sub_item[0]) ? strip_tags($sub_item[0]) : '';
                    if (empty($sub_title)) {
                        $sub_title = $sub_slug;
                    }

                    if (!isset($discovered['menus'][$slug]['submenus'][$sub_slug]) || $discovered['menus'][$slug]['submenus'][$sub_slug] !== $sub_title) {
                        $discovered['menus'][$slug]['submenus'][$sub_slug] = $sub_title;
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
        $changed = false;

        foreach ($nodes as $node) {
            if (empty($node->id)) continue;
            $id = $node->id;
            $title = isset($node->title) ? strip_tags($node->title) : '';
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
