<?php
/**
 * Enhanced Podcast Generator with Custom Names & AI Summaries
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
        add_action('wp_ajax_aipg_preview_voice', array($this, 'ajax_preview_voice'));
        add_action('aipg_process_generation', array($this, 'process_generation'), 10, 1);
    }
    
    /**
     * AJAX: Preview voice
     */
    public function ajax_preview_voice() {
        check_ajax_referer('aipg_generate', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }
        
        $voice = sanitize_text_field($_POST['voice'] ?? 'alloy');
        $text = sanitize_text_field($_POST['text'] ?? '');
        
        $tts = new AIPG_OpenAI_TTS();
        $preview_url = $tts->generate_voice_preview($voice, $text);
        
        if (is_wp_error($preview_url)) {
            wp_send_json_error($preview_url->get_error_message());
        }
        
        wp_send_json_success(array('url' => $preview_url));
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
        $podcast_style = sanitize_text_field($_POST['podcast_style'] ?? 'conversational');
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');
        
        // Get custom names
        $host_names = array();
        for ($i = 1; $i <= $hosts; $i++) {
            $name = sanitize_text_field($_POST["host_{$i}_name"] ?? '');
            if (empty($name)) {
                $name = "Host {$i}";
            }
            $host_names[] = $name;
        }
        
        $guest_name = sanitize_text_field($_POST['guest_name'] ?? 'Expert');
        if (empty($guest_name)) {
            $guest_name = 'Expert';
        }
        
        // Get voice mapping
        $voice_mapping = array();
        for ($i = 1; $i <= $hosts; $i++) {
            $voice = sanitize_text_field($_POST["voice_host_{$i}"] ?? 'alloy');
            $voice_mapping[$host_names[$i - 1]] = $voice;
        }
        
        if ($include_guest) {
            $voice_mapping[$guest_name] = sanitize_text_field($_POST['voice_guest'] ?? 'echo');
        }
        
        error_log('AIPG: Custom Names - ' . json_encode($host_names));
        error_log('AIPG: Guest Name - ' . $guest_name);
        error_log('AIPG: Voice Mapping - ' . json_encode($voice_mapping));
        
        $settings = array(
            'duration' => $duration,
            'language' => $language,
            'hosts' => $hosts,
            'host_names' => $host_names,
            'guest' => $include_guest,
            'guest_name' => $guest_name,
            'podcast_style' => $podcast_style,
            'tone' => $tone,
            'intro_text' => sanitize_textarea_field($_POST['intro_text'] ?? ''),
            'outro_text' => sanitize_textarea_field($_POST['outro_text'] ?? ''),
            'voice_mapping' => $voice_mapping,
            'include_emotions' => isset($_POST['include_emotions']),
            'model' => get_option('aipg_tts_model', 'tts-1'), // Default to tts-1 (works with all API keys)
            'speed' => 1.0,
        );
        
        $generation_id = AIPG_Database::create_generation(array(
            'post_id' => $post_id,
            'settings' => $settings,
        ));
        
        as_enqueue_async_action('aipg_process_generation', array($generation_id), 'aipg');
        
        wp_send_json_success(array(
            'message' => 'Podcast generation started',
            'generation_id' => $generation_id,
        ));
    }
    
    /**
     * AJAX: Retry generation
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
        
        AIPG_Database::update_generation($generation_id, array(
            'status' => 'pending',
            'error_log' => '',
        ));
        
        as_enqueue_async_action('aipg_process_generation', array($generation_id), 'aipg');
        
        wp_send_json_success(array('message' => 'Generation restarted'));
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
        $articles = $this->get_recent_articles($count);
        
        if (empty($articles)) {
            wp_send_json_error('No articles found');
        }
        
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
     * Process podcast generation
     */
    public function process_generation($generation_id) {
        $generation = AIPG_Database::get_generation($generation_id);
        
        if (!$generation) {
            error_log("AIPG: Generation {$generation_id} not found");
            return;
        }
        
        try {
            AIPG_Database::update_generation($generation_id, array('status' => 'processing'));
            
            $post = get_post($generation->post_id);
            if (!$post) {
                throw new Exception('Source post not found');
            }
            
            $article_content = $this->prepare_article_content($post);
            
            // Step 1: Optional search
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
            
            AIPG_Database::update_generation($generation_id, array(
                'audio_chunks' => $audio_chunks,
            ));
            
            // Step 4: Merge audio
            AIPG_Database::update_generation($generation_id, array('status' => 'merging_audio'));
            
            $final_audio = $tts->merge_audio_chunks($audio_chunks);
            
            if (is_wp_error($final_audio)) {
                throw new Exception('Audio merge failed: ' . $final_audio->get_error_message());
            }
            
            // Step 5: Generate AI summary
            AIPG_Database::update_generation($generation_id, array('status' => 'generating_summary'));
            
            $summary = $openrouter->generate_episode_summary($script_result['raw_script'], $post->post_title);
            
            // Step 6: Generate show notes
            $show_notes = $openrouter->generate_show_notes($script_result['parsed_script']);
            
            // Step 7: Create podcast post
            $podcast_id = $this->create_podcast_post($post, $script_result, $final_audio, $settings, $summary, $show_notes);
            
            AIPG_Database::update_generation($generation_id, array(
                'status' => 'completed',
                'final_audio_url' => $final_audio['url'],
            ));
            
            update_post_meta($podcast_id, '_aipg_generation_id', $generation_id);
            update_post_meta($podcast_id, '_aipg_generation_status', 'completed');
            
            error_log("AIPG: Generation {$generation_id} completed successfully");
            
        } catch (Exception $e) {
            error_log("AIPG: Generation {$generation_id} failed - " . $e->getMessage());
            
            AIPG_Database::update_generation($generation_id, array(
                'status' => 'failed',
                'error_log' => $e->getMessage(),
            ));
        }
    }
    
    /**
     * Prepare article content
     */
    private function prepare_article_content($post) {
        $content = $post->post_title . "\n\n" . $post->post_content;
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);
        $content = wp_trim_words($content, 5000);
        return $content;
    }
    
    /**
     * Create podcast post with AI summary
     */
    private function create_podcast_post($source_post, $script_result, $audio_file, $settings, $summary, $show_notes) {
        $podcast_title = 'Podcast: ' . $source_post->post_title;
        
        // Use AI-generated summary instead of raw script
        $post_content = $summary . "\n\n" . $show_notes;
        
        $podcast_id = wp_insert_post(array(
            'post_title' => $podcast_title,
            'post_content' => $post_content,
            'post_excerpt' => $summary,
            'post_type' => 'ai_podcast',
            'post_status' => 'publish',
        ));
        
        if (is_wp_error($podcast_id)) {
            throw new Exception('Failed to create podcast post');
        }
        
        // Meta data
        update_post_meta($podcast_id, '_aipg_audio_url', $audio_file['url']);
        update_post_meta($podcast_id, '_aipg_script', $script_result['raw_script']);
        update_post_meta($podcast_id, '_aipg_source_post_id', $source_post->ID);
        update_post_meta($podcast_id, '_aipg_language', $settings['language']);
        update_post_meta($podcast_id, '_aipg_duration', gmdate('H:i:s', $script_result['estimated_duration'] * 60));
        update_post_meta($podcast_id, '_aipg_hosts', implode(', ', $settings['host_names']));
        update_post_meta($podcast_id, '_aipg_style', $settings['podcast_style']);
        update_post_meta($podcast_id, '_aipg_has_emotions', $script_result['has_emotions']);
        
        if ($settings['guest']) {
            update_post_meta($podcast_id, '_aipg_guest', $settings['guest_name']);
        }
        
        // Copy featured image
        $thumbnail_id = get_post_thumbnail_id($source_post->ID);
        if ($thumbnail_id) {
            set_post_thumbnail($podcast_id, $thumbnail_id);
        }
        
        return $podcast_id;
    }
    
    /**
     * Get recent articles
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