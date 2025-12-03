<?php
/**
 * Database Handler
 */

if (!defined('ABSPATH')) exit;

class AIPG_Database {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for podcast generation tracking
        $table_name = $wpdb->prefix . 'aipg_generations';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            script_data longtext,
            audio_chunks longtext,
            final_audio_url varchar(500) DEFAULT NULL,
            settings longtext,
            error_log longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Verify table was created
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($table_exists) {
            update_option('aipg_db_version', AIPG_VERSION);
            error_log('AIPG: Database table created successfully - ' . $table_name);
        } else {
            error_log('AIPG: Failed to create database table - ' . $table_name);
            error_log('AIPG SQL: ' . $sql);
            error_log('AIPG dbDelta result: ' . print_r($result, true));
        }
        
        return $table_exists;
    }
    
    /**
     * Create generation record
     */
    public static function create_generation($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipg_generations';
        
        $wpdb->insert($table, array(
            'post_id' => $data['post_id'] ?? null,
            'status' => 'pending',
            'settings' => maybe_serialize($data['settings'] ?? array()),
            'created_at' => current_time('mysql'),
        ));
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update generation record
     */
    public static function update_generation($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipg_generations';
        
        $update_data = array();
        
        if (isset($data['status'])) $update_data['status'] = $data['status'];
        if (isset($data['script_data'])) $update_data['script_data'] = maybe_serialize($data['script_data']);
        if (isset($data['audio_chunks'])) $update_data['audio_chunks'] = maybe_serialize($data['audio_chunks']);
        if (isset($data['final_audio_url'])) $update_data['final_audio_url'] = $data['final_audio_url'];
        if (isset($data['error_log'])) $update_data['error_log'] = $data['error_log'];
        
        $update_data['updated_at'] = current_time('mysql');
        
        return $wpdb->update($table, $update_data, array('id' => $id));
    }
    
    /**
     * Get generation by ID
     */
    public static function get_generation($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipg_generations';
        
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if ($result) {
            $result->settings = maybe_unserialize($result->settings);
            $result->script_data = maybe_unserialize($result->script_data);
            $result->audio_chunks = maybe_unserialize($result->audio_chunks);
        }
        
        return $result;
    }
    
    /**
     * Get generations by post ID
     */
    public static function get_generations_by_post($post_id, $limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipg_generations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d ORDER BY created_at DESC LIMIT %d",
            $post_id, $limit
        ));
    }
    
    /**
     * Get recent generations
     */
    public static function get_recent_generations($limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipg_generations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Delete generation
     */
    public static function delete_generation($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipg_generations';
        
        return $wpdb->delete($table, array('id' => $id));
    }
}
