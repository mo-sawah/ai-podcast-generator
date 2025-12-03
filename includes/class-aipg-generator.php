<?php
/**
 * Main Podcast Generator
 */

if (!defined('ABSPATH')) exit;

class AIPG_Generator {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_aipg_generate_manual', array($this, 'ajax_generate_manual'));
        add_action('wp_ajax_aipg_select_article', array($this, 'ajax_select_article'));
        add_action('wp_ajax_aipg_retry_generation', array($this, 'ajax_retry_generation'));
        add_action('aipg_process_generation', array($this, 'process_generation'), 10, 1);
    }
    
    /**
     * AJAX: Retry failed generation
     */
    public function ajax_retry_generation() {
        check_ajax_referer('aipg_generate', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }
        
        $generation_id = intval($_POST['generation_id'] ?? 0);
        
        if (!$generation_id) {
            wp_send_json_error('Invalid generation ID');
        }
        
        // Reset status and retry
        AIPG_Database::update_generation($generation_id, array(
            'status' => 'pending',
            'error_log' => '',
        ));
        
        // Re-queue the task
        as_enqueue_async_action('aipg_process_generation', array($generation_id), 'aipg');
        
        wp_send_json_success(array(
            'message' => 'Generation restarted',
        ));
    }
    
    /**
     * AJAX: Generate podcast manually
     */
    public function ajax_generate_manual() {
        check_ajax_referer('aipg_generate', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $duration = intval($_POST['duration'] ?? 10);
        $language = sanitize_text_field($_POST['language'] ?? 'English');
        $hosts = intval($_POST['hosts'] ?? 2);
        $include_guest = isset($_POST['include_guest']) && $_POST['include_guest'] === 'yes';
        
        // Get voice mapping
        $voice_mapping_raw = json_decode(stripslashes($_POST['voice_mapping'] ?? '{}'), true);
        
        // Host names (for script)
        $host_names = array('Alex', 'Sam', 'Jordan');
        $guest_name = 'Expert';
        
        // Build proper voice mapping
        $voice_mapping = array();
        for ($i = 1; $i <= $hosts; $i++) {
            $key = "Host {$i}";
            $voice_mapping[$key] = $voice_mapping_raw["host_{$i}"] ?? 'alloy';
        }
        
        if ($include_guest) {
            $voice_mapping[$guest_name] = $voice_mapping_raw['guest'] ?? 'echo';
        }
        
        // Add intro/outro voices
        $voice_mapping['intro'] = $voice_mapping_raw['intro'] ?? $voice_mapping['Host 1'];
        $voice_mapping['outro'] = $voice_mapping_raw['outro'] ?? $voice_mapping['Host 1'];
        
        error_log('AIPG: Voice mapping - ' . json_encode($voice_mapping));
        
        $settings = array(
            'duration' => $duration,
            'language' => $language,
            'hosts' => $hosts,
            'host_names' => $host_names,
            'guest' => $include_guest,
            'guest_name' => $guest_name,
            'intro_text' => sanitize_textarea_field($_POST['intro_text'] ?? ''),
            'outro_text' => sanitize_textarea_field($_POST['outro_text'] ?? ''),
            'voice_mapping' => $voice_mapping,
            'model' => 'tts-1-hd',
            'speed' => 1.0,
        );
        
        // Create generation record
        $generation_id = AIPG_Database::create_generation(array(
            'post_id' => $post_id,
            'settings' => $settings,
        ));
        
        // Schedule background processing
        as_enqueue_async_action('aipg_process_generation', array($generation_id), 'aipg');
        
        wp_send_json_success(array(
            'message' => 'Podcast generation started',
            'generation_id' => $generation_id,
        ));
    }
    
    /**
     * AJAX: Select best article
     */
    public function ajax_select_article() {
        check_ajax_referer('aipg_generate', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }
        
        $count = intval($_POST['count'] ?? 10);
        
        // Get recent posts
        $articles = $this->get_recent_articles($count);
        
        if (empty($articles)) {
            wp_send_json_error('No articles found');
        }
        
        // Use OpenRouter to select best
        $openrouter = new AIPG_OpenRouter();
        $selected_index = $openrouter->select_best_article($articles);
        
        if (is_wp_error($selected_index)) {
            wp_send_json_error($selected_index->get_error_message());
        }
        
        wp_send_json_success(array(
            'post_id' => $articles[$selected_index]['id'],
            'title' => $articles[$selected_index]['title'],
        ));
    }
    
    /**
     * Process podcast generation (background task)
     */
    public function process_generation($generation_id) {
        $generation = AIPG_Database::get_generation($generation_id);
        
        if (!$generation) {
            error_log("Generation {$generation_id} not found");
            return;
        }
        
        try {
            // Update status
            AIPG_Database::update_generation($generation_id, array('status' => 'processing'));
            
            // Get article content
            $post = get_post($generation->post_id);
            if (!$post) {
                throw new Exception('Source post not found');
            }
            
            $article_content = $this->prepare_article_content($post);
            
            // Step 1: Generate search queries (optional)
            $search_data = '';
            if (get_option('aipg_enable_search', true)) {
                AIPG_Database::update_generation($generation_id, array('status' => 'searching'));
                
                $openrouter = new AIPG_OpenRouter();
                $queries = $openrouter->generate_search_queries($article_content);
                
                if (!is_wp_error($queries) && !empty($queries)) {
                    $tavily = new AIPG_Tavily();
                    $search_data = $tavily->enrich_article($article_content, $queries);
                }
            }
            
            // Step 2: Generate script
            AIPG_Database::update_generation($generation_id, array('status' => 'generating_script'));
            
            $settings = $generation->settings;
            $settings['search_data'] = $search_data;
            
            $openrouter = new AIPG_OpenRouter();
            $script_result = $openrouter->generate_script($article_content, $settings);
            
            if (is_wp_error($script_result)) {
                throw new Exception($script_result->get_error_message());
            }
            
            // Save script
            AIPG_Database::update_generation($generation_id, array(
                'script_data' => $script_result,
            ));
            
            // Step 3: Generate audio
            AIPG_Database::update_generation($generation_id, array('status' => 'generating_audio'));
            
            $tts = new AIPG_OpenAI_TTS();
            $audio_chunks = $tts->generate_podcast_audio($script_result, $settings);
            
            if (is_wp_error($audio_chunks)) {
                throw new Exception($audio_chunks->get_error_message());
            }
            
            // Save chunks
            AIPG_Database::update_generation($generation_id, array(
                'audio_chunks' => $audio_chunks,
            ));
            
            // Step 4: Merge audio
            AIPG_Database::update_generation($generation_id, array('status' => 'merging_audio'));
            
            error_log("AIPG Generation {$generation_id}: Starting audio merge with " . count($audio_chunks) . " chunks");
            
            $final_audio = $tts->merge_audio_chunks($audio_chunks);
            
            if (is_wp_error($final_audio)) {
                error_log("AIPG Generation {$generation_id}: Audio merge failed - " . $final_audio->get_error_message());
                throw new Exception('Audio merge failed: ' . $final_audio->get_error_message());
            }
            
            error_log("AIPG Generation {$generation_id}: Audio merged successfully - " . $final_audio['url']);
            
            // Step 5: Create podcast post
            $podcast_id = $this->create_podcast_post($post, $script_result, $final_audio, $settings);
            
            // Update generation
            AIPG_Database::update_generation($generation_id, array(
                'status' => 'completed',
                'final_audio_url' => $final_audio['url'],
            ));
            
            // Update podcast post meta
            update_post_meta($podcast_id, '_aipg_generation_id', $generation_id);
            update_post_meta($podcast_id, '_aipg_generation_status', 'completed');
            
            error_log("Podcast generation {$generation_id} completed successfully");
            
        } catch (Exception $e) {
            error_log("Podcast generation {$generation_id} failed: " . $e->getMessage());
            
            AIPG_Database::update_generation($generation_id, array(
                'status' => 'failed',
                'error_log' => $e->getMessage(),
            ));
        }
    }
    
    /**
     * Prepare article content for processing
     */
    private function prepare_article_content($post) {
        $content = $post->post_title . "\n\n" . $post->post_content;
        
        // Strip shortcodes and HTML
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);
        
        // Limit length (OpenRouter can handle large context but let's be reasonable)
        $content = wp_trim_words($content, 5000);
        
        return $content;
    }
    
    /**
     * Create podcast post
     */
    private function create_podcast_post($source_post, $script_result, $audio_file, $settings) {
        $podcast_title = 'Podcast: ' . $source_post->post_title;
        
        $podcast_id = wp_insert_post(array(
            'post_title' => $podcast_title,
            'post_content' => $script_result['raw_script'],
            'post_excerpt' => wp_trim_words($source_post->post_excerpt ?: $source_post->post_content, 55),
            'post_type' => 'ai_podcast',
            'post_status' => 'publish',
        ));
        
        if (is_wp_error($podcast_id)) {
            throw new Exception('Failed to create podcast post');
        }
        
        // Set meta data
        update_post_meta($podcast_id, '_aipg_audio_url', $audio_file['url']);
        update_post_meta($podcast_id, '_aipg_script', $script_result['raw_script']);
        update_post_meta($podcast_id, '_aipg_source_post_id', $source_post->ID);
        update_post_meta($podcast_id, '_aipg_language', $settings['language']);
        update_post_meta($podcast_id, '_aipg_duration', gmdate('H:i:s', $script_result['estimated_duration'] * 60));
        update_post_meta($podcast_id, '_aipg_hosts', $settings['hosts']);
        
        // Copy featured image from source
        $thumbnail_id = get_post_thumbnail_id($source_post->ID);
        if ($thumbnail_id) {
            set_post_thumbnail($podcast_id, $thumbnail_id);
        }
        
        return $podcast_id;
    }
    
    /**
     * Get recent articles for selection
     */
    private function get_recent_articles($count = 10) {
        $posts = get_posts(array(
            'posts_per_page' => $count,
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
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
}
