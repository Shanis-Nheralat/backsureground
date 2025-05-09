<?php
/**
 * Settings Management Functions
 * 
 * Centralized functions for accessing and managing application settings
 */

// Include database connection if not already included
if (!function_exists('db_query')) {
    require_once __DIR__ . '/db.php';
}

// Global settings cache
$GLOBALS['settings_cache'] = [];

/**
 * Get a setting value from database or cache
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @param bool $use_cache Whether to use the cache
 * @return mixed Setting value or default
 */
function get_setting($key, $default = null, $use_cache = true) {
    // Check cache first if enabled
    if ($use_cache && isset($GLOBALS['settings_cache'][$key])) {
        return $GLOBALS['settings_cache'][$key];
    }
    
    // Fetch from database
    $setting = db_query_row("SELECT setting_value, setting_type, autoload FROM settings WHERE setting_key = ?", [$key]);
    
    if (!$setting) {
        return $default;
    }
    
    // Process value based on type
    $value = process_setting_value($setting['setting_value'], $setting['setting_type']);
    
    // Cache if autoload is enabled
    if ($setting['autoload']) {
        $GLOBALS['settings_cache'][$key] = $value;
    }
    
    return $value;
}

/**
 * Process setting value based on its type
 * 
 * @param string $value Raw value from database
 * @param string $type Setting type
 * @return mixed Processed value
 */
function process_setting_value($value, $type) {
    switch ($type) {
        case 'boolean':
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        case 'json':
            return $value ? json_decode($value, true) : [];
        case 'image':
        case 'file':
            // Return file path or empty string
            return $value ?: '';
        default:
            return $value;
    }
}

/**
 * Set a setting value
 * 
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool Success or failure
 */
function set_setting($key, $value) {
    // Get current setting
    $setting = db_query_row("SELECT id, setting_type, autoload FROM settings WHERE setting_key = ?", [$key]);
    
    if (!$setting) {
        return false;
    }
    
    // Prepare value based on type
    $prepared_value = prepare_setting_value($value, $setting['setting_type']);
    
    // Update database
    $result = db_update('settings', ['setting_value' => $prepared_value], 'setting_key = ?', [$key]);
    
    // Update cache if autoload is enabled
    if ($result && $setting['autoload']) {
        $processed_value = process_setting_value($prepared_value, $setting['setting_type']);
        $GLOBALS['settings_cache'][$key] = $processed_value;
    }
    
    // Log activity if function exists
    if (function_exists('log_action')) {
        log_action('setting_update', "Updated setting: $key");
    }
    
    return (bool) $result;
}

/**
 * Prepare setting value for storage based on type
 * 
 * @param mixed $value Value to prepare
 * @param string $type Setting type
 * @return string Prepared value
 */
function prepare_setting_value($value, $type) {
    switch ($type) {
        case 'boolean':
            return $value ? 'true' : 'false';
        case 'json':
            return $value ? json_encode($value) : '';
        default:
            return (string) $value;
    }
}

/**
 * Load all settings for a specific group
 * 
 * @param string $group Setting group
 * @param bool $use_cache Whether to use cached values
 * @return array Associative array of settings
 */
function load_settings_group($group, $use_cache = true) {
    // Check if we have the whole group cached
    if ($use_cache && isset($GLOBALS['settings_cache']['groups'][$group])) {
        return $GLOBALS['settings_cache']['groups'][$group];
    }
    
    // Fetch all settings in the group
    $settings = db_query("SELECT setting_key, setting_value, setting_type, autoload FROM settings WHERE setting_group = ?", [$group]);
    
    $result = [];
    
    foreach ($settings as $setting) {
        $value = process_setting_value($setting['setting_value'], $setting['setting_type']);
        $result[$setting['setting_key']] = $value;
        
        // Cache individual settings if autoload is enabled
        if ($setting['autoload']) {
            $GLOBALS['settings_cache'][$setting['setting_key']] = $value;
        }
    }
    
    // Cache the entire group
    $GLOBALS['settings_cache']['groups'][$group] = $result;
    
    return $result;
}

/**
 * Load all autoload settings into cache
 */
function load_autoload_settings() {
    $settings = db_query("SELECT setting_key, setting_value, setting_type FROM settings WHERE autoload = TRUE");
    
    foreach ($settings as $setting) {
        $key = $setting['setting_key'];
        $value = process_setting_value($setting['setting_value'], $setting['setting_type']);
        $GLOBALS['settings_cache'][$key] = $value;
    }
}

/**
 * Get all setting groups
 * 
 * @return array List of setting groups
 */
function get_setting_groups() {
    $groups = db_query("SELECT DISTINCT setting_group FROM settings ORDER BY setting_group");
    return array_column($groups, 'setting_group');
}

/**
 * Render a setting field based on its type
 * 
 * @param array $setting Setting details
 * @return string HTML for the setting field
 */
function render_setting_field($setting) {
    $key = $setting['setting_key'];
    $value = $setting['setting_value'];
    $type = $setting['setting_type'];
    $label = $setting['setting_label'];
    $description = $setting['setting_description'];
    
    $field_html = '';
    
    // Start field container
    $field_html .= '<div class="mb-3">';
    $field_html .= '<label for="setting_' . htmlspecialchars($key) . '" class="form-label">' . htmlspecialchars($label) . '</label>';
    
    // Render field based on type
    switch ($type) {
        case 'text':
            $field_html .= '<input type="text" class="form-control" id="setting_' . htmlspecialchars($key) . '" name="settings[' . htmlspecialchars($key) . ']" value="' . htmlspecialchars($value) . '">';
            break;
            
        case 'textarea':
            $field_html .= '<textarea class="form-control" id="setting_' . htmlspecialchars($key) . '" name="settings[' . htmlspecialchars($key) . ']" rows="4">' . htmlspecialchars($value) . '</textarea>';
            break;
            
        case 'boolean':
            $checked = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '';
            $field_html .= '<div class="form-check form-switch">';
            $field_html .= '<input class="form-check-input" type="checkbox" id="setting_' . htmlspecialchars($key) . '" name="settings[' . htmlspecialchars($key) . ']" value="true" ' . $checked . '>';
            $field_html .= '</div>';
            break;
            
        case 'image':
            $field_html .= render_media_field($key, $value, 'image');
            break;
            
        case 'file':
            $field_html .= render_media_field($key, $value, 'file');
            break;
            
        case 'json':
            $field_html .= '<textarea class="form-control json-editor" id="setting_' . htmlspecialchars($key) . '" name="settings[' . htmlspecialchars($key) . ']" rows="8">' . htmlspecialchars($value) . '</textarea>';
            $field_html .= '<div class="invalid-feedback json-error"></div>';
            $field_html .= '<div class="form-text">Enter valid JSON format</div>';
            break;
    }
    
    // Add description if available
    if ($description) {
        $field_html .= '<div class="form-text">' . htmlspecialchars($description) . '</div>';
    }
    
    // Close field container
    $field_html .= '</div>';
    
    return $field_html;
}

/**
 * Render a media field for image or file uploads
 * 
 * @param string $key Setting key
 * @param string $value Current value
 * @param string $type Media type (image or file)
 * @return string HTML for the media field
 */
function render_media_field($key, $value, $type) {
    $field_html = '';
    
    // Hidden field to store the file path
    $field_html .= '<input type="hidden" id="setting_' . htmlspecialchars($key) . '" name="settings[' . htmlspecialchars($key) . ']" value="' . htmlspecialchars($value) . '">';
    
    // Current file preview
    if ($value) {
        $field_html .= '<div class="mb-2 current-file">';
        
        if ($type == 'image') {
            $file_url = $value . '?v=' . time(); // Add timestamp to prevent caching
            $field_html .= '<img src="' . htmlspecialchars($file_url) . '" alt="Current Image" class="img-thumbnail media-preview" style="max-height: 100px;">';
        } else {
            $file_name = basename($value);
            $field_html .= '<span class="badge bg-secondary"><i class="bi bi-file-earmark"></i> ' . htmlspecialchars($file_name) . '</span>';
        }
        
        $field_html .= ' <button type="button" class="btn btn-sm btn-danger remove-media" data-target="setting_' . htmlspecialchars($key) . '"><i class="bi bi-x"></i> Remove</button>';
        $field_html .= '</div>';
    }
    
    // File upload control
    $field_html .= '<div class="input-group">';
    $field_html .= '<input type="file" class="form-control media-upload" id="file_' . htmlspecialchars($key) . '" data-target="setting_' . htmlspecialchars($key) . '"';
    
    if ($type == 'image') {
        $field_html .= ' accept="image/*"';
    }
    
    $field_html .= '>';
    $field_html .= '<button class="btn btn-outline-secondary media-browser" type="button" data-target="setting_' . htmlspecialchars($key) . '">Media Library</button>';
    $field_html .= '</div>';
    
    return $field_html;
}

/**
 * Save settings from form submission
 * 
 * @param array $settings Associative array of settings
 * @return int Number of settings updated
 */
function save_settings($settings) {
    if (!is_array($settings)) {
        return 0;
    }
    
    $count = 0;
    
    foreach ($settings as $key => $value) {
        if (set_setting($key, $value)) {
            $count++;
        }
    }
    
    return $count;
}

// Load autoload settings at script initialization
load_autoload_settings();