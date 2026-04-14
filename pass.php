<?php
/*
Plugin Name: Auto Password Reset & Google Sheet Updater
Description: Automatically resets the passwords for specified users and updates them in Google Sheets using their email addresses. Developed by the Security Team.
Version: 2.0
Author: Lalit Suryan
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Function to reset the password for a given user and update the corresponding Google Sheet
function reset_user_password($email, $sheet_url) {
    $user = get_user_by('email', $email); // Get the user object by email

    if ($user) {
        // Generate a strong password that starts with an alphabet and does not contain "+" or "="
        $new_password = generate_strong_password();

        wp_set_password($new_password, $user->ID); // Reset the user's password
        update_google_sheet($email, $new_password, $sheet_url); // Update the Google Sheet with the email
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::success("Password reset for $email and updated in Google Sheet.");
        } else {
            error_log("Password reset for $email and updated in Google Sheet.");
        }
    } else {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::error("User with email $email not found.");
        } else {
            error_log("User with email $email not found.");
        }
        return; // Exit the function if the user is not found
    }
}

// Function to generate a strong password that starts with an alphabet and excludes "+" or "="
function generate_strong_password() {
    do {
        $password = preg_replace('/[+=]/', '', wp_generate_password(16, true, true));
    } while (!ctype_alpha($password[0])); // Ensure the first character is an alphabet

    return $password;
}

// Function to update Google Sheet with URL, Email, and Password
function update_google_sheet($email, $password, $sheet_url) {
    $site_url = get_site_url(); // Get the site URL

    // Determine the sheet type based on the domain
    $parsed_url = parse_url($site_url);
    $domain = isset($parsed_url['host']) ? $parsed_url['host'] : '';

    // Choose the sheet type based on the domain
    $sheet_type = strpos($domain, 'gogroth.com') !== false ? 'Beta' : 'logins';

    // Check if the URL is already present in the Google Sheet
    $check_url = add_query_arg(array('check_url' => $site_url, 'sheet' => $sheet_type), $sheet_url);
    $response = wp_remote_get($check_url);

    if (is_wp_error($response)) {
        error_log('Failed to check URL in Google Sheets: ' . $response->get_error_message());
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $body_data = json_decode($body, true);

    // Prepare the data to be updated based on the sheet type
    if (isset($body_data['found']) && $body_data['found']) {
        $update_data = array('update_url' => $site_url, 'password' => $password, 'sheet' => $sheet_type);
    } else {
        $update_data = array(
            'url' => $site_url,
            'username' => $email,
            'password' => $password,
            'sheet' => $sheet_type // Add the sheet type
        );
    }

    $options = array('method' => 'POST', 'body' => $update_data);
    $update_response = wp_remote_post($sheet_url, $options);

    if (is_wp_error($update_response)) {
        error_log('Failed to update Google Sheets: ' . $update_response->get_error_message());
    } else {
        error_log("Google Sheets ({$sheet_type}) updated successfully.");
    }
}

// CLI Commands
if (defined('WP_CLI') && WP_CLI) {
    // SEO User
    WP_CLI::add_command('reset-seo-loginuser-password', function() {
        $email = 'seo.loginuser@growth99.com';
        $sheet_url = 'https://script.google.com/macros/s/AKfycbwvGLZ6MuDelUcGmQCPPdXKkgKklqeJN9pVkF5Wtp_xzX8-HkCep94yW6VtxSQu3bTPYw/exec';
        reset_user_password($email, $sheet_url);
    });

    // Support User
    WP_CLI::add_command('reset-support-loginuser-password', function() {
        $email = 'support.loginuser@growth99.net';
        $sheet_url = 'https://script.google.com/macros/s/AKfycbxINg0x3LazTOv-JAzTpr2WjH-gjs9l9zQ0WYiPdfWw84F8NXTl-oldzYWt8B2jj6rydg/exec';
        reset_user_password($email, $sheet_url);
    });

    // Infra User
    WP_CLI::add_command('reset-infra-loginuser-password', function() {
        $email = 'growth99infra@growth99.com';
        $sheet_url = 'https://script.google.com/macros/s/AKfycbxgdsmjK9xS3pfaTwz37hNNkhELNnqweiJ8liWoNfOsADwrNHarKSuQ5ZcNaTrmoRk0bQ/exec';
        reset_user_password($email, $sheet_url);
    });

    // Onboarding User
    WP_CLI::add_command('reset-onboarding-loginuser-password', function() {
        $email = 'onboarding.india@growth99.com';
        $sheet_url = 'https://script.google.com/macros/s/AKfycbxIyjauNmgTCJyGX7AL4_qkR7Mkf9ZNmcp1Vqf5JIqV3MY242OghWi72jC2ZsO_RyRf/exec';
        reset_user_password($email, $sheet_url);
    });

    // Reset passwords for all users
    WP_CLI::add_command('reset-all-loginuser-passwords', function() {
        WP_CLI::runcommand('reset-seo-loginuser-password');
        WP_CLI::runcommand('reset-support-loginuser-password');
        WP_CLI::runcommand('reset-infra-loginuser-password');
        WP_CLI::runcommand('reset-onboarding-loginuser-password');
        WP_CLI::success("Passwords reset for all users.");
    });
}
