<?php
/**
 * OpenRouter API Handler
 */

if (!defined('ABSPATH')) exit;

class AIPG_OpenRouter {
    
    private $api_key;
    private $base_url = 'https://openrouter.ai/api/v1';
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('aipg_openrouter_key');
    }
    
    /**
     * Generate podcast script from article
     */
    public function generate_script($article_content, $settings = array()) {
        $defaults = array(
            'model' => 'anthropic/claude-sonnet-4',
            'duration' => 10, // minutes
            'language' => 'English',
            'hosts' => 2,
            'guest' => false,
            'host_names' => array('Alex', 'Sam'),
            'guest_name' => 'Jordan',
            'search_data' => '',
        );
        
        $settings = wp_parse_args($settings, $defaults);
        
        // Build the prompt
        $prompt = $this->build_script_prompt($article_content, $settings);
        
        // Make API call
        $response = $this->chat_completion($prompt, $settings['model']);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->parse_script_response($response, $settings);
    }
    
    /**
     * Build script generation prompt
     */
    private function build_script_prompt($content, $settings) {
        $duration_words = $settings['duration'] * 150; // ~150 words per minute
        
        $host_intro = '';
        if ($settings['hosts'] == 2) {
            $host_intro = "Host 1 is {$settings['host_names'][0]} and Host 2 is {$settings['host_names'][1]}.";
        } elseif ($settings['hosts'] == 3) {
            $host_intro = "Host 1 is {$settings['host_names'][0]}, Host 2 is {$settings['host_names'][1]}, and Host 3 is {$settings['host_names'][2]}.";
        }
        
        $guest_info = $settings['guest'] ? "There is also a guest expert named {$settings['guest_name']} who provides additional insights." : "";
        
        $search_context = '';
        if (!empty($settings['search_data'])) {
            $search_context = "\n\nAdditional Context from Web Search:\n" . $settings['search_data'];
        }
        
        $prompt = "You are a professional podcast script writer. Create an engaging, conversational podcast script based on the following article.

ARTICLE CONTENT:
{$content}
{$search_context}

PODCAST REQUIREMENTS:
- Duration: {$settings['duration']} minutes (approximately {$duration_words} words)
- Language: {$settings['language']}
- Number of hosts: {$settings['hosts']}
- {$host_intro}
- {$guest_info}

SCRIPT FORMAT INSTRUCTIONS:
1. Start with an engaging intro where hosts introduce the topic
2. Break down the content into a natural conversation between hosts
3. Use conversational language, not formal writing
4. Include natural transitions, reactions (\"That's interesting!\", \"Wait, really?\", etc.)
5. Have hosts ask each other questions and build on each other's points
6. " . ($settings['guest'] ? "Include the guest providing expert insights at appropriate moments" : "") . "
7. End with a memorable conclusion and call to action
8. Format as: SPEAKER_NAME: [dialogue]

IMPORTANT: 
- Make it sound like a real, engaging conversation
- Keep it within the target duration ({$duration_words} words)
- Use the specified language: {$settings['language']}
- Make sure each speaker has distinct personality

Generate the complete podcast script now:";
        
        return $prompt;
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
     * Parse script response
     */
    private function parse_script_response($response, $settings) {
        if (!isset($response['choices'][0]['message']['content'])) {
            return new WP_Error('parse_error', 'Invalid API response format');
        }
        
        $script_text = $response['choices'][0]['message']['content'];
        
        // Parse into structured format
        $script_lines = explode("\n", $script_text);
        $parsed_script = array();
        
        foreach ($script_lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Match format: SPEAKER: dialogue
            if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
                $parsed_script[] = array(
                    'speaker' => trim($matches[1]),
                    'text' => trim($matches[2])
                );
            }
        }
        
        return array(
            'raw_script' => $script_text,
            'parsed_script' => $parsed_script,
            'word_count' => str_word_count($script_text),
            'estimated_duration' => ceil(str_word_count($script_text) / 150), // minutes
        );
    }
    
    /**
     * Analyze article and generate search queries
     */
    public function generate_search_queries($article_content) {
        $prompt = "Analyze the following article and generate 3-5 web search queries to find the latest, most relevant information to enrich this content. Focus on facts, statistics, recent developments, and expert opinions.

ARTICLE:
{$article_content}

Return ONLY a JSON array of search query strings, nothing else. Example: [\"query 1\", \"query 2\", \"query 3\"]";
        
        $response = $this->chat_completion($prompt, 'anthropic/claude-sonnet-4', 1000);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        // Try to extract JSON
        if (preg_match('/\[.*\]/s', $content, $matches)) {
            $queries = json_decode($matches[0], true);
            if (is_array($queries)) {
                return $queries;
            }
        }
        
        return array(); // Return empty array if parsing fails
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
        
        $prompt = "You are a podcast content strategist. Review these articles and select THE BEST ONE for an engaging podcast episode. Consider:
- Conversational potential (does it have stories, examples, debates?)
- Relevance and timeliness
- Depth of content
- Potential audience interest

ARTICLES:
{$articles_text}

Return ONLY the article number (1, 2, 3, etc.) that would make the best podcast. Return just the number, nothing else.";
        
        $response = $this->chat_completion($prompt, 'anthropic/claude-sonnet-4', 100);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $content = trim($response['choices'][0]['message']['content'] ?? '1');
        $selected = intval(preg_replace('/[^0-9]/', '', $content));
        
        return max(1, min($selected, count($articles))) - 1; // Return 0-indexed
    }
}
