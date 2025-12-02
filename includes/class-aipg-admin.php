<?php
/**
 * Admin Interface
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
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add menu pages
     */
    public function add_menu_pages() {
        add_menu_page(
            __('AI Podcast Generator', 'ai-podcast-gen'),
            __('AI Podcasts', 'ai-podcast-gen'),
            'manage_options',
            'aipg-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-microphone',
            30
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
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'aipg-') === false) {
            return;
        }
        
        wp_enqueue_style('aipg-admin', AIPG_PLUGIN_URL . 'assets/css/admin.css', array(), AIPG_VERSION);
        wp_enqueue_script('aipg-admin', AIPG_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), AIPG_VERSION, true);
        
        wp_localize_script('aipg-admin', 'aipgAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aipg_generate'),
        ));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // API Keys
        register_setting('aipg_settings', 'aipg_openrouter_key');
        register_setting('aipg_settings', 'aipg_openai_key');
        register_setting('aipg_settings', 'aipg_tavily_key');
        register_setting('aipg_settings', 'aipg_enable_search');
        
        // Auto Generation
        register_setting('aipg_settings', 'aipg_auto_generate_enabled');
        register_setting('aipg_settings', 'aipg_auto_generate_frequency');
        register_setting('aipg_settings', 'aipg_auto_duration');
        register_setting('aipg_settings', 'aipg_auto_language');
        register_setting('aipg_settings', 'aipg_auto_hosts');
        register_setting('aipg_settings', 'aipg_auto_include_guest');
        register_setting('aipg_settings', 'aipg_auto_intro_text');
        register_setting('aipg_settings', 'aipg_auto_outro_text');
        register_setting('aipg_settings', 'aipg_auto_selection_count');
        register_setting('aipg_settings', 'aipg_auto_exclude_existing');
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        $recent_generations = AIPG_Database::get_recent_generations(20);
        ?>
        <div class="wrap aipg-wrap">
            <h1><?php _e('AI Podcast Generator Dashboard', 'ai-podcast-gen'); ?></h1>
            
            <div class="aipg-dashboard-grid">
                <div class="aipg-card">
                    <h2><?php _e('Quick Actions', 'ai-podcast-gen'); ?></h2>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=aipg-generate'); ?>" class="button button-primary button-large">
                            <?php _e('Generate New Podcast', 'ai-podcast-gen'); ?>
                        </a>
                    </p>
                    <p>
                        <a href="<?php echo admin_url('edit.php?post_type=ai_podcast'); ?>" class="button button-secondary">
                            <?php _e('View All Podcasts', 'ai-podcast-gen'); ?>
                        </a>
                    </p>
                </div>
                
                <div class="aipg-card">
                    <h2><?php _e('Statistics', 'ai-podcast-gen'); ?></h2>
                    <?php
                    $total_podcasts = wp_count_posts('ai_podcast')->publish;
                    $pending = count(array_filter($recent_generations, function($g) { 
                        return in_array($g->status, array('pending', 'processing', 'generating_script', 'generating_audio')); 
                    }));
                    ?>
                    <ul class="aipg-stats">
                        <li>
                            <strong><?php echo number_format($total_podcasts); ?></strong>
                            <span><?php _e('Total Podcasts', 'ai-podcast-gen'); ?></span>
                        </li>
                        <li>
                            <strong><?php echo number_format($pending); ?></strong>
                            <span><?php _e('In Progress', 'ai-podcast-gen'); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="aipg-card">
                <h2><?php _e('Recent Generations', 'ai-podcast-gen'); ?></h2>
                
                <?php if (empty($recent_generations)): ?>
                    <p><?php _e('No podcast generations yet.', 'ai-podcast-gen'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('ID', 'ai-podcast-gen'); ?></th>
                                <th><?php _e('Source Post', 'ai-podcast-gen'); ?></th>
                                <th><?php _e('Status', 'ai-podcast-gen'); ?></th>
                                <th><?php _e('Created', 'ai-podcast-gen'); ?></th>
                                <th><?php _e('Actions', 'ai-podcast-gen'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_generations as $gen): ?>
                                <tr>
                                    <td><?php echo $gen->id; ?></td>
                                    <td>
                                        <?php if ($gen->post_id): ?>
                                            <a href="<?php echo get_edit_post_link($gen->post_id); ?>">
                                                <?php echo get_the_title($gen->post_id); ?>
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="aipg-status-badge <?php echo esc_attr($gen->status); ?>">
                                            <?php echo esc_html(ucfirst($gen->status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo human_time_diff(strtotime($gen->created_at), current_time('timestamp')) . ' ago'; ?></td>
                                    <td>
                                        <?php if ($gen->status === 'completed' && $gen->final_audio_url): ?>
                                            <a href="<?php echo esc_url($gen->final_audio_url); ?>" target="_blank" class="button button-small">
                                                <?php _e('Listen', 'ai-podcast-gen'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render generate page
     */
    public function render_generate_page() {
        $tts = new AIPG_OpenAI_TTS();
        $voices = $tts->get_available_voices();
        ?>
        <div class="wrap aipg-wrap">
            <h1><?php _e('Generate Podcast', 'ai-podcast-gen'); ?></h1>
            
            <div class="aipg-generate-tabs">
                <button class="aipg-tab-btn active" data-tab="manual"><?php _e('Manual Selection', 'ai-podcast-gen'); ?></button>
                <button class="aipg-tab-btn" data-tab="auto-select"><?php _e('Auto-Select Article', 'ai-podcast-gen'); ?></button>
            </div>
            
            <!-- Manual Tab -->
            <div class="aipg-tab-content active" id="manual-tab">
                <form id="aipg-generate-form" class="aipg-card">
                    <h2><?php _e('Select Source Article', 'ai-podcast-gen'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Source Post', 'ai-podcast-gen'); ?></th>
                            <td>
                                <?php
                                wp_dropdown_pages(array(
                                    'post_type' => 'post',
                                    'name' => 'post_id',
                                    'id' => 'aipg-post-select',
                                    'show_option_none' => __('Select an article...', 'ai-podcast-gen'),
                                    'option_none_value' => '',
                                ));
                                ?>
                                <p class="description"><?php _e('Or enter post ID:', 'ai-podcast-gen'); ?></p>
                                <input type="number" name="post_id_manual" id="aipg-post-id" placeholder="<?php _e('Post ID', 'ai-podcast-gen'); ?>">
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Duration', 'ai-podcast-gen'); ?></th>
                            <td>
                                <select name="duration" id="aipg-duration">
                                    <option value="5">5 minutes</option>
                                    <option value="10" selected>10 minutes</option>
                                    <option value="15">15 minutes</option>
                                    <option value="20">20 minutes</option>
                                    <option value="30">30 minutes</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Language', 'ai-podcast-gen'); ?></th>
                            <td>
                                <select name="language" id="aipg-language">
                                    <option value="English">English</option>
                                    <option value="Spanish">Español</option>
                                    <option value="French">Français</option>
                                    <option value="German">Deutsch</option>
                                    <option value="Italian">Italiano</option>
                                    <option value="Portuguese">Português</option>
                                    <option value="Arabic">العربية</option>
                                    <option value="Turkish">Türkçe</option>
                                    <option value="Greek">Ελληνικά</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Number of Hosts', 'ai-podcast-gen'); ?></th>
                            <td>
                                <select name="hosts" id="aipg-hosts">
                                    <option value="1">1 Host</option>
                                    <option value="2" selected>2 Hosts</option>
                                    <option value="3">3 Hosts</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Include Guest', 'ai-podcast-gen'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="include_guest" value="yes">
                                    <?php _e('Add a guest expert to the conversation', 'ai-podcast-gen'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Voice Mapping', 'ai-podcast-gen'); ?></th>
                            <td>
                                <div id="aipg-voice-mapping">
                                    <div class="aipg-voice-row">
                                        <label>Host 1:</label>
                                        <select name="voice_host1">
                                            <?php foreach ($voices as $voice => $info): ?>
                                                <option value="<?php echo esc_attr($voice); ?>"><?php echo ucfirst($voice); ?> (<?php echo $info['gender']; ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="aipg-voice-row">
                                        <label>Host 2:</label>
                                        <select name="voice_host2">
                                            <?php foreach ($voices as $voice => $info): ?>
                                                <option value="<?php echo esc_attr($voice); ?>" <?php selected($voice, 'nova'); ?>><?php echo ucfirst($voice); ?> (<?php echo $info['gender']; ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Custom Intro', 'ai-podcast-gen'); ?></th>
                            <td>
                                <textarea name="intro_text" rows="3" class="large-text" placeholder="<?php _e('Optional intro text (e.g., podcast name, theme music cue)', 'ai-podcast-gen'); ?>"></textarea>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Custom Outro', 'ai-podcast-gen'); ?></th>
                            <td>
                                <textarea name="outro_text" rows="3" class="large-text" placeholder="<?php _e('Optional outro text (e.g., subscribe message, credits)', 'ai-podcast-gen'); ?>"></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">
                            <?php _e('Generate Podcast', 'ai-podcast-gen'); ?>
                        </button>
                    </p>
                    
                    <div id="aipg-generation-status" style="display:none;"></div>
                </form>
            </div>
            
            <!-- Auto-Select Tab -->
            <div class="aipg-tab-content" id="auto-select-tab">
                <div class="aipg-card">
                    <h2><?php _e('Auto-Select Best Article', 'ai-podcast-gen'); ?></h2>
                    <p><?php _e('Let AI choose the best article from your recent posts for a podcast.', 'ai-podcast-gen'); ?></p>
                    
                    <form id="aipg-auto-select-form">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Number of Articles to Consider', 'ai-podcast-gen'); ?></th>
                                <td>
                                    <input type="number" name="count" value="10" min="5" max="50">
                                    <p class="description"><?php _e('AI will review this many recent posts', 'ai-podcast-gen'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e('Select Best Article & Generate', 'ai-podcast-gen'); ?>
                            </button>
                        </p>
                        
                        <div id="aipg-auto-select-status"></div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_POST['aipg_save_settings'])) {
            check_admin_referer('aipg_settings');
            
            // Save settings
            update_option('aipg_openrouter_key', sanitize_text_field($_POST['aipg_openrouter_key'] ?? ''));
            update_option('aipg_openai_key', sanitize_text_field($_POST['aipg_openai_key'] ?? ''));
            update_option('aipg_tavily_key', sanitize_text_field($_POST['aipg_tavily_key'] ?? ''));
            update_option('aipg_enable_search', isset($_POST['aipg_enable_search']));
            
            // Auto-generation settings
            $auto_enabled = isset($_POST['aipg_auto_generate_enabled']);
            $auto_frequency = sanitize_text_field($_POST['aipg_auto_generate_frequency'] ?? 'hourly');
            
            update_option('aipg_auto_generate_enabled', $auto_enabled);
            update_option('aipg_auto_generate_frequency', $auto_frequency);
            update_option('aipg_auto_duration', intval($_POST['aipg_auto_duration'] ?? 10));
            update_option('aipg_auto_language', sanitize_text_field($_POST['aipg_auto_language'] ?? 'English'));
            update_option('aipg_auto_hosts', intval($_POST['aipg_auto_hosts'] ?? 2));
            update_option('aipg_auto_include_guest', isset($_POST['aipg_auto_include_guest']));
            update_option('aipg_auto_intro_text', sanitize_textarea_field($_POST['aipg_auto_intro_text'] ?? ''));
            update_option('aipg_auto_outro_text', sanitize_textarea_field($_POST['aipg_auto_outro_text'] ?? ''));
            update_option('aipg_auto_selection_count', intval($_POST['aipg_auto_selection_count'] ?? 20));
            update_option('aipg_auto_exclude_existing', isset($_POST['aipg_auto_exclude_existing']));
            
            // Update scheduler
            if ($auto_enabled) {
                AIPG_Scheduler::enable_auto_generation($auto_frequency);
            } else {
                AIPG_Scheduler::disable_auto_generation();
            }
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'ai-podcast-gen') . '</p></div>';
        }
        
        $openrouter_key = get_option('aipg_openrouter_key', '');
        $openai_key = get_option('aipg_openai_key', '');
        $tavily_key = get_option('aipg_tavily_key', '');
        $enable_search = get_option('aipg_enable_search', true);
        $auto_enabled = get_option('aipg_auto_generate_enabled', false);
        $auto_frequency = get_option('aipg_auto_generate_frequency', 'hourly');
        ?>
        <div class="wrap aipg-wrap">
            <h1><?php _e('Podcast Generator Settings', 'ai-podcast-gen'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('aipg_settings'); ?>
                
                <div class="aipg-card">
                    <h2><?php _e('API Configuration', 'ai-podcast-gen'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('OpenRouter API Key', 'ai-podcast-gen'); ?></th>
                            <td>
                                <input type="password" name="aipg_openrouter_key" value="<?php echo esc_attr($openrouter_key); ?>" class="regular-text">
                                <p class="description">
                                    <?php _e('Get your API key from', 'ai-podcast-gen'); ?> 
                                    <a href="https://openrouter.ai/keys" target="_blank">OpenRouter</a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('OpenAI API Key', 'ai-podcast-gen'); ?></th>
                            <td>
                                <input type="password" name="aipg_openai_key" value="<?php echo esc_attr($openai_key); ?>" class="regular-text">
                                <p class="description">
                                    <?php _e('Get your API key from', 'ai-podcast-gen'); ?> 
                                    <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Tavily API Key', 'ai-podcast-gen'); ?></th>
                            <td>
                                <input type="password" name="aipg_tavily_key" value="<?php echo esc_attr($tavily_key); ?>" class="regular-text">
                                <p class="description">
                                    <?php _e('Get your API key from', 'ai-podcast-gen'); ?> 
                                    <a href="https://tavily.com/" target="_blank">Tavily</a> (<?php _e('Optional', 'ai-podcast-gen'); ?>)
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Enable Web Search', 'ai-podcast-gen'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aipg_enable_search" value="1" <?php checked($enable_search); ?>>
                                    <?php _e('Use Tavily to enrich podcasts with latest web information', 'ai-podcast-gen'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="aipg-card">
                    <h2><?php _e('Automated Generation', 'ai-podcast-gen'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Enable Auto-Generation', 'ai-podcast-gen'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aipg_auto_generate_enabled" value="1" <?php checked($auto_enabled); ?>>
                                    <?php _e('Automatically generate podcasts from new articles', 'ai-podcast-gen'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Generation Frequency', 'ai-podcast-gen'); ?></th>
                            <td>
                                <select name="aipg_auto_generate_frequency">
                                    <option value="15min" <?php selected($auto_frequency, '15min'); ?>>Every 15 minutes</option>
                                    <option value="30min" <?php selected($auto_frequency, '30min'); ?>>Every 30 minutes</option>
                                    <option value="hourly" <?php selected($auto_frequency, 'hourly'); ?>>Every hour</option>
                                    <option value="twicedaily" <?php selected($auto_frequency, 'twicedaily'); ?>>Twice daily</option>
                                    <option value="daily" <?php selected($auto_frequency, 'daily'); ?>>Once daily</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Default Duration', 'ai-podcast-gen'); ?></th>
                            <td>
                                <select name="aipg_auto_duration">
                                    <option value="5">5 minutes</option>
                                    <option value="10" selected>10 minutes</option>
                                    <option value="15">15 minutes</option>
                                    <option value="20">20 minutes</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Articles to Review', 'ai-podcast-gen'); ?></th>
                            <td>
                                <input type="number" name="aipg_auto_selection_count" value="<?php echo get_option('aipg_auto_selection_count', 20); ?>" min="5" max="50">
                                <p class="description"><?php _e('Number of recent articles AI will review to select the best one', 'ai-podcast-gen'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><?php _e('Exclude Existing', 'ai-podcast-gen'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aipg_auto_exclude_existing" value="1" <?php checked(get_option('aipg_auto_exclude_existing', true)); ?>>
                                    <?php _e('Skip articles that already have podcasts', 'ai-podcast-gen'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="aipg_save_settings" class="button button-primary" value="<?php _e('Save Settings', 'ai-podcast-gen'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}
