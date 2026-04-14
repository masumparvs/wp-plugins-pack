<?php
/**
 * Plugin Name: Auto Clear Elementor, Sera, & LiteSpeed Cache (Optimized)
 * Description: Intelligently clears Elementor CSS & Data and Sera or LiteSpeed cache with optimized performance. Features daily automatic clearing and event-driven clearing on post saves.
 * Version: 4.0
 * Author: Lalit Suryan
 */

namespace AutoClearCache;

use Elementor\Plugin;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Configuration constants for fine-tuning performance
define('AUTO_CACHE_THROTTLE_TIME', 300); // Increased to 5 minutes (300s) to reduce frequency of cache clears
define('AUTO_CACHE_BATCH_DELAY', 5); // Seconds to batch multiple saves together
define('AUTO_CACHE_DEBUG_MODE', false); // Set to true to enable detailed logging


// Hook into save_post with optimized priority
add_action('save_post', __NAMESPACE__ . '\\auto_clear_dynamic_caches', 999, 2);

// Hook into Elementor's own save action for better integration
add_action('elementor/editor/after_save', __NAMESPACE__ . '\\handle_elementor_save', 10, 2);

/**
 * Handle saves from Elementor editor specifically
 *
 * @param int $post_id The ID of the post being saved.
 * @param array $editor_data The editor data.
 */
function handle_elementor_save($post_id, $editor_data) {
    debug_log("Elementor editor save detected for post ID: $post_id");
    auto_clear_dynamic_caches($post_id, get_post($post_id), true);
}

/**
 * Optimized cache clearing with intelligent throttling and selective clearing.
 *
 * @param int $post_id The ID of the post being saved.
 * @param WP_Post $post The post object.
 * @param bool $is_elementor_save Whether this is from Elementor editor.
 */
function auto_clear_dynamic_caches($post_id, $post = null, $is_elementor_save = false) {
    // Skip autosaves and revisions
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    // Get post object if not provided
    if (!$post) {
        $post = get_post($post_id);
    }
    
    if (!$post) {
        return;
    }

    // OPTIMIZATION: Skip cache clearing for non-public post types to reduce frequency
    $skip_post_types = ['revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block'];
    if (in_array($post->post_type, $skip_post_types)) {
        debug_log("Skipping cache clear for post type: {$post->post_type}");
        return;
    }

    // Only process published posts and pages (not drafts, etc.)
    if (!in_array($post->post_status, ['publish', 'private', 'future'])) {
        return;
    }
    
    // OPTIMIZATION: Skip minor updates (e.g., post view count, comment count updates)
    // Only clear cache if this is an Elementor save or if post content actually changed
    if (!$is_elementor_save && defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        debug_log("Skipping cache clear - autosave detected");
        return;
    }

    // Implement throttling to prevent rapid successive clears
    if (should_throttle_cache_clear($post_id)) {
        debug_log("Throttling cache clear for post ID $post_id - too soon since last clear");
        // Schedule a delayed clear instead
        schedule_delayed_cache_clear($post_id);
        return;
    }

    // Mark this post as recently cleared
    set_cache_clear_timestamp($post_id);

    debug_log("Clearing cache for post ID: $post_id (Type: {$post->post_type})");

    // Clear caches in order: ALWAYS Elementor first, then Sera/LiteSpeed
    clear_post_elementor_cache($post_id);
    
    // Small delay to ensure Elementor completes first
    usleep(100000); // 0.1 second delay
    
    clear_third_party_cache($post_id);

    // Small delay
    usleep(50000); // 0.05 second delay

    // Clear server cache (Standalone implementation)
    clear_server_cache_extended($post_id);

    debug_log("Cache cleared successfully for post ID: $post_id");
}

/**
 * Check if a post was built with Elementor
 *
 * @param int $post_id The post ID to check.
 * @return bool Whether the post uses Elementor.
 */
function is_built_with_elementor($post_id) {
    if (!class_exists('\\Elementor\\Plugin')) {
        return false;
    }

    // Check if Elementor data exists for this post
    $elementor_data = get_post_meta($post_id, '_elementor_data', true);
    $elementor_edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
    
    return !empty($elementor_data) || $elementor_edit_mode === 'builder';
}

/**
 * Check if we should throttle cache clearing for a post
 *
 * @param int $post_id The post ID to check.
 * @return bool Whether to throttle.
 */
function should_throttle_cache_clear($post_id) {
    $last_clear = get_transient("auto_cache_last_clear_$post_id");
    
    if ($last_clear === false) {
        return false; // No previous clear, don't throttle
    }

    // Check if enough time has passed
    return (time() - $last_clear) < AUTO_CACHE_THROTTLE_TIME;
}

/**
 * Record the timestamp of a cache clear
 *
 * @param int $post_id The post ID.
 */
function set_cache_clear_timestamp($post_id) {
    set_transient("auto_cache_last_clear_$post_id", time(), AUTO_CACHE_THROTTLE_TIME * 2);
}

/**
 * Schedule a delayed cache clear to batch multiple rapid saves
 *
 * @param int $post_id The post ID.
 */
function schedule_delayed_cache_clear($post_id) {
    // Use WordPress transients to batch clears
    $pending_clear = get_transient("auto_cache_pending_clear_$post_id");
    
    if ($pending_clear === false) {
        set_transient("auto_cache_pending_clear_$post_id", time(), AUTO_CACHE_BATCH_DELAY);
        
        // Schedule a single batch clear
        if (!wp_next_scheduled('auto_cache_batch_clear', [$post_id])) {
            wp_schedule_single_event(time() + AUTO_CACHE_BATCH_DELAY, 'auto_cache_batch_clear', [$post_id]);
        }
    }
}

// Register the batch clear action
add_action('auto_cache_batch_clear', __NAMESPACE__ . '\\execute_batch_clear');

/**
 * Execute a batched cache clear
 *
 * @param int $post_id The post ID.
 */
function execute_batch_clear($post_id) {
    delete_transient("auto_cache_pending_clear_$post_id");
    
    // Only clear if not already cleared recently
    if (!should_throttle_cache_clear($post_id)) {
        set_cache_clear_timestamp($post_id);
        
        // Clear in order: Elementor first, then Sera/LiteSpeed
        clear_post_elementor_cache($post_id);
        usleep(100000); // 0.1 second delay
        clear_third_party_cache($post_id);
        
        debug_log("Batch cache clear executed for post ID: $post_id");
    }
}

/**
 * Execute full cache clear (Used by Manual Button)
 * Clears Elementor, Mera/LiteSpeed, and Server Cache
 */
function execute_full_cache_clear() {
    debug_log("Starting manual/full cache clear...");
    
    // Clear all caches in order: ALWAYS Elementor first, then Sera/LiteSpeed
    
    // 1. Clear Elementor cache first
    if (class_exists('\\Elementor\\Plugin')) {
        try {
            Plugin::instance()->files_manager->clear_cache();
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            debug_log("Daily clear: Elementor cache cleared site-wide");
        } catch (\Throwable $e) {
            error_log('Daily clear: Error clearing Elementor cache: ' . $e->getMessage());
        }
    }
    
    // Small delay to ensure Elementor completes first
    usleep(100000); // 0.1 second delay
    
    // 2. Then clear Sera or LiteSpeed cache
    if (class_exists('\\seraph_accel\\API')) {
        try {
            // Clear Sera root cache (entire site)
            \seraph_accel\API::OperateCache(\seraph_accel\API::CACHE_OP_DEL, '');
            debug_log("Daily clear: Sera root cache cleared site-wide");
            
            // Small delay
            usleep(50000); // 0.05 second
            
        } catch (\Throwable $e) {
            error_log("Daily clear: Error clearing Sera cache: " . $e->getMessage());
        }
    } elseif (function_exists('do_action') && has_action('litespeed_purge_all')) {
        try {
            do_action('litespeed_purge_all');
            debug_log("Daily clear: LiteSpeed cache purged site-wide");
        } catch (\Throwable $e) {
            error_log('Daily clear: Error purging LiteSpeed cache: ' . $e->getMessage());
        }
    }
    

    
    // 3. Clear Server Cache
    // Safe to run here as this is triggered manually or by specific events, not a server-wide schedule
    clear_server_cache_extended();
    
    debug_log("Full cache clear completed successfully");
}

/**
 * Clear Elementor cache for the entire site (site-wide clearing)
 *
 * @param int $post_id The post ID that triggered the clear.
 */
function clear_post_elementor_cache($post_id) {
    if (!class_exists('\\Elementor\\Plugin')) {
        return;
    }

    try {
        // Clear Elementor's entire site cache (CSS & Data)
        Plugin::instance()->files_manager->clear_cache();
        debug_log("Elementor site-wide cache cleared (triggered by post ID: $post_id)");

        // Also clear global WordPress object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

    } catch (\Throwable $e) {
        error_log('Error clearing Elementor site-wide cache (post ' . $post_id . '): ' . $e->getMessage());
    }
}

/**
 * Clear third-party cache (Sera or LiteSpeed) intelligently
 *
 * @param int $post_id The post ID.
 */
function clear_third_party_cache($post_id) {
    if (class_exists('\\seraph_accel\\API')) {
        clear_sera_cache_optimized($post_id);
    } elseif (function_exists('do_action') && has_action('litespeed_purge_post')) {
        purge_litespeed_post_cache($post_id);
    } else {
        debug_log('No supported cache plugin (Sera or LiteSpeed) is active.');
    }
}

/**
 * Clear Sera cache for the entire site (site-wide clearing)
 * Order: Root cache first, then server cache
 *
 * @param int $post_id The ID of the post that triggered the clear.
 */
function clear_sera_cache_optimized($post_id) {
    debug_log("Attempting to clear Sera cache for post ID: $post_id");
    
    if (!class_exists('\\seraph_accel\\API')) {
        debug_log("Sera cache NOT cleared - seraph_accel\\API class not found");
        error_log("Sera cache NOT cleared - seraph_accel\\API class not found");
        return;
    }
    
    debug_log("Sera API class found, proceeding with cache clear...");

    try {
        // 1. Clear Sera root cache (entire site) - empty string means site root
        \seraph_accel\API::OperateCache(\seraph_accel\API::CACHE_OP_DEL, '');
        debug_log("✓ Sera root cache cleared site-wide (triggered by post ID: $post_id)");
        error_log("✓ Sera root cache cleared site-wide (triggered by post ID: $post_id)");
        
        // Small delay between operations
        usleep(50000); // 0.05 second delay
        
    } catch (\Throwable $e) {
        debug_log("✗ Error clearing Sera cache: " . $e->getMessage());
        error_log("✗ Error clearing Sera cache site-wide (post $post_id): " . $e->getMessage());
    }
}

/**
 * Purge LiteSpeed cache for the entire site (site-wide clearing)
 *
 * @param int $post_id The ID of the post that triggered the clear.
 */
function purge_litespeed_post_cache($post_id) {
    try {
        // Purge ALL LiteSpeed cache (site-wide)
        do_action('litespeed_purge_all');
        debug_log("LiteSpeed cache purged site-wide (triggered by post ID: $post_id)");
        
    } catch (\Throwable $e) {
        error_log('Error purging LiteSpeed cache site-wide (post ' . $post_id . '): ' . $e->getMessage());
    }
}

/**
 * Debug logging function
 *
 * @param string $message The message to log.
 */
function debug_log($message) {
    if (AUTO_CACHE_DEBUG_MODE) {
        error_log('[Auto Cache Plugin] ' . $message);
    }
}

// -----------------------------------------------------------------------------
// Admin Bar Manual Purge Button
// -----------------------------------------------------------------------------

add_action('admin_bar_menu', __NAMESPACE__ . '\\add_manual_purge_button', 100);
add_action('admin_post_manual_purge_all', __NAMESPACE__ . '\\handle_manual_purge_all');
add_action('admin_notices', __NAMESPACE__ . '\\manual_purge_admin_notice');

/**
 * Add Purge Button to WP Admin Bar
 *
 * @param WP_Admin_Bar $admin_bar
 */
function add_manual_purge_button($admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $cooldown_start = get_transient('auto_cache_manual_purge_limit');
    
    if ($cooldown_start) {
        // Cooldown is active
        $time_passed = time() - $cooldown_start;
        $remaining = 600 - $time_passed; // 600 seconds = 10 minutes
        $minutes = ceil($remaining / 60);
        
        if ($minutes < 1) $minutes = 1;
        
        $admin_bar->add_node([
            'id'    => 'auto_cache_purge_all',
            'title' => "Purge Cooldown ({$minutes}m)",
            'href'  => false, // Not clickable
            'meta'  => [
                'class' => 'auto-cache-purge-all disabled',
                'title' => "Purge is disabled for {$minutes} more minutes to prevent overuse.",
                'html'  => '<style>#wp-admin-bar-auto_cache_purge_all .ab-item { opacity: 0.6; cursor: not-allowed !important; color: #aaa !important; }</style>'
            ]
        ]);
    } else {
        // specific ID to allow targeting with CSS if needed
        $admin_bar->add_node([
            'id'    => 'auto_cache_purge_all',
            'title' => 'Purge All Cache',
            'href'  => wp_nonce_url(admin_url('admin-post.php?action=manual_purge_all'), 'manual_purge_all_nonce'),
            'meta'  => [
                'class' => 'auto-cache-purge-all',
                'title' => 'Clear Elementor, Sera, LiteSpeed, and Server caches'
            ]
        ]);
    }
}

/**
 * Handle Manual Purge Request from Admin Bar
 */
function handle_manual_purge_all() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    check_admin_referer('manual_purge_all_nonce');
    
    // Check cooldown enforcement
    if (get_transient('auto_cache_manual_purge_limit')) {
        // Redirect back with error if someone tries to force it via URL
        $redirect_url = remove_query_arg(['cache_purged', 'purge_error'], wp_get_referer());
        $redirect_url = add_query_arg('purge_error', 'cooldown', $redirect_url);
        wp_redirect($redirect_url);
        exit;
    }
    
    // Execute the full clear logic which clears everything
    execute_full_cache_clear();
    
    // Set Cooldown for 10 minutes (600 seconds)
    set_transient('auto_cache_manual_purge_limit', time(), 10 * 60);
    
    // Redirect back to the previous page with a success flag
    $redirect_url = remove_query_arg(['cache_purged', 'purge_error'], wp_get_referer());
    $redirect_url = add_query_arg('cache_purged', '1', $redirect_url);
    
    wp_redirect($redirect_url);
    exit;
}

/**
 * Display Admin Notice after Purge
 */
function manual_purge_admin_notice() {
    if (isset($_GET['cache_purged']) && $_GET['cache_purged'] === '1') {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('All caches (Elementor, Sera, LiteSpeed, Server) have been successfully purged.', 'auto-cache'); ?></p>
        </div>
        <?php
    }
    
    if (isset($_GET['purge_error']) && $_GET['purge_error'] === 'cooldown') {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e('Cache purge is currently on cooldown. Please wait 10 minutes between manual purges.', 'auto-cache'); ?></p>
        </div>
        <?php
    }
}

// -----------------------------------------------------------------------------
// Extended Server Cache Clearing (Derived from Seraphinite Accelerator)
// -----------------------------------------------------------------------------

/**
 * Clear Server Cache using logic derived from Seraphinite Accelerator.
 * This runs independently of the Seraphinite plugin.
 *
 * @param int|null $post_id The post ID (optional).
 */
function clear_server_cache_extended($post_id = null) {
    debug_log("Starting Extended Server Cache Clear...");
    
    $url = null;
    if ($post_id) {
        $url = get_permalink($post_id);
    }
    
    $site_root_url = get_site_url();
    $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';
    
    // 1. LiteSpeed Cache (Header-based)
    if (isset($_SERVER['HTTP_X_LSCACHE']) || stripos($server_software, 'litespeed') !== false) {
        if (!headers_sent()) {
            header('X-LiteSpeed-Purge: public,*');
            debug_log("[Server Cache] LiteSpeed: Purge header sent.");
        } else {
            debug_log("[Server Cache] LiteSpeed: Headers already sent, skipped.");
        }
    }
    
    // 2. BatCache
    if (function_exists('batcache_clear_url') && $url) {
        batcache_clear_url($url);
        debug_log("[Server Cache] BatCache: Cleared URL $url");
    }
    
    // 3. O2Switch Varnish
    if (defined('O2SWITCH_VARNISH_PURGE_KEY')) {
        _auto_cache_clear_o2switch_varnish($url);
    }

    debug_log("Extended Server Cache Clear completed.");
}

/**
 * Clear O2Switch Varnish
 */
function _auto_cache_clear_o2switch_varnish($url) {
    $server_addr = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    $purge_key = constant('O2SWITCH_VARNISH_PURGE_KEY');
    
    if ($url) {
        $parsed = parse_url($url);
        // Deconstruct URL to path/query
        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $target = $path . $query;
        
        $headers = isset($parsed['query']) ? ['X-Purge-Regex' => '.*'] : ['X-Purge-Method' => 'default'];
        $headers['X-VC-Purge-Key'] = $purge_key;
        
        _auto_cache_sock_do_request($server_addr, 'PURGE', $target, $headers);
    } else {
        $headers = ['X-Purge-Regex' => '.*', 'X-VC-Purge-Key' => $purge_key];
        _auto_cache_sock_do_request($server_addr, 'PURGE', get_site_url(), $headers);
    }
    debug_log("[Server Cache] O2Switch Varnish purged.");
}

/**
 * Helper: Perform Socket Request (Derived from Seraphinite)
 */
function _auto_cache_sock_do_request($addr, $method, $url, $headers = []) {
    $args = [
        'method' => $method,
        'headers' => $headers,
        'sslverify' => false,
        'timeout' => 5
    ];
    
    // If it's a relative URL or path, we need to handle it, but wp_remote_request needs a full URL usually,
    // unless we are hitting an IP.
    
    // If $addr is an IP, we might be trying to hit it directly with a Host header.
    // wp_remote_request might fail if we give it just a path with an IP?
    // Let's assume we construct a full URL: http://IP/path
    
    if (filter_var($addr, FILTER_VALIDATE_IP)) {
        // Construct standard URL
        $request_url = "http://$addr" . (strpos($url, '/') === 0 ? $url : '/' . $url);
        return wp_remote_request($request_url, $args);
    } else {
         // Maybe $addr is a hostname?
         return wp_remote_request($url, $args);
    }
}
