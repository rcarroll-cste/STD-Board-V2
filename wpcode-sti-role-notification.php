<?php
/**
 * WPCode Snippet: STI Role Change Email Notification
 *
 * Sends email notification to rcarroll@cste.org when users with specific
 * STI roles are added, deleted, or have their roles changed.
 *
 * Monitored Roles:
 * - STI Surveillance Coordinator
 * - Alternate STI Surveillance Coordinator
 * - STI Data Manager
 *
 * Works with both WordPress admin AND front-end AJAX updates (Kendo UI grid)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Configuration
define('STD_NOTIFICATION_EMAIL', 'rcarroll@cste.org');
define('STD_MONITORED_STI_ROLES', array(
    'STI Surveillance Coordinator',
    'Alternate STI Surveillance Coordinator',
    'STI Data Manager'
));

// DEBUG MODE - Set to true to enable logging, false for production
define('STD_DEBUG_MODE', true);

/**
 * Debug logging function
 */
function std_debug_log($message, $data = null) {
    if (!STD_DEBUG_MODE) {
        return;
    }
    $log_message = '[STI Role Notification] ' . $message;
    if ($data !== null) {
        $log_message .= ' | Data: ' . print_r($data, true);
    }
    error_log($log_message);
}

// Global variable to store roles before update
global $std_sti_roles_before_update;
$std_sti_roles_before_update = array();

/**
 * Get STI role names from term IDs
 *
 * @param array|int $term_ids Array of term IDs or single term ID
 * @return array Array of role names
 */
function std_get_sti_role_names($term_ids) {
    if (empty($term_ids)) {
        return array();
    }

    if (!is_array($term_ids)) {
        $term_ids = array($term_ids);
    }

    $role_names = array();
    foreach ($term_ids as $term_id) {
        // Handle both numeric IDs and term objects
        if (is_object($term_id)) {
            $role_names[] = $term_id->name;
        } else {
            $term = get_term((int)$term_id, 'sti-role');
            if ($term && !is_wp_error($term)) {
                $role_names[] = $term->name;
            }
        }
    }

    return $role_names;
}

/**
 * Check if any of the provided role names are monitored roles
 *
 * @param array $role_names Array of role names
 * @return bool True if at least one monitored role is present
 */
function std_has_monitored_sti_role($role_names) {
    if (empty($role_names)) {
        return false;
    }

    foreach ($role_names as $role_name) {
        if (in_array($role_name, STD_MONITORED_STI_ROLES)) {
            return true;
        }
    }

    return false;
}

/**
 * Get jurisdiction name for a user
 *
 * @param int $user_id User ID
 * @return string Jurisdiction name or 'Unknown'
 */
function std_get_user_jurisdiction_name($user_id) {
    $jurisdiction_id = get_user_meta($user_id, 'user_jurisdiction', true);

    if ($jurisdiction_id) {
        $jurisdiction_post = get_post($jurisdiction_id);
        if ($jurisdiction_post) {
            return $jurisdiction_post->post_title;
        }
    }

    return 'Unknown';
}

/**
 * Format role list for email display
 *
 * @param array $role_names Array of role names
 * @return string Formatted string of roles or 'No role'
 */
function std_format_role_list($role_names) {
    if (empty($role_names)) {
        return 'No role';
    }
    return implode(', ', $role_names);
}

/**
 * Send STI role change notification email
 *
 * @param int $user_id User ID
 * @param array $roles_before Array of role names before change
 * @param array $roles_after Array of role names after change
 */
function std_send_sti_role_change_email($user_id, $roles_before, $roles_after) {
    $user = get_userdata($user_id);
    if (!$user) {
        std_debug_log('Could not get user data', array('user_id' => $user_id));
        return;
    }

    $to = STD_NOTIFICATION_EMAIL;
    $subject = 'CSTE Contact Board - STI Role Change Notification';

    $jurisdiction = std_get_user_jurisdiction_name($user_id);
    $first_name = $user->first_name ?: 'Not provided';
    $last_name = $user->last_name ?: 'Not provided';
    $email = $user->user_email;
    $date_changed = current_time('F j, Y');

    $roles_before_str = std_format_role_list($roles_before);
    $roles_after_str = std_format_role_list($roles_after);

    $message = "This email is to notify you that a change has been made to the CSTE HIV-STI-OOJ Contact Board for at least one of the following roles: STI Surveillance Coordinator, Alternate STI Surveillance Coordinator, or STI Data Manager. Additional details are provided below.\n\n";
    $message .= "Jurisdiction: {$jurisdiction}\n";
    $message .= "First Name: {$first_name}\n";
    $message .= "Last Name: {$last_name}\n";
    $message .= "Email: {$email}\n";
    $message .= "STI role(s) before change: {$roles_before_str}\n";
    $message .= "STI role(s) after change: {$roles_after_str}\n";
    $message .= "Date change was made: {$date_changed}\n";

    $headers = array('Content-Type: text/plain; charset=UTF-8');

    std_debug_log('Attempting to send email', array(
        'to' => $to,
        'subject' => $subject,
        'user_id' => $user_id,
        'roles_before' => $roles_before_str,
        'roles_after' => $roles_after_str
    ));

    $result = wp_mail($to, $subject, $message, $headers);

    std_debug_log('wp_mail result', array('success' => $result));
}

/**
 * Capture STI roles BEFORE user meta is updated
 * This hook fires when update_user_meta is called
 */
function std_capture_roles_before_meta_update($meta_id, $user_id, $meta_key, $meta_value) {
    // Only process sti_role meta key
    if ($meta_key !== 'sti_role') {
        return;
    }

    std_debug_log('update_user_meta hook fired for sti_role', array(
        'user_id' => $user_id,
        'meta_key' => $meta_key,
        'new_value' => $meta_value
    ));

    global $std_sti_roles_before_update;

    // Get current roles BEFORE the update happens
    $current_term_ids = get_user_meta($user_id, 'sti_role', true);
    std_debug_log('Current term IDs before update', array('term_ids' => $current_term_ids));

    $current_role_names = std_get_sti_role_names($current_term_ids);
    std_debug_log('Current role names before update', array('role_names' => $current_role_names));

    $std_sti_roles_before_update[$user_id] = $current_role_names;
}
add_action('update_user_meta', 'std_capture_roles_before_meta_update', 10, 4);

/**
 * Check for role changes AFTER user meta is updated
 */
function std_check_roles_after_meta_update($meta_id, $user_id, $meta_key, $meta_value) {
    // Only process sti_role meta key
    if ($meta_key !== 'sti_role') {
        return;
    }

    std_debug_log('updated_user_meta hook fired for sti_role', array(
        'user_id' => $user_id,
        'meta_key' => $meta_key,
        'new_value' => $meta_value
    ));

    global $std_sti_roles_before_update;

    // Get roles before (from our capture)
    $roles_before = isset($std_sti_roles_before_update[$user_id])
        ? $std_sti_roles_before_update[$user_id]
        : array();

    std_debug_log('Roles BEFORE from capture', array('roles_before' => $roles_before));

    // Get the new roles from the meta value that was just saved
    $new_term_ids = $meta_value;
    if (!is_array($new_term_ids)) {
        $new_term_ids = maybe_unserialize($new_term_ids);
    }
    if (!is_array($new_term_ids)) {
        $new_term_ids = $new_term_ids ? array($new_term_ids) : array();
    }

    std_debug_log('New term IDs from meta_value', array('term_ids' => $new_term_ids));

    $roles_after = std_get_sti_role_names($new_term_ids);
    std_debug_log('Roles AFTER update', array('roles_after' => $roles_after));

    // Check if roles actually changed
    $roles_before_sorted = $roles_before;
    $roles_after_sorted = $roles_after;
    sort($roles_before_sorted);
    sort($roles_after_sorted);

    if ($roles_before_sorted === $roles_after_sorted) {
        std_debug_log('No role change detected, skipping email');
        unset($std_sti_roles_before_update[$user_id]);
        return;
    }

    std_debug_log('Role change detected!', array(
        'before' => $roles_before,
        'after' => $roles_after
    ));

    // Check if any monitored role is involved in the change
    $monitored_before = std_has_monitored_sti_role($roles_before);
    $monitored_after = std_has_monitored_sti_role($roles_after);

    std_debug_log('Monitored role check', array(
        'monitored_before' => $monitored_before,
        'monitored_after' => $monitored_after
    ));

    if ($monitored_before || $monitored_after) {
        std_debug_log('Sending notification email!');
        std_send_sti_role_change_email($user_id, $roles_before, $roles_after);
    } else {
        std_debug_log('No monitored roles involved, skipping email');
    }

    // Clean up
    unset($std_sti_roles_before_update[$user_id]);
}
add_action('updated_user_meta', 'std_check_roles_after_meta_update', 10, 4);

/**
 * Handle when sti_role meta is ADDED (new user or first time setting role)
 */
function std_check_roles_on_meta_add($meta_id, $user_id, $meta_key, $meta_value) {
    // Only process sti_role meta key
    if ($meta_key !== 'sti_role') {
        return;
    }

    std_debug_log('added_user_meta hook fired for sti_role', array(
        'user_id' => $user_id,
        'meta_key' => $meta_key,
        'meta_value' => $meta_value
    ));

    // Get the new roles from the meta value
    $new_term_ids = $meta_value;
    if (!is_array($new_term_ids)) {
        $new_term_ids = maybe_unserialize($new_term_ids);
    }
    if (!is_array($new_term_ids)) {
        $new_term_ids = $new_term_ids ? array($new_term_ids) : array();
    }

    $roles_after = std_get_sti_role_names($new_term_ids);
    std_debug_log('Roles being added', array('roles_after' => $roles_after));

    // Check if any monitored role is being added
    if (std_has_monitored_sti_role($roles_after)) {
        std_debug_log('New user with monitored role - sending notification');
        std_send_sti_role_change_email($user_id, array(), $roles_after);
    }
}
add_action('added_user_meta', 'std_check_roles_on_meta_add', 10, 4);

/**
 * Notify when a user with monitored STI role is deleted
 */
function std_notify_user_deletion($id, $reassign, $user) {
    std_debug_log('delete_user hook fired', array('user_id' => $id));

    // Get STI roles before user is deleted
    $term_ids = get_user_meta($id, 'sti_role', true);
    std_debug_log('User roles before deletion', array('term_ids' => $term_ids));

    $role_names = std_get_sti_role_names($term_ids);
    std_debug_log('Role names before deletion', array('role_names' => $role_names));

    // Only send notification if user had a monitored role
    if (std_has_monitored_sti_role($role_names)) {
        std_debug_log('User has monitored role - sending deletion notification');

        // Get user details before deletion
        $jurisdiction = std_get_user_jurisdiction_name($id);
        $first_name = $user->first_name ?: 'Not provided';
        $last_name = $user->last_name ?: 'Not provided';
        $email = $user->user_email;
        $date_changed = current_time('F j, Y');

        $roles_before_str = std_format_role_list($role_names);

        $to = STD_NOTIFICATION_EMAIL;
        $subject = 'CSTE Contact Board - STI Role Change Notification';

        $message = "This email is to notify you that a change has been made to the CSTE HIV-STI-OOJ Contact Board for at least one of the following roles: STI Surveillance Coordinator, Alternate STI Surveillance Coordinator, or STI Data Manager. Additional details are provided below.\n\n";
        $message .= "Jurisdiction: {$jurisdiction}\n";
        $message .= "First Name: {$first_name}\n";
        $message .= "Last Name: {$last_name}\n";
        $message .= "Email: {$email}\n";
        $message .= "STI role(s) before change: {$roles_before_str}\n";
        $message .= "STI role(s) after change: No role\n";
        $message .= "Date change was made: {$date_changed}\n";
        $message .= "\n(User account was deleted)\n";

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        std_debug_log('Sending deletion email');
        $result = wp_mail($to, $subject, $message, $headers);
        std_debug_log('wp_mail result for deletion', array('success' => $result));
    } else {
        std_debug_log('User does not have monitored role - skipping deletion notification');
    }
}
add_action('delete_user', 'std_notify_user_deletion', 10, 3);

// Log that the snippet has loaded
std_debug_log('STI Role Notification snippet loaded successfully');
