/**
 * Horizon Glass Podcast Player - JavaScript
 * Full-featured audio player with keyboard shortcuts
 */

(function ($) {
  "use strict";

  class HorizonPlayer {
    constructor(element) {
      this.$wrapper = $(element);
      this.$player = this.$wrapper.find(".podcast-player");
      this.$audio = this.$wrapper.find(".aipg-audio-element");
      this.audio = this.$audio[0];

      if (!this.audio) {
        console.error("AIPG Player: Audio element not found");
        return;
      }

      // UI Elements
      this.$playBtn = this.$wrapper.find(".aipg-play-btn");
      this.$playIcon = this.$wrapper.find(".aipg-play-icon");
      this.$pauseIcon = this.$wrapper.find(".aipg-pause-icon");
      this.$progressBar = this.$wrapper.find(".aipg-progress-bar");
      this.$progressFill = this.$wrapper.find(".aipg-progress-fill");
      this.$currentTime = this.$wrapper.find(".aipg-current-time");
      this.$totalTime = this.$wrapper.find(".aipg-total-time");
      this.$skipBack = this.$wrapper.find(".aipg-skip-back");
      this.$skipForward = this.$wrapper.find(".aipg-skip-forward");
      this.$speedBtn = this.$wrapper.find(".aipg-speed-btn");
      this.$speedText = this.$wrapper.find(".aipg-speed-text");
      this.$volumeSlider = this.$wrapper.find(".aipg-volume-slider");

      // State
      this.speeds = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
      this.currentSpeedIndex = 2; // 1x
      this.isDragging = false;

      this.init();
    }

    init() {
      // Set initial volume
      this.audio.volume = 0.8;

      // Bind events
      this.bindEvents();

      // Try to load metadata
      if (this.audio.readyState >= 1) {
        this.onLoadedMetadata();
      }
    }

    bindEvents() {
      // Play/Pause
      this.$playBtn.on("click", () => this.togglePlay());

      // Progress bar
      this.$progressBar.on("click", (e) => this.seek(e));
      this.$progressBar.on("mousedown", () => {
        this.isDragging = true;
      });
      $(document).on("mouseup", () => {
        this.isDragging = false;
      });
      this.$progressBar.on("mousemove", (e) => {
        if (this.isDragging) this.seek(e);
      });

      // Skip buttons
      this.$skipBack.on("click", () => this.skip(-15));
      this.$skipForward.on("click", () => this.skip(30));

      // Speed control
      this.$speedBtn.on("click", () => this.cycleSpeed());

      // Volume
      this.$volumeSlider.on("input", (e) =>
        this.setVolume(e.target.value / 100)
      );

      // Audio events
      this.audio.addEventListener("loadedmetadata", () =>
        this.onLoadedMetadata()
      );
      this.audio.addEventListener("timeupdate", () => this.onTimeUpdate());
      this.audio.addEventListener("ended", () => this.onEnded());
      this.audio.addEventListener("play", () => this.onPlay());
      this.audio.addEventListener("pause", () => this.onPause());
      this.audio.addEventListener("error", (e) => this.onError(e));

      // Keyboard shortcuts
      this.bindKeyboardShortcuts();
    }

    bindKeyboardShortcuts() {
      $(document).on("keydown", (e) => {
        // Ignore if typing in input
        if ($("input, textarea").is(":focus")) return;

        // Only handle if this player is visible
        if (!this.$wrapper.is(":visible")) return;

        switch (e.key) {
          case " ":
          case "k":
            e.preventDefault();
            this.togglePlay();
            break;
          case "ArrowLeft":
          case "j":
            e.preventDefault();
            this.skip(-15);
            break;
          case "ArrowRight":
          case "l":
            e.preventDefault();
            this.skip(30);
            break;
          case "ArrowUp":
            e.preventDefault();
            this.changeVolume(0.1);
            break;
          case "ArrowDown":
            e.preventDefault();
            this.changeVolume(-0.1);
            break;
          case "m":
            e.preventDefault();
            this.toggleMute();
            break;
          case "0":
            e.preventDefault();
            this.audio.currentTime = 0;
            break;
        }
      });
    }

    togglePlay() {
      if (this.audio.paused) {
        this.play();
      } else {
        this.pause();
      }
    }

    play() {
      const playPromise = this.audio.play();

      if (playPromise !== undefined) {
        playPromise.catch((error) => {
          console.error("AIPG Player: Playback failed", error);
          this.onPause();
        });
      }
    }

    pause() {
      this.audio.pause();
    }

    onPlay() {
      this.$player.addClass("is-playing");
      this.$playIcon.hide();
      this.$pauseIcon.show();
    }

    onPause() {
      this.$player.removeClass("is-playing");
      this.$playIcon.show();
      this.$pauseIcon.hide();
    }

    onLoadedMetadata() {
      const duration = this.audio.duration;
      if (!isNaN(duration) && duration > 0) {
        this.$totalTime.text(this.formatTime(duration));
        this.$wrapper
          .find(".aipg-duration-display")
          .text(this.formatTime(duration));
      }
    }

    onTimeUpdate() {
      const current = this.audio.currentTime;
      const duration = this.audio.duration;

      // Update time display
      this.$currentTime.text(this.formatTime(current));

      // Update progress bar
      if (!isNaN(duration) && duration > 0) {
        const percent = (current / duration) * 100;
        this.$progressFill.css("width", percent + "%");
      }
    }

    onEnded() {
      this.$player.removeClass("is-playing");
      this.$playIcon.show();
      this.$pauseIcon.hide();
      this.audio.currentTime = 0;
    }

    onError(e) {
      console.error("AIPG Player: Audio error", e);
      this.$player.addClass("error");
    }

    seek(e) {
      const rect = this.$progressBar[0].getBoundingClientRect();
      const percent = (e.clientX - rect.left) / rect.width;
      const newTime = percent * this.audio.duration;

      if (!isNaN(newTime) && newTime >= 0 && newTime <= this.audio.duration) {
        this.audio.currentTime = newTime;
      }
    }

    skip(seconds) {
      const newTime = this.audio.currentTime + seconds;
      this.audio.currentTime = Math.max(
        0,
        Math.min(newTime, this.audio.duration)
      );
    }

    cycleSpeed() {
      this.currentSpeedIndex =
        (this.currentSpeedIndex + 1) % this.speeds.length;
      const speed = this.speeds[this.currentSpeedIndex];
      this.audio.playbackRate = speed;
      this.$speedText.text(speed + "x");
    }

    setVolume(volume) {
      this.audio.volume = Math.max(0, Math.min(1, volume));
      this.$volumeSlider.val(this.audio.volume * 100);
    }

    changeVolume(delta) {
      this.setVolume(this.audio.volume + delta);
    }

    toggleMute() {
      if (this.audio.volume > 0) {
        this.lastVolume = this.audio.volume;
        this.setVolume(0);
      } else {
        this.setVolume(this.lastVolume || 0.8);
      }
    }

    formatTime(seconds) {
      if (isNaN(seconds) || seconds === 0) return "0:00";

      const h = Math.floor(seconds / 3600);
      const m = Math.floor((seconds % 3600) / 60);
      const s = Math.floor(seconds % 60);

      if (h > 0) {
        return (
          h + ":" + (m < 10 ? "0" : "") + m + ":" + (s < 10 ? "0" : "") + s
        );
      }
      return m + ":" + (s < 10 ? "0" : "") + s;
    }
  }

  // Initialize all players on page
  $(document).ready(function () {
    $(".aipg-player-wrapper").each(function () {
      new HorizonPlayer(this);
    });
  });

  // Make HorizonPlayer available globally for dynamic players
  window.HorizonPlayer = HorizonPlayer;
})(jQuery);

/**
 * Initialize player theme from backend setting
 */
(function () {
  if (typeof aipgPlayer !== "undefined" && aipgPlayer.theme) {
    document
      .querySelectorAll(".aipg-player-wrapper")
      .forEach(function (wrapper) {
        wrapper.setAttribute("data-theme", aipgPlayer.theme);
      });
  }
})();
