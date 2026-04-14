<?php
/*
Plugin Name: Custom Robots
Description: Adds custom robots.txt rules and enforces trailing slashes on URLs.
Version: 1.5
Author: Lalit Suryan
Author URI: https://growth99.com
*/

function custom_robots_txt($output, $public) {
    // Find the position of "Disallow: /wp-admin/" line
    $position = strpos($output, "Disallow: /wp-admin/");
    
    // If "Disallow: /wp-admin/" line is found, insert the new directives after it
    if ($position !== false) {
        // Add the disallow rules after "Disallow: /wp-admin/"
        $output = substr_replace(
            $output,
            "Disallow: /*?wordfence_lh=\nDisallow: /category/\nDisallow: /tag/\nDisallow: /*/feed/\n",
            $position + strlen("Disallow: /wp-admin/\n"),
            0
        );
    }
    
    return $output;
}
add_filter('robots_txt', 'custom_robots_txt', 10, 2);

// Enhanced functionality for handling trailing slashes
function enforce_trailing_slash() {
    // Check if there are any query parameters in the URL
    if (strpos($_SERVER['REQUEST_URI'], '?') !== false) {
        return; // Skip redirection if the URL contains any query parameters
    }

    // Proceed with the redirection if no query parameters are found
    if (is_singular() && !is_404() && substr($_SERVER['REQUEST_URI'], -1) !== '/') {
        wp_redirect(trailingslashit($_SERVER['REQUEST_URI']), 301);
        exit();
    }
}

add_action('template_redirect', 'enforce_trailing_slash');
