<?php
/**
 * IMPROVED OpenRouter API Handler
 * Features:
 * - Multi-stage script generation for complete podcasts
 * - Professional branded intro/outro
 * - Script validation and completion checking
 * - Better handling of long-form content
 */

if (!defined('ABSPATH')) exit;

class AIPG_OpenRouter {
    
    private $api_key;
    private $base_url = 'https://openrouter.ai/api/v1';
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('aipg_openrouter_key');
    }
    
    /**
     * IMPROVED: Generate complete podcast script with multi-stage approach
     */
    public function generate_script($article_content, $settings = array()) {
        $defaults = array(
            'model' => 'openai/gpt-4o-mini',
            'duration' => 10,
            'language' => 'English',
            'hosts' => 2,
            'guest' => false,
            'host_names' => array('Alex', 'Sam'),
            'guest_name' => 'Expert',
            'podcast_style' => 'conversational',
            'tone' => 'professional',
            'include_emotions' => true,
            'search_data' => '',
        );
        
        $settings = wp_parse_args($settings, $defaults);
        
        error_log('AIPG: Starting multi-stage script generation');
        error_log('AIPG: Duration: ' . $settings['duration'] . ' minutes');
        
        // Get website info for branding
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        // Stage 1: Generate outline
        $outline = $this->generate_script_outline($article_content, $settings);
        
        if (is_wp_error($outline)) {
            error_log('AIPG: Outline generation failed - ' . $outline->get_error_message());
            return $outline;
        }
        
        error_log('AIPG: ✓ Outline generated with ' . count($outline['sections']) . ' sections');
        
        // Stage 2: Generate intro with branding
        $intro = $this->generate_intro($settings, $site_name);
        
        if (is_wp_error($intro)) {
            error_log('AIPG: Intro generation failed');
            return $intro;
        }
        
        error_log('AIPG: ✓ Professional intro generated');
        
        // Stage 3: Generate main content sections
        $main_sections = $this->generate_main_content($article_content, $settings, $outline);
        
        if (is_wp_error($main_sections)) {
            error_log('AIPG: Main content generation failed');
            return $main_sections;
        }
        
        error_log('AIPG: ✓ Main content generated (' . count($main_sections) . ' sections)');
        
        // Stage 4: Generate outro with call-to-action
        $outro = $this->generate_outro($settings, $site_name, $site_url);
        
        if (is_wp_error($outro)) {
            error_log('AIPG: Outro generation failed');
            return $outro;
        }
        
        error_log('AIPG: ✓ Professional outro generated');
        
        // Combine all sections
        $complete_script = $intro . "\n\n" . implode("\n\n", $main_sections) . "\n\n" . $outro;
        
        // Parse and validate
        $parsed = $this->parse_script_text($complete_script, $settings);
        
        // Validation
        $target_words = $settings['duration'] * 150;
        $actual_words = $parsed['word_count'];
        $completion_ratio = $actual_words / $target_words;
        
        error_log("AIPG: Script complete - Target: {$target_words} words, Actual: {$actual_words} words ({$completion_ratio}%)");
        
        // If script is too short, regenerate main content with more detail
        if ($completion_ratio < 0.7) {
            error_log('AIPG: Script too short, regenerating with more detail...');
            $main_sections = $this->generate_main_content($article_content, $settings, $outline, true);
            $complete_script = $intro . "\n\n" . implode("\n\n", $main_sections) . "\n\n" . $outro;
            $parsed = $this->parse_script_text($complete_script, $settings);
        }
        
        return $parsed;
    }
    
    /**
     * Generate professional branded intro
     */
    private function generate_intro($settings, $site_name) {
        $host_names_text = implode(' and ', array_slice($settings['host_names'], 0, 2));
        
        $prompt = "You are a professional podcast intro writer. Create an engaging 30-second intro for a podcast.

REQUIREMENTS:
- Podcast website: {$site_name}
- Hosts: {$host_names_text}
- Style: {$settings['podcast_style']}
- Tone: {$settings['tone']}
- Language: {$settings['language']}

The intro should:
1. Start with an attention-grabbing hook
2. Introduce the podcast name ({$site_name} Podcast)
3. Introduce the hosts by name
4. Set expectations for the episode
5. Be energetic and welcoming
6. Include emotion tags for natural delivery

Format each line as: SPEAKER_NAME: [emotion] dialogue

Example structure:
{$settings['host_names'][0]}: [excited] Welcome to the {$site_name} Podcast!
{$settings['host_names'][1]}: [happy] I'm {$settings['host_names'][1]}, and joining me today is my co-host {$settings['host_names'][0]}!

Generate the complete intro now (5-7 lines):";

        $response = $this->chat_completion($prompt, $settings['model'], 1000);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return trim($response['choices'][0]['message']['content'] ?? '');
    }
    
    /**
     * Generate professional outro with call-to-action
     */
    private function generate_outro($settings, $site_name, $site_url) {
        $host_names_text = implode(' and ', array_slice($settings['host_names'], 0, 2));
        
        $prompt = "You are a professional podcast outro writer. Create a compelling outro for a podcast.

REQUIREMENTS:
- Podcast website: {$site_name}
- Website URL: {$site_url}
- Hosts: {$host_names_text}
- Style: {$settings['podcast_style']}
- Language: {$settings['language']}

The outro should:
1. Summarize key takeaways (1-2 lines)
2. Thank the listener
3. Include call-to-action (visit website, subscribe, share)
4. Mention the website name clearly
5. Be warm and memorable
6. Include emotion tags

Format each line as: SPEAKER_NAME: [emotion] dialogue

Generate the complete outro now (6-8 lines):";

        $response = $this->chat_completion($prompt, $settings['model'], 1000);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return trim($response['choices'][0]['message']['content'] ?? '');
    }
    
    /**
     * Generate script outline
     */
    private function generate_script_outline($content, $settings) {
        $duration_words = $settings['duration'] * 150;
        
        $prompt = "You are a podcast content strategist. Analyze this article and create a structured outline for a {$settings['duration']}-minute podcast.

ARTICLE:
" . substr($content, 0, 3000) . "

Create an outline with 3-5 main sections. Each section should have:
- A clear topic/theme
- Key points to cover
- Estimated word count (total should be ~{$duration_words} words)

Return ONLY a JSON array like this:
[
  {\"section\": \"Introduction to topic\", \"points\": [\"point 1\", \"point 2\"], \"words\": 300},
  {\"section\": \"Deep dive\", \"points\": [\"detail 1\", \"detail 2\"], \"words\": 600}
]";

        $response = $this->chat_completion($prompt, $settings['model'], 2000);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        // Extract JSON
        if (preg_match('/\[.*\]/s', $content, $matches)) {
            $outline = json_decode($matches[0], true);
            if (is_array($outline) && !empty($outline)) {
                return array('sections' => $outline);
            }
        }
        
        // Fallback: create basic outline
        return array(
            'sections' => array(
                array('section' => 'Main Content', 'points' => array(), 'words' => $duration_words - 200)
            )
        );
    }
    
    /**
     * Generate main content sections
     */
    private function generate_main_content($article_content, $settings, $outline, $detailed = false) {
        $sections = array();
        $search_context = !empty($settings['search_data']) ? "\n\nADDITIONAL RESEARCH:\n" . substr($settings['search_data'], 0, 1000) : '';
        
        foreach ($outline['sections'] as $index => $section_info) {
            error_log("AIPG: Generating section " . ($index + 1) . ": " . $section_info['section']);
            
            $section_words = $section_info['words'] ?? 500;
            if ($detailed) {
                $section_words = (int)($section_words * 1.3); // 30% more detail
            }
            
            $prompt = $this->build_section_prompt(
                $article_content, 
                $settings, 
                $section_info,
                $section_words,
                $index + 1,
                count($outline['sections']),
                $search_context
            );
            
            $response = $this->chat_completion($prompt, $settings['model'], $section_words * 2);
            
            if (is_wp_error($response)) {
                error_log('AIPG: Section ' . ($index + 1) . ' failed - ' . $response->get_error_message());
                continue;
            }
            
            $section_text = trim($response['choices'][0]['message']['content'] ?? '');
            
            if (!empty($section_text)) {
                $sections[] = $section_text;
                $word_count = str_word_count($section_text);
                error_log("AIPG: ✓ Section " . ($index + 1) . " generated ({$word_count} words)");
            }
            
            // Small delay to avoid rate limits
            if ($index < count($outline['sections']) - 1) {
                usleep(500000); // 0.5 second
            }
        }
        
        return $sections;
    }
    
    /**
     * Build prompt for a specific section
     */
    private function build_section_prompt($content, $settings, $section_info, $target_words, $section_num, $total_sections, $search_context) {
        $host_names = $this->format_speaker_names($settings);
        $style_instructions = $this->get_style_instructions($settings['podcast_style']);
        $emotion_instructions = $settings['include_emotions'] ? $this->get_emotion_instructions() : '';
        
        $section_title = $section_info['section'];
        $key_points = !empty($section_info['points']) ? "\nKey points to cover:\n- " . implode("\n- ", $section_info['points']) : '';
        
        $context_note = $section_num === 1 ? 
            "\nNote: This is the start of the main content (after the intro). Jump right into the topic." : 
            "\nNote: This is section {$section_num} of {$total_sections}. Build on previous discussion.";
        
        return "You are writing section {$section_num} of a {$settings['duration']}-minute podcast.

SECTION TOPIC: {$section_title}
{$key_points}
{$context_note}

ARTICLE CONTENT:
" . substr($content, 0, 2000) . "
{$search_context}

SPEAKERS: {$host_names}
{$style_instructions}
{$emotion_instructions}

CRITICAL REQUIREMENTS:
1. Write EXACTLY ~{$target_words} words for this section
2. Format: SPEAKER_NAME: [emotion] dialogue
3. Make conversation natural with reactions, questions, back-and-forth
4. Include specific facts, examples, and insights from the article
5. Keep energy high and engaging
6. Use the speaker names exactly as specified

Generate this section now ({$target_words} words):";
    }
    
    /**
     * Parse complete script text
     */
    private function parse_script_text($script_text, $settings) {
        $script_lines = explode("\n", $script_text);
        $parsed_script = array();
        
        foreach ($script_lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Match format: SPEAKER: [emotion] dialogue or SPEAKER: dialogue
            if (preg_match('/^([^:]+):\s*(\[(\w+)\])?\s*(.+)$/i', $line, $matches)) {
                $speaker = trim($matches[1]);
                $emotion = !empty($matches[3]) ? trim($matches[3]) : '';
                $text = trim($matches[4]);
                
                // Skip if text is too short (likely parsing error)
                if (strlen($text) < 5) continue;
                
                $parsed_script[] = array(
                    'speaker' => $speaker,
                    'emotion' => $emotion,
                    'text' => $text,
                    'full_text' => ($emotion ? "[{$emotion}] " : '') . $text,
                );
            }
        }
        
        $word_count = str_word_count($script_text);
        $has_emotions = !empty(array_filter($parsed_script, function($s) { 
            return !empty($s['emotion']); 
        }));
        
        return array(
            'raw_script' => $script_text,
            'parsed_script' => $parsed_script,
            'word_count' => $word_count,
            'estimated_duration' => ceil($word_count / 150),
            'has_emotions' => $has_emotions,
            'line_count' => count($parsed_script),
        );
    }
    
    /**
     * Get style instructions
     */
    private function get_style_instructions($style) {
        $styles = array(
            'conversational' => "CONVERSATIONAL STYLE:
- Sound like friends discussing an interesting topic
- Use casual but professional language
- Include natural reactions and interruptions
- Build excitement throughout",
            
            'interview' => "INTERVIEW STYLE:
- Host asks probing questions
- Guest provides expert answers
- Include follow-up questions
- Guide conversation to key points",
            
            'debate' => "DEBATE STYLE:
- Present multiple perspectives
- Respectful disagreement allowed
- Use evidence and reasoning
- Find common ground",
            
            'educational' => "EDUCATIONAL STYLE:
- Break down complex concepts
- Use clear analogies
- Build from basics to advanced
- Include teaching moments",
            
            'storytelling' => "STORYTELLING STYLE:
- Frame as narrative journey
- Use vivid descriptions
- Build suspense
- Include examples and scenarios",
        );
        
        return $styles[$style] ?? $styles['conversational'];
    }
    
    /**
     * Get emotion instructions
     */
    private function get_emotion_instructions() {
        return "EMOTION TAGS (Use strategically):
- [excited] - Enthusiasm, big reveals
- [thoughtful] - Reflection, considering
- [concerned] - Serious topics, warnings
- [happy] - Positive moments, humor
- [curious] - Questions, seeking understanding
- [calm] - Explanations, conclusions";
    }
    
    /**
     * Format speaker names
     */
    private function format_speaker_names($settings) {
        $names = array();
        for ($i = 0; $i < $settings['hosts']; $i++) {
            $names[] = $settings['host_names'][$i] ?? "Host" . ($i + 1);
        }
        if ($settings['guest']) {
            $names[] = $settings['guest_name'];
        }
        return implode(', ', $names);
    }
    
    /**
     * Generate episode summary
     */
    public function generate_episode_summary($script_text, $article_title) {
        $prompt = "Create a compelling 2-3 paragraph podcast episode description.

PODCAST SCRIPT:
" . substr($script_text, 0, 2000) . "

Requirements:
1. Capture main topic and key insights
2. Highlight most interesting points
3. Make people want to listen
4. Use natural, conversational language
5. SEO-friendly with keywords
6. 150-200 words

Write the summary now:";
        
        $response = $this->chat_completion($prompt, 'openai/gpt-4o-mini', 500);
        
        if (is_wp_error($response)) {
            return "In this episode, we explore {$article_title} in depth, breaking down the key concepts and discussing what it means for you.";
        }
        
        return trim($response['choices'][0]['message']['content'] ?? '');
    }
    
    /**
     * Generate show notes
     */
    public function generate_show_notes($parsed_script) {
        $notes = "## Show Notes\n\n";
        
        $current_time = 0;
        $topics = array();
        $current_topic = '';
        $topic_start = 0;
        
        foreach ($parsed_script as $segment) {
            $words = str_word_count($segment['text']);
            $duration = $words / 2.5;
            
            if (empty($current_topic) || $words > 50) {
                if ($current_topic) {
                    $topics[] = array(
                        'time' => $this->format_timestamp($topic_start),
                        'topic' => $current_topic,
                    );
                }
                $current_topic = $this->extract_topic($segment['text']);
                $topic_start = $current_time;
            }
            
            $current_time += $duration;
        }
        
        if ($current_topic) {
            $topics[] = array(
                'time' => $this->format_timestamp($topic_start),
                'topic' => $current_topic,
            );
        }
        
        foreach ($topics as $topic) {
            $notes .= "**[{$topic['time']}]** {$topic['topic']}\n\n";
        }
        
        return $notes;
    }
    
    /**
     * Extract topic from text
     */
    private function extract_topic($text) {
        $sentences = preg_split('/[.!?]+/', $text);
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 20 && strlen($sentence) < 100) {
                return $sentence;
            }
        }
        return substr($text, 0, 80) . '...';
    }
    
    /**
     * Format timestamp
     */
    private function format_timestamp($seconds) {
        $mins = floor($seconds / 60);
        $secs = floor($seconds % 60);
        return sprintf('%02d:%02d', $mins, $secs);
    }
    
    /**
     * Chat completion API call
     */
    public function chat_completion($prompt, $model = 'openai/gpt-4o-mini', $max_tokens = 4000) {
        $endpoint = $this->base_url . '/chat/completions';
        
        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $max_tokens,
            'temperature' => 0.7,
        );
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode($body),
            'timeout' => 120,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            $error_msg = isset($body_data['error']['message']) ? $body_data['error']['message'] : 'Unknown API error';
            error_log('AIPG: OpenRouter API error: ' . $error_msg);
            return new WP_Error('openrouter_error', $error_msg);
        }
        
        return $body_data;
    }
    
    /**
     * Generate search queries
     */
    public function generate_search_queries($article_content) {
        $prompt = "Analyze this article and generate 3-5 search queries for enrichment.

ARTICLE:
" . substr($article_content, 0, 1500) . "

Return ONLY JSON array: [\"query 1\", \"query 2\", \"query 3\"]";
        
        $response = $this->chat_completion($prompt, 'openai/gpt-4o-mini', 500);
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        if (preg_match('/\[.*\]/s', $content, $matches)) {
            $queries = json_decode($matches[0], true);
            if (is_array($queries)) {
                return $queries;
            }
        }
        
        return array();
    }
    
    /**
     * Select best article
     */
    public function select_best_article($articles) {
        if (empty($articles)) {
            return new WP_Error('no_articles', 'No articles provided');
        }
        
        $articles_text = '';
        foreach ($articles as $index => $article) {
            $articles_text .= "\n\nARTICLE #" . ($index + 1) . ":\n";
            $articles_text .= "Title: " . $article['title'] . "\n";
            $articles_text .= "Excerpt: " . wp_trim_words($article['content'], 100) . "\n";
        }
        
        $prompt = "Select the BEST article for a podcast episode.

Consider:
- Conversational potential
- Relevance and timeliness
- Depth and substance
- Audience interest

ARTICLES:
{$articles_text}

Return ONLY the number (1, 2, 3, etc.):";
        
        $response = $this->chat_completion($prompt, 'openai/gpt-4o-mini', 100);
        
        if (is_wp_error($response)) {
            return 0;
        }
        
        $content = trim($response['choices'][0]['message']['content'] ?? '1');
        $selected = intval(preg_replace('/[^0-9]/', '', $content));
        
        return max(1, min($selected, count($articles))) - 1;
    }
}