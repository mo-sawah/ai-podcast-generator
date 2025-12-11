<?php
/**
 * FFmpeg Diagnostic Script for AI Podcast Generator
 * Place this in your plugin root and access via wp-admin/admin.php?page=aipg-ffmpeg-test
 * OR run directly via PHP CLI: php ffmpeg-diagnostic.php
 */

// If running from command line
if (php_sapi_name() === 'cli') {
    define('ABSPATH', dirname(__FILE__) . '/');
    run_diagnostics();
    exit;
}

// WordPress hook
add_action('admin_menu', function() {
    add_submenu_page(
        'aipg-dashboard',
        'FFmpeg Diagnostic',
        'FFmpeg Test',
        'manage_options',
        'aipg-ffmpeg-test',
        'aipg_ffmpeg_diagnostic_page'
    );
});

function aipg_ffmpeg_diagnostic_page() {
    echo '<div class="wrap">';
    echo '<h1>FFmpeg Diagnostic</h1>';
    echo '<div style="background: white; padding: 20px; border: 1px solid #ccc;">';
    echo '<pre>';
    run_diagnostics();
    echo '</pre>';
    echo '</div>';
    echo '</div>';
}

function run_diagnostics() {
    echo "=== AI PODCAST GENERATOR - FFMPEG DIAGNOSTIC ===\n\n";
    
    // 1. Check PHP version
    echo "1. PHP Version: " . PHP_VERSION . "\n";
    if (version_compare(PHP_VERSION, '7.4', '>=')) {
        echo "   ✓ PHP version is compatible\n\n";
    } else {
        echo "   ✗ PHP version too old (need 7.4+)\n\n";
    }
    
    // 2. Check exec() function
    echo "2. exec() Function:\n";
    if (function_exists('exec')) {
        echo "   ✓ exec() function exists\n";
        
        $disabled = ini_get('disable_functions');
        if (!empty($disabled)) {
            $disabled_funcs = array_map('trim', explode(',', $disabled));
            if (in_array('exec', $disabled_funcs)) {
                echo "   ✗ exec() is in disable_functions list\n";
                echo "   Fix: Remove 'exec' from disable_functions in php.ini\n\n";
                return;
            }
        }
        echo "   ✓ exec() is not disabled\n";
    } else {
        echo "   ✗ exec() function does not exist\n";
        echo "   Fix: Recompile PHP with exec support\n\n";
        return;
    }
    
    // 3. Test exec() functionality
    echo "\n3. Testing exec() Functionality:\n";
    $output = array();
    $return_var = 999;
    @exec('echo test 2>&1', $output, $return_var);
    
    if ($return_var === 0 || !empty($output)) {
        echo "   ✓ exec() test successful\n";
        echo "   Output: " . implode(' ', $output) . "\n";
    } else {
        echo "   ✗ exec() test failed (return code: {$return_var})\n\n";
        return;
    }
    
    // 4. Search for FFmpeg
    echo "\n4. Searching for FFmpeg:\n";
    
    $paths_to_check = array(
        'ffmpeg',                 // System PATH
        '/usr/bin/ffmpeg',        // Ubuntu/Debian
        '/usr/local/bin/ffmpeg',  // macOS/Homebrew
        '/opt/local/bin/ffmpeg',  // MacPorts
        '/bin/ffmpeg',            // Some systems
        '/usr/bin/local/ffmpeg',
    );
    
    $ffmpeg_found = false;
    $ffmpeg_path = null;
    
    foreach ($paths_to_check as $path) {
        echo "   Checking: {$path}... ";
        
        $output = array();
        $return_var = 999;
        @exec($path . ' -version 2>&1', $output, $return_var);
        
        if ($return_var === 0 && !empty($output)) {
            $version_line = $output[0] ?? '';
            if (stripos($version_line, 'ffmpeg') !== false) {
                echo "✓ FOUND!\n";
                echo "   Version: {$version_line}\n";
                $ffmpeg_found = true;
                $ffmpeg_path = $path;
                break;
            }
        }
        echo "not found\n";
    }
    
    if (!$ffmpeg_found) {
        echo "\n   ✗ FFmpeg NOT FOUND in any standard location\n\n";
        echo "   === INSTALLATION INSTRUCTIONS ===\n";
        echo "   Ubuntu/Debian: sudo apt-get install ffmpeg\n";
        echo "   CentOS/RHEL:   sudo yum install ffmpeg\n";
        echo "   macOS:         brew install ffmpeg\n";
        echo "   Docker:        apt-get update && apt-get install -y ffmpeg\n\n";
        echo "   After installation, run this diagnostic again.\n\n";
        return;
    }
    
    // 5. Test FFmpeg concat functionality
    echo "\n5. Testing FFmpeg Concat Functionality:\n";
    
    $temp_dir = sys_get_temp_dir();
    $test_files = array();
    
    // Create 2 test MP3 files
    for ($i = 1; $i <= 2; $i++) {
        $file = $temp_dir . '/test_audio_' . $i . '.mp3';
        // Minimal valid MP3 header
        $mp3_data = hex2bin('FFFB90640000000000000000000000000000000000000000');
        file_put_contents($file, $mp3_data);
        $test_files[] = $file;
        echo "   Created test file: " . basename($file) . "\n";
    }
    
    // Create concat list
    $concat_file = $temp_dir . '/concat_test.txt';
    $concat_content = '';
    foreach ($test_files as $file) {
        $safe_path = str_replace("'", "'\\''", $file);
        $concat_content .= "file '{$safe_path}'\n";
    }
    file_put_contents($concat_file, $concat_content);
    
    $output_file = $temp_dir . '/merged_test.mp3';
    
    $command = sprintf(
        '%s -f concat -safe 0 -i %s -c:a libmp3lame -b:a 128k %s 2>&1',
        $ffmpeg_path,
        escapeshellarg($concat_file),
        escapeshellarg($output_file)
    );
    
    echo "   Running: " . $command . "\n";
    
    $output = array();
    $return_var = 999;
    exec($command, $output, $return_var);
    
    if ($return_var === 0 && file_exists($output_file)) {
        echo "   ✓ FFmpeg concat test SUCCESSFUL!\n";
        echo "   Output file size: " . filesize($output_file) . " bytes\n";
    } else {
        echo "   ✗ FFmpeg concat test FAILED (return code: {$return_var})\n";
        echo "   Output:\n";
        foreach ($output as $line) {
            echo "     " . $line . "\n";
        }
    }
    
    // Cleanup
    foreach ($test_files as $file) {
        @unlink($file);
    }
    @unlink($concat_file);
    @unlink($output_file);
    
    echo "\n6. Upload Directory Check:\n";
    if (function_exists('wp_upload_dir')) {
        $upload_dir = wp_upload_dir();
        $podcast_dir = $upload_dir['basedir'] . '/ai-podcasts/';
        
        echo "   Upload base: " . $upload_dir['basedir'] . "\n";
        echo "   Podcast dir: " . $podcast_dir . "\n";
        
        if (!file_exists($podcast_dir)) {
            echo "   Creating podcast directory...\n";
            wp_mkdir_p($podcast_dir);
        }
        
        if (is_writable($podcast_dir)) {
            echo "   ✓ Podcast directory is writable\n";
        } else {
            echo "   ✗ Podcast directory is NOT writable\n";
            echo "   Fix: chmod 755 " . $podcast_dir . "\n";
        }
    }
    
    echo "\n=== DIAGNOSTIC COMPLETE ===\n";
    
    if ($ffmpeg_found) {
        echo "\n✓ System is ready for audio merging!\n";
        echo "FFmpeg path to use: {$ffmpeg_path}\n";
    } else {
        echo "\n✗ System requires FFmpeg installation\n";
    }
}
