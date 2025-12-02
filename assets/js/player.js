// AI Podcast Generator - Player Script
(function($) {
    'use strict';
    
    class PodcastPlayer {
        constructor(element) {
            this.$player = $(element);
            this.$audio = this.$player.find('.aipg-audio-element');
            this.audio = this.$audio[0];
            
            this.$playBtn = this.$player.find('.aipg-play-button');
            this.$progressBar = this.$player.find('.aipg-progress-bar');
            this.$progressFill = this.$player.find('.aipg-progress-fill');
            this.$progressHandle = this.$player.find('.aipg-progress-handle');
            this.$currentTime = this.$player.find('.aipg-time-current');
            this.$duration = this.$player.find('.aipg-time-duration');
            this.$speedBtn = this.$player.find('.aipg-btn-speed');
            this.$speedText = this.$player.find('.aipg-speed-text');
            this.$skipBackBtn = this.$player.find('.aipg-btn-skip-back');
            this.$skipForwardBtn = this.$player.find('.aipg-btn-skip-forward');
            this.$volumeBtn = this.$player.find('.aipg-btn-volume');
            this.$volumeSlider = this.$player.find('.aipg-volume-slider');
            this.$volumeInput = this.$player.find('.aipg-volume-input');
            
            this.speeds = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
            this.currentSpeedIndex = 2; // 1x
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.audio.volume = 1;
        }
        
        bindEvents() {
            // Play/Pause
            this.$playBtn.on('click', () => this.togglePlay());
            
            // Progress bar
            this.$progressBar.on('click', (e) => this.seek(e));
            
            // Time updates
            this.audio.addEventListener('timeupdate', () => this.updateProgress());
            this.audio.addEventListener('loadedmetadata', () => this.updateDuration());
            
            // Speed control
            this.$speedBtn.on('click', () => this.cycleSpeed());
            
            // Skip buttons
            this.$skipBackBtn.on('click', () => this.skip(-15));
            this.$skipForwardBtn.on('click', () => this.skip(30));
            
            // Volume
            this.$volumeBtn.on('click', () => this.toggleVolume());
            this.$volumeInput.on('input', (e) => this.changeVolume(e));
            
            // Ended
            this.audio.addEventListener('ended', () => this.onEnded());
            
            // Keyboard shortcuts
            $(document).on('keydown', (e) => this.handleKeyboard(e));
        }
        
        togglePlay() {
            if (this.audio.paused) {
                this.audio.play();
                this.$player.addClass('playing');
            } else {
                this.audio.pause();
                this.$player.removeClass('playing');
            }
        }
        
        updateProgress() {
            const percent = (this.audio.currentTime / this.audio.duration) * 100;
            this.$progressFill.css('width', percent + '%');
            this.$progressHandle.css('left', percent + '%');
            this.$currentTime.text(this.formatTime(this.audio.currentTime));
        }
        
        updateDuration() {
            this.$duration.text(this.formatTime(this.audio.duration));
        }
        
        seek(e) {
            const rect = this.$progressBar[0].getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            this.audio.currentTime = percent * this.audio.duration;
        }
        
        cycleSpeed() {
            this.currentSpeedIndex = (this.currentSpeedIndex + 1) % this.speeds.length;
            const speed = this.speeds[this.currentSpeedIndex];
            this.audio.playbackRate = speed;
            this.$speedText.text(speed + 'x');
        }
        
        skip(seconds) {
            this.audio.currentTime = Math.max(0, Math.min(this.audio.duration, this.audio.currentTime + seconds));
        }
        
        toggleVolume() {
            this.$volumeSlider.toggle();
        }
        
        changeVolume(e) {
            this.audio.volume = e.target.value / 100;
        }
        
        onEnded() {
            this.$player.removeClass('playing');
            this.audio.currentTime = 0;
        }
        
        formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = Math.floor(seconds % 60);
            
            if (h > 0) {
                return h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            }
            return m + ':' + (s < 10 ? '0' : '') + s;
        }
        
        handleKeyboard(e) {
            // Only if player is visible and focused
            if (!this.$player.is(':visible')) return;
            
            switch(e.key) {
                case ' ':
                    e.preventDefault();
                    this.togglePlay();
                    break;
                case 'ArrowLeft':
                    this.skip(-15);
                    break;
                case 'ArrowRight':
                    this.skip(30);
                    break;
                case 'ArrowUp':
                    this.audio.volume = Math.min(1, this.audio.volume + 0.1);
                    break;
                case 'ArrowDown':
                    this.audio.volume = Math.max(0, this.audio.volume - 0.1);
                    break;
            }
        }
    }
    
    // Initialize all players
    $(document).ready(function() {
        $('.aipg-player').each(function() {
            new PodcastPlayer(this);
        });
    });
    
})(jQuery);
