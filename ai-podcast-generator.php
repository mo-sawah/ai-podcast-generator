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

// Autoloader
spl_autoload_register(function($class) {
    if (strpos($class, 'AIPG_') !== 0) return;
    
    $file = AIPG_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

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
        $this->load_dependencies();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }
    
    private function load_dependencies() {
        // Include Action Scheduler
        require_once AIPG_PLUGIN_DIR . 'vendor/action-scheduler/action-scheduler.php';
        
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
    
    public function init() {
        // Initialize components
        AIPG_Database::instance();
        AIPG_CPT::instance();
        AIPG_Admin::instance();
        AIPG_Generator::instance();
        AIPG_Scheduler::instance();
        AIPG_Player::instance();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('ai-podcast-gen', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function activate() {
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

aipg();
