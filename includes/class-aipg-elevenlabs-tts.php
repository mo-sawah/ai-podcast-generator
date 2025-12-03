<?php
/**
 * ElevenLabs TTS Integration - Latest API (2024)
 */

if (!defined('ABSPATH')) exit;

class AIPG_ElevenLabs_TTS {
    
    private $api_key;
    private $api_endpoint = 'https://api.elevenlabs.io';
    private $model_id;
    
    public function __construct() {
        $this->api_key = get_option('aipg_elevenlabs_key', '');
        $this->model_id = get_option('aipg_elevenlabs_model', 'eleven_flash_v2_5');
    }
    
    /**
     * Get available models
     */
    public function get_available_models() {
        return array(
            'eleven_v3' => array(
                'name' => 'Eleven v3',
                'description' => 'Most advanced, highest emotional range',
                'cost' => '$0.30 per 1K chars',
                'quality' => 'Highest',
                'latency' => 'Standard'
            ),
            'eleven_flash_v2_5' => array(
                'name' => 'Flash v2.5',
                'description' => 'Fastest, ultra-low latency (75ms)',
                'cost' => '$0.10 per 1K chars',
                'quality' => 'High',
                'latency' => 'Ultra-Low'
            ),
            'eleven_turbo_v2_5' => array(
                'name' => 'Turbo v2.5',
                'description' => 'Balanced speed and quality',
                'cost' => '$0.20 per 1K chars',
                'quality' => 'Very High',
                'latency' => 'Low'
            ),
            'eleven_multilingual_v2' => array(
                'name' => 'Multilingual v2',
                'description' => '32 languages support',
                'cost' => '$0.30 per 1K chars',
                'quality' => 'High',
                'latency' => 'Standard'
            )
        );
    }
    
    /**
     * Get available voices from ElevenLabs
     */
    public function get_available_voices() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'ElevenLabs API key not configured');
        }
        
        $response = wp_remote_get(
            $this->api_endpoint . '/v1/voices',
            array(
                'headers' => array(
                    'xi-api-key' => $this->api_key
                ),
                'timeout' => 15
            )
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['voices'])) {
            return new WP_Error('invalid_response', 'Invalid API response');
        }
        
        // Format voices for our plugin
        $voices = array();
        foreach ($data['voices'] as $voice) {
            $voices[$voice['voice_id']] = array(
                'name' => $voice['name'],
                'description' => $voice['description'] ?? '',
                'category' => $voice['category'] ?? 'custom',
                'labels' => $voice['labels'] ?? array()
            );
        }
        
        return $voices;
    }
    
    /**
     * Get default voices (built-in ElevenLabs voices)
     */
    public function get_default_voices() {
        return array(
            '21m00Tcm4TlvDq8ikWAM' => array(
                'name' => 'Rachel',
                'description' => 'Female, Professional',
                'gender' => 'female'
            ),
            'AZnzlk1XvdvUeBnXmlld' => array(
                'name' => 'Domi',
                'description' => 'Female, Energetic',
                'gender' => 'female'
            ),
            'EXAVITQu4vr4xnSDxMaL' => array(
                'name' => 'Bella',
                'description' => 'Female, Soft',
                'gender' => 'female'
            ),
            'ErXwobaYiN019PkySvjV' => array(
                'name' => 'Antoni',
                'description' => 'Male, Balanced',
                'gender' => 'male'
            ),
            'VR6AewLTigWG4xSOukaG' => array(
                'name' => 'Arnold',
                'description' => 'Male, Authoritative',
                'gender' => 'male'
            ),
            'pNInz6obpgDQGcFmaJgB' => array(
                'name' => 'Adam',
                'description' => 'Male, Professional',
                'gender' => 'male'
            ),
            'yoZ06aMxZJJ28mfd3POQ' => array(
                'name' => 'Sam',
                'description' => 'Male, Conversational',
                'gender' => 'male'
            ),
            'MF3mGyEYCl7XYWbV9V6O' => array(
                'name' => 'Elli',
                'description' => 'Female, Young',
                'gender' => 'female'
            ),
            'TxGEqnHWrfWFTfGW9XjX' => array(
                'name' => 'Josh',
                'description' => 'Male, Expressive',
                'gender' => 'male'
            ),
            'IKne3meq5aSn9XLyUdCD' => array(
                'name' => 'Charlie',
                'description' => 'Male, Casual',
                'gender' => 'male'
            ),
            'onwK4e9ZLuTAKqWW03F9' => array(
                'name' => 'Daniel',
                'description' => 'Male, Deep',
                'gender' => 'male'
            ),
            'JBFqnCBsd6RMkjVDRZzb' => array(
                'name' => 'George',
                'description' => 'Male, British',
                'gender' => 'male'
            )
        );
    }
    
    /**
     * Test API access
     */
    public function test_api_access() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'API key not configured'
            );
        }
        
        // Try to get voices to test API access
        $response = wp_remote_get(
            $this->api_endpoint . '/v1/voices',
            array(
                'headers' => array(
                    'xi-api-key' => $this->api_key
                ),
                'timeout' => 15
            )
        );
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 401) {
            return array(
                'success' => false,
                'message' => 'Invalid API key'
            );
        }
        
        if ($code !== 200) {
            return array(
                'success' => false,
                'message' => 'API error (code ' . $code . ')'
            );
        }
        
        // Try to generate a small test audio
        $test_result = $this->generate_test_audio();
        
        return $test_result;
    }
    
    /**
     * Generate test audio
     */
    private function generate_test_audio() {
        $voice_id = '21m00Tcm4TlvDq8ikWAM'; // Rachel (default female voice)
        
        $response = wp_remote_post(
            $this->api_endpoint . '/v1/text-to-speech/' . $voice_id,
            array(
                'headers' => array(
                    'xi-api-key' => $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'text' => 'ElevenLabs test successful!',
                    'model_id' => $this->model_id
                )),
                'timeout' => 30
            )
        );
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Test generation failed: ' . $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200) {
            return array(
                'success' => true,
                'message' => 'API connected successfully!',
                'model' => $this->model_id
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Generation failed (code ' . $code . ')'
        );
    }
    
    /**
     * Generate single audio segment
     */
    public function generate_single_audio($text, $voice_id, $output_file) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'ElevenLabs API key not configured');
        }
        
        // Use default voice if not provided
        if (empty($voice_id)) {
            $voice_id = '21m00Tcm4TlvDq8ikWAM'; // Rachel
        }
        
        // Voice settings for optimal quality
        $voice_settings = array(
            'stability' => 0.5,
            'similarity_boost' => 0.75,
            'style' => 0.5,
            'use_speaker_boost' => true
        );
        
        $request_body = array(
            'text' => $text,
            'model_id' => $this->model_id,
            'voice_settings' => $voice_settings,
            'output_format' => 'mp3_44100_128'
        );
        
        error_log('AIPG ElevenLabs: Generating audio for voice: ' . $voice_id);
        
        $response = wp_remote_post(
            $this->api_endpoint . '/v1/text-to-speech/' . $voice_id,
            array(
                'headers' => array(
                    'xi-api-key' => $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($request_body),
                'timeout' => 60
            )
        );
        
        if (is_wp_error($response)) {
            error_log('AIPG ElevenLabs Error: ' . $response->get_error_message());
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            $error_body = wp_remote_retrieve_body($response);
            error_log('AIPG ElevenLabs API Error (code ' . $code . '): ' . $error_body);
            
            if ($code === 401) {
                return new WP_Error('auth_failed', 'Invalid API key');
            } elseif ($code === 429) {
                return new WP_Error('rate_limit', 'Rate limit exceeded - please wait and try again');
            } elseif ($code === 400) {
                return new WP_Error('invalid_request', 'Invalid request - check voice ID and text');
            } else {
                return new WP_Error('api_error', 'ElevenLabs API error (code ' . $code . ')');
            }
        }
        
        $audio_data = wp_remote_retrieve_body($response);
        
        if (empty($audio_data)) {
            return new WP_Error('empty_response', 'Empty audio response from API');
        }
        
        // Save audio file
        $saved = file_put_contents($output_file, $audio_data);
        
        if ($saved === false) {
            error_log('AIPG ElevenLabs: Failed to save audio file: ' . $output_file);
            return new WP_Error('save_failed', 'Failed to save audio file');
        }
        
        error_log('AIPG ElevenLabs: Audio saved successfully (' . $saved . ' bytes)');
        
        return $output_file;
    }
    
    /**
     * Generate voice preview
     */
    public function generate_voice_preview($voice_id, $text = '') {
        if (empty($text)) {
            $text = 'Hello! This is a preview of this voice.';
        }
        
        $upload_dir = wp_upload_dir();
        $preview_dir = $upload_dir['basedir'] . '/aipg-previews';
        
        if (!file_exists($preview_dir)) {
            wp_mkdir_p($preview_dir);
        }
        
        $preview_file = $preview_dir . '/preview-' . md5($voice_id . $text) . '.mp3';
        
        // Check if preview already exists
        if (file_exists($preview_file)) {
            return $upload_dir['baseurl'] . '/aipg-previews/' . basename($preview_file);
        }
        
        $result = $this->generate_single_audio($text, $voice_id, $preview_file);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $upload_dir['baseurl'] . '/aipg-previews/' . basename($preview_file);
    }
    
    /**
     * Get voice name from voice ID
     */
    public function get_voice_name($voice_id) {
        $default_voices = $this->get_default_voices();
        
        if (isset($default_voices[$voice_id])) {
            return $default_voices[$voice_id]['name'];
        }
        
        return 'Custom Voice';
    }
    
    /**
     * Map OpenAI voice to ElevenLabs voice (for compatibility)
     */
    public function map_openai_voice($openai_voice) {
        $mapping = array(
            'alloy' => 'pNInz6obpgDQGcFmaJgB',  // Adam
            'echo' => 'TxGEqnHWrfWFTfGW9XjX',   // Josh
            'fable' => 'IKne3meq5aSn9XLyUdCD',  // Charlie
            'onyx' => 'onwK4e9ZLuTAKqWW03F9',   // Daniel
            'nova' => '21m00Tcm4TlvDq8ikWAM',   // Rachel
            'shimmer' => 'EXAVITQu4vr4xnSDxMaL', // Bella
            'ash' => 'VR6AewLTigWG4xSOukaG',    // Arnold
            'ballad' => 'ErXwobaYiN019PkySvjV', // Antoni
            'coral' => 'AZnzlk1XvdvUeBnXmlld',  // Domi
            'sage' => 'yoZ06aMxZJJ28mfd3POQ',   // Sam
            'verse' => 'MF3mGyEYCl7XYWbV9V6O'  // Elli
        );
        
        return $mapping[$openai_voice] ?? '21m00Tcm4TlvDq8ikWAM'; // Default to Rachel
    }
    
    /**
     * Generate podcast audio (main method)
     */
    public function generate_podcast_audio($script_result, $settings = array()) {
        if (empty($script_result['parsed_script'])) {
            return new WP_Error('no_script', 'No parsed script available');
        }
        
        $defaults = array(
            'voice_mapping' => array(),
            'intro_text' => '',
            'outro_text' => '',
        );
        
        $settings = wp_parse_args($settings, $defaults);
        
        // Assign voices to speakers if not provided
        if (empty($settings['voice_mapping'])) {
            $settings['voice_mapping'] = $this->auto_assign_voices($script_result['parsed_script']);
        }
        
        error_log('AIPG ElevenLabs: Starting audio generation with ' . count($script_result['parsed_script']) . ' segments');
        error_log('AIPG ElevenLabs: Voice mapping - ' . json_encode($settings['voice_mapping']));
        
        $audio_chunks = array();
        
        // Generate intro if provided
        if (!empty($settings['intro_text'])) {
            $intro_voice = $settings['voice_mapping']['intro'] ?? '21m00Tcm4TlvDq8ikWAM';
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/aipg-temp';
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            $intro_file = $temp_dir . '/intro-' . uniqid() . '.mp3';
            
            $result = $this->generate_single_audio($settings['intro_text'], $intro_voice, $intro_file);
            
            if (!is_wp_error($result)) {
                $audio_chunks[] = array(
                    'type' => 'intro',
                    'file' => $intro_file,
                    'speaker' => 'Intro',
                    'voice' => $intro_voice,
                );
            }
        }
        
        // Generate main content
        $content_chunks = $this->generate_script_audio($script_result['parsed_script'], $settings);
        
        if (is_wp_error($content_chunks)) {
            return $content_chunks;
        }
        
        $audio_chunks = array_merge($audio_chunks, $content_chunks);
        
        // Generate outro if provided
        if (!empty($settings['outro_text'])) {
            $outro_voice = $settings['voice_mapping']['outro'] ?? '21m00Tcm4TlvDq8ikWAM';
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/aipg-temp';
            $outro_file = $temp_dir . '/outro-' . uniqid() . '.mp3';
            
            $result = $this->generate_single_audio($settings['outro_text'], $outro_voice, $outro_file);
            
            if (!is_wp_error($result)) {
                $audio_chunks[] = array(
                    'type' => 'outro',
                    'file' => $outro_file,
                    'speaker' => 'Outro',
                    'voice' => $outro_voice,
                );
            }
        }
        
        error_log('AIPG ElevenLabs: Generated ' . count($audio_chunks) . ' audio chunks successfully');
        
        return $audio_chunks;
    }
    
    /**
     * Generate audio for parsed script
     */
    private function generate_script_audio($parsed_script, $settings) {
        $audio_chunks = array();
        $chunk_index = 0;
        
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/aipg-temp';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        foreach ($parsed_script as $line) {
            $speaker = $line['speaker'];
            $text = $line['text'];
            
            // Get voice for this speaker
            $voice = $this->get_voice_for_speaker($speaker, $settings['voice_mapping']);
            
            $audio_file = $temp_dir . '/chunk-' . $chunk_index . '-' . uniqid() . '.mp3';
            
            $result = $this->generate_single_audio($text, $voice, $audio_file);
            
            if (is_wp_error($result)) {
                error_log("AIPG ElevenLabs: Error generating chunk {$chunk_index} - " . $result->get_error_message());
                return $result;
            }
            
            $audio_chunks[] = array(
                'type' => 'content',
                'speaker' => $speaker,
                'voice' => $voice,
                'file' => $audio_file,
                'index' => $chunk_index++,
            );
            
            error_log("AIPG ElevenLabs: Generated chunk {$chunk_index} for {$speaker} using voice " . $this->get_voice_name($voice));
        }
        
        return $audio_chunks;
    }
    
    /**
     * Get voice for speaker from voice mapping
     */
    private function get_voice_for_speaker($speaker, $voice_mapping) {
        if (isset($voice_mapping[$speaker])) {
            return $voice_mapping[$speaker];
        }
        
        // Default voice assignment
        $default_voices = array(
            '21m00Tcm4TlvDq8ikWAM', // Rachel
            'pNInz6obpgDQGcFmaJgB', // Adam
            'TxGEqnHWrfWFTfGW9XjX', // Josh
        );
        
        $speaker_hash = crc32($speaker);
        $voice_index = $speaker_hash % count($default_voices);
        
        return $default_voices[$voice_index];
    }
    
    /**
     * Auto assign voices to speakers
     */
    private function auto_assign_voices($parsed_script) {
        $speakers = array();
        
        foreach ($parsed_script as $line) {
            if (!in_array($line['speaker'], $speakers)) {
                $speakers[] = $line['speaker'];
            }
        }
        
        $available_voices = array(
            '21m00Tcm4TlvDq8ikWAM', // Rachel (female)
            'pNInz6obpgDQGcFmaJgB', // Adam (male)
            'TxGEqnHWrfWFTfGW9XjX', // Josh (male)
            'EXAVITQu4vr4xnSDxMaL', // Bella (female)
            'onwK4e9ZLuTAKqWW03F9', // Daniel (male)
        );
        
        $voice_mapping = array();
        
        foreach ($speakers as $index => $speaker) {
            $voice_mapping[$speaker] = $available_voices[$index % count($available_voices)];
        }
        
        return $voice_mapping;
    }
    
    /**
     * Merge audio chunks into final file
     */
    public function merge_audio_chunks($audio_chunks) {
        if (empty($audio_chunks)) {
            return new WP_Error('no_chunks', 'No audio chunks to merge');
        }
        
        error_log('AIPG ElevenLabs: Starting merge of ' . count($audio_chunks) . ' chunks');
        
        $upload_dir = wp_upload_dir();
        $podcast_dir = $upload_dir['basedir'] . '/aipg-podcasts';
        
        // Create directory if needed
        if (!file_exists($podcast_dir)) {
            wp_mkdir_p($podcast_dir);
        }
        
        $final_file = $podcast_dir . '/podcast-' . uniqid() . '.mp3';
        
        // If only one chunk, just copy it
        if (count($audio_chunks) === 1) {
            if (file_exists($audio_chunks[0]['file'])) {
                copy($audio_chunks[0]['file'], $final_file);
                @unlink($audio_chunks[0]['file']);
                error_log('AIPG ElevenLabs: Single chunk, copied directly');
                return $upload_dir['baseurl'] . '/aipg-podcasts/' . basename($final_file);
            }
        }
        
        // Check if ffmpeg is available
        exec('which ffmpeg 2>&1', $output, $return_code);
        
        if ($return_code !== 0) {
            // Try common paths
            $ffmpeg_paths = array('/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg');
            $ffmpeg = null;
            
            foreach ($ffmpeg_paths as $path) {
                exec("$path -version 2>&1", $test_output, $test_code);
                if ($test_code === 0) {
                    $ffmpeg = $path;
                    break;
                }
            }
            
            if (!$ffmpeg) {
                error_log('AIPG ElevenLabs: FFmpeg not found');
                return new WP_Error('no_ffmpeg', 'FFmpeg not installed. Please install: apt-get install ffmpeg');
            }
        } else {
            $ffmpeg = 'ffmpeg';
        }
        
        error_log('AIPG ElevenLabs: Using ffmpeg: ' . $ffmpeg);
        
        // Create list of files to merge
        $files_list = array();
        foreach ($audio_chunks as $chunk) {
            if (file_exists($chunk['file'])) {
                $files_list[] = $chunk['file'];
                error_log('AIPG ElevenLabs: Adding to merge: ' . basename($chunk['file']) . ' (' . filesize($chunk['file']) . ' bytes)');
            } else {
                error_log('AIPG ElevenLabs: WARNING - Missing chunk file: ' . $chunk['file']);
            }
        }
        
        if (empty($files_list)) {
            return new WP_Error('no_files', 'No audio files found to merge');
        }
        
        // Method 1: Try concat demuxer (fastest)
        $temp_dir = $upload_dir['basedir'] . '/aipg-temp';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $concat_file = $temp_dir . '/concat-' . uniqid() . '.txt';
        $concat_content = '';
        
        foreach ($files_list as $file) {
            // Use absolute path and escape single quotes
            $safe_path = str_replace("'", "'\\''", $file);
            $concat_content .= "file '$safe_path'\n";
        }
        
        file_put_contents($concat_file, $concat_content);
        
        error_log('AIPG ElevenLabs: Concat file created: ' . $concat_file);
        error_log('AIPG ElevenLabs: Concat content: ' . $concat_content);
        
        $command = sprintf(
            '%s -f concat -safe 0 -i %s -c copy %s 2>&1',
            $ffmpeg,
            escapeshellarg($concat_file),
            escapeshellarg($final_file)
        );
        
        error_log('AIPG ElevenLabs: Running command: ' . $command);
        
        exec($command, $output, $return_code);
        
        error_log('AIPG ElevenLabs: FFmpeg output: ' . implode("\n", $output));
        error_log('AIPG ElevenLabs: FFmpeg return code: ' . $return_code);
        
        // Clean up
        @unlink($concat_file);
        
        // Check if merge succeeded
        if ($return_code !== 0 || !file_exists($final_file) || filesize($final_file) < 1000) {
            error_log('AIPG ElevenLabs: Concat method failed, trying alternative...');
            
            // Method 2: Re-encode all files (slower but more compatible)
            $input_args = '';
            foreach ($files_list as $file) {
                $input_args .= '-i ' . escapeshellarg($file) . ' ';
            }
            
            $filter_complex = '';
            for ($i = 0; $i < count($files_list); $i++) {
                $filter_complex .= "[$i:a]";
            }
            $filter_complex .= "concat=n=" . count($files_list) . ":v=0:a=1[outa]";
            
            $command = sprintf(
                '%s %s -filter_complex "%s" -map "[outa]" %s 2>&1',
                $ffmpeg,
                $input_args,
                $filter_complex,
                escapeshellarg($final_file)
            );
            
            error_log('AIPG ElevenLabs: Trying alternative merge: ' . $command);
            
            exec($command, $output2, $return_code2);
            
            error_log('AIPG ElevenLabs: Alternative FFmpeg output: ' . implode("\n", $output2));
            
            if ($return_code2 !== 0 || !file_exists($final_file)) {
                // Clean up temp files
                foreach ($files_list as $file) {
                    @unlink($file);
                }
                return new WP_Error('merge_failed', 'FFmpeg merge failed. Output: ' . implode("\n", array_merge($output, $output2)));
            }
        }
        
        // Clean up temporary chunk files
        foreach ($files_list as $file) {
            @unlink($file);
        }
        
        $final_size = filesize($final_file);
        error_log('AIPG ElevenLabs: Successfully merged audio: ' . $final_file . ' (' . $final_size . ' bytes)');
        
        return $upload_dir['baseurl'] . '/aipg-podcasts/' . basename($final_file);
    }
}