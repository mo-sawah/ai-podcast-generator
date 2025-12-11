<?php
/**
 * MULTILINGUAL OpenRouter API Handler
 * Full support for Greek and 30+ languages
 * Professional script generation in any language
 */

if (!defined('ABSPATH')) exit;

class AIPG_OpenRouter {
    
    private $api_key;
    private $base_url = 'https://openrouter.ai/api/v1';
    
    // Supported languages with native names
    private $supported_languages = array(
        'English' => array('code' => 'en', 'native' => 'English', 'voice_support' => 'full'),
        'Greek' => array('code' => 'el', 'native' => 'Ελληνικά', 'voice_support' => 'full'),
        'Spanish' => array('code' => 'es', 'native' => 'Español', 'voice_support' => 'full'),
        'French' => array('code' => 'fr', 'native' => 'Français', 'voice_support' => 'full'),
        'German' => array('code' => 'de', 'native' => 'Deutsch', 'voice_support' => 'full'),
        'Italian' => array('code' => 'it', 'native' => 'Italiano', 'voice_support' => 'full'),
        'Portuguese' => array('code' => 'pt', 'native' => 'Português', 'voice_support' => 'full'),
        'Dutch' => array('code' => 'nl', 'native' => 'Nederlands', 'voice_support' => 'full'),
        'Polish' => array('code' => 'pl', 'native' => 'Polski', 'voice_support' => 'full'),
        'Russian' => array('code' => 'ru', 'native' => 'Русский', 'voice_support' => 'full'),
        'Turkish' => array('code' => 'tr', 'native' => 'Türkçe', 'voice_support' => 'full'),
        'Arabic' => array('code' => 'ar', 'native' => 'العربية', 'voice_support' => 'full'),
        'Chinese' => array('code' => 'zh', 'native' => '中文', 'voice_support' => 'full'),
        'Japanese' => array('code' => 'ja', 'native' => '日本語', 'voice_support' => 'full'),
        'Korean' => array('code' => 'ko', 'native' => '한국어', 'voice_support' => 'full'),
        'Hindi' => array('code' => 'hi', 'native' => 'हिन्दी', 'voice_support' => 'full'),
        'Swedish' => array('code' => 'sv', 'native' => 'Svenska', 'voice_support' => 'full'),
        'Norwegian' => array('code' => 'no', 'native' => 'Norsk', 'voice_support' => 'full'),
        'Danish' => array('code' => 'da', 'native' => 'Dansk', 'voice_support' => 'full'),
        'Finnish' => array('code' => 'fi', 'native' => 'Suomi', 'voice_support' => 'full'),
        'Czech' => array('code' => 'cs', 'native' => 'Čeština', 'voice_support' => 'full'),
        'Romanian' => array('code' => 'ro', 'native' => 'Română', 'voice_support' => 'full'),
        'Bulgarian' => array('code' => 'bg', 'native' => 'Български', 'voice_support' => 'full'),
        'Ukrainian' => array('code' => 'uk', 'native' => 'Українська', 'voice_support' => 'full'),
        'Croatian' => array('code' => 'hr', 'native' => 'Hrvatski', 'voice_support' => 'full'),
        'Serbian' => array('code' => 'sr', 'native' => 'Српски', 'voice_support' => 'full'),
        'Slovak' => array('code' => 'sk', 'native' => 'Slovenčina', 'voice_support' => 'full'),
        'Hungarian' => array('code' => 'hu', 'native' => 'Magyar', 'voice_support' => 'full'),
        'Indonesian' => array('code' => 'id', 'native' => 'Bahasa Indonesia', 'voice_support' => 'full'),
        'Malay' => array('code' => 'ms', 'native' => 'Bahasa Melayu', 'voice_support' => 'full'),
        'Vietnamese' => array('code' => 'vi', 'native' => 'Tiếng Việt', 'voice_support' => 'full'),
        'Thai' => array('code' => 'th', 'native' => 'ไทย', 'voice_support' => 'full'),
    );
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('aipg_openrouter_key');
    }
    
    /**
     * Get supported languages for admin dropdown
     */
    public function get_supported_languages() {
        return $this->supported_languages;
    }
    
    /**
     * MULTILINGUAL: Generate complete podcast script
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
        
        // CRITICAL: Clean article content to fix any UTF-8 encoding issues
        $article_content = $this->clean_utf8($article_content);
        if (!empty($settings['search_data'])) {
            $settings['search_data'] = $this->clean_utf8($settings['search_data']);
        }
        
        $language = $settings['language'];
        $lang_info = $this->supported_languages[$language] ?? $this->supported_languages['English'];
        
        error_log('AIPG: Starting multilingual script generation');
        error_log('AIPG: Language: ' . $language . ' (' . $lang_info['native'] . ')');
        error_log('AIPG: Duration: ' . $settings['duration'] . ' minutes');
        error_log('AIPG: Article length: ' . strlen($article_content) . ' bytes');
        
        // Get website info for branding
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        // Stage 1: Generate outline
        $outline = $this->generate_script_outline($article_content, $settings, $language);
        
        if (is_wp_error($outline)) {
            error_log('AIPG: Outline generation failed - ' . $outline->get_error_message());
            return $outline;
        }
        
        error_log('AIPG: ✓ Outline generated with ' . count($outline['sections']) . ' sections');
        
        // Stage 2: Generate intro with branding
        $intro = $this->generate_intro($settings, $site_name, $language);
        
        if (is_wp_error($intro)) {
            error_log('AIPG: Intro generation failed');
            return $intro;
        }
        
        error_log('AIPG: ✓ Professional intro generated in ' . $language);
        
        // Stage 3: Generate main content sections
        $main_sections = $this->generate_main_content($article_content, $settings, $outline, $language);
        
        if (is_wp_error($main_sections)) {
            error_log('AIPG: Main content generation failed');
            return $main_sections;
        }
        
        error_log('AIPG: ✓ Main content generated (' . count($main_sections) . ' sections)');
        
        // Stage 4: Generate outro with call-to-action
        $outro = $this->generate_outro($settings, $site_name, $site_url, $language);
        
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
        
        error_log("AIPG: Script complete - Target: {$target_words} words, Actual: {$actual_words} words (" . round($completion_ratio * 100) . "%)");
        
        // If script is too short, regenerate with more detail
        if ($completion_ratio < 0.7) {
            error_log('AIPG: Script too short, regenerating with more detail...');
            $main_sections = $this->generate_main_content($article_content, $settings, $outline, $language, true);
            $complete_script = $intro . "\n\n" . implode("\n\n", $main_sections) . "\n\n" . $outro;
            $parsed = $this->parse_script_text($complete_script, $settings);
        }
        
        return $parsed;
    }
    
    /**
     * MULTILINGUAL: Generate professional branded intro
     */
    private function generate_intro($settings, $site_name, $language) {
        $host_names_text = implode(' and ', array_slice($settings['host_names'], 0, 2));
        $lang_info = $this->supported_languages[$language] ?? $this->supported_languages['English'];
        
        // Get localized welcome phrases
        $welcome_phrases = $this->get_welcome_phrases($language);
        
        $prompt = "You are a professional podcast intro writer. Create an engaging 30-second intro for a podcast.

CRITICAL: Write the ENTIRE intro in {$language} ({$lang_info['native']}). Every word must be in {$language}.

REQUIREMENTS:
- Podcast website: {$site_name}
- Hosts: {$host_names_text}
- Style: {$settings['podcast_style']}
- Tone: {$settings['tone']}
- Language: {$language}

The intro should:
1. Start with an attention-grabbing hook in {$language}
2. Introduce the podcast name ({$site_name} Podcast)
3. Introduce the hosts by name
4. Set expectations for the episode
5. Be energetic and welcoming
6. Include emotion tags for natural delivery

{$welcome_phrases}

Format each line as: SPEAKER_NAME: [emotion] dialogue in {$language}

Generate the complete intro now (5-7 lines) in {$language}:";

        $response = $this->chat_completion($prompt, $settings['model'], 1000);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return trim($response['choices'][0]['message']['content'] ?? '');
    }
    
    /**
     * MULTILINGUAL: Generate professional outro
     */
    private function generate_outro($settings, $site_name, $site_url, $language) {
        $host_names_text = implode(' and ', array_slice($settings['host_names'], 0, 2));
        $lang_info = $this->supported_languages[$language] ?? $this->supported_languages['English'];
        
        // Get localized thank you phrases
        $thank_you_phrases = $this->get_thank_you_phrases($language);
        
        $prompt = "You are a professional podcast outro writer. Create a compelling outro for a podcast.

CRITICAL: Write the ENTIRE outro in {$language} ({$lang_info['native']}). Every word must be in {$language}.

REQUIREMENTS:
- Podcast website: {$site_name}
- Website URL: {$site_url}
- Hosts: {$host_names_text}
- Style: {$settings['podcast_style']}
- Language: {$language}

The outro should:
1. Summarize key takeaways (1-2 lines) in {$language}
2. Thank the listener in {$language}
3. Include call-to-action (visit website, subscribe, share) in {$language}
4. Mention the website name clearly
5. Be warm and memorable
6. Include emotion tags

{$thank_you_phrases}

Format each line as: SPEAKER_NAME: [emotion] dialogue in {$language}

Generate the complete outro now (6-8 lines) in {$language}:";

        $response = $this->chat_completion($prompt, $settings['model'], 1000);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return trim($response['choices'][0]['message']['content'] ?? '');
    }
    
    /**
     * Get localized welcome phrases for different languages
     */
    private function get_welcome_phrases($language) {
        $phrases = array(
            'English' => "Example: Welcome to the {site} Podcast! / Thanks for tuning in!",
            'Greek' => "Παράδειγμα: Καλώς ήρθατε στο Podcast του {site}! / Ευχαριστούμε που μας ακούτε!",
            'Spanish' => "Ejemplo: ¡Bienvenidos al Podcast de {site}! / ¡Gracias por escucharnos!",
            'French' => "Exemple: Bienvenue sur le Podcast {site}! / Merci de nous écouter!",
            'German' => "Beispiel: Willkommen beim {site} Podcast! / Danke fürs Zuhören!",
            'Italian' => "Esempio: Benvenuti al Podcast di {site}! / Grazie per l'ascolto!",
            'Portuguese' => "Exemplo: Bem-vindos ao Podcast {site}! / Obrigado por nos ouvir!",
            'Arabic' => "مثال: مرحباً بكم في بودكاست {site}! / شكراً للاستماع!",
            'Russian' => "Пример: Добро пожаловать в подкаст {site}! / Спасибо что слушаете!",
            'Turkish' => "Örnek: {site} Podcast'ine hoş geldiniz! / Dinlediğiniz için teşekkürler!",
            'Chinese' => "示例：欢迎来到{site}播客！/ 感谢收听！",
            'Japanese' => "例：{site}ポッドキャストへようこそ！/ お聴きいただきありがとうございます！",
        );
        
        return $phrases[$language] ?? $phrases['English'];
    }
    
    /**
     * Get localized thank you phrases
     */
    private function get_thank_you_phrases($language) {
        $phrases = array(
            'English' => "Example: Thanks for listening! / Visit our website for more!",
            'Greek' => "Παράδειγμα: Ευχαριστούμε που ακούσατε! / Επισκεφθείτε την ιστοσελίδα μας για περισσότερα!",
            'Spanish' => "Ejemplo: ¡Gracias por escuchar! / ¡Visita nuestro sitio para más!",
            'French' => "Exemple: Merci d'avoir écouté! / Visitez notre site pour plus!",
            'German' => "Beispiel: Danke fürs Zuhören! / Besuchen Sie unsere Website für mehr!",
            'Italian' => "Esempio: Grazie per l'ascolto! / Visita il nostro sito per altro!",
            'Portuguese' => "Exemplo: Obrigado por ouvir! / Visite nosso site para mais!",
            'Arabic' => "مثال: شكراً للاستماع! / زوروا موقعنا لمزيد من المعلومات!",
            'Russian' => "Пример: Спасибо за внимание! / Посетите наш сайт!",
            'Turkish' => "Örnek: Dinlediğiniz için teşekkürler! / Daha fazlası için sitemizi ziyaret edin!",
            'Chinese' => "示例：感谢收听！/ 访问我们的网站了解更多！",
            'Japanese' => "例：お聴きいただきありがとうございます！/ 詳しくは当サイトへ！",
        );
        
        return $phrases[$language] ?? $phrases['English'];
    }
    
    /**
     * MULTILINGUAL: Generate script outline
     */
    private function generate_script_outline($content, $settings, $language) {
        $duration_words = $settings['duration'] * 150;
        $lang_info = $this->supported_languages[$language] ?? $this->supported_languages['English'];
        
        $prompt = "You are a podcast content strategist. Analyze this article and create a structured outline for a {$settings['duration']}-minute podcast in {$language}.

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
]

IMPORTANT: Section titles and points should reflect the {$language} language context.";

        $response = $this->chat_completion($prompt, $settings['model'], 2000);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $content_response = $response['choices'][0]['message']['content'] ?? '';
        
        // Extract JSON
        if (preg_match('/\[.*\]/s', $content_response, $matches)) {
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
     * MULTILINGUAL: Generate main content sections
     */
    private function generate_main_content($article_content, $settings, $outline, $language, $detailed = false) {
        $sections = array();
        $search_context = !empty($settings['search_data']) ? "\n\nADDITIONAL RESEARCH:\n" . substr($settings['search_data'], 0, 1000) : '';
        $lang_info = $this->supported_languages[$language] ?? $this->supported_languages['English'];
        
        foreach ($outline['sections'] as $index => $section_info) {
            error_log("AIPG: Generating section " . ($index + 1) . " in {$language}: " . $section_info['section']);
            
            $section_words = $section_info['words'] ?? 500;
            if ($detailed) {
                $section_words = (int)($section_words * 1.3);
            }
            
            $prompt = $this->build_section_prompt(
                $article_content, 
                $settings, 
                $section_info,
                $section_words,
                $index + 1,
                count($outline['sections']),
                $search_context,
                $language
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
                error_log("AIPG: ✓ Section " . ($index + 1) . " generated ({$word_count} words in {$language})");
            }
            
            // Small delay to avoid rate limits
            if ($index < count($outline['sections']) - 1) {
                usleep(500000);
            }
        }
        
        return $sections;
    }
    
    /**
     * MULTILINGUAL: Build prompt for a specific section
     */
    private function build_section_prompt($content, $settings, $section_info, $target_words, $section_num, $total_sections, $search_context, $language) {
        $host_names = $this->format_speaker_names($settings);
        $style_instructions = $this->get_style_instructions($settings['podcast_style'], $language);
        $emotion_instructions = $settings['include_emotions'] ? $this->get_emotion_instructions($language) : '';
        $lang_info = $this->supported_languages[$language] ?? $this->supported_languages['English'];
        
        $section_title = $section_info['section'];
        $key_points = !empty($section_info['points']) ? "\nKey points to cover:\n- " . implode("\n- ", $section_info['points']) : '';
        
        $context_note = $section_num === 1 ? 
            "\nNote: This is the start of the main content (after the intro). Jump right into the topic." : 
            "\nNote: This is section {$section_num} of {$total_sections}. Build on previous discussion.";
        
        return "You are writing section {$section_num} of a {$settings['duration']}-minute podcast.

CRITICAL: Write the ENTIRE dialogue in {$language} ({$lang_info['native']}). Every line of dialogue must be in {$language}.

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
2. Format: SPEAKER_NAME: [emotion] dialogue in {$language}
3. ALL dialogue must be in {$language} ({$lang_info['native']})
4. Make conversation natural with reactions, questions, back-and-forth
5. Include specific facts, examples, and insights from the article
6. Keep energy high and engaging
7. Use the speaker names exactly as specified

Generate this section now ({$target_words} words) in {$language}:";
    }
    
    /**
     * Get style instructions in multiple languages
     */
    private function get_style_instructions($style, $language) {
        $is_english = ($language === 'English');
        
        $styles = array(
            'conversational' => $is_english ? 
                "CONVERSATIONAL STYLE: Sound like friends discussing an interesting topic. Use casual but professional language." :
                "CONVERSATIONAL STYLE: Create natural, friendly dialogue like friends discussing an interesting topic in {$language}.",
            
            'interview' => $is_english ?
                "INTERVIEW STYLE: Host asks probing questions, guest provides expert answers." :
                "INTERVIEW STYLE: One host asks questions, the other provides expert answers in {$language}.",
            
            'debate' => $is_english ?
                "DEBATE STYLE: Present multiple perspectives respectfully." :
                "DEBATE STYLE: Present different viewpoints respectfully in {$language}.",
            
            'educational' => $is_english ?
                "EDUCATIONAL STYLE: Break down complex concepts clearly with examples." :
                "EDUCATIONAL STYLE: Explain concepts clearly with examples in {$language}.",
            
            'storytelling' => $is_english ?
                "STORYTELLING STYLE: Frame content as a narrative journey with vivid descriptions." :
                "STORYTELLING STYLE: Tell a story with vivid descriptions in {$language}.",
        );
        
        return $styles[$style] ?? $styles['conversational'];
    }
    
    /**
     * Get emotion instructions
     */
    private function get_emotion_instructions($language) {
        return "EMOTION TAGS (Use strategically):
[excited] [thoughtful] [concerned] [happy] [curious] [calm]

These work in any language - add them before dialogue to guide the voice delivery.";
    }
    
    /**
     * Parse complete script text
     */
    private function parse_script_text($script_text, $settings) {
        $script_lines = explode("\n", $script_text);
        $parsed_script = array();
        
        // Build a normalized name lookup for voice mapping
        $name_lookup = array();
        if (!empty($settings['voice_mapping'])) {
            foreach ($settings['voice_mapping'] as $mapped_name => $voice) {
                $normalized = mb_strtolower(trim($mapped_name), 'UTF-8');
                $name_lookup[$normalized] = $mapped_name; // Store original casing
            }
        }
        
        foreach ($script_lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Match format: SPEAKER: [emotion] dialogue or SPEAKER: dialogue
            if (preg_match('/^([^:]+):\s*(\[(\w+)\])?\s*(.+)$/u', $line, $matches)) {
                $speaker_raw = trim($matches[1]);
                $emotion = !empty($matches[3]) ? trim($matches[3]) : '';
                $text = trim($matches[4]);
                
                if (strlen($text) < 5) continue;
                
                // Normalize speaker name to match voice mapping
                $speaker_normalized = mb_strtolower($speaker_raw, 'UTF-8');
                $speaker = $name_lookup[$speaker_normalized] ?? $speaker_raw;
                
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
    
    // ... (Keep all the other methods: generate_episode_summary, generate_show_notes, 
    // chat_completion, generate_search_queries, select_best_article - these are language-agnostic)
    
    /**
     * Generate episode summary
     */
    public function generate_episode_summary($script_text, $article_title) {
        $prompt = "Create a compelling 2-3 paragraph podcast episode description.

PODCAST SCRIPT:
" . substr($script_text, 0, 2000) . "

Requirements:
1. Match the language of the script
2. Capture main topic and key insights
3. Make people want to listen
4. 150-200 words

Write the summary now:";
        
        $response = $this->chat_completion($prompt, 'openai/gpt-4o-mini', 500);
        
        if (is_wp_error($response)) {
            return "In this episode, we explore {$article_title} in depth.";
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
    
    private function format_timestamp($seconds) {
        $mins = floor($seconds / 60);
        $secs = floor($seconds % 60);
        return sprintf('%02d:%02d', $mins, $secs);
    }
    
    /**
     * Clean and fix UTF-8 encoding issues
     */
    private function clean_utf8($text) {
        if (empty($text)) {
            return $text;
        }
        
        // Remove any non-UTF-8 characters
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // Remove any remaining invalid UTF-8 sequences
        $text = iconv('UTF-8', 'UTF-8//IGNORE', $text);
        
        // Normalize Unicode characters (e.g., combining accents)
        if (class_exists('Normalizer')) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C);
        }
        
        return $text;
    }
    
    /**
     * Chat completion API call with UTF-8 support for Greek and other languages
     */
    public function chat_completion($prompt, $model = 'openai/gpt-4o-mini', $max_tokens = 4000) {
        $endpoint = $this->base_url . '/chat/completions';
        
        // Clean the prompt to ensure valid UTF-8
        $prompt = $this->clean_utf8($prompt);
        
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
        
        // Encode with JSON_UNESCAPED_UNICODE to support Greek and other Unicode characters
        $json_body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        
        // Check if encoding failed
        if ($json_body === false) {
            $error = json_last_error_msg();
            error_log('AIPG: JSON encoding failed - ' . $error);
            error_log('AIPG: Prompt length: ' . strlen($prompt) . ' bytes');
            error_log('AIPG: Prompt sample: ' . substr($prompt, 0, 200));
            return new WP_Error('json_encode_error', 'Failed to encode request: ' . $error);
        }
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json; charset=utf-8',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => $json_body,
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