<?php

/**
 * STD Board AJAX Handlers
 *
 * This file contains all AJAX handlers for the STD Board application.
 * Add this as a PHP snippet in WP Code plugin.
 *
 * Replaces REST API write operations with direct database calls.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to verify AJAX request and user permissions
 */
function std_verify_ajax_request($capability = 'edit_posts') {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_rest')) {
        wp_send_json_error(array('message' => 'Security check failed.'), 403);
        exit;
    }

    // Check user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in.'), 401);
        exit;
    }

    // Check capability
    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'), 403);
        exit;
    }

    return true;
}

/**
 * Helper function to sanitize phone/fax numbers
 */
function std_sanitize_phone($value) {
    if (empty($value)) {
        return '';
    }
    // Remove all non-digits
    return preg_replace('/\D/', '', sanitize_text_field($value));
}

// ============================================================================
// JURISDICTION HANDLERS
// ============================================================================

/**
 * Update Jurisdiction
 * Action: std_update_jurisdiction
 */
add_action('wp_ajax_std_update_jurisdiction', 'std_update_jurisdiction_handler');
function std_update_jurisdiction_handler() {
    std_verify_ajax_request('edit_posts');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!$post_id) {
        wp_send_json_error(array('message' => 'Invalid jurisdiction ID.'), 400);
        exit;
    }

    // Verify post exists and is correct type
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'std_jurisdiction') {
        wp_send_json_error(array('message' => 'Jurisdiction not found.'), 404);
        exit;
    }

    // Update post title if provided
    if (isset($_POST['title'])) {
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => sanitize_text_field($_POST['title'])
        ));
    }

    // Update ACF fields
    if (isset($_POST['agency_name'])) {
        update_field('agency_name', sanitize_text_field($_POST['agency_name']), $post_id);
    }
    if (isset($_POST['address_jurisdiction'])) {
        update_field('address_jurisdiction', sanitize_textarea_field($_POST['address_jurisdiction']), $post_id);
    }
    if (isset($_POST['phone_jurisdiction'])) {
        update_field('phone_jurisdiction', std_sanitize_phone($_POST['phone_jurisdiction']), $post_id);
    }

    // Return updated data
    wp_send_json_success(array(
        'id' => $post_id,
        'title' => array('rendered' => get_the_title($post_id)),
        'acf' => array(
            'agency_name' => get_field('agency_name', $post_id),
            'address_jurisdiction' => get_field('address_jurisdiction', $post_id),
            'phone_jurisdiction' => get_field('phone_jurisdiction', $post_id)
        )
    ));
}

// ============================================================================
// USER HANDLERS
// ============================================================================

/**
 * Create User
 * Action: std_create_user
 */
add_action('wp_ajax_std_create_user', 'std_create_user_handler');
function std_create_user_handler() {
    std_verify_ajax_request('create_users');

    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
    $jurisdiction_id = isset($_POST['jurisdiction_id']) ? intval($_POST['jurisdiction_id']) : 0;

    // Validate required fields
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(array('message' => 'Valid email is required.'), 400);
        exit;
    }

    // Check if email already exists
    if (email_exists($email)) {
        wp_send_json_error(array('message' => 'A user with this email already exists.'), 400);
        exit;
    }

    // Generate username from email
    $username = sanitize_user(explode('@', $email)[0], true);
    if (username_exists($username)) {
        $username = $username . '_' . time();
    }

    // Generate secure password
    $password = wp_generate_password(12, true, true);

    // Create user
    $user_id = wp_insert_user(array(
        'user_login' => $username,
        'user_email' => $email,
        'user_pass' => $password,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => 'subscriber'
    ));

    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => $user_id->get_error_message()), 400);
        exit;
    }

    // Update ACF fields
    update_field('user_jurisdiction', $jurisdiction_id, 'user_' . $user_id);

    if (isset($_POST['user_phone'])) {
        update_field('user_phone', std_sanitize_phone($_POST['user_phone']), 'user_' . $user_id);
    }
    if (isset($_POST['user_fax'])) {
        update_field('user_fax', std_sanitize_phone($_POST['user_fax']), 'user_' . $user_id);
    }
    if (isset($_POST['notes_sti_hiv'])) {
        update_field('notes_sti_hiv', sanitize_textarea_field($_POST['notes_sti_hiv']), 'user_' . $user_id);
    }

    // Handle role arrays (sent as JSON strings or arrays)
    $hiv_role = isset($_POST['hiv_role']) ? $_POST['hiv_role'] : array();
    if (is_string($hiv_role)) {
        $hiv_role = json_decode(stripslashes($hiv_role), true) ?: array();
    }
    $hiv_role = array_map('intval', (array)$hiv_role);
    update_field('hiv_role', $hiv_role, 'user_' . $user_id);

    $sti_role = isset($_POST['sti_role']) ? $_POST['sti_role'] : array();
    if (is_string($sti_role)) {
        $sti_role = json_decode(stripslashes($sti_role), true) ?: array();
    }
    $sti_role = array_map('intval', (array)$sti_role);
    update_field('sti_role', $sti_role, 'user_' . $user_id);

    // Return created user data
    wp_send_json_success(array(
        'id' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'acf' => array(
            'user_phone' => get_field('user_phone', 'user_' . $user_id),
            'user_fax' => get_field('user_fax', 'user_' . $user_id),
            'notes_sti_hiv' => get_field('notes_sti_hiv', 'user_' . $user_id),
            'hiv_role' => $hiv_role,
            'sti_role' => $sti_role,
            'user_jurisdiction' => $jurisdiction_id
        )
    ));
}

/**
 * Update User
 * Action: std_update_user
 */
add_action('wp_ajax_std_update_user', 'std_update_user_handler');
function std_update_user_handler() {
    std_verify_ajax_request('edit_users');

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if (!$user_id) {
        wp_send_json_error(array('message' => 'Invalid user ID.'), 400);
        exit;
    }

    // Verify user exists
    $user = get_user_by('id', $user_id);
    if (!$user) {
        wp_send_json_error(array('message' => 'User not found.'), 404);
        exit;
    }

    // Prepare user data update
    $userdata = array('ID' => $user_id);

    if (isset($_POST['first_name'])) {
        $userdata['first_name'] = sanitize_text_field($_POST['first_name']);
    }
    if (isset($_POST['last_name'])) {
        $userdata['last_name'] = sanitize_text_field($_POST['last_name']);
    }
    if (isset($_POST['email'])) {
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address.'), 400);
            exit;
        }
        // Check if email is used by another user
        $existing_user = get_user_by('email', $email);
        if ($existing_user && $existing_user->ID !== $user_id) {
            wp_send_json_error(array('message' => 'Email is already in use by another user.'), 400);
            exit;
        }
        $userdata['user_email'] = $email;
    }

    // Update user
    $result = wp_update_user($userdata);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 400);
        exit;
    }

    // Update ACF fields
    if (isset($_POST['user_phone'])) {
        update_field('user_phone', std_sanitize_phone($_POST['user_phone']), 'user_' . $user_id);
    }
    if (isset($_POST['user_fax'])) {
        update_field('user_fax', std_sanitize_phone($_POST['user_fax']), 'user_' . $user_id);
    }
    if (isset($_POST['notes_sti_hiv'])) {
        update_field('notes_sti_hiv', sanitize_textarea_field($_POST['notes_sti_hiv']), 'user_' . $user_id);
    }
    if (isset($_POST['jurisdiction_id'])) {
        update_field('user_jurisdiction', intval($_POST['jurisdiction_id']), 'user_' . $user_id);
    }

    // Handle role arrays
    if (isset($_POST['hiv_role'])) {
        $hiv_role = $_POST['hiv_role'];
        if (is_string($hiv_role)) {
            $hiv_role = json_decode(stripslashes($hiv_role), true) ?: array();
        }
        $hiv_role = array_map('intval', (array)$hiv_role);
        update_field('hiv_role', $hiv_role, 'user_' . $user_id);
    }

    if (isset($_POST['sti_role'])) {
        $sti_role = $_POST['sti_role'];
        if (is_string($sti_role)) {
            $sti_role = json_decode(stripslashes($sti_role), true) ?: array();
        }
        $sti_role = array_map('intval', (array)$sti_role);
        update_field('sti_role', $sti_role, 'user_' . $user_id);
    }

    // Get fresh user data
    $updated_user = get_user_by('id', $user_id);

    wp_send_json_success(array(
        'id' => $user_id,
        'first_name' => $updated_user->first_name,
        'last_name' => $updated_user->last_name,
        'email' => $updated_user->user_email,
        'acf' => array(
            'user_phone' => get_field('user_phone', 'user_' . $user_id),
            'user_fax' => get_field('user_fax', 'user_' . $user_id),
            'notes_sti_hiv' => get_field('notes_sti_hiv', 'user_' . $user_id),
            'hiv_role' => get_field('hiv_role', 'user_' . $user_id) ?: array(),
            'sti_role' => get_field('sti_role', 'user_' . $user_id) ?: array(),
            'user_jurisdiction' => get_field('user_jurisdiction', 'user_' . $user_id)
        )
    ));
}

/**
 * Delete User
 * Action: std_delete_user
 */
add_action('wp_ajax_std_delete_user', 'std_delete_user_handler');
function std_delete_user_handler() {
    std_verify_ajax_request('delete_users');

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if (!$user_id) {
        wp_send_json_error(array('message' => 'Invalid user ID.'), 400);
        exit;
    }

    // Verify user exists
    $user = get_user_by('id', $user_id);
    if (!$user) {
        wp_send_json_error(array('message' => 'User not found.'), 404);
        exit;
    }

    // Prevent deleting admins
    if (in_array('administrator', $user->roles)) {
        wp_send_json_error(array('message' => 'Cannot delete administrator accounts.'), 403);
        exit;
    }

    // Include user admin functions for wp_delete_user
    require_once(ABSPATH . 'wp-admin/includes/user.php');

    // Delete user, reassign content to user ID 1
    $result = wp_delete_user($user_id, 1);

    if (!$result) {
        wp_send_json_error(array('message' => 'Failed to delete user.'), 500);
        exit;
    }

    wp_send_json_success(array(
        'deleted' => true,
        'id' => $user_id
    ));
}

// ============================================================================
// OOJ (Out-of-Jurisdiction) HANDLERS
// ============================================================================

/**
 * Create OOJ Record
 * Action: std_create_ooj
 */
add_action('wp_ajax_std_create_ooj', 'std_create_ooj_handler');
function std_create_ooj_handler() {
    std_verify_ajax_request('edit_posts');

    $jurisdiction_id = isset($_POST['jurisdiction_id']) ? intval($_POST['jurisdiction_id']) : 0;

    // Create post
    $post_id = wp_insert_post(array(
        'post_type' => 'ooj-detail',
        'post_title' => isset($_POST['title']) ? sanitize_text_field($_POST['title']) : 'OOJ Detail',
        'post_status' => 'publish'
    ));

    if (is_wp_error($post_id)) {
        wp_send_json_error(array('message' => $post_id->get_error_message()), 400);
        exit;
    }

    // Update ACF fields
    update_field('jurisdiction_selection', $jurisdiction_id, $post_id);

    if (isset($_POST['infection'])) {
        $infection = $_POST['infection'] !== '' ? intval($_POST['infection']) : null;
        update_field('infection', $infection, $post_id);
    }
    if (isset($_POST['activity'])) {
        $activity = $_POST['activity'] !== '' ? intval($_POST['activity']) : null;
        update_field('activity', $activity, $post_id);
    }
    if (isset($_POST['point_of_contact'])) {
        $poc = $_POST['point_of_contact'] !== '' ? intval($_POST['point_of_contact']) : null;
        update_field('point_of_contact', $poc, $post_id);
    }
    if (isset($_POST['last_date_of_exposure'])) {
        update_field('last_date_of_exposure', sanitize_text_field($_POST['last_date_of_exposure']), $post_id);
    }
    if (isset($_POST['dispositions_returned'])) {
        update_field('dispositions_returned', sanitize_text_field($_POST['dispositions_returned']), $post_id);
    }
    if (isset($_POST['accept_and_investigate'])) {
        update_field('accept_and_investigate', sanitize_text_field($_POST['accept_and_investigate']), $post_id);
    }
    if (isset($_POST['notes'])) {
        update_field('notes', sanitize_textarea_field($_POST['notes']), $post_id);
    }
    if (isset($_POST['ooj_phone'])) {
        $phone = std_sanitize_phone($_POST['ooj_phone']);
        update_field('ooj_phone', $phone !== '' ? $phone : null, $post_id);
    }
    if (isset($_POST['ooj_fax'])) {
        $fax = std_sanitize_phone($_POST['ooj_fax']);
        update_field('ooj_fax', $fax !== '' ? $fax : null, $post_id);
    }
    if (isset($_POST['ooj_email'])) {
        $email = sanitize_email($_POST['ooj_email']);
        update_field('ooj_email', $email !== '' ? $email : null, $post_id);
    }

    // Handle acceptable_for_pii array
    if (isset($_POST['acceptable_for_pii'])) {
        $pii = $_POST['acceptable_for_pii'];
        if (is_string($pii)) {
            $pii = json_decode(stripslashes($pii), true) ?: array();
        }
        $pii = array_map('intval', (array)$pii);
        update_field('acceptable_for_pii', $pii, $post_id);
    }

    // Handle methods of transmitting taxonomy
    if (isset($_POST['methods_of_transmitting'])) {
        $methods = $_POST['methods_of_transmitting'];
        if (is_string($methods)) {
            $methods = json_decode(stripslashes($methods), true) ?: array();
        }
        $methods = array_map('intval', (array)$methods);
        wp_set_object_terms($post_id, $methods, 'iccr_method-of-transmitting');
    }

    // Return created record
    wp_send_json_success(array(
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'acf' => array(
            'jurisdiction_selection' => $jurisdiction_id,
            'infection' => get_field('infection', $post_id),
            'activity' => get_field('activity', $post_id),
            'point_of_contact' => get_field('point_of_contact', $post_id),
            'acceptable_for_pii' => get_field('acceptable_for_pii', $post_id) ?: array(),
            'last_date_of_exposure' => get_field('last_date_of_exposure', $post_id),
            'dispositions_returned' => get_field('dispositions_returned', $post_id),
            'accept_and_investigate' => get_field('accept_and_investigate', $post_id),
            'notes' => get_field('notes', $post_id),
            'ooj_phone' => get_field('ooj_phone', $post_id),
            'ooj_fax' => get_field('ooj_fax', $post_id),
            'ooj_email' => get_field('ooj_email', $post_id)
        ),
        'iccr_method-of-transmitting' => wp_get_object_terms($post_id, 'iccr_method-of-transmitting', array('fields' => 'ids'))
    ));
}

/**
 * Update OOJ Record
 * Action: std_update_ooj
 */
add_action('wp_ajax_std_update_ooj', 'std_update_ooj_handler');
function std_update_ooj_handler() {
    std_verify_ajax_request('edit_posts');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!$post_id) {
        wp_send_json_error(array('message' => 'Invalid OOJ record ID.'), 400);
        exit;
    }

    // Verify post exists and is correct type
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'ooj-detail') {
        wp_send_json_error(array('message' => 'OOJ record not found.'), 404);
        exit;
    }

    // Update post title if provided
    if (isset($_POST['title'])) {
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => sanitize_text_field($_POST['title'])
        ));
    }

    // Update ACF fields
    if (isset($_POST['jurisdiction_id'])) {
        update_field('jurisdiction_selection', intval($_POST['jurisdiction_id']), $post_id);
    }
    if (isset($_POST['infection'])) {
        $infection = $_POST['infection'] !== '' ? intval($_POST['infection']) : null;
        update_field('infection', $infection, $post_id);
    }
    if (isset($_POST['activity'])) {
        $activity = $_POST['activity'] !== '' ? intval($_POST['activity']) : null;
        update_field('activity', $activity, $post_id);
    }
    if (isset($_POST['point_of_contact'])) {
        $poc = $_POST['point_of_contact'] !== '' ? intval($_POST['point_of_contact']) : null;
        update_field('point_of_contact', $poc, $post_id);
    }
    if (isset($_POST['last_date_of_exposure'])) {
        update_field('last_date_of_exposure', sanitize_text_field($_POST['last_date_of_exposure']), $post_id);
    }
    if (isset($_POST['dispositions_returned'])) {
        update_field('dispositions_returned', sanitize_text_field($_POST['dispositions_returned']), $post_id);
    }
    if (isset($_POST['accept_and_investigate'])) {
        update_field('accept_and_investigate', sanitize_text_field($_POST['accept_and_investigate']), $post_id);
    }
    if (isset($_POST['notes'])) {
        update_field('notes', sanitize_textarea_field($_POST['notes']), $post_id);
    }
    if (isset($_POST['ooj_phone'])) {
        $phone = std_sanitize_phone($_POST['ooj_phone']);
        update_field('ooj_phone', $phone !== '' ? $phone : null, $post_id);
    }
    if (isset($_POST['ooj_fax'])) {
        $fax = std_sanitize_phone($_POST['ooj_fax']);
        update_field('ooj_fax', $fax !== '' ? $fax : null, $post_id);
    }
    if (isset($_POST['ooj_email'])) {
        $email = sanitize_email($_POST['ooj_email']);
        update_field('ooj_email', $email !== '' ? $email : null, $post_id);
    }

    // Handle acceptable_for_pii array
    if (isset($_POST['acceptable_for_pii'])) {
        $pii = $_POST['acceptable_for_pii'];
        if (is_string($pii)) {
            $pii = json_decode(stripslashes($pii), true) ?: array();
        }
        $pii = array_map('intval', (array)$pii);
        update_field('acceptable_for_pii', $pii, $post_id);
    }

    // Handle methods of transmitting taxonomy
    if (isset($_POST['methods_of_transmitting'])) {
        $methods = $_POST['methods_of_transmitting'];
        if (is_string($methods)) {
            $methods = json_decode(stripslashes($methods), true) ?: array();
        }
        $methods = array_map('intval', (array)$methods);
        wp_set_object_terms($post_id, $methods, 'iccr_method-of-transmitting');
    }

    // Return updated record
    wp_send_json_success(array(
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'acf' => array(
            'jurisdiction_selection' => get_field('jurisdiction_selection', $post_id),
            'infection' => get_field('infection', $post_id),
            'activity' => get_field('activity', $post_id),
            'point_of_contact' => get_field('point_of_contact', $post_id),
            'acceptable_for_pii' => get_field('acceptable_for_pii', $post_id) ?: array(),
            'last_date_of_exposure' => get_field('last_date_of_exposure', $post_id),
            'dispositions_returned' => get_field('dispositions_returned', $post_id),
            'accept_and_investigate' => get_field('accept_and_investigate', $post_id),
            'notes' => get_field('notes', $post_id),
            'ooj_phone' => get_field('ooj_phone', $post_id),
            'ooj_fax' => get_field('ooj_fax', $post_id),
            'ooj_email' => get_field('ooj_email', $post_id)
        ),
        'iccr_method-of-transmitting' => wp_get_object_terms($post_id, 'iccr_method-of-transmitting', array('fields' => 'ids'))
    ));
}

/**
 * Delete OOJ Record
 * Action: std_delete_ooj
 */
add_action('wp_ajax_std_delete_ooj', 'std_delete_ooj_handler');
function std_delete_ooj_handler() {
    std_verify_ajax_request('delete_posts');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!$post_id) {
        wp_send_json_error(array('message' => 'Invalid OOJ record ID.'), 400);
        exit;
    }

    // Verify post exists and is correct type
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'ooj-detail') {
        wp_send_json_error(array('message' => 'OOJ record not found.'), 404);
        exit;
    }

    // Delete permanently
    $result = wp_delete_post($post_id, true);

    if (!$result) {
        wp_send_json_error(array('message' => 'Failed to delete OOJ record.'), 500);
        exit;
    }

    wp_send_json_success(array(
        'deleted' => true,
        'id' => $post_id
    ));
}
