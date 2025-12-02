#!/bin/bash
# AI Podcast Generator - Installation Helper

echo "============================================"
echo "AI Podcast Generator - Installation"
echo "============================================"
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed!"
    echo ""
    echo "Please install Composer first:"
    echo "https://getcomposer.org/download/"
    echo ""
    echo "Or use the WordPress admin to install WooCommerce"
    echo "(WooCommerce includes Action Scheduler)"
    exit 1
fi

echo "✓ Composer found"
echo ""

# Check if we're in the plugin directory
if [ ! -f "composer.json" ]; then
    echo "❌ composer.json not found!"
    echo "Please run this script from the plugin directory:"
    echo "cd wp-content/plugins/ai-podcast-generator"
    exit 1
fi

echo "Installing dependencies..."
echo ""

composer install --no-dev --optimize-autoloader

if [ $? -eq 0 ]; then
    echo ""
    echo "============================================"
    echo "✅ Installation Complete!"
    echo "============================================"
    echo ""
    echo "Next steps:"
    echo "1. Activate the plugin in WordPress admin"
    echo "2. Go to AI Podcasts > Settings"
    echo "3. Add your API keys:"
    echo "   - OpenRouter: https://openrouter.ai/keys"
    echo "   - OpenAI: https://platform.openai.com/api-keys"
    echo "   - Tavily: https://tavily.com/ (optional)"
    echo "4. Start generating podcasts!"
    echo ""
else
    echo ""
    echo "❌ Installation failed!"
    echo "Please check the error messages above"
    exit 1
fi
