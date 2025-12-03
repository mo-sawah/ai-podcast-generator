<?php
/**
 * Test exec() availability on your server
 * 
 * Upload this file to your WordPress root directory
 * Access it via: https://yoursite.com/test-exec.php
 * Then DELETE it for security
 */

echo "<h1>Server exec() Test</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;} pre{background:#f5f5f5;padding:10px;}</style>";

// Test 1: Check if function exists
echo "<h2>Test 1: Function Exists</h2>";
if (function_exists('exec')) {
    echo "<p class='success'>✓ exec() function exists</p>";
} else {
    echo "<p class='error'>✗ exec() function does NOT exist</p>";
    echo "<p><strong>Solution:</strong> Contact your hosting provider to enable exec()</p>";
    exit;
}

// Test 2: Check disable_functions
echo "<h2>Test 2: Disabled Functions</h2>";
$disabled = ini_get('disable_functions');
if (empty($disabled)) {
    echo "<p class='success'>✓ No functions disabled</p>";
} else {
    $disabled_array = array_map('trim', explode(',', $disabled));
    if (in_array('exec', $disabled_array)) {
        echo "<p class='error'>✗ exec() is in disable_functions</p>";
        echo "<p><strong>Solution:</strong> Contact your hosting provider to enable exec()</p>";
        echo "<pre>" . htmlspecialchars($disabled) . "</pre>";
        exit;
    } else {
        echo "<p class='success'>✓ exec() not in disable_functions</p>";
        echo "<p>Disabled functions: <code>" . htmlspecialchars($disabled) . "</code></p>";
    }
}

// Test 3: Try basic exec
echo "<h2>Test 3: Basic exec() Test</h2>";
$output = array();
$return_var = 0;
@exec('echo "Hello from exec"', $output, $return_var);

if ($return_var === 0 && !empty($output)) {
    echo "<p class='success'>✓ exec() works!</p>";
    echo "<p>Output: <code>" . htmlspecialchars(implode(' ', $output)) . "</code></p>";
} else {
    echo "<p class='error'>✗ exec() exists but doesn't work</p>";
    echo "<p>Return code: <code>" . $return_var . "</code></p>";
    echo "<p><strong>Solution:</strong> Contact your hosting provider about exec() restrictions</p>";
    exit;
}

// Test 4: Check for FFmpeg
echo "<h2>Test 4: FFmpeg Availability</h2>";
$ffmpeg_paths = array('/bin/ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg');
$ffmpeg_found = false;

foreach ($ffmpeg_paths as $path) {
    $output = array();
    $return_var = 0;
    @exec($path . ' -version 2>&1', $output, $return_var);
    
    if ($return_var === 0) {
        echo "<p class='success'>✓ FFmpeg found at: <code>" . htmlspecialchars($path) . "</code></p>";
        if (!empty($output)) {
            echo "<pre>" . htmlspecialchars(implode("\n", array_slice($output, 0, 3))) . "</pre>";
        }
        $ffmpeg_found = true;
        break;
    }
}

if (!$ffmpeg_found) {
    echo "<p class='error'>✗ FFmpeg not found</p>";
    echo "<p>The plugin will use PHP fallback for audio merging (slightly lower quality)</p>";
}

// Test 5: PHP Info
echo "<h2>Test 5: PHP Configuration</h2>";
echo "<p>PHP Version: <code>" . phpversion() . "</code></p>";
echo "<p>Memory Limit: <code>" . ini_get('memory_limit') . "</code></p>";
echo "<p>Max Execution Time: <code>" . ini_get('max_execution_time') . " seconds</code></p>";
echo "<p>Upload Max Filesize: <code>" . ini_get('upload_max_filesize') . "</code></p>";

// Summary
echo "<h2>Summary</h2>";
if (function_exists('exec') && $return_var === 0) {
    if ($ffmpeg_found) {
        echo "<p class='success'><strong>✓ Your server is FULLY compatible with the AI Podcast Generator plugin!</strong></p>";
        echo "<p>FFmpeg is available for high-quality audio merging.</p>";
    } else {
        echo "<p class='success'><strong>✓ Your server is compatible with the AI Podcast Generator plugin.</strong></p>";
        echo "<p>FFmpeg not found - the plugin will use PHP fallback method for audio merging.</p>";
    }
} else {
    echo "<p class='error'><strong>✗ exec() is not available on your server.</strong></p>";
    echo "<p>The plugin will work but will use PHP fallback for audio merging.</p>";
    echo "<p><strong>Recommendation:</strong> Contact your hosting provider to enable exec() for better performance.</p>";
}

echo "<hr>";
echo "<p><strong>⚠️ IMPORTANT:</strong> Delete this file after testing for security reasons!</p>";
echo "<p>File location: <code>" . __FILE__ . "</code></p>";
?>
