/**
 * MODERN HORIZON GLASS PODCAST PLAYER
 * JavaScript for player functionality
 * Version: 2.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        const player = $('#aipgPlayer');
        if (!player.length) return;
        
        const audio = document.getElementById('aipgAudio');
        const playButton = $('#aipgPlayButton');
        const playIcon = $('#aipgPlayIcon');
        const pauseIcon = $('#aipgPauseIcon');
        const progressBar = $('#aipgProgressBar');
        const progressFill = $('#aipgProgressFill');
        const currentTimeEl = $('#aipgCurrentTime');
        const totalTimeEl = $('#aipgTotalTime');
        const durationEl = $('#aipgDuration');
        const skipBackBtn = $('#aipgSkipBack');
        const skipForwardBtn = $('#aipgSkipForward');
        const speedButton = $('#aipgSpeedButton');
        const speedLabel = $('#aipgSpeedLabel');
        const volumeSlider = $('#aipgVolumeSlider');
        
        // Playback speeds
        const speeds = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
        let currentSpeedIndex = 2; // 1x
        
        // Initialize
        audio.volume = 0.8;
        
        // Format time
        function formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }
        
        // Load metadata
        audio.addEventListener('loadedmetadata', function() {
            totalTimeEl.text(formatTime(audio.duration));
            durationEl.text(formatTime(audio.duration));
        });
        
        // Update progress
        audio.addEventListener('timeupdate', function() {
            const progress = (audio.currentTime / audio.duration) * 100;
            progressFill.css('width', progress + '%');
            currentTimeEl.text(formatTime(audio.currentTime));
        });
        
        // Play/Pause
        playButton.on('click', function() {
            if (audio.paused) {
                audio.play();
                player.addClass('is-playing');
                playIcon.hide();
                pauseIcon.show();
            } else {
                audio.pause();
                player.removeClass('is-playing');
                playIcon.show();
                pauseIcon.hide();
            }
        });
        
        // Progress bar click
        progressBar.on('click', function(e) {
            const rect = this.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            audio.currentTime = percent * audio.duration;
        });
        
        // Skip back 15s
        skipBackBtn.on('click', function() {
            audio.currentTime = Math.max(0, audio.currentTime - 15);
        });
        
        // Skip forward 30s
        skipForwardBtn.on('click', function() {
            audio.currentTime = Math.min(audio.duration, audio.currentTime + 30);
        });
        
        // Speed control
        speedButton.on('click', function() {
            currentSpeedIndex = (currentSpeedIndex + 1) % speeds.length;
            const speed = speeds[currentSpeedIndex];
            audio.playbackRate = speed;
            speedLabel.text(speed + 'x');
        });
        
        // Volume control
        volumeSlider.on('input', function() {
            audio.volume = this.value / 100;
        });
        
        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            if ($('input, textarea').is(':focus')) return;
            
            switch(e.key) {
                case ' ':
                    e.preventDefault();
                    playButton.click();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    skipBackBtn.click();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    skipForwardBtn.click();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    audio.volume = Math.min(1, audio.volume + 0.1);
                    volumeSlider.val(audio.volume * 100);
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    audio.volume = Math.max(0, audio.volume - 0.1);
                    volumeSlider.val(audio.volume * 100);
                    break;
            }
        });
        
        // Auto-pause when audio ends
        audio.addEventListener('ended', function() {
            player.removeClass('is-playing');
            playIcon.show();
            pauseIcon.hide();
            audio.currentTime = 0;
        });
        
        // Error handling
        audio.addEventListener('error', function() {
            console.error('Audio loading error:', audio.error);
            alert('Error loading audio. Please try again later.');
        });
    });
    
})(jQuery);

/**
 * Theme toggle function (global)
 */
function aipgToggleTheme() {
    const wrapper = document.querySelector('.aipg-player-wrapper');
    const currentTheme = wrapper.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    wrapper.setAttribute('data-theme', newTheme);
    
    // Save preference
    localStorage.setItem('aipg-theme', newTheme);
}

// Load saved theme preference
document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.querySelector('.aipg-player-wrapper');
    if (!wrapper) return;
    
    const savedTheme = localStorage.getItem('aipg-theme');
    if (savedTheme) {
        wrapper.setAttribute('data-theme', savedTheme);
    }
});
