<?php
/**
 * Custom Post Type Handler
 */

if (!defined('ABSPATH')) exit;

class AIPG_CPT {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_post_type_hook'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_ai_podcast', array($this, 'save_meta_boxes'));
        add_filter('manage_ai_podcast_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_ai_podcast_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
    }
    
    /**
     * Hook wrapper for register_post_type
     */
    public function register_post_type_hook() {
        self::register_post_type();
    }
    
    /**
     * Register podcast post type
     */
    public static function register_post_type() {
        $labels = array(
            'name' => __('Podcasts', 'ai-podcast-gen'),
            'singular_name' => __('Podcast', 'ai-podcast-gen'),
            'menu_name' => __('AI Podcasts', 'ai-podcast-gen'),
            'add_new' => __('Add New', 'ai-podcast-gen'),
            'add_new_item' => __('Add New Podcast', 'ai-podcast-gen'),
            'edit_item' => __('Edit Podcast', 'ai-podcast-gen'),
            'new_item' => __('New Podcast', 'ai-podcast-gen'),
            'view_item' => __('View Podcast', 'ai-podcast-gen'),
            'search_items' => __('Search Podcasts', 'ai-podcast-gen'),
            'not_found' => __('No podcasts found', 'ai-podcast-gen'),
            'not_found_in_trash' => __('No podcasts found in trash', 'ai-podcast-gen'),
        );
        
        $args = array(
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-microphone',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'elementor'),
            'rewrite' => array('slug' => 'podcast'),
            'show_in_menu' => true,
            'capability_type' => 'post',
        );
        
        register_post_type('ai_podcast', $args);
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'aipg_podcast_audio',
            __('Podcast Audio', 'ai-podcast-gen'),
            array($this, 'render_audio_meta_box'),
            'ai_podcast',
            'normal',
            'high'
        );
        
        add_meta_box(
            'aipg_podcast_details',
            __('Podcast Details', 'ai-podcast-gen'),
            array($this, 'render_details_meta_box'),
            'ai_podcast',
            'side',
            'default'
        );
    }
    
    /**
     * Render audio meta box
     */
    public function render_audio_meta_box($post) {
        wp_nonce_field('aipg_save_meta', 'aipg_meta_nonce');
        
        $audio_url = get_post_meta($post->ID, '_aipg_audio_url', true);
        $duration = get_post_meta($post->ID, '_aipg_duration', true);
        $script = get_post_meta($post->ID, '_aipg_script', true);
        ?>
        <div class="aipg-audio-meta">
            <p>
                <label><?php _e('Audio File URL:', 'ai-podcast-gen'); ?></label><br>
                <input type="url" name="aipg_audio_url" class="widefat" value="<?php echo esc_attr($audio_url); ?>">
            </p>
            
            <?php if ($audio_url): ?>
            <div class="aipg-audio-player">
                <audio controls style="width: 100%;">
                    <source src="<?php echo esc_url($audio_url); ?>" type="audio/mpeg">
                </audio>
            </div>
            <?php endif; ?>
            
            <p>
                <label><?php _e('Duration:', 'ai-podcast-gen'); ?></label><br>
                <input type="text" name="aipg_duration" value="<?php echo esc_attr($duration); ?>" placeholder="00:00:00">
            </p>
            
            <p>
                <label><?php _e('Podcast Script:', 'ai-podcast-gen'); ?></label><br>
                <textarea name="aipg_script" class="widefat" rows="10"><?php echo esc_textarea($script); ?></textarea>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render details meta box
     */
    public function render_details_meta_box($post) {
        $source_post = get_post_meta($post->ID, '_aipg_source_post_id', true);
        $language = get_post_meta($post->ID, '_aipg_language', true);
        $hosts = get_post_meta($post->ID, '_aipg_hosts', true);
        $generation_status = get_post_meta($post->ID, '_aipg_generation_status', true);
        ?>
        <div class="aipg-details-meta">
            <p>
                <strong><?php _e('Source Article:', 'ai-podcast-gen'); ?></strong><br>
                <?php if ($source_post): ?>
                    <a href="<?php echo get_edit_post_link($source_post); ?>">
                        <?php echo get_the_title($source_post); ?>
                    </a>
                <?php else: ?>
                    <?php _e('N/A', 'ai-podcast-gen'); ?>
                <?php endif; ?>
            </p>
            
            <p>
                <strong><?php _e('Language:', 'ai-podcast-gen'); ?></strong><br>
                <?php echo esc_html($language ?: 'English'); ?>
            </p>
            
            <p>
                <strong><?php _e('Hosts:', 'ai-podcast-gen'); ?></strong><br>
                <?php echo esc_html($hosts ?: 'Default'); ?>
            </p>
            
            <?php if ($generation_status): ?>
            <p>
                <strong><?php _e('Status:', 'ai-podcast-gen'); ?></strong><br>
                <span class="aipg-status <?php echo esc_attr($generation_status); ?>">
                    <?php echo esc_html(ucfirst($generation_status)); ?>
                </span>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['aipg_meta_nonce']) || !wp_verify_nonce($_POST['aipg_meta_nonce'], 'aipg_save_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save audio URL
        if (isset($_POST['aipg_audio_url'])) {
            update_post_meta($post_id, '_aipg_audio_url', sanitize_text_field($_POST['aipg_audio_url']));
        }
        
        // Save duration
        if (isset($_POST['aipg_duration'])) {
            update_post_meta($post_id, '_aipg_duration', sanitize_text_field($_POST['aipg_duration']));
        }
        
        // Save script
        if (isset($_POST['aipg_script'])) {
            update_post_meta($post_id, '_aipg_script', wp_kses_post($_POST['aipg_script']));
        }
    }
    
    /**
     * Set custom columns
     */
    public function set_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['audio'] = __('Audio', 'ai-podcast-gen');
        $new_columns['duration'] = __('Duration', 'ai-podcast-gen');
        $new_columns['status'] = __('Status', 'ai-podcast-gen');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Custom column content
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'audio':
                $audio_url = get_post_meta($post_id, '_aipg_audio_url', true);
                if ($audio_url) {
                    echo '<audio controls style="width: 200px; height: 30px;"><source src="' . esc_url($audio_url) . '"></audio>';
                } else {
                    echo '—';
                }
                break;
                
            case 'duration':
                $duration = get_post_meta($post_id, '_aipg_duration', true);
                echo $duration ? esc_html($duration) : '—';
                break;
                
            case 'status':
                $status = get_post_meta($post_id, '_aipg_generation_status', true);
                if ($status) {
                    echo '<span class="aipg-status-badge ' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span>';
                } else {
                    echo '—';
                }
                break;
        }
    }
}
