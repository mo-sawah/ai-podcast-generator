<?php
/**
 * OpenAI TTS Handler - Enhanced with Voice Mapping, Emotions & Robust Merging
 * Combines new features (proper voice mapping, emotions, custom names) with 
 * original robust chunking and merging system
 */

if (!defined('ABSPATH')) exit;

class AIPG_OpenAI_TTS {
    
    private $api_key;
    private $base_url = 'https://api.openai.com/v1/audio/speech';
    private $chunk_limit = 4000; // Safe limit below 4096
    
    // Available voices with characteristics
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
     * Generate podcast audio with proper voice mapping
     */
    public function generate_podcast_audio($script_result, $settings = array()) {
        if (empty($script_result['parsed_script'])) {
            return new WP_Error('no_script', 'No parsed script available');
        }
        
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
            $settings['voice_mapping'] = $this->auto_assign_voices($script_result['parsed_script']);
        }
        
        error_log('AIPG TTS: Starting audio generation with ' . count($script_result['parsed_script']) . ' segments');
        error_log('AIPG TTS: Voice mapping - ' . json_encode($settings['voice_mapping']));
        
        $audio_chunks = array();
        
        // Generate intro if provided
        if (!empty($settings['intro_text'])) {
            $intro_voice = $settings['voice_mapping']['intro'] ?? 'alloy';
            $intro_chunk = $this->generate_single_audio($settings['intro_text'], $intro_voice, $settings);
            
            if (!is_wp_error($intro_chunk)) {
                $audio_chunks[] = array(
                    'type' => 'intro',
                    'file' => $intro_chunk,
                    'speaker' => 'Intro',
                    'voice' => $intro_voice,
                );
            }
        }
        
        // Generate main content with proper voice mapping
        $content_chunks = $this->generate_script_audio($script_result['parsed_script'], $settings);
        
        if (is_wp_error($content_chunks)) {
            return $content_chunks;
        }
        
        $audio_chunks = array_merge($audio_chunks, $content_chunks);
        
        // Generate outro if provided
        if (!empty($settings['outro_text'])) {
            $outro_voice = $settings['voice_mapping']['outro'] ?? 'alloy';
            $outro_chunk = $this->generate_single_audio($settings['outro_text'], $outro_voice, $settings);
            
            if (!is_wp_error($outro_chunk)) {
                $audio_chunks[] = array(
                    'type' => 'outro',
                    'file' => $outro_chunk,
                    'speaker' => 'Outro',
                    'voice' => $outro_voice,
                );
            }
        }
        
        error_log('AIPG TTS: Generated ' . count($audio_chunks) . ' audio chunks successfully');
        
        return $audio_chunks;
    }
    
    /**
     * Generate audio for parsed script with voice mapping
     */
    private function generate_script_audio($parsed_script, $settings) {
        $audio_chunks = array();
        $current_chunk = '';
        $current_voice = null;
        $current_speaker = null;
        $chunk_index = 0;
        
        foreach ($parsed_script as $line) {
            $speaker = $line['speaker'];
            $text = $this->process_emotion_tags($line['text']);
            
            // Get voice for this speaker
            $voice = $this->get_voice_for_speaker($speaker, $settings['voice_mapping']);
            
            // If voice changes or chunk would exceed limit, generate current chunk
            if ($current_voice && ($voice !== $current_voice || strlen($current_chunk . ' ' . $text) > $this->chunk_limit)) {
                // Generate audio for current chunk
                $audio_file = $this->generate_single_audio($current_chunk, $current_voice, $settings);
                
                if (is_wp_error($audio_file)) {
                    error_log("AIPG TTS: Error generating chunk {$chunk_index} - " . $audio_file->get_error_message());
                    return $audio_file;
                }
                
                $audio_chunks[] = array(
                    'type' => 'content',
                    'speaker' => $current_speaker,
                    'voice' => $current_voice,
                    'file' => $audio_file,
                    'index' => $chunk_index++,
                );
                
                error_log("AIPG TTS: Generated chunk {$chunk_index} for {$current_speaker} using {$current_voice}");
                
                // Reset chunk
                $current_chunk = $text;
                $current_voice = $voice;
                $current_speaker = $speaker;
            } else {
                // Add to current chunk
                if ($current_chunk) {
                    $current_chunk .= ' ' . $text;
                } else {
                    $current_chunk = $text;
                }
                $current_voice = $voice;
                $current_speaker = $speaker;
            }
        }
        
        // Generate final chunk
        if ($current_chunk && $current_voice) {
            $audio_file = $this->generate_single_audio($current_chunk, $current_voice, $settings);
            
            if (!is_wp_error($audio_file)) {
                $audio_chunks[] = array(
                    'type' => 'content',
                    'speaker' => $current_speaker,
                    'voice' => $current_voice,
                    'file' => $audio_file,
                    'index' => $chunk_index,
                );
                
                error_log("AIPG TTS: Generated final chunk for {$current_speaker} using {$current_voice}");
            }
        }
        
        return $audio_chunks;
    }
    
    /**
     * Get voice for speaker based on mapping
     */
    private function get_voice_for_speaker($speaker, $voice_mapping) {
        // Direct match
        if (isset($voice_mapping[$speaker])) {
            return $voice_mapping[$speaker];
        }
        
        // Try to match host names (case insensitive)
        foreach ($voice_mapping as $mapped_speaker => $voice) {
            if (strcasecmp($speaker, $mapped_speaker) === 0) {
                return $voice;
            }
        }
        
        // Try to match "Host X" patterns
        if (stripos($speaker, 'Host') !== false) {
            preg_match('/Host\s*(\d+)/i', $speaker, $matches);
            if (!empty($matches[1])) {
                $host_num = $matches[1];
                if (isset($voice_mapping["Host {$host_num}"])) {
                    return $voice_mapping["Host {$host_num}"];
                }
            }
            // Fallback to Host 1 voice
            if (isset($voice_mapping['Host 1'])) {
                return $voice_mapping['Host 1'];
            }
        }
        
        // Guest fallback
        if (stripos($speaker, 'Guest') !== false || stripos($speaker, 'Expert') !== false) {
            if (isset($voice_mapping['Guest'])) {
                return $voice_mapping['Guest'];
            }
        }
        
        // Default fallback
        return 'alloy';
    }
    
    /**
     * Process emotion tags in text
     */
    private function process_emotion_tags($text) {
        // Emotion tags: [excited], [thoughtful], [concerned], [happy], [sad], [angry], [calm], [pause]
        // OpenAI doesn't support SSML emotions directly, but we adjust punctuation for better delivery
        
        $emotions = array(
            '[excited]' => '! ',
            '[thoughtful]' => '... ',
            '[concerned]' => '. ',
            '[happy]' => '! ',
            '[sad]' => '... ',
            '[angry]' => '! ',
            '[calm]' => '. ',
            '[pause]' => '... ',
            '[curious]' => '? ',
        );
        
        foreach ($emotions as $tag => $replacement) {
            $text = str_ireplace($tag, $replacement, $text);
        }
        
        return $text;
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
            'size' => filesize($filepath),
        );
    }
    
    /**
     * Merge audio chunks into final podcast
     */
    public function merge_audio_chunks($chunks) {
        if (empty($chunks)) {
            return new WP_Error('no_chunks', 'No audio chunks to merge');
        }
        
        // Filter out invalid chunks
        $valid_chunks = array_filter($chunks, function($chunk) {
            return isset($chunk['file']['path']) && file_exists($chunk['file']['path']);
        });
        
        if (empty($valid_chunks)) {
            return new WP_Error('no_valid_chunks', 'No valid audio chunks found');
        }
        
        if (count($valid_chunks) === 1) {
            error_log('AIPG: Only one chunk, using it directly');
            return reset($valid_chunks)['file'];
        }
        
        error_log('AIPG: Merging ' . count($valid_chunks) . ' audio chunks');
        
        // Try FFmpeg first
        $ffmpeg_path = $this->is_ffmpeg_available();
        if ($ffmpeg_path && $this->is_exec_available()) {
            error_log('AIPG: Using FFmpeg for merging: ' . $ffmpeg_path);
            $result = $this->merge_with_ffmpeg($valid_chunks, $ffmpeg_path);
            if (!is_wp_error($result)) {
                return $result;
            }
            error_log('AIPG: FFmpeg merge failed, trying PHP fallback');
        } else {
            error_log('AIPG: FFmpeg not available, using PHP fallback');
        }
        
        // Fallback to PHP concatenation
        return $this->simple_merge_fallback($valid_chunks);
    }
    
    /**
     * Merge using FFmpeg
     */
    private function merge_with_ffmpeg($chunks, $ffmpeg_path) {
        $upload_dir = wp_upload_dir();
        $podcast_dir = $upload_dir['basedir'] . '/ai-podcasts/';
        
        if (!file_exists($podcast_dir)) {
            wp_mkdir_p($podcast_dir);
        }
        
        // Create temporary file list
        $temp_list = $podcast_dir . 'filelist_' . uniqid() . '.txt';
        $file_list_content = '';
        
        foreach ($chunks as $chunk) {
            if (isset($chunk['file']['path']) && file_exists($chunk['file']['path'])) {
                $file_list_content .= "file '" . $chunk['file']['path'] . "'\n";
            }
        }
        
        if (empty($file_list_content)) {
            return new WP_Error('no_files', 'No valid files to merge');
        }
        
        file_put_contents($temp_list, $file_list_content);
        
        // Output file
        $output_file = $podcast_dir . 'podcast_merged_' . uniqid() . '.mp3';
        
        // FFmpeg command with audio normalization
        $command = sprintf(
            '%s -f concat -safe 0 -i %s -af "loudnorm=I=-16:TP=-1.5:LRA=11" -codec:a libmp3lame -q:a 2 %s 2>&1',
            escapeshellarg($ffmpeg_path),
            escapeshellarg($temp_list),
            escapeshellarg($output_file)
        );
        
        error_log('AIPG: Running FFmpeg command');
        
        $exec_output = array();
        $return_var = 0;
        
        try {
            @exec($command, $exec_output, $return_var);
            
            error_log('AIPG: FFmpeg exit code: ' . $return_var);
            if (!empty($exec_output)) {
                error_log('AIPG: FFmpeg output: ' . implode("\n", array_slice($exec_output, 0, 10)));
            }
        } catch (Exception $e) {
            error_log('AIPG: FFmpeg execution exception: ' . $e->getMessage());
            @unlink($temp_list);
            return new WP_Error('ffmpeg_error', $e->getMessage());
        }
        
        // Clean up temp file
        @unlink($temp_list);
        
        // Check if merge succeeded
        if ($return_var !== 0 || !file_exists($output_file) || filesize($output_file) == 0) {
            error_log('AIPG: FFmpeg failed (exit: ' . $return_var . ')');
            if (file_exists($output_file)) {
                @unlink($output_file);
            }
            return new WP_Error('ffmpeg_failed', 'FFmpeg merge failed');
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
     * Simple PHP fallback merge
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
                        // For subsequent chunks, try to skip ID3v2 tag
                        if (substr($content, 0, 3) === 'ID3') {
                            // Get tag size from header
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
                }
            }
        }
        
        if (empty($merged_content)) {
            error_log('AIPG: No content to merge, using first chunk');
            foreach ($chunks as $chunk) {
                if (isset($chunk['file']['path']) && file_exists($chunk['file']['path'])) {
                    return $chunk['file'];
                }
            }
            return new WP_Error('merge_failed', 'No valid audio chunks found');
        }
        
        error_log('AIPG: Total merged size: ' . $total_size . ' bytes');
        
        $result = file_put_contents($output_file, $merged_content);
        
        if ($result === false) {
            error_log('AIPG: Binary merge write failed');
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
     * Check if exec() is available
     */
    private function is_exec_available() {
        if (!function_exists('exec')) {
            error_log('AIPG: exec() function does not exist');
            return false;
        }
        
        $disabled = ini_get('disable_functions');
        if (!empty($disabled)) {
            $disabled_funcs = array_map('trim', explode(',', $disabled));
            if (in_array('exec', $disabled_funcs)) {
                error_log('AIPG: exec() is in disable_functions');
                return false;
            }
        }
        
        try {
            $test_output = array();
            $test_return = 999;
            @exec('echo test 2>&1', $test_output, $test_return);
            
            if ($test_return === 0 || !empty($test_output)) {
                error_log('AIPG: exec() test successful');
                return true;
            } else {
                error_log('AIPG: exec() test failed');
                return false;
            }
        } catch (Exception $e) {
            error_log('AIPG: exec() test threw exception');
            return false;
        }
    }
    
    /**
     * Check if FFmpeg is available
     */
    private function is_ffmpeg_available() {
        if (!$this->is_exec_available()) {
            return false;
        }
        
        $paths = array('/bin/ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg');
        
        foreach ($paths as $path) {
            $output = array();
            $return_var = 0;
            
            $result = @exec($path . ' -version 2>&1', $output, $return_var);
            
            if ($return_var === 0 && !empty($result)) {
                error_log('AIPG: FFmpeg found at: ' . $path);
                return $path;
            }
        }
        
        error_log('AIPG: FFmpeg not found');
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
    
    /**
     * Generate preview audio for voice testing
     */
    public function generate_voice_preview($voice = 'alloy', $text = null) {
        if (!$text) {
            $text = "Hi! This is a preview of the {$voice} voice. How do you like the sound?";
        }
        
        $settings = array(
            'model' => 'tts-1',
            'speed' => 1.0,
        );
        
        $audio_file = $this->generate_single_audio($text, $voice, $settings);
        
        if (is_wp_error($audio_file)) {
            return $audio_file;
        }
        
        return $audio_file['url'];
    }
}