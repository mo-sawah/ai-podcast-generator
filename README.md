# AI Podcast Generator

Generate AI-powered podcasts from your WordPress articles with multiple AI hosts!

## 🚀 Quick Start (2 Methods)

### Method 1: With Composer (Recommended)

```bash
cd wp-content/plugins/ai-podcast-generator
composer install
```

Then activate the plugin in WordPress.

### Method 2: Without Composer (Easy Alternative)

**Just install WooCommerce!** WooCommerce includes Action Scheduler (the only dependency we need).

1. Install WooCommerce plugin from WordPress.org
2. Activate WooCommerce
3. Activate AI Podcast Generator
4. Done! (You don't even need to use WooCommerce features)

## ⚙️ Configuration

Go to **AI Podcasts > Settings** and add your API keys:

### Required API Keys

**OpenRouter** (for script generation)
- Sign up: https://openrouter.ai/
- Get key: https://openrouter.ai/keys
- Cost: ~$0.10 per script

**OpenAI** (for audio/voice)
- Sign up: https://platform.openai.com/
- Get key: https://platform.openai.com/api-keys
- Cost: ~$0.20 per 10-min podcast

### Optional API Key

**Tavily** (for web research)
- Sign up: https://tavily.com/
- Cost: ~$0.03 per podcast

## 🎙️ Features

- **Manual Generation**: Select any article and generate podcast
- **Auto-Select**: Let AI pick the best article
- **Automated**: Schedule hourly/daily automatic generation
- **Multiple Languages**: English, Spanish, French, German, etc.
- **Multiple Hosts**: 1-3 AI hosts with different voices
- **11 Voice Options**: Professional OpenAI voices
- **Custom Intro/Outro**: Brand your podcasts
- **Duration Control**: 5-30 minutes
- **Modern Player**: Beautiful embedded audio player
- **Elementor Compatible**: Customize podcast layouts

## 📖 How to Use

### Generate Your First Podcast

1. Go to **AI Podcasts > Generate**
2. Select a source article
3. Choose settings:
   - Duration: 10 minutes (recommended)
   - Language: English
   - Hosts: 2 (recommended)
4. Click **Generate Podcast**
5. Wait 5-10 minutes
6. Check **AI Podcasts > Dashboard** for status

### Enable Automation

1. Go to **AI Podcasts > Settings**
2. Enable "Auto-Generation"
3. Set frequency (e.g., "Every hour")
4. Plugin will automatically:
   - Review your recent articles
   - Select the best one using AI
   - Generate and publish podcast

## 🎨 Voice Options

Choose from 11 professional voices:

- **alloy** - Neutral, balanced
- **ash** - Male, clear
- **ballad** - Female, warm
- **coral** - Female, friendly
- **echo** - Male, professional
- **fable** - Male, expressive
- **nova** - Female, energetic
- **onyx** - Male, authoritative
- **sage** - Female, calm
- **shimmer** - Female, soft
- **verse** - Neutral, versatile

## 💰 Pricing

Per 10-minute podcast:
- Script generation: ~$0.10
- Audio generation: ~$0.20
- Web search: ~$0.03
- **Total: ~$0.33**

Monthly estimates:
- 1/day: ~$10/month
- 5/day: ~$50/month
- 20/day: ~$200/month

## 🔧 Technical Details

- **Background Processing**: Uses Action Scheduler (no timeouts!)
- **Smart Chunking**: Handles OpenAI's 4096 char limit
- **FFmpeg Support**: Merges audio chunks (optional)
- **Custom Post Type**: Full Elementor support
- **Shortcode**: `[ai_podcast_player]`

## 🐛 Troubleshooting

**"Plugin triggered fatal error"**
- Install WooCommerce (includes Action Scheduler)
- OR run: `composer install` in plugin directory

**"Generation stuck"**
- Check: WordPress Admin > Tools > Scheduled Actions
- Normal generation time: 5-10 minutes

**"Audio not merging"**
- Install FFmpeg: `sudo apt-get install ffmpeg`
- Or accept separate audio chunks (still works!)

**"API Error"**
- Verify API keys are correct
- Check you have credits/balance
- Test API keys directly on provider websites

## 📁 Plugin Structure

```
ai-podcast-generator/
├── ai-podcast-generator.php    # Main plugin file
├── composer.json               # Dependencies
├── install.sh                 # Installation helper
├── includes/                  # PHP classes
│   ├── class-aipg-database.php
│   ├── class-aipg-cpt.php
│   ├── class-aipg-admin.php
│   ├── class-aipg-openrouter.php
│   ├── class-aipg-openai-tts.php
│   ├── class-aipg-tavily.php
│   ├── class-aipg-generator.php
│   ├── class-aipg-scheduler.php
│   └── class-aipg-player.php
└── assets/
    ├── css/                   # Styles
    └── js/                    # Scripts
```

## 🎯 Requirements

- WordPress 6.0+
- PHP 7.4+
- Action Scheduler (via Composer or WooCommerce)
- FFmpeg (optional, for audio merging)

## 📝 Usage Tips

1. **Best duration**: 10-15 minutes
2. **Best format**: 2 hosts with different genders
3. **Best articles**: 500+ words with clear structure
4. **Languages**: Works best with English, Spanish, French, German
5. **Intro/outro**: Keep under 50 words

## 🆘 Support

- Documentation: Check INSTALLATION.txt and PLUGIN-OVERVIEW.txt
- Issues: contact@sawahsolutions.com
- WordPress Logs: Check for errors in debug.log

## 📜 License

GPL v2 or later

## 👨‍💻 Author

Mohamed Sawah - Sawah Solutions
https://sawahsolutions.com

---

**Made with ❤️ for the WordPress community**
