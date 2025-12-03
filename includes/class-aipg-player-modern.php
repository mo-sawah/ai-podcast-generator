<?php
/**
 * MODERN HORIZON GLASS PLAYER
 * Complete replacement for class-aipg-player.php
 * Features: Light/Dark mode, Glassmorphism, Responsive, Full controls
 */

class AIPG_Player {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('ai_podcast_player', array($this, 'render_player'));
        add_filter('the_content', array($this, 'auto_embed_player'));
    }
    
    /**
     * Enqueue player assets
     */
    public function enqueue_assets() {
        if (is_singular('ai_podcast')) {
            wp_enqueue_style('aipg-player', plugins_url('assets/css/player-modern.css', dirname(__FILE__)), array(), '2.0');
            wp_enqueue_script('aipg-player', plugins_url('assets/js/player-modern.js', dirname(__FILE__)), array('jquery'), '2.0', true);
        }
    }
    
    /**
     * Auto-embed player in podcast posts
     */
    public function auto_embed_player($content) {
        if (is_singular('ai_podcast') && in_the_loop() && is_main_query()) {
            $audio_url = get_post_meta(get_the_ID(), '_aipg_audio_url', true);
            if ($audio_url) {
                $player = $this->render_player(array('url' => $audio_url));
                $content = $player . $content;
            }
        }
        return $content;
    }
    
    /**
     * Render modern player HTML
     */
    public function render_player($atts = array()) {
        $atts = shortcode_atts(array(
            'url' => '',
            'title' => get_the_title(),
            'duration' => '',
            'thumbnail' => get_the_post_thumbnail_url(get_the_ID(), 'medium'),
        ), $atts);
        
        if (empty($atts['url'])) {
            $atts['url'] = get_post_meta(get_the_ID(), '_aipg_audio_url', true);
        }
        
        if (empty($atts['url'])) {
            return '<p>No audio available.</p>';
        }
        
        if (empty($atts['thumbnail'])) {
            $atts['thumbnail'] = plugins_url('assets/img/default-podcast.png', dirname(__FILE__));
        }
        
        ob_start();
        ?>
        <div class="aipg-player-wrapper" data-theme="light">
            <!-- Theme Toggle -->
            <button class="aipg-theme-toggle" onclick="aipgToggleTheme()" aria-label="Toggle theme">
                <svg class="theme-icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                    <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
                <svg class="theme-icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>
            
            <div class="aipg-player" id="aipgPlayer">
                <audio id="aipgAudio" preload="metadata">
                    <source src="<?php echo esc_url($atts['url']); ?>" type="audio/mpeg">
                </audio>
                
                <!-- Album Art -->
                <div class="aipg-art-wrapper">
                    <img src="<?php echo esc_url($atts['thumbnail']); ?>" alt="Podcast Cover">
                    <div class="aipg-playing-indicator">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" color="white">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"></path>
                        </svg>
                    </div>
                </div>
                
                <!-- Track Details & Controls -->
                <div class="aipg-track-details">
                    <div class="aipg-track-title"><?php echo esc_html($atts['title']); ?></div>
                    <div class="aipg-track-meta">
                        <span class="aipg-badge">PODCAST</span>
                        <span id="aipgDuration">--:--</span>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="aipg-progress-wrapper">
                        <span class="aipg-time-text" id="aipgCurrentTime">0:00</span>
                        <div class="aipg-progress-bar-bg" id="aipgProgressBar">
                            <div class="aipg-progress-bar-fill" id="aipgProgressFill"></div>
                        </div>
                        <span class="aipg-time-text" id="aipgTotalTime">0:00</span>
                    </div>
                </div>
                
                <!-- Playback Controls -->
                <div class="aipg-controls-right">
                    <button class="aipg-btn-icon-only" id="aipgSkipBack" title="Skip back 15s">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 17l-5-5 5-5M18 17l-5-5 5-5"/>
                        </svg>
                    </button>
                    
                    <button class="aipg-btn-main-play" id="aipgPlayButton">
                        <svg id="aipgPlayIcon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        <svg id="aipgPauseIcon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
                            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                        </svg>
                    </button>
                    
                    <button class="aipg-btn-icon-only" id="aipgSkipForward" title="Skip forward 30s">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 17l5-5-5-5M6 17l5-5-5-5"/>
                        </svg>
                    </button>
                    
                    <!-- Speed Control -->
                    <button class="aipg-btn-icon-only" id="aipgSpeedButton" title="Playback speed">
                        <span id="aipgSpeedLabel">1x</span>
                    </button>
                    
                    <!-- Volume Control -->
                    <div class="aipg-volume-control">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                            <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
                        </svg>
                        <input type="range" class="aipg-volume-slider" id="aipgVolumeSlider" min="0" max="100" value="80">
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize
AIPG_Player::get_instance();
