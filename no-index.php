<?php
/*
Plugin Name: Prevent Indexing in Search Engines
Description: Prevents WordPress from being indexed by search engines.
Version: 1.3
Author: Lalit Suryan
Author URI: https://growth99.com
*/

function add_noindex_nofollow_tag() {
    echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
}

add_action('wp_head', 'add_noindex_nofollow_tag');
