<?php
/**
 * Tavily Search API Handler
 */

if (!defined('ABSPATH')) exit;

class AIPG_Tavily {
    
    private $api_key;
    private $base_url = 'https://api.tavily.com';
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('aipg_tavily_key');
    }
    
    /**
     * Search web for queries
     */
    public function search($queries, $max_results = 5) {
        if (empty($queries)) {
            return array();
        }
        
        // If single query string, convert to array
        if (is_string($queries)) {
            $queries = array($queries);
        }
        
        $all_results = array();
        
        foreach ($queries as $query) {
            $results = $this->single_search($query, $max_results);
            
            if (!is_wp_error($results)) {
                $all_results = array_merge($all_results, $results);
            }
        }
        
        return $all_results;
    }
    
    /**
     * Perform single search
     */
    private function single_search($query, $max_results = 5) {
        $endpoint = $this->base_url . '/search';
        
        $body = array(
            'query' => $query,
            'max_results' => $max_results,
            'search_depth' => 'advanced',
            'include_answer' => true,
            'include_raw_content' => false,
        );
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array_merge($body, array(
                'api_key' => $this->api_key
            ))),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            error_log('Tavily search error: ' . $response->get_error_message());
            return array();
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_content = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            error_log('Tavily API error: ' . print_r($body_content, true));
            return array();
        }
        
        return $this->format_results($body_content);
    }
    
    /**
     * Format search results
     */
    private function format_results($response) {
        $formatted = array();
        
        // Add the AI answer if available
        if (!empty($response['answer'])) {
            $formatted[] = array(
                'type' => 'answer',
                'content' => $response['answer'],
            );
        }
        
        // Add search results
        if (!empty($response['results']) && is_array($response['results'])) {
            foreach ($response['results'] as $result) {
                $formatted[] = array(
                    'type' => 'result',
                    'title' => $result['title'] ?? '',
                    'url' => $result['url'] ?? '',
                    'content' => $result['content'] ?? '',
                    'score' => $result['score'] ?? 0,
                );
            }
        }
        
        return $formatted;
    }
    
    /**
     * Format results as text for AI consumption
     */
    public function format_as_context($results) {
        if (empty($results)) {
            return '';
        }
        
        $context = "ADDITIONAL WEB RESEARCH:\n\n";
        
        foreach ($results as $index => $result) {
            if ($result['type'] === 'answer') {
                $context .= "AI Summary: " . $result['content'] . "\n\n";
            } else {
                $context .= "Source " . ($index + 1) . ":\n";
                $context .= "Title: " . $result['title'] . "\n";
                $context .= "Content: " . wp_trim_words($result['content'], 150) . "\n";
                $context .= "URL: " . $result['url'] . "\n\n";
            }
        }
        
        return $context;
    }
    
    /**
     * Enrich article with web search
     */
    public function enrich_article($article_content, $search_queries) {
        if (empty($search_queries)) {
            return '';
        }
        
        $search_results = $this->search($search_queries, 3);
        
        if (empty($search_results)) {
            return '';
        }
        
        return $this->format_as_context($search_results);
    }
}
