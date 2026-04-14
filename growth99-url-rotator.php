<?php
/**
 * Plugin Name: Growth99 URL Cloaker (Exact Home URLs Only)
 * Description: Replaces ONLY the home URLs of Growth99 (not internal pages) with randomized Bitly links.
 * Version: 1.0
 * Author: Lalit Suryan
 */

function g99_get_random_url() {
    $bitly_urls = [
        'https://bit.ly/4m7TfKB',
        'https://bit.ly/4lSfEeH',
        'https://bit.ly/3H3azRw',
        'https://bit.ly/46vAzzf',
        'https://bit.ly/4lHRALk',
        'https://bit.ly/4lJd2zD',
        'https://bit.ly/40DwtkS',
        'https://bit.ly/40CyYnz',
        'https://bit.ly/4lUMKdY',
        'https://bit.ly/4mh2dVn',
        'https://bit.ly/473BLKp',
        'https://bit.ly/4m8uOMk',
        'https://bit.ly/3U1kNF2',
        'https://bit.ly/3H8T56f',
        'https://bit.ly/40GJB8Z',
        'https://bit.ly/4mh3XOu',
        'https://bit.ly/4fcI28D',
        'https://bit.ly/44Ufaic',
        'https://bit.ly/41h6AYi',
        'https://bit.ly/4lHRBim',
        'https://bit.ly/3TWEGgm',
        'https://bit.ly/4mjHv7q',
        'https://bit.ly/4l3CsXn',
        'https://bit.ly/3TXaQs9',
        'https://bit.ly/4mkMzIF',
        'https://bit.ly/4laOjDq',
        'https://bit.ly/4l0SbGY',
        'https://bit.ly/3UlH4O1',
        'https://bit.ly/44YpvYU',
        'https://bit.ly/45ovbwL',
        'https://bit.ly/474yP06',
        'https://bit.ly/459C2Jq',
        'https://bit.ly/46uZqmX',
        'https://bit.ly/4lIOxCA',
        'https://bit.ly/3H74WBF',
        'https://bit.ly/453Rx5q',
        'https://bit.ly/46w6oYL',
        'https://bit.ly/4fazWNG',
        'https://bit.ly/4o9l1aR',
        'https://bit.ly/4f8YoiN',
        'https://bit.ly/44RNSJd',
        'https://bit.ly/44PXMLj',
        'https://bit.ly/4fbtPce',
        'https://bit.ly/4fb3yun',
        'https://bit.ly/4o74GTU',
        'https://bit.ly/474WsFL',
        'https://bit.ly/41eJ1iT',
        'https://bit.ly/4lZ2JaL',
        'https://bit.ly/4o5kqqN',
        'https://bit.ly/4ogZJIv',
        'https://bit.ly/4oa4aEE',
        'https://bit.ly/4o74zrs',
        'https://bit.ly/41eIOMD',
        'https://bit.ly/44OKKh8',
        'https://bit.ly/4lOvqHj',
        'https://bit.ly/4lSAQkK',
        'https://bit.ly/40Dwe9s',
        'https://bit.ly/3INiz9M',
        'https://bit.ly/46wnCFo',
        'https://bit.ly/4o9ksOh',
        'https://bit.ly/4lSAYRg',
        'https://bit.ly/4517ta1',
        'https://bit.ly/4lOQu0g',
        'https://bit.ly/3IPhZIy',
        'https://bit.ly/41g2pvP',
        'https://bit.ly/417ePpS',
        'https://bit.ly/4lQ3NOg',
        'https://bit.ly/4lOvgQd',
        'https://bit.ly/4ofDjaC',
        'https://bit.ly/3IPaXn9',
        'https://bit.ly/3IMv7y3',
        'https://bit.ly/4mdqECS',
        'https://bit.ly/4fegFLu',
        'https://bit.ly/471YpCP',
        'https://bit.ly/41eIyNF',
        'https://bit.ly/4mk6xmW',
        'https://bit.ly/4mjGTi8',
        'https://bit.ly/4mfONZI',
        'https://bit.ly/3IPRNOc',
        'https://bit.ly/45cTw7G',
        'https://bit.ly/4kZ4V0w',
        'https://bit.ly/4l1Y6eW',
        'https://bit.ly/459quG3',
        'https://bit.ly/457timZ',
        'https://bit.ly/456HjRX',
        'https://bit.ly/46ZWq20',
        'https://bit.ly/4f9T1jb',
        'https://bit.ly/458CreS',
        'https://bit.ly/3IMHVVi',
        'https://bit.ly/4o5jJhb',
        'https://bit.ly/4lZ21u7',
        'https://bit.ly/459nLw6',
        'https://bit.ly/4oaD14C',
        'https://bit.ly/3IPasth',
        'https://bit.ly/45cT35q',
        'https://bit.ly/4lMjMN4',
        'https://bit.ly/3IKd8Zb',
        'https://bit.ly/3GVZrG4',
        'https://bit.ly/4kX9NTS',
        'https://bit.ly/4kVCOzm'
    ];
    return $bitly_urls[array_rand($bitly_urls)];
}

function g99_buffer_start() {
    ob_start('g99_replace_output_exact_home');
}
add_action('template_redirect', 'g99_buffer_start');

function g99_replace_output_exact_home($html) {
    $replacement = g99_get_random_url();

    // Match ONLY the exact home URLs, with optional trailing slash, and followed by space or quote
    $pattern = [
        '#https://growth99\.com/?(?=["\'\s])#i',
        '#https://www\.growth99\.com/?(?=["\'\s])#i'
    ];

    foreach ($pattern as $regex) {
        $html = preg_replace($regex, $replacement, $html);
    }

    return $html;
}
