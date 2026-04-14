<?php
/**
 * Plugin Name: Disable Growth99 URL Completely
 * Description: A WordPress MU Plugin to completely remove the 'growth99.com' URL from source code.
 * Version: 1.0
 * Author: Lalit Suryan
 */

// Prevent direct access to the file
if (!defined('WPINC')) {
    die;
}

// Function to search and remove instances of the URL
function remove_growth99_url_from_output($buffer) {
    $target_url = 'growth99.com';

    // Use a regular expression to remove instances of the URL
    $buffer = preg_replace(
        '/https?:\/\/' . preg_quote($target_url, '/') . '\b/i',
        '',
        $buffer
    );

    return $buffer;
}

// Start output buffering to clean up the output before rendering
function start_output_buffering() {
    ob_start('remove_growth99_url_from_output');
}
add_action('init', 'start_output_buffering');

// End output buffering
function end_output_buffering() {
    if (ob_get_length()) {
        ob_end_flush();
    }
}
add_action('shutdown', 'end_output_buffering');
