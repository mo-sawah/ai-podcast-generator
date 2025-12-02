<?php
/**
 * Podcast Scheduler for Automated Generation
 */

if (!defined('ABSPATH')) exit;

class AIPG_Scheduler {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'setup_schedules'));
        add_action('aipg_auto_generate_podcast', array($this, 'auto_generate_podcast'));
    }
    
    /**
     * Setup scheduled events
     */
    public function setup_schedules() {
        $enabled = get_option('aipg_auto_generate_enabled', false);
        $frequency = get_option('aipg_auto_generate_frequency', 'hourly');
        
        if ($enabled) {
            if (!as_next_scheduled_action('aipg_auto_generate_podcast')) {
                $this->schedule_auto_generation($frequency);
            }
        } else {
            as_unschedule_all_actions('aipg_auto_generate_podcast');
        }
    }
    
    /**
     * Schedule automatic podcast generation
     */
    public function schedule_auto_generation($frequency = 'hourly') {
        // Cancel existing schedule
        as_unschedule_all_actions('aipg_auto_generate_podcast');
        
        // Convert frequency to seconds
        $intervals = array(
            '15min' => 15 * MINUTE_IN_SECONDS,
            '30min' => 30 * MINUTE_IN_SECONDS,
            'hourly' => HOUR_IN_SECONDS,
            'twicedaily' => 12 * HOUR_IN_SECONDS,
            'daily' => DAY_IN_SECONDS,
        );
        
        $interval = $intervals[$frequency] ?? HOUR_IN_SECONDS;
        
        // Schedule recurring action
        as_schedule_recurring_action(time(), $interval, 'aipg_auto_generate_podcast', array(), 'aipg');
    }
    
    /**
     * Auto-generate podcast (scheduled task)
     */
    public function auto_generate_podcast() {
        // Check if auto-generation is still enabled
        if (!get_option('aipg_auto_generate_enabled', false)) {
            return;
        }
        
        try {
            $settings = $this->get_auto_generation_settings();
            
            // Get recent articles
            $count = get_option('aipg_auto_selection_count', 20);
            $articles = $this->get_candidate_articles($count);
            
            if (empty($articles)) {
                error_log('AIPG Auto-generate: No articles found');
                return;
            }
            
            // Select best article using AI
            $openrouter = new AIPG_OpenRouter();
            $selected_index = $openrouter->select_best_article($articles);
            
            if (is_wp_error($selected_index)) {
                error_log('AIPG Auto-generate error: ' . $selected_index->get_error_message());
                return;
            }
            
            $selected_post_id = $articles[$selected_index]['id'];
            
            // Check if we already generated a podcast for this post
            if ($this->has_podcast($selected_post_id)) {
                error_log("AIPG: Post {$selected_post_id} already has a podcast, skipping");
                
                // Try next best article
                if (isset($articles[$selected_index + 1])) {
                    $selected_post_id = $articles[$selected_index + 1]['id'];
                } else {
                    return; // No more articles
                }
            }
            
            // Create generation record
            $generation_id = AIPG_Database::create_generation(array(
                'post_id' => $selected_post_id,
                'settings' => $settings,
            ));
            
            // Process immediately in background
            as_enqueue_async_action('aipg_process_generation', array($generation_id), 'aipg');
            
            error_log("AIPG: Auto-generated podcast for post {$selected_post_id} (generation #{$generation_id})");
            
        } catch (Exception $e) {
            error_log('AIPG Auto-generate error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get auto-generation settings
     */
    private function get_auto_generation_settings() {
        return array(
            'duration' => get_option('aipg_auto_duration', 10),
            'language' => get_option('aipg_auto_language', 'English'),
            'hosts' => get_option('aipg_auto_hosts', 2),
            'guest' => get_option('aipg_auto_include_guest', false),
            'intro_text' => get_option('aipg_auto_intro_text', ''),
            'outro_text' => get_option('aipg_auto_outro_text', ''),
            'voice_mapping' => get_option('aipg_auto_voice_mapping', array()),
        );
    }
    
    /**
     * Get candidate articles for automatic generation
     */
    private function get_candidate_articles($count = 20) {
        $exclude_with_podcasts = get_option('aipg_auto_exclude_existing', true);
        
        $args = array(
            'posts_per_page' => $count,
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        // Exclude posts that already have podcasts
        if ($exclude_with_podcasts) {
            $existing_sources = $this->get_posts_with_podcasts();
            if (!empty($existing_sources)) {
                $args['post__not_in'] = $existing_sources;
            }
        }
        
        $posts = get_posts($args);
        
        $articles = array();
        foreach ($posts as $post) {
            $articles[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => wp_strip_all_tags($post->post_content),
                'date' => $post->post_date,
            );
        }
        
        return $articles;
    }
    
    /**
     * Get post IDs that already have podcasts
     */
    private function get_posts_with_podcasts() {
        global $wpdb;
        
        $query = "SELECT DISTINCT meta_value 
                  FROM {$wpdb->postmeta} 
                  WHERE meta_key = '_aipg_source_post_id'
                  AND meta_value != ''";
        
        $results = $wpdb->get_col($query);
        
        return array_map('intval', $results);
    }
    
    /**
     * Check if post has a podcast
     */
    private function has_podcast($post_id) {
        $existing_podcasts = get_posts(array(
            'post_type' => 'ai_podcast',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_aipg_source_post_id',
                    'value' => $post_id,
                ),
            ),
            'fields' => 'ids',
            'posts_per_page' => 1,
        ));
        
        return !empty($existing_podcasts);
    }
    
    /**
     * Enable auto-generation
     */
    public static function enable_auto_generation($frequency = 'hourly') {
        update_option('aipg_auto_generate_enabled', true);
        update_option('aipg_auto_generate_frequency', $frequency);
        
        $scheduler = self::instance();
        $scheduler->schedule_auto_generation($frequency);
    }
    
    /**
     * Disable auto-generation
     */
    public static function disable_auto_generation() {
        update_option('aipg_auto_generate_enabled', false);
        as_unschedule_all_actions('aipg_auto_generate_podcast');
    }
}
