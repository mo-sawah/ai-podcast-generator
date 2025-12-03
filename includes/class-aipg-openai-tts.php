<?php
/**
 * OpenAI TTS API Handler with Chunking
 */

if (!defined('ABSPATH')) exit;

class AIPG_OpenAI_TTS {
    
    private $api_key;
    private $base_url = 'https://api.openai.com/v1/audio/speech';
    private $chunk_limit = 4000; // Safe limit below 4096
    
    // Available voices with gender/characteristics
    private $voices = array(
        'alloy' => array('gender' => 'neutral', 'style' => 'balanced'),
        'ash' => array('gender' => 'male', 'style' => 'clear'),
        'ballad' => array('gender' => 'female', 'style' => 'warm'),
        'coral' => array('gender' => 'female', 'style' => 'friendly'),
        'echo' => array('gender' => 'male', 'style' => 'professional'),
        'fable' => array('gender' => 'male', 'style' => 'expressive'),
        'nova' => array('gender' => 'female', 'style' => 'energetic'),
        'onyx' => array('gender' => 'male', 'style' => 'authoritative'),
        'sage' => array('gender' => 'female', 'style' => 'calm'),
        'shimmer' => array('gender' => 'female', 'style' => 'soft'),
        'verse' => array('gender' => 'neutral', 'style' => 'versatile'),
    );
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('aipg_openai_key');
    }
    
    /**
     * Generate podcast audio from script with multiple hosts
     */
    public function generate_podcast_audio($script_data, $settings = array()) {
        $defaults = array(
            'model' => 'tts-1-hd',
            'voice_mapping' => array(),
            'speed' => 1.0,
            'intro_text' => '',
            'outro_text' => '',
        );
        
        $settings = wp_parse_args($settings, $defaults);
        
        // Assign voices to speakers if not provided
        if (empty($settings['voice_mapping'])) {
            $settings['voice_mapping'] = $this->auto_assign_voices($script_data['parsed_script']);
        }
        
        $audio_chunks = array();
        
        // Generate intro if provided
        if (!empty($settings['intro_text'])) {
            $intro_chunk = $this->generate_single_audio(
                $settings['intro_text'],
                $settings['voice_mapping']['intro'] ?? 'alloy',
                $settings
            );
            
            if (!is_wp_error($intro_chunk)) {
                $audio_chunks[] = array(
                    'type' => 'intro',
                    'file' => $intro_chunk,
                );
            }
        }
        
        // Generate main content
        $content_chunks = $this->generate_script_audio($script_data['parsed_script'], $settings);
        
        if (is_wp_error($content_chunks)) {
            return $content_chunks;
        }
        
        $audio_chunks = array_merge($audio_chunks, $content_chunks);
        
        // Generate outro if provided
        if (!empty($settings['outro_text'])) {
            $outro_chunk = $this->generate_single_audio(
                $settings['outro_text'],
                $settings['voice_mapping']['outro'] ?? 'alloy',
                $settings
            );
            
            if (!is_wp_error($outro_chunk)) {
                $audio_chunks[] = array(
                    'type' => 'outro',
                    'file' => $outro_chunk,
                );
            }
        }
        
        return $audio_chunks;
    }
    
    /**
     * Generate audio for parsed script
     */
    private function generate_script_audio($parsed_script, $settings) {
        $audio_chunks = array();
        $current_chunk = '';
        $current_voice = null;
        $chunk_index = 0;
        
        foreach ($parsed_script as $line) {
            $speaker = $line['speaker'];
            $text = $line['text'];
            $voice = $settings['voice_mapping'][$speaker] ?? 'alloy';
            
            // If voice changes or chunk would exceed limit, generate current chunk
            if ($current_voice && ($voice !== $current_voice || strlen($current_chunk . ' ' . $text) > $this->chunk_limit)) {
                // Generate audio for current chunk
                $audio_file = $this->generate_single_audio($current_chunk, $current_voice, $settings);
                
                if (is_wp_error($audio_file)) {
                    return $audio_file;
                }
                
                $audio_chunks[] = array(
                    'type' => 'content',
                    'speaker' => $current_voice,
                    'file' => $audio_file,
                    'index' => $chunk_index++,
                );
                
                // Reset chunk
                $current_chunk = $text;
                $current_voice = $voice;
            } else {
                // Add to current chunk
                if ($current_chunk) {
                    $current_chunk .= ' ' . $text;
                } else {
                    $current_chunk = $text;
                }
                $current_voice = $voice;
            }
        }
        
        // Generate final chunk
        if ($current_chunk && $current_voice) {
            $audio_file = $this->generate_single_audio($current_chunk, $current_voice, $settings);
            
            if (!is_wp_error($audio_file)) {
                $audio_chunks[] = array(
                    'type' => 'content',
                    'speaker' => $current_voice,
                    'file' => $audio_file,
                    'index' => $chunk_index,
                );
            }
        }
        
        return $audio_chunks;
    }
    
    /**
     * Generate single audio chunk
     */
    private function generate_single_audio($text, $voice, $settings) {
        $body = array(
            'model' => $settings['model'],
            'input' => $text,
            'voice' => $voice,
            'speed' => $settings['speed'],
            'response_format' => 'mp3',
        );
        
        $response = wp_remote_post($this->base_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 120,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $body_content = wp_remote_retrieve_body($response);
            $error = json_decode($body_content, true);
            return new WP_Error('tts_error', 
                isset($error['error']['message']) ? $error['error']['message'] : 'TTS generation failed'
            );
        }
        
        // Save audio file
        $audio_data = wp_remote_retrieve_body($response);
        return $this->save_audio_file($audio_data);
    }
    
    /**
     * Save audio file to uploads
     */
    private function save_audio_file($audio_data) {
        $upload_dir = wp_upload_dir();
        $podcast_dir = $upload_dir['basedir'] . '/ai-podcasts/';
        
        if (!file_exists($podcast_dir)) {
            wp_mkdir_p($podcast_dir);
        }
        
        $filename = 'chunk_' . uniqid() . '.mp3';
        $filepath = $podcast_dir . $filename;
        
        $result = file_put_contents($filepath, $audio_data);
        
        if ($result === false) {
            return new WP_Error('save_error', 'Failed to save audio file');
        }
        
        return array(
            'path' => $filepath,
            'url' => $upload_dir['baseurl'] . '/ai-podcasts/' . $filename,
            'size' => $result,
        );
    }
    
    /**
     * Merge audio chunks into final file
     */
    public function merge_audio_chunks($chunks) {
        if (empty($chunks)) {
            error_log('AIPG: No audio chunks to merge');
            return new WP_Error('no_chunks', 'No audio chunks to merge');
        }
        
        error_log('AIPG: Starting merge of ' . count($chunks) . ' audio chunks');
        
        // If only one chunk, return it
        if (count($chunks) === 1) {
            error_log('AIPG: Only one chunk, skipping merge');
            return $chunks[0]['file'];
        }
        
        // Verify all chunk files exist
        $valid_chunks = array();
        foreach ($chunks as $chunk) {
            if (isset($chunk['file']['path']) && file_exists($chunk['file']['path'])) {
                $valid_chunks[] = $chunk;
                error_log('AIPG: Valid chunk: ' . basename($chunk['file']['path']) . ' (' . filesize($chunk['file']['path']) . ' bytes)');
            } else {
                error_log('AIPG: Missing chunk: ' . ($chunk['file']['path'] ?? 'unknown'));
            }
        }
        
        if (empty($valid_chunks)) {
            error_log('AIPG: ERROR - No valid audio chunks found!');
            return new WP_Error('no_valid_chunks', 'No valid audio chunks found');
        }
        
        error_log('AIPG: Found ' . count($valid_chunks) . ' valid chunks, checking exec() availability...');
        
        // CRITICAL: Check exec() FIRST before attempting FFmpeg
        if (!$this->is_exec_available()) {
            error_log('AIPG: exec() not available, using PHP fallback immediately');
            return $this->simple_merge_fallback($valid_chunks);
        }
        
        error_log('AIPG: exec() is available, checking for FFmpeg...');
        
        // Check if ffmpeg is available
        $ffmpeg_path = $this->is_ffmpeg_available();
        
        if (!$ffmpeg_path) {
            error_log('AIPG: FFmpeg not available, using PHP fallback');
            return $this->simple_merge_fallback($valid_chunks);
        }
        
        error_log('AIPG: Using FFmpeg at: ' . $ffmpeg_path);
        
        $upload_dir = wp_upload_dir();
        $podcast_dir = $upload_dir['basedir'] . '/ai-podcasts/';
        
        if (!file_exists($podcast_dir)) {
            wp_mkdir_p($podcast_dir);
        }
        
        $temp_list = $podcast_dir . 'merge_list_' . uniqid() . '.txt';
        $output_file = $podcast_dir . 'podcast_' . uniqid() . '.mp3';
        
        // Create file list for ffmpeg
        $list_content = '';
        foreach ($valid_chunks as $chunk) {
            $list_content .= "file '" . $chunk['file']['path'] . "'\n";
        }
        
        $list_write = file_put_contents($temp_list, $list_content);
        if ($list_write === false) {
            error_log('AIPG: Failed to write temp list, using PHP fallback');
            return $this->simple_merge_fallback($valid_chunks);
        }
        
        error_log('AIPG: Created temp list: ' . $temp_list);
        
        // Use ffmpeg with full path
        $command = sprintf(
            '%s -f concat -safe 0 -i %s -c copy %s 2>&1',
            escapeshellarg($ffmpeg_path),
            escapeshellarg($temp_list),
            escapeshellarg($output_file)
        );
        
        error_log('AIPG: FFmpeg command: ' . $command);
        
        // Execute with error handling
        $exec_output = array();
        $return_var = 0;
        
        try {
            // Attempt execution with @ to suppress warnings
            @exec($command, $exec_output, $return_var);
            
            error_log('AIPG: FFmpeg exit code: ' . $return_var);
            if (!empty($exec_output)) {
                error_log('AIPG: FFmpeg output: ' . implode("\n", array_slice($exec_output, 0, 10))); // First 10 lines only
            }
        } catch (Exception $e) {
            error_log('AIPG: FFmpeg execution exception: ' . $e->getMessage());
            @unlink($temp_list);
            return $this->simple_merge_fallback($valid_chunks);
        }
        
        // Clean up temp file
        @unlink($temp_list);
        
        // Check if merge succeeded
        if ($return_var !== 0 || !file_exists($output_file) || filesize($output_file) == 0) {
            error_log('AIPG: FFmpeg failed (exit: ' . $return_var . ', exists: ' . (file_exists($output_file) ? 'yes' : 'no') . '), using PHP fallback');
            if (file_exists($output_file)) {
                @unlink($output_file);
            }
            return $this->simple_merge_fallback($valid_chunks);
        }
        
        $file_size = filesize($output_file);
        error_log('AIPG: FFmpeg SUCCESS! Created: ' . basename($output_file) . ' (' . $file_size . ' bytes)');
        
        return array(
            'path' => $output_file,
            'url' => $upload_dir['baseurl'] . '/ai-podcasts/' . basename($output_file),
            'size' => $file_size,
        );
    }
    
    /**
     * Simple fallback: use MP3 concatenation library or raw concat
     * NOTE: Simple binary concat of MP3s may have issues due to ID3 tags
     */
    private function simple_merge_fallback($chunks) {
        error_log('AIPG: Using PHP MP3 concatenation fallback');
        
        $upload_dir = wp_upload_dir();
        $podcast_dir = $upload_dir['basedir'] . '/ai-podcasts/';
        
        if (!file_exists($podcast_dir)) {
            wp_mkdir_p($podcast_dir);
        }
        
        $output_file = $podcast_dir . 'podcast_merged_' . uniqid() . '.mp3';
        $merged_content = '';
        $total_size = 0;
        $first_chunk = true;
        
        foreach ($chunks as $index => $chunk) {
            if (isset($chunk['file']['path']) && file_exists($chunk['file']['path'])) {
                $content = file_get_contents($chunk['file']['path']);
                if ($content !== false) {
                    // For first chunk, keep everything including ID3 tag
                    if ($first_chunk) {
                        $merged_content .= $content;
                        $first_chunk = false;
                    } else {
                        // For subsequent chunks, try to skip ID3v2 tag (if present)
                        // ID3v2 starts with "ID3" and has 10-byte header
                        if (substr($content, 0, 3) === 'ID3') {
                            // Get tag size from header (bytes 6-9, syncsafe integer)
                            $size_bytes = substr($content, 6, 4);
                            $size = (ord($size_bytes[0]) & 0x7F) << 21 |
                                   (ord($size_bytes[1]) & 0x7F) << 14 |
                                   (ord($size_bytes[2]) & 0x7F) << 7 |
                                   (ord($size_bytes[3]) & 0x7F);
                            // Skip ID3 tag (10 byte header + tag size)
                            $content = substr($content, 10 + $size);
                        }
                        $merged_content .= $content;
                    }
                    
                    $size = strlen($content);
                    $total_size += $size;
                    error_log('AIPG: Merged chunk ' . ($index + 1) . ': ' . $size . ' bytes');
                } else {
                    error_log('AIPG: Failed to read chunk: ' . basename($chunk['file']['path']));
                }
            }
        }
        
        if (empty($merged_content)) {
            error_log('AIPG: No content to merge, using first chunk as fallback');
            // Last resort: use the first available chunk
            foreach ($chunks as $chunk) {
                if (isset($chunk['file']['path']) && file_exists($chunk['file']['path'])) {
                    error_log('AIPG: Using first available chunk: ' . basename($chunk['file']['path']));
                    return $chunk['file'];
                }
            }
            return new WP_Error('merge_failed', 'No valid audio chunks found');
        }
        
        error_log('AIPG: Total merged size: ' . $total_size . ' bytes, writing to file...');
        
        $result = file_put_contents($output_file, $merged_content);
        
        if ($result === false) {
            error_log('AIPG: Binary merge write failed, using first chunk');
            return $chunks[0]['file'];
        }
        
        error_log('AIPG: PHP merge SUCCESS! Created: ' . basename($output_file) . ' (' . $result . ' bytes)');
        
        return array(
            'path' => $output_file,
            'url' => $upload_dir['baseurl'] . '/ai-podcasts/' . basename($output_file),
            'size' => filesize($output_file),
        );
    }
    
    /**
     * Check if exec() function is available
     */
    private function is_exec_available() {
        // Check if function exists
        if (!function_exists('exec')) {
            error_log('AIPG: exec() function does not exist');
            return false;
        }
        
        // Check if it's in disable_functions
        $disabled = ini_get('disable_functions');
        if (!empty($disabled)) {
            $disabled_funcs = array_map('trim', explode(',', $disabled));
            if (in_array('exec', $disabled_funcs)) {
                error_log('AIPG: exec() is in disable_functions: ' . $disabled);
                return false;
            }
        }
        
        // Try a simple test
        try {
            $test_output = array();
            $test_return = 999; // Default to error
            @exec('echo test 2>&1', $test_output, $test_return);
            
            if ($test_return === 0 || !empty($test_output)) {
                error_log('AIPG: exec() test successful');
                return true;
            } else {
                error_log('AIPG: exec() test failed (return: ' . $test_return . ')');
                return false;
            }
        } catch (Exception $e) {
            error_log('AIPG: exec() test threw exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if ffmpeg is available
     */
    private function is_ffmpeg_available() {
        // First check if exec() is available
        if (!$this->is_exec_available()) {
            error_log('AIPG: Cannot check for FFmpeg - exec() not available');
            return false;
        }
        
        // Try multiple possible paths
        $paths = array('/bin/ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg');
        
        foreach ($paths as $path) {
            $output = array();
            $return_var = 0;
            
            // Use @ to suppress any warnings
            $result = @exec($path . ' -version 2>&1', $output, $return_var);
            
            if ($return_var === 0 && !empty($result)) {
                error_log('AIPG: FFmpeg found at: ' . $path);
                return $path;
            }
        }
        
        error_log('AIPG: FFmpeg not found in any standard location');
        return false;
    }
    
    /**
     * Auto-assign voices to speakers
     */
    private function auto_assign_voices($parsed_script) {
        $speakers = array();
        foreach ($parsed_script as $line) {
            $speaker = $line['speaker'];
            if (!isset($speakers[$speaker])) {
                $speakers[$speaker] = true;
            }
        }
        
        $speaker_names = array_keys($speakers);
        $voice_pool = array('echo', 'nova', 'onyx', 'ballad', 'sage', 'fable', 'coral', 'ash');
        
        $voice_mapping = array();
        foreach ($speaker_names as $index => $speaker) {
            $voice_mapping[$speaker] = $voice_pool[$index % count($voice_pool)];
        }
        
        return $voice_mapping;
    }
    
    /**
     * Get available voices
     */
    public function get_available_voices() {
        return $this->voices;
    }
}
