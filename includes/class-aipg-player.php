<?php
/**
 * Horizon Glass Podcast Player with Light/Dark Mode
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
        
        wp_enqueue_style('aipg-player-horizon', 
            AIPG_PLUGIN_URL . 'assets/css/player-horizon.css', 
            array(), 
            AIPG_VERSION
        );
        
        wp_enqueue_script('aipg-player-horizon', 
            AIPG_PLUGIN_URL . 'assets/js/player-horizon.js', 
            array('jquery'), 
            AIPG_VERSION, 
            true
        );
        
        // Pass player theme setting
        wp_localize_script('aipg-player-horizon', 'aipgPlayer', array(
            'theme' => get_option('aipg_player_theme', 'dark'),
        ));
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
            'theme' => get_option('aipg_player_theme', 'dark'),
        ), $atts);
        
        $post_id = intval($atts['id']);
        $audio_url = get_post_meta($post_id, '_aipg_audio_url', true);
        
        if (!$audio_url) {
            return '<p>No audio available</p>';
        }
        
        $title = get_the_title($post_id);
        $duration = get_post_meta($post_id, '_aipg_duration', true);
        $episode_num = get_post_meta($post_id, '_aipg_episode_number', true);
        $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
        
        return $this->render_player($audio_url, $title, $duration, $episode_num, $thumbnail, $atts['theme']);
    }
    
    /**
     * Auto-add player to podcast posts
     */
    public function auto_add_player($content) {
        if (!is_singular('ai_podcast') || !in_the_loop() || !is_main_query()) {
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
            get_post_meta(get_the_ID(), '_aipg_episode_number', true),
            get_the_post_thumbnail_url(get_the_ID(), 'medium'),
            get_option('aipg_player_theme', 'dark')
        );
        
        return $player . $content;
    }
    
    /**
     * Render Horizon Glass player HTML
     */
    private function render_player($audio_url, $title, $duration, $episode_num = '', $thumbnail = '', $theme = 'dark') {
        if (empty($thumbnail)) {
            $thumbnail = AIPG_PLUGIN_URL . 'assets/img/default-podcast.png';
        }
        
        $episode_badge = $episode_num ? 'EP ' . $episode_num : 'PODCAST';
        $duration_display = $duration ?: '--:--';
        
        ob_start();
        ?>
        <div class="aipg-player-wrapper" data-theme="<?php echo esc_attr($theme); ?>" data-audio-url="<?php echo esc_url($audio_url); ?>">
            <div class="podcast-player" id="aipgPlayer-<?php echo uniqid(); ?>">
                <audio class="aipg-audio-element" preload="metadata">
                    <source src="<?php echo esc_url($audio_url); ?>" type="audio/mpeg">
                </audio>
                
                <div class="art-wrapper">
                    <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($title); ?>">
                    <div class="playing-indicator">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" color="white">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"></path>
                        </svg>
                    </div>
                </div>

                <div class="track-details">
                    <div class="track-title"><?php echo esc_html($title); ?></div>
                    <div class="track-meta">
                        <span class="badge"><?php echo esc_html($episode_badge); ?></span>
                        <span class="aipg-duration-display"><?php echo esc_html($duration_display); ?></span>
                    </div>

                    <div class="progress-wrapper">
                        <span class="time-text aipg-current-time">0:00</span>
                        <div class="progress-bar-bg aipg-progress-bar">
                            <div class="progress-bar-fill aipg-progress-fill" style="width: 0%"></div>
                        </div>
                        <span class="time-text aipg-total-time">0:00</span>
                    </div>
                </div>

                <div class="controls-right">
                    <button class="btn-icon-only aipg-skip-back" title="Skip back 15s" aria-label="Skip back 15 seconds">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 17l-5-5 5-5M18 17l-5-5 5-5"/>
                        </svg>
                    </button>

                    <button class="btn-main-play aipg-play-btn" aria-label="Play/Pause">
                        <svg class="aipg-play-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        <svg class="aipg-pause-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
                            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                        </svg>
                    </button>

                    <button class="btn-icon-only aipg-skip-forward" title="Skip forward 30s" aria-label="Skip forward 30 seconds">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 17l5-5-5-5M6 17l5-5-5-5"/>
                        </svg>
                    </button>

                    <button class="btn-icon-only aipg-speed-btn" title="Playback speed" aria-label="Playback speed">
                        <span class="aipg-speed-text">1x</span>
                    </button>

                    <div class="volume-control">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                            <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
                        </svg>
                        <input type="range" class="volume-slider aipg-volume-slider" min="0" max="100" value="80" aria-label="Volume">
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}