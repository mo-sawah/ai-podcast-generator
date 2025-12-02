<?php
/**
 * Plugin Name: AI Podcast Generator
 * Plugin URI: https://sawahsolutions.com
 * Description: Generate AI-powered podcasts with multiple hosts using OpenRouter and ChatGPT TTS
 * Version: 1.0.0
 * Author: Mohamed Sawah
 * Author URI: https://sawahsolutions.com
 * License: GPL v2 or later
 * Text Domain: ai-podcast-gen
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('AIPG_VERSION', '1.0.0');
define('AIPG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIPG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIPG_PLUGIN_FILE', __FILE__);

/**
 * Main Plugin Class
 */
class AI_Podcast_Generator {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'), 20);
    }
    
    public function init() {
        // Check if Action Scheduler is available (from WooCommerce or standalone)
        if (!function_exists('as_enqueue_async_action')) {
            // Try to load from vendor if exists
            $action_scheduler = AIPG_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
            if (file_exists($action_scheduler)) {
                require_once $action_scheduler;
            } else {
                // Show admin notice
                add_action('admin_notices', array($this, 'action_scheduler_missing_notice'));
                return;
            }
        }
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
    }
    
    private function load_dependencies() {
        // Core classes
        require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-database.php';
        require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-cpt.php';
        require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-admin.php';
        require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-openrouter.php';
        require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-openai-tts.php';
        require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-tavily.php';
        require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-generator.php';
        require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-scheduler.php';
        require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-player.php';
    }
    
    private function init_components() {
        AIPG_Database::instance();
        AIPG_CPT::instance();
        AIPG_Admin::instance();
        AIPG_Generator::instance();
        AIPG_Scheduler::instance();
        AIPG_Player::instance();
    }
    
    public function action_scheduler_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('AI Podcast Generator:', 'ai-podcast-gen'); ?></strong>
                <?php _e('Action Scheduler is required. Please run "composer install" in the plugin directory:', 'ai-podcast-gen'); ?>
                <code>cd <?php echo AIPG_PLUGIN_DIR; ?> && composer install</code>
            </p>
            <p>
                <?php _e('Or install WooCommerce which includes Action Scheduler.', 'ai-podcast-gen'); ?>
            </p>
        </div>
        <?php
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('ai-podcast-gen', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function activate() {
        // Check for Action Scheduler
        if (!function_exists('as_enqueue_async_action')) {
            $action_scheduler = AIPG_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
            if (file_exists($action_scheduler)) {
                require_once $action_scheduler;
            }
        }
        
        // Load dependencies for activation
        require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-database.php';
        require_once AIPG_PLUGIN_DIR . 'includes/class-aipg-cpt.php';
        
        AIPG_Database::create_tables();
        AIPG_CPT::register_post_type();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize plugin
function aipg() {
    return AI_Podcast_Generator::instance();
}

// Start the plugin
add_action('plugins_loaded', 'aipg', 10);
