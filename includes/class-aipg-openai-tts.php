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
            return new WP_Error('no_chunks', 'No audio chunks to merge');
        }
        
        // If only one chunk, return it
        if (count($chunks) === 1) {
            return $chunks[0]['file'];
        }
        
        // Check if ffmpeg is available
        if (!$this->is_ffmpeg_available()) {
            return new WP_Error('ffmpeg_missing', 'FFmpeg is required to merge audio chunks');
        }
        
        $upload_dir = wp_upload_dir();
        $temp_list = $upload_dir['basedir'] . '/ai-podcasts/merge_list_' . uniqid() . '.txt';
        $output_file = $upload_dir['basedir'] . '/ai-podcasts/podcast_' . uniqid() . '.mp3';
        
        // Create file list for ffmpeg
        $list_content = '';
        foreach ($chunks as $chunk) {
            if (isset($chunk['file']['path'])) {
                $list_content .= "file '" . $chunk['file']['path'] . "'\n";
            }
        }
        
        file_put_contents($temp_list, $list_content);
        
        // Use ffmpeg to concatenate
        $command = sprintf(
            'ffmpeg -f concat -safe 0 -i %s -c copy %s 2>&1',
            escapeshellarg($temp_list),
            escapeshellarg($output_file)
        );
        
        exec($command, $output, $return_var);
        
        // Clean up temp file
        unlink($temp_list);
        
        if ($return_var !== 0 || !file_exists($output_file)) {
            return new WP_Error('merge_failed', 'Failed to merge audio chunks: ' . implode("\n", $output));
        }
        
        return array(
            'path' => $output_file,
            'url' => $upload_dir['baseurl'] . '/ai-podcasts/' . basename($output_file),
            'size' => filesize($output_file),
        );
    }
    
    /**
     * Check if ffmpeg is available
     */
    private function is_ffmpeg_available() {
        exec('ffmpeg -version 2>&1', $output, $return_var);
        return $return_var === 0;
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
