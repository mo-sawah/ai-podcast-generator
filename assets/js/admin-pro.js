/**
 * AI Podcast Generator - FIXED Admin Script
 * Improved voice mapping and form submission
 */

(function ($) {
  "use strict";

  let currentAudio = null;

  $(document).ready(function () {
    // TTS Provider Switcher
    $("#aipg_tts_provider").on("change", function () {
      const provider = $(this).val();
      $(".aipg-provider-settings").hide();
      if (provider === "elevenlabs") {
        $(".aipg-elevenlabs-settings").show();
      } else {
        $(".aipg-openai-settings").show();
      }
    });

    // TTS Model Test
    $("#aipg-test-tts").on("click", function () {
      const $btn = $(this);
      const $results = $("#aipg-tts-test-results");
      const provider = $("#aipg_tts_provider").val() || "openai";

      $btn
        .prop("disabled", true)
        .html(
          '<span class="dashicons dashicons-update aipg-spin"></span> Testing...'
        );
      $results.html(
        '<p style="color: #666;"><em>Testing ' +
          (provider === "elevenlabs" ? "ElevenLabs" : "OpenAI") +
          " TTS...</em></p>"
      );

      $.ajax({
        url: aipgAdmin.ajaxurl,
        type: "POST",
        data: {
          action: "aipg_test_tts_access",
          nonce: aipgAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            const provider = response.data.provider;
            const results = response.data.results;

            if (provider === "elevenlabs") {
              let html =
                '<div class="aipg-tts-test-results" style="border-left: 4px solid #7d5fff; padding-left: 15px; margin-top: 10px;">';
              if (results[0].success) {
                html +=
                  '<p style="color: #28a745; margin: 8px 0; font-weight: 500;"><strong>✅ ElevenLabs API:</strong> ' +
                  results[0].message +
                  "</p>";
                html +=
                  '<p style="color: #666; font-size: 12px; margin: 5px 0 15px 20px;"><em>✓ Connected! Model: ' +
                  results[0].model +
                  "</em></p>";
              } else {
                html +=
                  '<p style="color: #dc3545; margin: 8px 0;"><strong>❌ ElevenLabs API:</strong> ' +
                  results[0].message +
                  "</p>";
              }
              html += "</div>";
              $results.html(html);
            } else {
              let html =
                '<div class="aipg-tts-test-results" style="border-left: 4px solid #7d5fff; padding-left: 15px; margin-top: 10px;">';
              if (results["tts-1"]) {
                const test = results["tts-1"];
                if (test.success) {
                  html +=
                    '<p style="color: #28a745; margin: 8px 0; font-weight: 500;"><strong>✅ Standard (tts-1):</strong> ' +
                    test.message +
                    "</p>";
                } else {
                  html +=
                    '<p style="color: #dc3545; margin: 8px 0;"><strong>❌ Standard (tts-1):</strong> ' +
                    test.error +
                    "</p>";
                }
              }
              if (results["tts-1-hd"]) {
                const test = results["tts-1-hd"];
                if (test.success) {
                  html +=
                    '<p style="color: #28a745; margin: 8px 0;"><strong>✅ HD (tts-1-hd):</strong> ' +
                    test.message +
                    "</p>";
                } else {
                  html +=
                    '<p style="color: #dc3545; margin: 8px 0;"><strong>❌ HD (tts-1-hd):</strong> ' +
                    test.error +
                    "</p>";
                }
              }
              html += "</div>";
              $results.html(html);
            }
          } else {
            $results.html(
              '<p style="color: #dc3545;">' + response.data.message + "</p>"
            );
          }
        },
        error: function () {
          $results.html(
            '<p style="color: #dc3545;">❌ Test failed. Check your API key.</p>'
          );
        },
        complete: function () {
          $btn
            .prop("disabled", false)
            .html(
              '<span class="dashicons dashicons-admin-tools"></span> Test TTS Provider'
            );
        },
      });
    });

    // Dynamic hosts configuration
    $("#aipg-hosts")
      .on("change", function () {
        const numHosts = parseInt($(this).val());
        $("#aipg-host-1-config").toggle(numHosts >= 1);
        $("#aipg-host-2-config").toggle(numHosts >= 2);
        $("#aipg-host-3-config").toggle(numHosts >= 3);
      })
      .trigger("change");

    // Guest toggle
    $("#aipg-include-guest")
      .on("change", function () {
        $("#aipg-guest-config").toggle($(this).is(":checked"));
      })
      .trigger("change");

    // Voice preview
    $(".aipg-voice-preview-btn").on("click", function (e) {
      e.preventDefault();
      const $btn = $(this);
      const voice = $btn
        .closest(".aipg-voice-select-wrapper")
        .find(".aipg-voice-select")
        .val();
      playVoicePreview(voice, $btn);
    });

    // Update button data when voice changes
    $(".aipg-voice-select").on("change", function () {
      const voice = $(this).val();
      $(this)
        .closest(".aipg-voice-select-wrapper")
        .find(".aipg-voice-preview-btn")
        .attr("data-voice", voice);
    });

    // AI Auto-Select
    $("#aipg-auto-select-btn").on("click", function () {
      const $btn = $(this);
      const originalText = $btn.html();

      $btn
        .prop("disabled", true)
        .html(
          '<span class="dashicons dashicons-update aipg-spin"></span> Analyzing...'
        );

      $.ajax({
        url: aipgAdmin.ajaxurl,
        type: "POST",
        data: {
          action: "aipg_select_article",
          nonce: aipgAdmin.nonce,
          count: 20,
        },
        success: function (response) {
          if (response.success) {
            $("#aipg-post-select").val(response.data.post_id);
            showNotice("Selected: " + response.data.title, "success");
          } else {
            showNotice("Error: " + response.data, "error");
          }
        },
        error: function () {
          showNotice("Network error occurred", "error");
        },
        complete: function () {
          $btn.prop("disabled", false).html(originalText);
        },
      });
    });

    // FIXED: Generate form submission with proper voice mapping
    $("#aipg-generate-form").on("submit", function (e) {
      e.preventDefault();

      const numHosts = parseInt($("#aipg-hosts").val());
      const includeGuest = $("#aipg-include-guest").is(":checked");

      // Build form data with EXACT speaker names matching what will be in the script
      const formData = {
        action: "aipg_generate_manual",
        nonce: aipgAdmin.nonce,
        post_id: $("#aipg-post-select").val(),
        duration: $("#aipg-duration").val(),
        language: $("#aipg-language").val(),
        podcast_style: $("#aipg-style").val(),
        tone: $("#aipg-tone").val(),
        hosts: numHosts,
        include_guest: includeGuest ? "yes" : "no",
        include_emotions: $('input[name="include_emotions"]').is(":checked")
          ? "1"
          : "0",
      };

      // Add host names and voices with proper indexing
      for (let i = 1; i <= numHosts; i++) {
        const hostName =
          $(`input[name="host_${i}_name"]`).val().trim() || `Host ${i}`;
        const hostVoice = $(`select[name="voice_host_${i}"]`).val();

        formData[`host_${i}_name`] = hostName;
        formData[`voice_host_${i}`] = hostVoice;

        console.log(`Host ${i}: Name="${hostName}", Voice="${hostVoice}"`);
      }

      // Add guest if included
      if (includeGuest) {
        const guestName =
          $('input[name="guest_name"]').val().trim() || "Expert";
        const guestVoice = $('select[name="voice_guest"]').val();

        formData.guest_name = guestName;
        formData.voice_guest = guestVoice;

        console.log(`Guest: Name="${guestName}", Voice="${guestVoice}"`);
      }

      console.log("Form data being submitted:", formData);

      showStatusMessage("Initiating podcast generation...", "info", true);

      $.ajax({
        url: aipgAdmin.ajaxurl,
        type: "POST",
        data: formData,
        success: function (response) {
          if (response.success) {
            showStatusMessage(
              "<strong>Success!</strong> Podcast generation started (ID: #" +
                response.data.generation_id +
                "). " +
                "The process will continue in the background. " +
                '<a href="' +
                aipgAdmin.dashboardUrl +
                '">View dashboard</a> to monitor progress.',
              "success"
            );

            setTimeout(function () {
              $("#aipg-generate-form")[0].reset();
              $("#aipg-hosts").trigger("change");
            }, 3000);
          } else {
            showStatusMessage(
              "<strong>Error:</strong> " + response.data,
              "error"
            );
          }
        },
        error: function (xhr) {
          showStatusMessage(
            "<strong>Network Error:</strong> " + xhr.statusText,
            "error"
          );
        },
      });
    });

    // Retry generation
    $(document).on("click", ".aipg-retry-btn", function () {
      const $btn = $(this);
      const generationId = $btn.data("generation-id");

      if (!confirm("Retry this podcast generation?")) {
        return;
      }

      $btn
        .prop("disabled", true)
        .html('<span class="dashicons dashicons-update aipg-spin"></span>');

      $.ajax({
        url: aipgAdmin.ajaxurl,
        type: "POST",
        data: {
          action: "aipg_retry_generation",
          nonce: aipgAdmin.nonce,
          generation_id: generationId,
        },
        success: function (response) {
          if (response.success) {
            showNotice(
              "Generation restarted! Refresh page to see updates.",
              "success"
            );
            setTimeout(function () {
              location.reload();
            }, 2000);
          } else {
            showNotice("Error: " + response.data, "error");
            $btn
              .prop("disabled", false)
              .html('<span class="dashicons dashicons-update"></span>');
          }
        },
        error: function () {
          showNotice("Network error occurred", "error");
          $btn
            .prop("disabled", false)
            .html('<span class="dashicons dashicons-update"></span>');
        },
      });
    });

    // View error
    $(document).on("click", ".aipg-view-error-btn", function () {
      const error = $(this).data("error");
      alert("Error Details:\n\n" + error);
    });
  });

  /**
   * Play voice preview
   */
  function playVoicePreview(voice, $btn) {
    if (currentAudio) {
      currentAudio.pause();
      currentAudio = null;
      $(".aipg-voice-preview-btn").removeClass("is-playing");
    }

    $btn.prop("disabled", true).addClass("is-playing");

    $.ajax({
      url: aipgAdmin.ajaxurl,
      type: "POST",
      data: {
        action: "aipg_preview_voice",
        nonce: aipgAdmin.nonce,
        voice: voice,
        text:
          "Hi! This is a preview of the " +
          voice +
          " voice. How do you like the sound?",
      },
      success: function (response) {
        if (response.success) {
          currentAudio = new Audio(response.data.url);

          currentAudio.addEventListener("ended", function () {
            $btn.prop("disabled", false).removeClass("is-playing");
            currentAudio = null;
          });

          currentAudio.addEventListener("error", function () {
            showNotice("Error playing audio preview", "error");
            $btn.prop("disabled", false).removeClass("is-playing");
            currentAudio = null;
          });

          currentAudio.play();
        } else {
          showNotice("Error: " + response.data, "error");
          $btn.prop("disabled", false).removeClass("is-playing");
        }
      },
      error: function () {
        showNotice("Network error occurred", "error");
        $btn.prop("disabled", false).removeClass("is-playing");
      },
    });

    $btn.one("click", function (e) {
      e.preventDefault();
      if (currentAudio) {
        currentAudio.pause();
        currentAudio = null;
        $btn.prop("disabled", false).removeClass("is-playing");
      }
    });
  }

  /**
   * Show status message
   */
  function showStatusMessage(message, type, loading = false) {
    const $status = $("#aipg-generation-status");
    let className =
      "notice notice-" +
      (type === "error" ? "error" : type === "success" ? "success" : "info");

    if (loading) {
      message =
        '<span class="dashicons dashicons-update aipg-spin"></span> ' + message;
    }

    $status
      .html('<div class="' + className + '"><p>' + message + "</p></div>")
      .show();
  }

  /**
   * Show notice
   */
  function showNotice(message, type) {
    const $notice = $(
      '<div class="notice notice-' +
        type +
        ' is-dismissible"><p>' +
        message +
        "</p></div>"
    );

    $(".aipg-wrap").prepend($notice);

    setTimeout(function () {
      $notice.fadeOut(function () {
        $(this).remove();
      });
    }, 5000);
  }

  // Add spinning animation
  $("<style>")
    .prop("type", "text/css")
    .html(
      ".aipg-spin { animation: aipg-spin 1s linear infinite; } @keyframes aipg-spin { to { transform: rotate(360deg); } }"
    )
    .appendTo("head");
})(jQuery);
