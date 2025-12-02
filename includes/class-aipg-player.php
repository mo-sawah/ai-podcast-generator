<?php
/**
 * Modern Audio Player
 */

if (!defined('ABSPATH')) exit;

class AIPG_Player {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('ai_podcast_player', array($this, 'player_shortcode'));
        add_filter('the_content', array($this, 'auto_add_player'));
    }
    
    /**
     * Enqueue player assets
     */
    public function enqueue_assets() {
        if (!is_singular('ai_podcast') && !$this->has_player_shortcode()) {
            return;
        }
        
        wp_enqueue_style('aipg-player', AIPG_PLUGIN_URL . 'assets/css/player.css', array(), AIPG_VERSION);
        wp_enqueue_script('aipg-player', AIPG_PLUGIN_URL . 'assets/js/player.js', array('jquery'), AIPG_VERSION, true);
    }
    
    /**
     * Check if current post has player shortcode
     */
    private function has_player_shortcode() {
        global $post;
        return $post && has_shortcode($post->post_content, 'ai_podcast_player');
    }
    
    /**
     * Player shortcode
     */
    public function player_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => get_the_ID(),
            'theme' => 'modern', // modern, minimal, classic
        ), $atts);
        
        $post_id = intval($atts['id']);
        $audio_url = get_post_meta($post_id, '_aipg_audio_url', true);
        
        if (!$audio_url) {
            return '<p>No audio available</p>';
        }
        
        $title = get_the_title($post_id);
        $duration = get_post_meta($post_id, '_aipg_duration', true);
        
        return $this->render_player($audio_url, $title, $duration, $atts['theme']);
    }
    
    /**
     * Auto-add player to podcast posts
     */
    public function auto_add_player($content) {
        if (!is_singular('ai_podcast')) {
            return $content;
        }
        
        $audio_url = get_post_meta(get_the_ID(), '_aipg_audio_url', true);
        
        if (!$audio_url) {
            return $content;
        }
        
        $player = $this->render_player(
            $audio_url,
            get_the_title(),
            get_post_meta(get_the_ID(), '_aipg_duration', true),
            'modern'
        );
        
        return $player . $content;
    }
    
    /**
     * Render player HTML
     */
    private function render_player($audio_url, $title, $duration, $theme = 'modern') {
        ob_start();
        ?>
        <div class="aipg-player aipg-player-<?php echo esc_attr($theme); ?>" data-audio="<?php echo esc_url($audio_url); ?>">
            <div class="aipg-player-inner">
                <div class="aipg-player-artwork">
                    <div class="aipg-play-button">
                        <svg class="aipg-icon-play" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        <svg class="aipg-icon-pause" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
                            <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                        </svg>
                    </div>
                    <?php if (has_post_thumbnail()): ?>
                        <?php the_post_thumbnail('medium', array('class' => 'aipg-thumbnail')); ?>
                    <?php else: ?>
                        <div class="aipg-placeholder">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="aipg-player-info">
                    <h3 class="aipg-player-title"><?php echo esc_html($title); ?></h3>
                    
                    <div class="aipg-player-progress">
                        <div class="aipg-progress-bar">
                            <div class="aipg-progress-fill"></div>
                            <div class="aipg-progress-handle"></div>
                        </div>
                        <div class="aipg-player-time">
                            <span class="aipg-time-current">0:00</span>
                            <span class="aipg-time-duration"><?php echo esc_html($duration ?: '--:--'); ?></span>
                        </div>
                    </div>
                    
                    <div class="aipg-player-controls">
                        <button class="aipg-btn aipg-btn-skip-back" title="Skip back 15s">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M11 18V6l-8.5 6 8.5 6zm.5-6l8.5 6V6l-8.5 6z"/>
                            </svg>
                            <span>15</span>
                        </button>
                        
                        <button class="aipg-btn aipg-btn-speed" title="Playback speed">
                            <span class="aipg-speed-text">1x</span>
                        </button>
                        
                        <button class="aipg-btn aipg-btn-skip-forward" title="Skip forward 30s">
                            <span>30</span>
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M4 18l8.5-6L4 6v12zm9-12v12l8.5-6L13 6z"/>
                            </svg>
                        </button>
                        
                        <button class="aipg-btn aipg-btn-volume" title="Volume">
                            <svg class="aipg-icon-volume-up" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/>
                            </svg>
                        </button>
                        
                        <div class="aipg-volume-slider" style="display:none;">
                            <input type="range" min="0" max="100" value="100" class="aipg-volume-input">
                        </div>
                    </div>
                </div>
            </div>
            
            <audio class="aipg-audio-element" preload="metadata">
                <source src="<?php echo esc_url($audio_url); ?>" type="audio/mpeg">
            </audio>
        </div>
        <?php
        return ob_get_clean();
    }
}
