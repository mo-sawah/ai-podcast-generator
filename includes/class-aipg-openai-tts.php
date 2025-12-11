<?php
/**
 * COMPLETE MULTILINGUAL OpenAI TTS Handler
 * - ALL original features preserved
 * - Full Greek and 30+ language support added
 * - Smart chunking system maintained
 * - Stream-based merging kept
 * - Voice preview functionality intact
 */

if (!defined('ABSPATH')) exit;

class AIPG_OpenAI_TTS {
    
    private $api_key;
    private $base_url = 'https://api.openai.com/v1/audio/speech';
    private $chunk_limit = 4000;
    
    // Language codes and TTS compatibility
    private $language_support = array(
        'English' => array('code' => 'en', 'supported' => true),
        'Greek' => array('code' => 'el', 'supported' => true),
        'Spanish' => array('code' => 'es', 'supported' => true),
        'French' => array('code' => 'fr', 'supported' => true),
        'German' => array('code' => 'de', 'supported' => true),
        'Italian' => array('code' => 'it', 'supported' => true),
        'Portuguese' => array('code' => 'pt', 'supported' => true),
        'Dutch' => array('code' => 'nl', 'supported' => true),
        'Polish' => array('code' => 'pl', 'supported' => true),
        'Russian' => array('code' => 'ru', 'supported' => true),
        'Turkish' => array('code' => 'tr', 'supported' => true),
        'Arabic' => array('code' => 'ar', 'supported' => true),
        'Chinese' => array('code' => 'zh', 'supported' => true),
        'Japanese' => array('code' => 'ja', 'supported' => true),
        'Korean' => array('code' => 'ko', 'supported' => true),
        'Hindi' => array('code' => 'hi', 'supported' => true),
        'Swedish' => array('code' => 'sv', 'supported' => true),
        'Norwegian' => array('code' => 'no', 'supported' => true),
        'Danish' => array('code' => 'da', 'supported' => true),
        'Finnish' => array('code' => 'fi', 'supported' => true),
        'Czech' => array('code' => 'cs', 'supported' => true),
        'Romanian' => array('code' => 'ro', 'supported' => true),
        'Bulgarian' => array('code' => 'bg', 'supported' => true),
        'Ukrainian' => array('code' => 'uk', 'supported' => true),
        'Croatian' => array('code' => 'hr', 'supported' => true),
        'Serbian' => array('code' => 'sr', 'supported' => true),
        'Slovak' => array('code' => 'sk', 'supported' => true),
        'Hungarian' => array('code' => 'hu', 'supported' => true),
        'Indonesian' => array('code' => 'id', 'supported' => true),
        'Malay' => array('code' => 'ms', 'supported' => true),
        'Vietnamese' => array('code' => 'vi', 'supported' => true),
        'Thai' => array('code' => 'th', 'supported' => true),
        'Hebrew' => array('code' => 'he', 'supported' => true),
        'Persian' => array('code' => 'fa', 'supported' => true),
    );
    
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
     * Check if a language is supported
     */
    public function is_language_supported($language) {
        return isset($this->language_support[$language]) && 
               $this->language_support[$language]['supported'];
    }
    
    /**
     * Get language code
     */
    public function get_language_code($language) {
        return $this->language_support[$language]['code'] ?? 'en';
    }
    
    /**
     * MULTILINGUAL: Generate podcast audio with proper voice mapping
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
            'language' => 'English',
        );
        
        $settings = wp_parse_args($settings, $defaults);
        
        // Normalize voice mapping keys (remove extra spaces, standardize case)
        if (!empty($settings['voice_mapping'])) {
            $normalized_mapping = array();
            foreach ($settings['voice_mapping'] as $speaker => $voice) {
                $normalized_speaker = trim($speaker);
                $normalized_mapping[$normalized_speaker] = $voice;
            }
            $settings['voice_mapping'] = $normalized_mapping;
        }
        
        $language = $settings['language'];
        error_log('AIPG TTS: ===== MULTILINGUAL AUDIO GENERATION START =====');
        error_log('AIPG TTS: Language: ' . $language);
        error_log('AIPG TTS: Segments count: ' . count($script_result['parsed_script']));
        error_log('AIPG TTS: Voice mapping: ' . json_encode($settings['voice_mapping']));
        
        // Verify language support
        if (!$this->is_language_supported($language)) {
            error_log('AIPG TTS: WARNING - Language not officially listed: ' . $language);
            error_log('AIPG TTS: Proceeding anyway - OpenAI TTS supports many languages');
        } else {
            error_log('AIPG TTS: ✓ Language confirmed supported: ' . $language);
        }
        
        $audio_chunks = array();
        
        // Intro
        if (!empty($settings['intro_text'])) {
            $intro_voice = $settings['voice_mapping']['Intro'] ?? 
                          $settings['voice_mapping'][array_key_first($settings['voice_mapping'])] ?? 
                          'alloy';
            $intro_chunk = $this->generate_single_audio($settings['intro_text'], $intro_voice, $settings);
            
            if (!is_wp_error($intro_chunk)) {
                $audio_chunks[] = array(
                    'type' => 'intro',
                    'file' => $intro_chunk,
                    'speaker' => 'Intro',
                    'voice' => $intro_voice,
                    'language' => $language,
                );
            }
        }
        
        // Main content with smart chunking
        $content_chunks = $this->generate_script_audio($script_result['parsed_script'], $settings);
        
        if (is_wp_error($content_chunks)) {
            return $content_chunks;
        }
        
        $audio_chunks = array_merge($audio_chunks, $content_chunks);
        
        // Outro
        if (!empty($settings['outro_text'])) {
            $outro_voice = $settings['voice_mapping']['Outro'] ?? 
                          $settings['voice_mapping'][array_key_first($settings['voice_mapping'])] ?? 
                          'alloy';
            $outro_chunk = $this->generate_single_audio($settings['outro_text'], $outro_voice, $settings);
            
            if (!is_wp_error($outro_chunk)) {
                $audio_chunks[] = array(
                    'type' => 'outro',
                    'file' => $outro_chunk,
                    'speaker' => 'Outro',
                    'voice' => $outro_voice,
                    'language' => $language,
                );
            }
        }
        
        error_log('AIPG TTS: Generated ' . count($audio_chunks) . ' audio chunks in ' . $language);
        error_log('AIPG TTS: ===== AUDIO GENERATION COMPLETE =====');
        
        return $audio_chunks;
    }
    
    /**
     * Generate audio for parsed script with FIXED voice mapping and SMART CHUNKING
     */
    private function generate_script_audio($parsed_script, $settings) {
        $audio_chunks = array();
        $current_chunk = '';
        $current_voice = null;
        $current_speaker = null;
        $chunk_index = 0;
        $max_chunks = 500; // Safety limit to prevent infinite loops
        
        $language = $settings['language'] ?? 'English';
        
        error_log("AIPG TTS: Processing " . count($parsed_script) . " script segments");
        
        foreach ($parsed_script as $segment_index => $line) {
            // Safety check for infinite loops
            if ($chunk_index >= $max_chunks) {
                error_log("AIPG TTS: ⚠ WARNING - Reached maximum chunk limit ({$max_chunks}), stopping");
                break;
            }
            
            $speaker = trim($line['speaker']);
            $text = $this->process_emotion_tags($line['text']);
            
            // Skip empty text
            if (empty($text)) {
                continue;
            }
            
            // Get voice for this speaker
            $voice = $this->get_voice_for_speaker($speaker, $settings['voice_mapping']);
            
            // Log progress every 10 segments
            if ($segment_index % 10 === 0) {
                error_log("AIPG TTS: Processing segment {$segment_index}/" . count($parsed_script) . " - Speaker: {$speaker}, Voice: {$voice}");
            }
            
            // If voice changes or chunk would exceed limit, generate current chunk
            if ($current_voice && ($voice !== $current_voice || strlen($current_chunk . ' ' . $text) > $this->chunk_limit)) {
                $audio_file = $this->generate_single_audio($current_chunk, $current_voice, $settings);
                
                if (is_wp_error($audio_file)) {
                    error_log("AIPG TTS: Error chunk {$chunk_index} - " . $audio_file->get_error_message());
                    return $audio_file;
                }
                
                $audio_chunks[] = array(
                    'type' => 'content',
                    'speaker' => $current_speaker,
                    'voice' => $current_voice,
                    'file' => $audio_file,
                    'index' => $chunk_index++,
                    'language' => $language,
                );
                
                if ($chunk_index % 5 === 0) {
                    error_log("AIPG TTS: ✓ Generated chunk {$chunk_index} - {$current_speaker} ({$current_voice})");
                }
                
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
                    'language' => $language,
                );
                
                error_log("AIPG TTS: ✓ Generated final chunk {$chunk_index} - {$current_speaker} ({$current_voice})");
            }
        }
        
        error_log("AIPG TTS: Completed audio generation - Total chunks: " . count($audio_chunks));
        
        return $audio_chunks;
    }
    
    /**
     * FIXED: Get voice for speaker with GREEK UNICODE support
     */
    private function get_voice_for_speaker($speaker, $voice_mapping) {
        $speaker = trim($speaker);
        
        // Normalize speaker name for Greek characters
        $normalized_speaker = $this->normalize_speaker_name($speaker);
        
        // Log for debugging (only log every 10th lookup to avoid spam)
        static $lookup_count = 0;
        $lookup_count++;
        if ($lookup_count % 10 === 1) {
            error_log("AIPG TTS: Looking up voice for speaker: '{$speaker}' (normalized: '{$normalized_speaker}')");
        }
        
        // Build normalized mapping cache
        static $normalized_cache = null;
        if ($normalized_cache === null) {
            $normalized_cache = array();
            foreach ($voice_mapping as $mapped_speaker => $voice) {
                $normalized_mapped = $this->normalize_speaker_name($mapped_speaker);
                $normalized_cache[$normalized_mapped] = $voice;
                
                // Also cache original for direct match
                $normalized_cache[$mapped_speaker] = $voice;
            }
            error_log("AIPG TTS: Voice mapping cache built with " . count($normalized_cache) . " entries");
        }
        
        // 1. Try normalized match
        if (isset($normalized_cache[$normalized_speaker])) {
            return $normalized_cache[$normalized_speaker];
        }
        
        // 2. Try direct original match
        if (isset($voice_mapping[$speaker])) {
            return $voice_mapping[$speaker];
        }
        
        // 3. Try case-insensitive match
        foreach ($voice_mapping as $mapped_speaker => $voice) {
            if (mb_strtolower($speaker, 'UTF-8') === mb_strtolower($mapped_speaker, 'UTF-8')) {
                return $voice;
            }
        }
        
        // 4. Try partial match
        foreach ($voice_mapping as $mapped_speaker => $voice) {
            $speaker_lower = mb_strtolower($speaker, 'UTF-8');
            $mapped_lower = mb_strtolower($mapped_speaker, 'UTF-8');
            
            if (mb_strpos($speaker_lower, $mapped_lower) !== false || 
                mb_strpos($mapped_lower, $speaker_lower) !== false) {
                return $voice;
            }
        }
        
        // Fallback: use first voice in mapping
        $fallback_voice = !empty($voice_mapping) ? reset($voice_mapping) : 'alloy';
        
        if ($lookup_count % 10 === 1) {
            error_log("AIPG TTS: ⚠ No match found for '{$speaker}', using fallback: {$fallback_voice}");
        }
        
        return $fallback_voice;
    }
    
    /**
     * Normalize speaker names for Greek Unicode matching
     */
    private function normalize_speaker_name($name) {
        $name = trim($name);
        
        // Remove emotion tags
        $name = preg_replace('/\s*\[.*?\]\s*/', '', $name);
        
        // Normalize Unicode (NFC form)
        if (class_exists('Normalizer')) {
            $name = Normalizer::normalize($name, Normalizer::FORM_C);
        }
        
        // Convert to lowercase for case-insensitive matching
        $name = mb_strtolower($name, 'UTF-8');
        
        // Remove extra whitespace
        $name = preg_replace('/\s+/', ' ', $name);
        
        return $name;
    }
    
    /**
     * Process emotion tags
     */
    private function process_emotion_tags($text) {
        // Remove emotion tags for TTS (they're already in the text structure)
        $text = preg_replace('/\[(excited|thoughtful|concerned|happy|curious|calm|pause)\]\s*/i', '', $text);
        return trim($text);
    }
    
    /**
     * MULTILINGUAL: Generate single audio file
     * OpenAI TTS automatically detects and handles the language from the text
     */
    private function generate_single_audio($text, $voice = 'alloy', $settings = array()) {
        if (empty($text)) {
            return new WP_Error('empty_text', 'No text to generate audio from');
        }
        
        $model = $settings['model'] ?? 'tts-1-hd';
        $speed = $settings['speed'] ?? 1.0;
        
        $response = wp_remote_post($this->base_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $model,
                'input' => $text,
                'voice' => $voice,
                'speed' => $speed,
                'response_format' => 'mp3',
            )),
            'timeout' => 120,
        ));
        
        if (is_wp_error($response)) {
            error_log('AIPG TTS: API request failed - ' . $response->get_error_message());
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = $error_data['error']['message'] ?? 'Unknown error';
            
            error_log('AIPG TTS: API error ' . $status_code . ' - ' . $error_message);
            return new WP_Error('tts_api_error', $error_message);
        }
        
        $audio_content = wp_remote_retrieve_body($response);
        
        if (empty($audio_content)) {
            return new WP_Error('empty_response', 'Empty audio response from API');
        }
        
        // Save audio file
        $upload_dir = wp_upload_dir();
        $podcast_dir = $upload_dir['basedir'] . '/ai-podcasts/';
        
        if (!file_exists($podcast_dir)) {
            wp_mkdir_p($podcast_dir);
        }
        
        $filename = 'chunk_' . uniqid() . '_' . sanitize_file_name($voice) . '.mp3';
        $file_path = $podcast_dir . $filename;
        
        $result = file_put_contents($file_path, $audio_content);
        
        if ($result === false) {
            return new WP_Error('file_write_error', 'Failed to write audio file');
        }
        
        return array(
            'path' => $file_path,
            'url' => $upload_dir['baseurl'] . '/ai-podcasts/' . $filename,
            'size' => $result,
        );
    }
    
    /**
     * ROBUST STREAM MERGE - Low Memory & Safe
     */
    public function merge_audio_chunks($chunks) {
        if (empty($chunks)) {
            return new WP_Error('no_chunks', 'No audio chunks to merge');
        }
        
        // 1. Setup paths
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/ai-podcasts/';
        
        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
        }

        $output_filename = 'podcast_merged_' . uniqid() . '.mp3';
        $output_path = $base_dir . $output_filename;
        $output_url  = $upload_dir['baseurl'] . '/ai-podcasts/' . $output_filename;
        
        error_log('AIPG Merge: Starting safe stream merge for ' . count($chunks) . ' chunks');

        // 2. Open output file for writing (Binary Mode)
        $output_handle = @fopen($output_path, 'wb');
        if ($output_handle === false) {
            error_log('AIPG Merge: Failed to open output file for writing: ' . $output_path);
            return new WP_Error('write_error', 'Could not create output file. Check permissions.');
        }
        
        // 3. Loop through chunks and stream them into the output file
        $total_bytes = 0;
        
        foreach ($chunks as $index => $chunk) {
            $file_path = $chunk['file']['path'] ?? '';
            
            if (empty($file_path) || !file_exists($file_path)) {
                error_log("AIPG Merge: Warning - Chunk $index file missing at: $file_path");
                continue;
            }
            
            // Open input chunk for reading
            $input_handle = @fopen($file_path, 'rb');
            if ($input_handle === false) {
                error_log("AIPG Merge: Could not read chunk $index");
                continue;
            }
            
            // Read and write in small 8KB buffers (Keeps RAM usage extremely low)
            while (!feof($input_handle)) {
                $buffer = fread($input_handle, 8192); // Read 8KB
                fwrite($output_handle, $buffer);      // Write 8KB
            }
            
            // Close input file
            fclose($input_handle);
            
            // Calculate size for logging
            $filesize = filesize($file_path);
            $total_bytes += $filesize;
            
            // Log progress occasionally to keep connection alive
            if ($index % 5 === 0) {
                error_log("AIPG Merge: Merged $index chunks...");
            }
        }
        
        // 4. Close output file
        fclose($output_handle);
        
        // Verify success
        if (!file_exists($output_path) || filesize($output_path) === 0) {
            error_log('AIPG Merge: Failed - Output file is empty');
            return new WP_Error('merge_failed', 'Merged file is empty');
        }
        
        error_log("AIPG Merge: Success! Created $output_filename ($total_bytes bytes)");
        
        return array(
            'path' => $output_path,
            'url'  => $output_url,
            'size' => $total_bytes
        );
    }
    
    /**
     * IMPROVED: Find FFmpeg executable
     */
    private function find_ffmpeg() {
        if (!$this->is_exec_available()) {
            return false;
        }
        
        // Common paths to check
        $paths = array(
            'ffmpeg',               // System PATH
            '/usr/bin/ffmpeg',      // Ubuntu/Debian
            '/usr/local/bin/ffmpeg', // macOS/Homebrew
            '/opt/local/bin/ffmpeg', // MacPorts
            '/bin/ffmpeg',          // Some systems
        );
        
        foreach ($paths as $path) {
            $output = array();
            $return_var = 999;
            
            $result = @exec($path . ' -version 2>&1', $output, $return_var);
            
            if ($return_var === 0 && !empty($result) && stripos($result, 'ffmpeg') !== false) {
                error_log('AIPG: ✓ FFmpeg found at: ' . $path);
                error_log('AIPG: FFmpeg version: ' . $result);
                return $path;
            }
        }
        
        error_log('AIPG: FFmpeg not found in any standard location');
        return false;
    }
    
    /**
     * FFmpeg merge with improved error handling
     */
    private function ffmpeg_merge($chunks, $ffmpeg_path) {
        $upload_dir = wp_upload_dir();
        $podcast_dir = $upload_dir['basedir'] . '/ai-podcasts/';
        
        if (!file_exists($podcast_dir)) {
            wp_mkdir_p($podcast_dir);
        }
        
        // Create file list for FFmpeg concat
        $list_file = $podcast_dir . 'concat_list_' . uniqid() . '.txt';
        $list_content = '';
        
        foreach ($chunks as $chunk) {
            if (isset($chunk['file']['path']) && file_exists($chunk['file']['path'])) {
                // Escape single quotes in path and wrap in quotes
                $safe_path = str_replace("'", "'\\''", $chunk['file']['path']);
                $list_content .= "file '{$safe_path}'\n";
            }
        }
        
        if (empty($list_content)) {
            return new WP_Error('no_valid_chunks', 'No valid audio chunks found');
        }
        
        file_put_contents($list_file, $list_content);
        
        $output_file = $podcast_dir . 'podcast_merged_' . uniqid() . '.mp3';
        
        // FFmpeg concat command with audio re-encoding for compatibility
        $command = sprintf(
            '%s -f concat -safe 0 -i %s -c:a libmp3lame -b:a 128k -ar 44100 %s 2>&1',
            escapeshellcmd($ffmpeg_path),
            escapeshellarg($list_file),
            escapeshellarg($output_file)
        );
        
        error_log('AIPG: FFmpeg command: ' . $command);
        
        $output = array();
        $return_var = 999;
        
        try {
            exec($command, $output, $return_var);
        } catch (Exception $e) {
            @unlink($list_file);
            return new WP_Error('ffmpeg_exception', $e->getMessage());
        }
        
        @unlink($list_file);
        
        // Check if merge succeeded
        if ($return_var !== 0 || !file_exists($output_file) || filesize($output_file) == 0) {
            error_log('AIPG: FFmpeg failed (exit: ' . $return_var . ')');
            error_log('AIPG: FFmpeg output: ' . implode("\n", $output));
            
            if (file_exists($output_file)) {
                @unlink($output_file);
            }
            return new WP_Error('ffmpeg_failed', 'FFmpeg merge failed (exit ' . $return_var . ')');
        }
        
        $file_size = filesize($output_file);
        error_log('AIPG: ✓ FFmpeg SUCCESS! Size: ' . $file_size . ' bytes');
        
        return array(
            'path' => $output_file,
            'url' => $upload_dir['baseurl'] . '/ai-podcasts/' . basename($output_file),
            'size' => $file_size,
        );
    }
    
    /**
     * IMPROVED: Simple PHP concatenation fallback
     */
    private function simple_merge_fallback($chunks) {
        error_log('AIPG: Starting PHP binary concatenation');
        
        $upload_dir = wp_upload_dir();
        $podcast_dir = $upload_dir['basedir'] . '/ai-podcasts/';
        
        if (!file_exists($podcast_dir)) {
            wp_mkdir_p($podcast_dir);
        }
        
        $output_file = $podcast_dir . 'podcast_merged_' . uniqid() . '.mp3';
        $merged_content = '';
        $total_size = 0;
        
        foreach ($chunks as $index => $chunk) {
            if (!isset($chunk['file']['path']) || !file_exists($chunk['file']['path'])) {
                error_log("AIPG: Chunk {$index} file missing: " . ($chunk['file']['path'] ?? 'N/A'));
                continue;
            }
            
            $content = file_get_contents($chunk['file']['path']);
            
            if ($content === false) {
                error_log("AIPG: Failed to read chunk {$index}");
                continue;
            }
            
            // For first chunk, keep ID3 tag
            if ($index === 0) {
                $merged_content .= $content;
            } else {
                // For subsequent chunks, skip ID3 tag if present
                if (substr($content, 0, 3) === 'ID3') {
                    $size_bytes = substr($content, 6, 4);
                    $size = (ord($size_bytes[0]) & 0x7F) << 21 |
                           (ord($size_bytes[1]) & 0x7F) << 14 |
                           (ord($size_bytes[2]) & 0x7F) << 7 |
                           (ord($size_bytes[3]) & 0x7F);
                    $content = substr($content, 10 + $size);
                }
                $merged_content .= $content;
            }
            
            $chunk_size = strlen($content);
            $total_size += $chunk_size;
            error_log("AIPG: Merged chunk {$index}: {$chunk_size} bytes");
        }
        
        if (empty($merged_content)) {
            error_log('AIPG: No content merged, using first valid chunk');
            foreach ($chunks as $chunk) {
                if (isset($chunk['file']['path']) && file_exists($chunk['file']['path'])) {
                    return $chunk['file'];
                }
            }
            return new WP_Error('merge_failed', 'No valid audio chunks found');
        }
        
        $result = file_put_contents($output_file, $merged_content);
        
        if ($result === false) {
            error_log('AIPG: Failed to write merged file');
            return new WP_Error('write_failed', 'Failed to write merged file');
        }
        
        error_log('AIPG: ✓ PHP merge SUCCESS! Size: ' . $result . ' bytes (from ' . $total_size . ' bytes)');
        
        return array(
            'path' => $output_file,
            'url' => $upload_dir['baseurl'] . '/ai-podcasts/' . basename($output_file),
            'size' => $result,
        );
    }
    
    /**
     * Check if exec() is available
     */
    private function is_exec_available() {
        if (!function_exists('exec')) {
            return false;
        }
        
        $disabled = ini_get('disable_functions');
        if (!empty($disabled)) {
            $disabled_funcs = array_map('trim', explode(',', $disabled));
            if (in_array('exec', $disabled_funcs)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Test TTS access
     */
    public function test_tts_access() {
        $models = array('tts-1', 'tts-1-hd');
        $results = array();
        
        foreach ($models as $model) {
            $response = wp_remote_post($this->base_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'model' => $model,
                    'input' => 'Test',
                    'voice' => 'alloy',
                )),
                'timeout' => 30,
            ));
            
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code === 200) {
                $results[$model] = array(
                    'success' => true,
                    'message' => 'Model accessible',
                );
            } else {
                $body = wp_remote_retrieve_body($response);
                $error_data = json_decode($body, true);
                $results[$model] = array(
                    'success' => false,
                    'error' => $error_data['error']['message'] ?? 'Unknown error',
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Get available voices
     */
    public function get_available_voices() {
        return $this->voices;
    }
    
    /**
     * Generate voice preview
     */
    public function generate_voice_preview($voice = 'alloy', $text = null) {
        if (!$text) {
            $text = "Hi! This is a preview of the {$voice} voice. How do you like the sound?";
        }
        
        $settings = array('model' => 'tts-1', 'speed' => 1.0);
        $audio_file = $this->generate_single_audio($text, $voice, $settings);
        
        if (is_wp_error($audio_file)) {
            return $audio_file;
        }
        
        return $audio_file['url'];
    }
}