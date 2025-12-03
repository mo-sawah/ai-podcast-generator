<?php
/**
 * Professional Admin Interface - Enhanced with Custom Names, Voice Preview & Advanced Settings
 */

if (!defined('ABSPATH')) exit;

class AIPG_Admin {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_aipg_test_tts_access', array($this, 'ajax_test_tts_access'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Podcast Generator', 'ai-podcast-gen'),
            __('AI Podcasts', 'ai-podcast-gen'),
            'edit_posts',
            'aipg-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-microphone',
            30
        );
        
        add_submenu_page(
            'aipg-dashboard',
            __('Dashboard', 'ai-podcast-gen'),
            __('Dashboard', 'ai-podcast-gen'),
            'edit_posts',
            'aipg-dashboard',
            array($this, 'render_dashboard_page')
        );
        
        add_submenu_page(
            'aipg-dashboard',
            __('Generate Podcast', 'ai-podcast-gen'),
            __('Generate', 'ai-podcast-gen'),
            'edit_posts',
            'aipg-generate',
            array($this, 'render_generate_page')
        );
        
        add_submenu_page(
            'aipg-dashboard',
            __('Settings', 'ai-podcast-gen'),
            __('Settings', 'ai-podcast-gen'),
            'manage_options',
            'aipg-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'aipg-dashboard',
            __('History', 'ai-podcast-gen'),
            __('History', 'ai-podcast-gen'),
            'edit_posts',
            'aipg-history',
            array($this, 'render_history_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'aipg-') === false) {
            return;
        }
        
        wp_enqueue_style('aipg-admin-pro', 
            AIPG_PLUGIN_URL . 'assets/css/admin-pro.css', 
            array(), 
            AIPG_VERSION
        );
        
        wp_enqueue_script('aipg-admin-pro', 
            AIPG_PLUGIN_URL . 'assets/js/admin-pro.js', 
            array('jquery'), 
            AIPG_VERSION, 
            true
        );
        
        wp_localize_script('aipg-admin-pro', 'aipgAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aipg_generate'),
            'dashboardUrl' => admin_url('admin.php?page=aipg-dashboard'),
            'voices' => $this->get_available_voices(),
        ));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // API Keys
        register_setting('aipg_settings', 'aipg_openrouter_key');
        register_setting('aipg_settings', 'aipg_openai_key');
        register_setting('aipg_settings', 'aipg_elevenlabs_key');
        register_setting('aipg_settings', 'aipg_tavily_key');
        
        // TTS Provider Settings
        register_setting('aipg_settings', 'aipg_tts_provider');
        register_setting('aipg_settings', 'aipg_tts_model');
        register_setting('aipg_settings', 'aipg_elevenlabs_model');
        
        // Player Settings
        register_setting('aipg_settings', 'aipg_player_theme');
        
        // Generation Defaults
        register_setting('aipg_settings', 'aipg_default_duration');
        register_setting('aipg_settings', 'aipg_default_language');
        register_setting('aipg_settings', 'aipg_default_hosts');
        register_setting('aipg_settings', 'aipg_default_style');
        register_setting('aipg_settings', 'aipg_default_tone');
        
        // Feature Toggles
        register_setting('aipg_settings', 'aipg_enable_search');
        register_setting('aipg_settings', 'aipg_enable_emotions');
        
        // Auto-generation
        register_setting('aipg_settings', 'aipg_auto_generate_enabled');
        register_setting('aipg_settings', 'aipg_auto_generate_frequency');
    }
    
    /**
     * Get available voices
     */
    private function get_available_voices() {
        $provider = get_option('aipg_tts_provider', 'openai');
        
        if ($provider === 'elevenlabs') {
            // ElevenLabs voices
            return array(
                '21m00Tcm4TlvDq8ikWAM' => array(
                    'name' => 'Rachel',
                    'description' => 'Female, Professional',
                    'gender' => 'female',
                ),
                'AZnzlk1XvdvUeBnXmlld' => array(
                    'name' => 'Domi',
                    'description' => 'Female, Energetic',
                    'gender' => 'female',
                ),
                'EXAVITQu4vr4xnSDxMaL' => array(
                    'name' => 'Bella',
                    'description' => 'Female, Soft',
                    'gender' => 'female',
                ),
                'ErXwobaYiN019PkySvjV' => array(
                    'name' => 'Antoni',
                    'description' => 'Male, Balanced',
                    'gender' => 'male',
                ),
                'VR6AewLTigWG4xSOukaG' => array(
                    'name' => 'Arnold',
                    'description' => 'Male, Authoritative',
                    'gender' => 'male',
                ),
                'pNInz6obpgDQGcFmaJgB' => array(
                    'name' => 'Adam',
                    'description' => 'Male, Professional',
                    'gender' => 'male',
                ),
                'yoZ06aMxZJJ28mfd3POQ' => array(
                    'name' => 'Sam',
                    'description' => 'Male, Conversational',
                    'gender' => 'male',
                ),
                'MF3mGyEYCl7XYWbV9V6O' => array(
                    'name' => 'Elli',
                    'description' => 'Female, Young',
                    'gender' => 'female',
                ),
                'TxGEqnHWrfWFTfGW9XjX' => array(
                    'name' => 'Josh',
                    'description' => 'Male, Expressive',
                    'gender' => 'male',
                ),
                'IKne3meq5aSn9XLyUdCD' => array(
                    'name' => 'Charlie',
                    'description' => 'Male, Casual',
                    'gender' => 'male',
                ),
                'onwK4e9ZLuTAKqWW03F9' => array(
                    'name' => 'Daniel',
                    'description' => 'Male, Deep',
                    'gender' => 'male',
                ),
                'JBFqnCBsd6RMkjVDRZzb' => array(
                    'name' => 'George',
                    'description' => 'Male, British',
                    'gender' => 'male',
                ),
            );
        }
        
        // OpenAI voices (default)
        return array(
            'alloy' => array(
                'name' => 'Alloy',
                'description' => 'Neutral, Balanced',
                'gender' => 'neutral',
            ),
            'echo' => array(
                'name' => 'Echo',
                'description' => 'Male, Professional',
                'gender' => 'male',
            ),
            'fable' => array(
                'name' => 'Fable',
                'description' => 'Male, Expressive',
                'gender' => 'male',
            ),
            'onyx' => array(
                'name' => 'Onyx',
                'description' => 'Male, Authoritative',
                'gender' => 'male',
            ),
            'nova' => array(
                'name' => 'Nova',
                'description' => 'Female, Energetic',
                'gender' => 'female',
            ),
            'shimmer' => array(
                'name' => 'Shimmer',
                'description' => 'Female, Soft',
                'gender' => 'female',
            ),
        );
    }
    
    /**
     * Render Dashboard Page
     */
    public function render_dashboard_page() {
        $total_podcasts = wp_count_posts('ai_podcast')->publish;
        $recent_generations = AIPG_Database::get_recent_generations(10);
        $processing_count = count(array_filter($recent_generations, function($g) {
            return in_array($g->status, array('pending', 'processing', 'generating_script', 'generating_audio'));
        }));
        
        ?>
        <div class="wrap aipg-wrap">
            <h1 class="aipg-page-title">
                <span class="dashicons dashicons-microphone"></span>
                <?php _e('AI Podcast Generator', 'ai-podcast-gen'); ?>
            </h1>
            
            <!-- Stats Cards -->
            <div class="aipg-stats-grid">
                <div class="aipg-stat-card">
                    <div class="aipg-stat-icon aipg-stat-primary">
                        <span class="dashicons dashicons-media-audio"></span>
                    </div>
                    <div class="aipg-stat-content">
                        <div class="aipg-stat-value"><?php echo esc_html($total_podcasts); ?></div>
                        <div class="aipg-stat-label"><?php _e('Total Podcasts', 'ai-podcast-gen'); ?></div>
                    </div>
                </div>
                
                <div class="aipg-stat-card">
                    <div class="aipg-stat-icon aipg-stat-warning">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <div class="aipg-stat-content">
                        <div class="aipg-stat-value"><?php echo esc_html($processing_count); ?></div>
                        <div class="aipg-stat-label"><?php _e('Processing', 'ai-podcast-gen'); ?></div>
                    </div>
                </div>
                
                <div class="aipg-stat-card">
                    <div class="aipg-stat-icon aipg-stat-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="aipg-stat-content">
                        <div class="aipg-stat-value"><?php echo count($recent_generations); ?></div>
                        <div class="aipg-stat-label"><?php _e('Recent Generations', 'ai-podcast-gen'); ?></div>
                    </div>
                </div>
                
                <div class="aipg-stat-card">
                    <div class="aipg-stat-icon aipg-stat-info">
                        <span class="dashicons dashicons-admin-settings"></span>
                    </div>
                    <div class="aipg-stat-content">
                        <div class="aipg-stat-value"><?php echo $this->check_api_keys() ? '✓' : '✗'; ?></div>
                        <div class="aipg-stat-label"><?php _e('API Status', 'ai-podcast-gen'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="aipg-card">
                <h2><?php _e('Quick Actions', 'ai-podcast-gen'); ?></h2>
                <div class="aipg-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=aipg-generate'); ?>" class="button button-primary button-hero">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Generate New Podcast', 'ai-podcast-gen'); ?>
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=ai_podcast'); ?>" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-playlist-audio"></span>
                        <?php _e('View All Podcasts', 'ai-podcast-gen'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=aipg-settings'); ?>" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('Settings', 'ai-podcast-gen'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Recent Generations -->
            <div class="aipg-card">
                <h2><?php _e('Recent Generations', 'ai-podcast-gen'); ?></h2>
                
                <?php if (empty($recent_generations)): ?>
                    <p class="aipg-no-results"><?php _e('No generations yet. Create your first podcast!', 'ai-podcast-gen'); ?></p>
                <?php else: ?>
                    <div class="aipg-table-responsive">
                        <table class="aipg-table">
                            <thead>
                                <tr>
                                    <th><?php _e('ID', 'ai-podcast-gen'); ?></th>
                                    <th><?php _e('Article', 'ai-podcast-gen'); ?></th>
                                    <th><?php _e('Status', 'ai-podcast-gen'); ?></th>
                                    <th><?php _e('Created', 'ai-podcast-gen'); ?></th>
                                    <th><?php _e('Actions', 'ai-podcast-gen'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_generations as $gen): ?>
                                    <tr>
                                        <td><strong>#<?php echo esc_html($gen->id); ?></strong></td>
                                        <td>
                                            <?php if ($gen->post_id): ?>
                                                <a href="<?php echo get_edit_post_link($gen->post_id); ?>" target="_blank">
                                                    <?php echo esc_html(get_the_title($gen->post_id)); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="aipg-text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $this->render_status_badge($gen->status); ?></td>
                                        <td><?php echo esc_html(human_time_diff(strtotime($gen->created_at), current_time('timestamp')) . ' ago'); ?></td>
                                        <td>
                                            <?php if ($gen->status === 'failed'): ?>
                                                <button class="button button-small aipg-retry-btn" data-generation-id="<?php echo esc_attr($gen->id); ?>">
                                                    <span class="dashicons dashicons-update"></span>
                                                    <?php _e('Retry', 'ai-podcast-gen'); ?>
                                                </button>
                                            <?php elseif ($gen->status === 'completed' && $gen->final_audio_url): ?>
                                                <a href="<?php echo esc_url($gen->final_audio_url); ?>" class="button button-small" target="_blank">
                                                    <span class="dashicons dashicons-download"></span>
                                                    <?php _e('Download', 'ai-podcast-gen'); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="aipg-text-muted"><?php _e('Processing...', 'ai-podcast-gen'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render status badge
     */
    private function render_status_badge($status) {
        $classes = array('aipg-badge');
        $labels = array(
            'pending' => __('Pending', 'ai-podcast-gen'),
            'processing' => __('Processing', 'ai-podcast-gen'),
            'searching' => __('Searching', 'ai-podcast-gen'),
            'generating_script' => __('Generating Script', 'ai-podcast-gen'),
            'generating_audio' => __('Generating Audio', 'ai-podcast-gen'),
            'merging_audio' => __('Merging Audio', 'ai-podcast-gen'),
            'generating_summary' => __('Creating Summary', 'ai-podcast-gen'),
            'completed' => __('Completed', 'ai-podcast-gen'),
            'failed' => __('Failed', 'ai-podcast-gen'),
        );
        
        $classes[] = 'aipg-badge-' . $status;
        $label = $labels[$status] ?? ucfirst($status);
        
        return '<span class="' . implode(' ', $classes) . '">' . esc_html($label) . '</span>';
    }
    
    /**
     * Check if API keys are configured
     */
    private function check_api_keys() {
        return !empty(get_option('aipg_openrouter_key')) && !empty(get_option('aipg_openai_key'));
    }
    /**
     * Render Generate Page
     */
    public function render_generate_page() {
        $voices = $this->get_available_voices();
        $podcast_styles = array(
            'conversational' => __('Conversational', 'ai-podcast-gen'),
            'interview' => __('Interview', 'ai-podcast-gen'),
            'debate' => __('Debate', 'ai-podcast-gen'),
            'educational' => __('Educational', 'ai-podcast-gen'),
            'storytelling' => __('Storytelling', 'ai-podcast-gen'),
        );
        
        $tones = array(
            'professional' => __('Professional', 'ai-podcast-gen'),
            'casual' => __('Casual', 'ai-podcast-gen'),
            'enthusiastic' => __('Enthusiastic', 'ai-podcast-gen'),
            'academic' => __('Academic', 'ai-podcast-gen'),
            'humorous' => __('Humorous', 'ai-podcast-gen'),
        );
        
        ?>
        <div class="wrap aipg-wrap">
            <h1 class="aipg-page-title">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Generate New Podcast', 'ai-podcast-gen'); ?>
            </h1>
            
            <div class="aipg-generate-container">
                <!-- Main Form -->
                <div class="aipg-generate-main">
                    <form id="aipg-generate-form" class="aipg-card">
                        <h2><?php _e('Podcast Configuration', 'ai-podcast-gen'); ?></h2>
                        
                        <!-- Article Selection -->
                        <div class="aipg-form-section">
                            <h3><?php _e('Source Article', 'ai-podcast-gen'); ?></h3>
                            
                            <div class="aipg-form-row">
                                <label><?php _e('Select Article', 'ai-podcast-gen'); ?></label>
                                <select id="aipg-post-select" name="post_id" class="aipg-select" required>
                                    <option value=""><?php _e('— Select an article —', 'ai-podcast-gen'); ?></option>
                                    <?php
                                    $posts = get_posts(array('posts_per_page' => 50, 'post_type' => 'post'));
                                    foreach ($posts as $post) {
                                        echo '<option value="' . esc_attr($post->ID) . '">' . esc_html($post->post_title) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="aipg-form-help"><?php _e('Or use AI to select the best article', 'ai-podcast-gen'); ?></p>
                                <button type="button" class="button" id="aipg-auto-select-btn">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <?php _e('AI Auto-Select', 'ai-podcast-gen'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Podcast Settings -->
                        <div class="aipg-form-section">
                            <h3><?php _e('Podcast Settings', 'ai-podcast-gen'); ?></h3>
                            
                            <div class="aipg-form-grid">
                                <div class="aipg-form-row">
                                    <label><?php _e('Duration (minutes)', 'ai-podcast-gen'); ?></label>
                                    <input type="number" name="duration" id="aipg-duration" value="10" min="3" max="60" class="aipg-input" required>
                                </div>
                                
                                <div class="aipg-form-row">
                                    <label><?php _e('Language', 'ai-podcast-gen'); ?></label>
                                    <select name="language" id="aipg-language" class="aipg-select">
                                        <option value="English">English</option>
                                        <option value="Spanish">Español</option>
                                        <option value="French">Français</option>
                                        <option value="German">Deutsch</option>
                                        <option value="Italian">Italiano</option>
                                        <option value="Portuguese">Português</option>
                                    </select>
                                </div>
                                
                                <div class="aipg-form-row">
                                    <label><?php _e('Podcast Style', 'ai-podcast-gen'); ?></label>
                                    <select name="podcast_style" id="aipg-style" class="aipg-select">
                                        <?php foreach ($podcast_styles as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="aipg-form-row">
                                    <label><?php _e('Tone', 'ai-podcast-gen'); ?></label>
                                    <select name="tone" id="aipg-tone" class="aipg-select">
                                        <?php foreach ($tones as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="aipg-form-row">
                                <label class="aipg-checkbox-label">
                                    <input type="checkbox" name="include_emotions" value="1" checked>
                                    <span><?php _e('Include emotion tags for natural delivery', 'ai-podcast-gen'); ?></span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Hosts Configuration -->
                        <div class="aipg-form-section">
                            <h3><?php _e('Hosts & Speakers', 'ai-podcast-gen'); ?></h3>
                            
                            <div class="aipg-form-row">
                                <label><?php _e('Number of Hosts', 'ai-podcast-gen'); ?></label>
                                <select name="hosts" id="aipg-hosts" class="aipg-select">
                                    <option value="1">1 Host</option>
                                    <option value="2" selected>2 Hosts</option>
                                    <option value="3">3 Hosts</option>
                                </select>
                            </div>
                            
                            <!-- Host 1 -->
                            <div class="aipg-speaker-config" id="aipg-host-1-config">
                                <h4><?php _e('Host 1', 'ai-podcast-gen'); ?></h4>
                                <div class="aipg-form-grid">
                                    <div class="aipg-form-row">
                                        <label><?php _e('Name', 'ai-podcast-gen'); ?></label>
                                        <input type="text" name="host_1_name" value="Alex" class="aipg-input" placeholder="Alex">
                                    </div>
                                    <div class="aipg-form-row">
                                        <label><?php _e('Voice', 'ai-podcast-gen'); ?></label>
                                        <div class="aipg-voice-select-wrapper">
                                            <select name="voice_host_1" class="aipg-select aipg-voice-select" data-speaker="host_1">
                                                <?php foreach ($voices as $voice_id => $voice): ?>
                                                    <option value="<?php echo esc_attr($voice_id); ?>" <?php selected($voice_id, 'echo'); ?>>
                                                        <?php echo esc_html($voice['name'] . ' - ' . $voice['description']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="button aipg-voice-preview-btn" data-voice="echo">
                                                <span class="dashicons dashicons-controls-play"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Host 2 -->
                            <div class="aipg-speaker-config" id="aipg-host-2-config">
                                <h4><?php _e('Host 2', 'ai-podcast-gen'); ?></h4>
                                <div class="aipg-form-grid">
                                    <div class="aipg-form-row">
                                        <label><?php _e('Name', 'ai-podcast-gen'); ?></label>
                                        <input type="text" name="host_2_name" value="Sam" class="aipg-input" placeholder="Sam">
                                    </div>
                                    <div class="aipg-form-row">
                                        <label><?php _e('Voice', 'ai-podcast-gen'); ?></label>
                                        <div class="aipg-voice-select-wrapper">
                                            <select name="voice_host_2" class="aipg-select aipg-voice-select" data-speaker="host_2">
                                                <?php foreach ($voices as $voice_id => $voice): ?>
                                                    <option value="<?php echo esc_attr($voice_id); ?>" <?php selected($voice_id, 'nova'); ?>>
                                                        <?php echo esc_html($voice['name'] . ' - ' . $voice['description']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="button aipg-voice-preview-btn" data-voice="nova">
                                                <span class="dashicons dashicons-controls-play"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Host 3 (hidden by default) -->
                            <div class="aipg-speaker-config" id="aipg-host-3-config" style="display:none;">
                                <h4><?php _e('Host 3', 'ai-podcast-gen'); ?></h4>
                                <div class="aipg-form-grid">
                                    <div class="aipg-form-row">
                                        <label><?php _e('Name', 'ai-podcast-gen'); ?></label>
                                        <input type="text" name="host_3_name" value="Jordan" class="aipg-input" placeholder="Jordan">
                                    </div>
                                    <div class="aipg-form-row">
                                        <label><?php _e('Voice', 'ai-podcast-gen'); ?></label>
                                        <div class="aipg-voice-select-wrapper">
                                            <select name="voice_host_3" class="aipg-select aipg-voice-select" data-speaker="host_3">
                                                <?php foreach ($voices as $voice_id => $voice): ?>
                                                    <option value="<?php echo esc_attr($voice_id); ?>" <?php selected($voice_id, 'shimmer'); ?>>
                                                        <?php echo esc_html($voice['name'] . ' - ' . $voice['description']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="button aipg-voice-preview-btn" data-voice="shimmer">
                                                <span class="dashicons dashicons-controls-play"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="aipg-form-row">
                                <label class="aipg-checkbox-label">
                                    <input type="checkbox" name="include_guest" id="aipg-include-guest" value="yes">
                                    <span><?php _e('Include Guest Expert', 'ai-podcast-gen'); ?></span>
                                </label>
                            </div>
                            
                            <!-- Guest Configuration (hidden by default) -->
                            <div class="aipg-speaker-config" id="aipg-guest-config" style="display:none;">
                                <h4><?php _e('Guest Expert', 'ai-podcast-gen'); ?></h4>
                                <div class="aipg-form-grid">
                                    <div class="aipg-form-row">
                                        <label><?php _e('Name', 'ai-podcast-gen'); ?></label>
                                        <input type="text" name="guest_name" value="Dr. Expert" class="aipg-input" placeholder="Dr. Expert">
                                    </div>
                                    <div class="aipg-form-row">
                                        <label><?php _e('Voice', 'ai-podcast-gen'); ?></label>
                                        <div class="aipg-voice-select-wrapper">
                                            <select name="voice_guest" class="aipg-select aipg-voice-select" data-speaker="guest">
                                                <?php foreach ($voices as $voice_id => $voice): ?>
                                                    <option value="<?php echo esc_attr($voice_id); ?>" <?php selected($voice_id, 'onyx'); ?>>
                                                        <?php echo esc_html($voice['name'] . ' - ' . $voice['description']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="button aipg-voice-preview-btn" data-voice="onyx">
                                                <span class="dashicons dashicons-controls-play"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit -->
                        <div class="aipg-form-actions">
                            <button type="submit" class="button button-primary button-hero">
                                <span class="dashicons dashicons-controls-play"></span>
                                <?php _e('Generate Podcast', 'ai-podcast-gen'); ?>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Status Display -->
                    <div id="aipg-generation-status" class="aipg-status-display" style="display:none;"></div>
                </div>
                
                <!-- Sidebar -->
                <div class="aipg-generate-sidebar">
                    <!-- Voice Preview -->
                    <div class="aipg-card">
                        <h3><?php _e('Voice Preview', 'ai-podcast-gen'); ?></h3>
                        <p class="aipg-form-help"><?php _e('Click the play button next to any voice to hear a preview', 'ai-podcast-gen'); ?></p>
                        <div id="aipg-voice-preview-player"></div>
                    </div>
                    
                    <!-- Tips -->
                    <div class="aipg-card aipg-tips-card">
                        <h3><?php _e('Pro Tips', 'ai-podcast-gen'); ?></h3>
                        <ul class="aipg-tips-list">
                            <li><?php _e('Mix male and female voices for variety', 'ai-podcast-gen'); ?></li>
                            <li><?php _e('Choose contrasting voices to make speakers easily distinguishable', 'ai-podcast-gen'); ?></li>
                            <li><?php _e('Custom names make the conversation feel more natural', 'ai-podcast-gen'); ?></li>
                            <li><?php _e('Emotion tags add expressiveness to the audio', 'ai-podcast-gen'); ?></li>
                            <li><?php _e('Interview style works best with guest experts', 'ai-podcast-gen'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        // Save settings
        if (isset($_POST['aipg_save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'aipg_settings')) {
            update_option('aipg_openrouter_key', sanitize_text_field($_POST['aipg_openrouter_key']));
            update_option('aipg_openai_key', sanitize_text_field($_POST['aipg_openai_key']));
            update_option('aipg_elevenlabs_key', sanitize_text_field($_POST['aipg_elevenlabs_key']));
            update_option('aipg_tavily_key', sanitize_text_field($_POST['aipg_tavily_key']));
            update_option('aipg_tts_provider', sanitize_text_field($_POST['aipg_tts_provider']));
            update_option('aipg_tts_model', sanitize_text_field($_POST['aipg_tts_model']));
            update_option('aipg_elevenlabs_model', sanitize_text_field($_POST['aipg_elevenlabs_model']));
            update_option('aipg_player_theme', sanitize_text_field($_POST['aipg_player_theme']));
            update_option('aipg_enable_search', isset($_POST['aipg_enable_search']));
            update_option('aipg_enable_emotions', isset($_POST['aipg_enable_emotions']));
            update_option('aipg_default_duration', intval($_POST['aipg_default_duration']));
            update_option('aipg_default_language', sanitize_text_field($_POST['aipg_default_language']));
            update_option('aipg_default_hosts', intval($_POST['aipg_default_hosts']));
            update_option('aipg_default_style', sanitize_text_field($_POST['aipg_default_style']));
            update_option('aipg_default_tone', sanitize_text_field($_POST['aipg_default_tone']));
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'ai-podcast-gen') . '</p></div>';
        }
        
        ?>
        <div class="wrap aipg-wrap">
            <h1 class="aipg-page-title">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php _e('Settings', 'ai-podcast-gen'); ?>
            </h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('aipg_settings'); ?>
                
                <!-- API Keys -->
                <div class="aipg-card">
                    <h2><?php _e('API Keys', 'ai-podcast-gen'); ?></h2>
                    <p class="aipg-form-help"><?php _e('Configure your API keys for podcast generation', 'ai-podcast-gen'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="aipg_openrouter_key"><?php _e('OpenRouter API Key', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="aipg_openrouter_key" name="aipg_openrouter_key" 
                                       value="<?php echo esc_attr(get_option('aipg_openrouter_key')); ?>" 
                                       class="regular-text" required>
                                <p class="description">
                                    <?php _e('Required for script generation. Get your key from', 'ai-podcast-gen'); ?>
                                    <a href="https://openrouter.ai/keys" target="_blank">OpenRouter</a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aipg_openai_key"><?php _e('OpenAI API Key', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="aipg_openai_key" name="aipg_openai_key" 
                                       value="<?php echo esc_attr(get_option('aipg_openai_key')); ?>" 
                                       class="regular-text">
                                <p class="description">
                                    <?php _e('For OpenAI TTS. Get your key from', 'ai-podcast-gen'); ?>
                                    <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aipg_elevenlabs_key"><?php _e('ElevenLabs API Key', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="aipg_elevenlabs_key" name="aipg_elevenlabs_key" 
                                       value="<?php echo esc_attr(get_option('aipg_elevenlabs_key')); ?>" 
                                       class="regular-text">
                                <p class="description">
                                    <?php _e('For ElevenLabs TTS. Get your key from', 'ai-podcast-gen'); ?>
                                    <a href="https://elevenlabs.io/app/settings/api-keys" target="_blank">ElevenLabs</a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aipg_tavily_key"><?php _e('Tavily API Key', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="aipg_tavily_key" name="aipg_tavily_key" 
                                       value="<?php echo esc_attr(get_option('aipg_tavily_key')); ?>" 
                                       class="regular-text">
                                <p class="description">
                                    <?php _e('Optional. For web search enhancement. Get your key from', 'ai-podcast-gen'); ?>
                                    <a href="https://tavily.com" target="_blank">Tavily</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- TTS Provider & Quality Settings -->
                <div class="aipg-card">
                    <h2><?php _e('TTS Provider & Quality Settings', 'ai-podcast-gen'); ?></h2>
                    <p class="aipg-form-help"><?php _e('Choose your TTS provider and quality settings', 'ai-podcast-gen'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="aipg_tts_provider"><?php _e('TTS Provider', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <select id="aipg_tts_provider" name="aipg_tts_provider" class="regular-text">
                                    <option value="openai" <?php selected(get_option('aipg_tts_provider', 'openai'), 'openai'); ?>>
                                        <?php _e('OpenAI TTS', 'ai-podcast-gen'); ?>
                                    </option>
                                    <option value="elevenlabs" <?php selected(get_option('aipg_tts_provider', 'openai'), 'elevenlabs'); ?>>
                                        <?php _e('ElevenLabs TTS', 'ai-podcast-gen'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e('Choose which text-to-speech provider to use for podcast generation', 'ai-podcast-gen'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <!-- OpenAI Settings -->
                        <tr class="aipg-provider-settings aipg-openai-settings" style="<?php echo get_option('aipg_tts_provider', 'openai') === 'openai' ? '' : 'display:none;'; ?>">
                            <th scope="row">
                                <label for="aipg_tts_model"><?php _e('OpenAI Model', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <select id="aipg_tts_model" name="aipg_tts_model" class="regular-text">
                                    <option value="gpt-4o-mini-tts" <?php selected(get_option('aipg_tts_model', 'gpt-4o-mini-tts'), 'gpt-4o-mini-tts'); ?>>
                                        🔊 Fast & Cost-effective (gpt-4o-mini-tts)
                                    </option>
                                    <option value="gpt-4o-tts" <?php selected(get_option('aipg_tts_model', 'gpt-4o-mini-tts'), 'gpt-4o-tts'); ?>>
                                        🎵 Higher Quality (gpt-4o-tts)
                                    </option>

                                    <!-- Legacy choices – still work because of the map above -->
                                    <option value="tts-1" <?php selected(get_option('aipg_tts_model', 'gpt-4o-mini-tts'), 'tts-1'); ?>>
                                        (Legacy) Standard (tts-1)
                                    </option>
                                    <option value="tts-1-hd" <?php selected(get_option('aipg_tts_model', 'gpt-4o-mini-tts'), 'tts-1-hd'); ?>>
                                        (Legacy) HD (tts-1-hd)
                                    </option>
                                </select>
                                <p class="description">
                                    <strong><?php _e('Standard (tts-1):', 'ai-podcast-gen'); ?></strong>
                                    <?php _e('$0.015/1K chars, works with all API keys', 'ai-podcast-gen'); ?><br>
                                    <strong><?php _e('HD (tts-1-hd):', 'ai-podcast-gen'); ?></strong>
                                    <?php _e('$0.030/1K chars, requires paid account with billing', 'ai-podcast-gen'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <!-- ElevenLabs Settings -->
                        <tr class="aipg-provider-settings aipg-elevenlabs-settings" style="<?php echo get_option('aipg_tts_provider', 'openai') === 'elevenlabs' ? '' : 'display:none;'; ?>">
                            <th scope="row">
                                <label for="aipg_elevenlabs_model"><?php _e('ElevenLabs Model', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <select id="aipg_elevenlabs_model" name="aipg_elevenlabs_model" class="regular-text">
                                    <option value="eleven_flash_v2_5" <?php selected(get_option('aipg_elevenlabs_model', 'eleven_flash_v2_5'), 'eleven_flash_v2_5'); ?>>
                                        <?php _e('⚡ Flash v2.5 - Fastest (75ms latency)', 'ai-podcast-gen'); ?>
                                    </option>
                                    <option value="eleven_turbo_v2_5" <?php selected(get_option('aipg_elevenlabs_model', 'eleven_flash_v2_5'), 'eleven_turbo_v2_5'); ?>>
                                        <?php _e('🚀 Turbo v2.5 - Balanced', 'ai-podcast-gen'); ?>
                                    </option>
                                    <option value="eleven_v3" <?php selected(get_option('aipg_elevenlabs_model', 'eleven_flash_v2_5'), 'eleven_v3'); ?>>
                                        <?php _e('⭐ Eleven v3 - Highest Quality', 'ai-podcast-gen'); ?>
                                    </option>
                                    <option value="eleven_multilingual_v2" <?php selected(get_option('aipg_elevenlabs_model', 'eleven_flash_v2_5'), 'eleven_multilingual_v2'); ?>>
                                        <?php _e('🌐 Multilingual v2 - 32 Languages', 'ai-podcast-gen'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <strong><?php _e('Flash v2.5:', 'ai-podcast-gen'); ?></strong>
                                    <?php _e('$0.10/1K chars, ultra-fast', 'ai-podcast-gen'); ?><br>
                                    <strong><?php _e('Turbo v2.5:', 'ai-podcast-gen'); ?></strong>
                                    <?php _e('$0.20/1K chars, balanced', 'ai-podcast-gen'); ?><br>
                                    <strong><?php _e('Eleven v3:', 'ai-podcast-gen'); ?></strong>
                                    <?php _e('$0.30/1K chars, highest emotional range', 'ai-podcast-gen'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Test API Access', 'ai-podcast-gen'); ?></th>
                            <td>
                                <button type="button" id="aipg-test-tts" class="button button-secondary">
                                    <span class="dashicons dashicons-admin-tools"></span>
                                    <?php _e('Test TTS Provider', 'ai-podcast-gen'); ?>
                                </button>
                                <div id="aipg-tts-test-results" style="margin-top: 15px;"></div>
                                <p class="description">
                                    <?php _e('Click to verify your TTS provider API access', 'ai-podcast-gen'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Player Settings -->
                <div class="aipg-card">
                    <h2><?php _e('Player Settings', 'ai-podcast-gen'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="aipg_player_theme"><?php _e('Player Theme', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <select id="aipg_player_theme" name="aipg_player_theme" class="regular-text">
                                    <option value="dark" <?php selected(get_option('aipg_player_theme', 'dark'), 'dark'); ?>>
                                        <?php _e('Dark Mode', 'ai-podcast-gen'); ?>
                                    </option>
                                    <option value="light" <?php selected(get_option('aipg_player_theme', 'dark'), 'light'); ?>>
                                        <?php _e('Light Mode', 'ai-podcast-gen'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e('Choose the default theme for the Horizon Glass player', 'ai-podcast-gen'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Generation Defaults -->
                <div class="aipg-card">
                    <h2><?php _e('Default Generation Settings', 'ai-podcast-gen'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="aipg_default_duration"><?php _e('Default Duration', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="aipg_default_duration" name="aipg_default_duration" 
                                       value="<?php echo esc_attr(get_option('aipg_default_duration', 10)); ?>" 
                                       min="3" max="60" class="small-text">
                                <span><?php _e('minutes', 'ai-podcast-gen'); ?></span>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aipg_default_language"><?php _e('Default Language', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <select id="aipg_default_language" name="aipg_default_language">
                                    <option value="English" <?php selected(get_option('aipg_default_language', 'English'), 'English'); ?>>English</option>
                                    <option value="Spanish" <?php selected(get_option('aipg_default_language', 'English'), 'Spanish'); ?>>Español</option>
                                    <option value="French" <?php selected(get_option('aipg_default_language', 'English'), 'French'); ?>>Français</option>
                                    <option value="German" <?php selected(get_option('aipg_default_language', 'English'), 'German'); ?>>Deutsch</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aipg_default_hosts"><?php _e('Default Number of Hosts', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <select id="aipg_default_hosts" name="aipg_default_hosts">
                                    <option value="1" <?php selected(get_option('aipg_default_hosts', 2), 1); ?>>1</option>
                                    <option value="2" <?php selected(get_option('aipg_default_hosts', 2), 2); ?>>2</option>
                                    <option value="3" <?php selected(get_option('aipg_default_hosts', 2), 3); ?>>3</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aipg_default_style"><?php _e('Default Style', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <select id="aipg_default_style" name="aipg_default_style">
                                    <option value="conversational" <?php selected(get_option('aipg_default_style', 'conversational'), 'conversational'); ?>>Conversational</option>
                                    <option value="interview" <?php selected(get_option('aipg_default_style', 'conversational'), 'interview'); ?>>Interview</option>
                                    <option value="debate" <?php selected(get_option('aipg_default_style', 'conversational'), 'debate'); ?>>Debate</option>
                                    <option value="educational" <?php selected(get_option('aipg_default_style', 'conversational'), 'educational'); ?>>Educational</option>
                                    <option value="storytelling" <?php selected(get_option('aipg_default_style', 'conversational'), 'storytelling'); ?>>Storytelling</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aipg_default_tone"><?php _e('Default Tone', 'ai-podcast-gen'); ?></label>
                            </th>
                            <td>
                                <select id="aipg_default_tone" name="aipg_default_tone">
                                    <option value="professional" <?php selected(get_option('aipg_default_tone', 'professional'), 'professional'); ?>>Professional</option>
                                    <option value="casual" <?php selected(get_option('aipg_default_tone', 'professional'), 'casual'); ?>>Casual</option>
                                    <option value="enthusiastic" <?php selected(get_option('aipg_default_tone', 'professional'), 'enthusiastic'); ?>>Enthusiastic</option>
                                    <option value="academic" <?php selected(get_option('aipg_default_tone', 'professional'), 'academic'); ?>>Academic</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Feature Toggles -->
                <div class="aipg-card">
                    <h2><?php _e('Features', 'ai-podcast-gen'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Web Search Enhancement', 'ai-podcast-gen'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aipg_enable_search" value="1" 
                                           <?php checked(get_option('aipg_enable_search', true)); ?>>
                                    <?php _e('Enable web search to enrich podcast content with latest information', 'ai-podcast-gen'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Emotion Tags', 'ai-podcast-gen'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aipg_enable_emotions" value="1" 
                                           <?php checked(get_option('aipg_enable_emotions', true)); ?>>
                                    <?php _e('Include emotion tags for more expressive audio delivery', 'ai-podcast-gen'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="aipg_save_settings" class="button button-primary button-large">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Save Settings', 'ai-podcast-gen'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render History Page
     */
    public function render_history_page() {
        $generations = AIPG_Database::get_recent_generations(50);
        
        ?>
        <div class="wrap aipg-wrap">
            <h1 class="aipg-page-title">
                <span class="dashicons dashicons-backup"></span>
                <?php _e('Generation History', 'ai-podcast-gen'); ?>
            </h1>
            
            <div class="aipg-card">
                <?php if (empty($generations)): ?>
                    <div class="aipg-empty-state">
                        <span class="dashicons dashicons-media-audio"></span>
                        <h3><?php _e('No Generations Yet', 'ai-podcast-gen'); ?></h3>
                        <p><?php _e('Start generating podcasts to see them here', 'ai-podcast-gen'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=aipg-generate'); ?>" class="button button-primary">
                            <?php _e('Generate Your First Podcast', 'ai-podcast-gen'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="aipg-table-responsive">
                        <table class="aipg-table aipg-table-hover">
                            <thead>
                                <tr>
                                    <th><?php _e('ID', 'ai-podcast-gen'); ?></th>
                                    <th><?php _e('Source Article', 'ai-podcast-gen'); ?></th>
                                    <th><?php _e('Settings', 'ai-podcast-gen'); ?></th>
                                    <th><?php _e('Status', 'ai-podcast-gen'); ?></th>
                                    <th><?php _e('Created', 'ai-podcast-gen'); ?></th>
                                    <th><?php _e('Updated', 'ai-podcast-gen'); ?></th>
                                    <th><?php _e('Actions', 'ai-podcast-gen'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($generations as $gen): ?>
                                    <?php
                                    $settings = maybe_unserialize($gen->settings);
                                    ?>
                                    <tr class="aipg-generation-row" data-generation-id="<?php echo esc_attr($gen->id); ?>">
                                        <td><strong>#<?php echo esc_html($gen->id); ?></strong></td>
                                        <td>
                                            <?php if ($gen->post_id): ?>
                                                <a href="<?php echo get_edit_post_link($gen->post_id); ?>" target="_blank">
                                                    <?php echo esc_html(get_the_title($gen->post_id)); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="aipg-text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (is_array($settings)): ?>
                                                <div class="aipg-settings-summary">
                                                    <span><?php echo esc_html($settings['duration'] ?? 10); ?> min</span>
                                                    <span>·</span>
                                                    <span><?php echo esc_html($settings['hosts'] ?? 2); ?> hosts</span>
                                                    <?php if (!empty($settings['guest'])): ?>
                                                        <span>· Guest</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $this->render_status_badge($gen->status); ?></td>
                                        <td><?php echo esc_html(date('M j, Y g:i A', strtotime($gen->created_at))); ?></td>
                                        <td><?php echo esc_html(human_time_diff(strtotime($gen->updated_at), current_time('timestamp')) . ' ago'); ?></td>
                                        <td>
                                            <div class="aipg-action-buttons">
                                                <?php if ($gen->status === 'failed'): ?>
                                                    <button class="button button-small aipg-retry-btn" data-generation-id="<?php echo esc_attr($gen->id); ?>" title="Retry">
                                                        <span class="dashicons dashicons-update"></span>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($gen->status === 'completed' && $gen->final_audio_url): ?>
                                                    <a href="<?php echo esc_url($gen->final_audio_url); ?>" class="button button-small" target="_blank" title="Download">
                                                        <span class="dashicons dashicons-download"></span>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($gen->error_log)): ?>
                                                    <button class="button button-small aipg-view-error-btn" data-error="<?php echo esc_attr($gen->error_log); ?>" title="View Error">
                                                        <span class="dashicons dashicons-warning"></span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler to test TTS model access
     */
    public function ajax_test_tts_access() {
        check_ajax_referer('aipg_test_tts', 'nonce');
        
        $provider = get_option('aipg_tts_provider', 'openai');
        
        if ($provider === 'elevenlabs') {
            $api_key = get_option('aipg_elevenlabs_key');
            
            if (empty($api_key)) {
                wp_send_json_error(array(
                    'message' => __('ElevenLabs API key not configured. Please add your key first.', 'ai-podcast-gen')
                ));
            }
            
            require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-elevenlabs-tts.php';
            $tts = new AIPG_ElevenLabs_TTS();
            
            $result = $tts->test_api_access();
            
            wp_send_json_success(array(
                'provider' => 'elevenlabs',
                'results' => array($result)
            ));
        } else {
            // OpenAI
            $api_key = get_option('aipg_openai_key');
            
            if (empty($api_key)) {
                wp_send_json_error(array(
                    'message' => __('OpenAI API key not configured. Please add your key first.', 'ai-podcast-gen')
                ));
            }
            
            require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-openai-tts.php';
            $tts = new AIPG_OpenAI_TTS($api_key);
            
            $results = $tts->test_tts_access();
            
            wp_send_json_success(array(
                'provider' => 'openai',
                'results' => $results
            ));
        }
    }
}