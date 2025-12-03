<?php
/**
 * OpenRouter API Handler - Enhanced with Emotions & Custom Names
 */

if (!defined('ABSPATH')) exit;

class AIPG_OpenRouter {
    
    private $api_key;
    private $base_url = 'https://openrouter.ai/api/v1';
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('aipg_openrouter_key');
    }
    
    /**
     * Generate podcast script with emotions and custom names
     */
    public function generate_script($article_content, $settings = array()) {
        $defaults = array(
            'model' => 'anthropic/claude-sonnet-4',
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
        
        $prompt = $this->build_enhanced_script_prompt($article_content, $settings);
        
        $response = $this->chat_completion($prompt, $settings['model']);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->parse_script_response($response, $settings);
    }
    
    /**
     * Build enhanced script prompt with emotions
     */
    private function build_enhanced_script_prompt($content, $settings) {
        $duration_words = $settings['duration'] * 150;
        
        // Build host introduction
        $host_intro = $this->build_host_intro($settings['host_names'], $settings['hosts']);
        
        $guest_info = $settings['guest'] 
            ? "\n- There is a guest expert named {$settings['guest_name']} who joins the conversation to provide deep insights and expertise."
            : "";
        
        $search_context = '';
        if (!empty($settings['search_data'])) {
            $search_context = "\n\nADDITIONAL RESEARCH CONTEXT:\n" . $settings['search_data'];
        }
        
        // Style-specific instructions
        $style_instructions = $this->get_style_instructions($settings['podcast_style']);
        
        // Emotion instructions
        $emotion_instructions = $settings['include_emotions'] ? $this->get_emotion_instructions() : '';
        
        $prompt = "You are an expert podcast script writer creating engaging, natural conversations.

ARTICLE CONTENT:
{$content}
{$search_context}

PODCAST SPECIFICATIONS:
- Duration: {$settings['duration']} minutes (approximately {$duration_words} words)
- Language: {$settings['language']}
- Style: {$settings['podcast_style']}
- Tone: {$settings['tone']}
- Number of hosts: {$settings['hosts']}
{$host_intro}
{$guest_info}

{$style_instructions}

{$emotion_instructions}

CRITICAL FORMATTING RULES:
1. Use this exact format for each line: SPEAKER_NAME: [emotion] dialogue text
2. Speaker names must be exactly as specified: {$this->format_speaker_names($settings)}
3. Make the conversation flow naturally with reactions, questions, and building on each other's points
4. Include natural transitions like \"That's interesting!\", \"Wait, really?\", \"I see what you mean\"
5. Have hosts reference each other by name occasionally
6. Keep the pacing dynamic - vary between quick exchanges and longer explanations
7. End with a memorable conclusion and call to action

TARGET WORD COUNT: {$duration_words} words (strictly maintain this)

Generate the complete podcast script now:";
        
        return $prompt;
    }
    
    /**
     * Build host introduction text
     */
    private function build_host_intro($host_names, $host_count) {
        $intros = array();
        for ($i = 0; $i < $host_count && $i < count($host_names); $i++) {
            $intros[] = "Host " . ($i + 1) . " is named {$host_names[$i]}";
        }
        
        if (empty($intros)) {
            return "- Hosts: Alex and Sam";
        }
        
        return "- " . implode("\n- ", $intros);
    }
    
    /**
     * Format speaker names for prompt
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
     * Get style-specific instructions
     */
    private function get_style_instructions($style) {
        $styles = array(
            'conversational' => "CONVERSATIONAL STYLE:
- Make it sound like friends discussing an interesting topic over coffee
- Use casual language while maintaining professionalism
- Include natural interruptions, agreements, and reactions
- Build excitement and curiosity throughout",
            
            'interview' => "INTERVIEW STYLE:
- Host asks probing questions, guest provides expert answers
- Include follow-up questions based on answers
- Guest should demonstrate deep knowledge
- Host guides the conversation to key points",
            
            'debate' => "DEBATE STYLE:
- Present multiple perspectives on the topic
- Hosts may respectfully disagree
- Use evidence and reasoning
- Find common ground while exploring differences",
            
            'educational' => "EDUCATIONAL STYLE:
- Break down complex concepts clearly
- Use analogies and examples
- Build from basics to advanced concepts
- Include \"teaching moments\" and summaries",
            
            'storytelling' => "STORYTELLING STYLE:
- Frame content as a narrative journey
- Use vivid descriptions and scenarios
- Build suspense and revelation
- Include personal anecdotes or hypothetical examples",
        );
        
        return $styles[$style] ?? $styles['conversational'];
    }
    
    /**
     * Get emotion instructions
     */
    private function get_emotion_instructions() {
        return "EMOTION TAGS (Use these to add natural expression):
Use emotion tags at the start of dialogue to guide delivery:

- [excited] - For enthusiasm, big reveals, surprising facts
  Example: Alex: [excited] Wait, this is actually groundbreaking!

- [thoughtful] - For reflection, considering ideas
  Example: Sam: [thoughtful] That makes me wonder about the implications...

- [concerned] - For serious topics, warnings, challenges
  Example: Alex: [concerned] But we should consider the risks here.

- [happy] - For positive moments, celebrations, humor
  Example: Sam: [happy] That's exactly what we needed to hear!

- [curious] - For genuine questions, seeking understanding
  Example: Alex: [curious] How does that actually work in practice?

- [calm] - For explanations, wrapping up, soothing
  Example: Sam: [calm] Let me break this down simply.

- [pause] - For dramatic pauses, letting ideas sink in
  Example: Alex: [pause] And here's where it gets interesting...

Use emotions strategically - not every line needs one, but they add depth and engagement when used well.";
    }
    
    /**
     * Parse script response with emotion preservation
     */
    private function parse_script_response($response, $settings) {
        if (!isset($response['choices'][0]['message']['content'])) {
            return new WP_Error('parse_error', 'Invalid API response format');
        }
        
        $script_text = $response['choices'][0]['message']['content'];
        
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
                
                $parsed_script[] = array(
                    'speaker' => $speaker,
                    'emotion' => $emotion,
                    'text' => $text,
                    'full_text' => ($emotion ? "[{$emotion}] " : '') . $text,
                );
            }
        }
        
        return array(
            'raw_script' => $script_text,
            'parsed_script' => $parsed_script,
            'word_count' => str_word_count($script_text),
            'estimated_duration' => ceil(str_word_count($script_text) / 150),
            'has_emotions' => !empty(array_filter($parsed_script, function($s) { return !empty($s['emotion']); })),
        );
    }
    
    /**
     * Generate episode summary from script
     */
    public function generate_episode_summary($script_text, $article_title) {
        $prompt = "You are a podcast episode description writer. Based on the following podcast script, create a compelling episode summary.

PODCAST SCRIPT:
{$script_text}

Create a 2-3 paragraph summary that:
1. Captures the main topic and key insights
2. Highlights the most interesting points discussed
3. Is engaging and makes people want to listen
4. Uses natural, conversational language
5. Is SEO-friendly with relevant keywords

Write the summary now (2-3 paragraphs, around 150 words):";
        
        $response = $this->chat_completion($prompt, 'anthropic/claude-sonnet-4', 500);
        
        if (is_wp_error($response)) {
            return "In this episode, we explore {$article_title} in depth, breaking down the key concepts and discussing what it means for you.";
        }
        
        return trim($response['choices'][0]['message']['content'] ?? '');
    }
    
    /**
     * Generate show notes with timestamps
     */
    public function generate_show_notes($parsed_script) {
        $notes = "SHOW NOTES\n\n";
        
        $current_time = 0;
        $topics = array();
        $current_topic = '';
        $topic_start = 0;
        
        foreach ($parsed_script as $segment) {
            $words = str_word_count($segment['text']);
            $duration = $words / 2.5; // ~150 words per minute = 2.5 words per second
            
            // Simple topic detection (could be enhanced with AI)
            if (empty($current_topic)) {
                $current_topic = $this->extract_topic($segment['text']);
                $topic_start = $current_time;
            } elseif ($words > 50) { // New significant point
                $topics[] = array(
                    'time' => $this->format_timestamp($topic_start),
                    'topic' => $current_topic,
                );
                $current_topic = $this->extract_topic($segment['text']);
                $topic_start = $current_time;
            }
            
            $current_time += $duration;
        }
        
        // Add last topic
        if ($current_topic) {
            $topics[] = array(
                'time' => $this->format_timestamp($topic_start),
                'topic' => $current_topic,
            );
        }
        
        foreach ($topics as $topic) {
            $notes .= "[{$topic['time']}] {$topic['topic']}\n";
        }
        
        return $notes;
    }
    
    /**
     * Extract topic from text
     */
    private function extract_topic($text) {
        // Simple extraction - take first meaningful sentence
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
    public function chat_completion($prompt, $model = 'anthropic/claude-sonnet-4', $max_tokens = 16000) {
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
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            return new WP_Error('openrouter_error', 
                isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error'
            );
        }
        
        return $body;
    }
    
    /**
     * Generate search queries
     */
    public function generate_search_queries($article_content) {
        $prompt = "Analyze this article and generate 3-5 specific search queries to find the latest information, statistics, and expert opinions to enrich the podcast.

ARTICLE:
{$article_content}

Return ONLY a JSON array of search query strings. Example: [\"query 1\", \"query 2\", \"query 3\"]";
        
        $response = $this->chat_completion($prompt, 'anthropic/claude-sonnet-4', 1000);
        
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
     * Select best article for podcast
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
        
        $prompt = "You are a podcast content strategist. Analyze these articles and select THE ONE that would make the most engaging podcast episode.

Consider:
- Conversational potential (stories, examples, multiple angles to discuss)
- Relevance and timeliness
- Depth and substance
- Audience interest and relatability
- Potential for interesting dialogue between hosts

ARTICLES:
{$articles_text}

Return ONLY the article number (1, 2, 3, etc.) of the best choice. Return just the number, nothing else.";
        
        $response = $this->chat_completion($prompt, 'anthropic/claude-sonnet-4', 100);
        
        if (is_wp_error($response)) {
            return 0;
        }
        
        $content = trim($response['choices'][0]['message']['content'] ?? '1');
        $selected = intval(preg_replace('/[^0-9]/', '', $content));
        
        return max(1, min($selected, count($articles))) - 1;
    }
}