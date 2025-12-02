// AI Podcast Generator - Admin Script
(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Tab switching
        $('.aipg-tab-btn').on('click', function() {
            const tab = $(this).data('tab');
            $('.aipg-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.aipg-tab-content').removeClass('active');
            $('#' + tab + '-tab').addClass('active');
        });
        
        // Manual generation form
        $('#aipg-generate-form').on('submit', function(e) {
            e.preventDefault();
            
            const postId = $('#aipg-post-select').val() || $('#aipg-post-id').val();
            
            if (!postId) {
                alert('Please select or enter a post ID');
                return;
            }
            
            const formData = {
                action: 'aipg_generate_manual',
                nonce: aipgAdmin.nonce,
                post_id: postId,
                duration: $('#aipg-duration').val(),
                language: $('#aipg-language').val(),
                hosts: $('#aipg-hosts').val(),
                include_guest: $('input[name="include_guest"]').is(':checked') ? 'yes' : 'no',
                intro_text: $('textarea[name="intro_text"]').val(),
                outro_text: $('textarea[name="outro_text"]').val(),
                voice_mapping: JSON.stringify({
                    'Host 1': $('select[name="voice_host1"]').val(),
                    'Host 2': $('select[name="voice_host2"]').val(),
                })
            };
            
            showStatus('Initiating podcast generation...', 'info');
            
            $.ajax({
                url: aipgAdmin.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showStatus(
                            'Podcast generation started! Generation ID: ' + response.data.generation_id + 
                            '. The process will continue in the background. You can check the dashboard for progress.',
                            'success'
                        );
                        
                        // Show progress monitor
                        monitorGeneration(response.data.generation_id);
                    } else {
                        showStatus('Error: ' + response.data, 'error');
                    }
                },
                error: function(xhr) {
                    showStatus('AJAX error: ' + xhr.statusText, 'error');
                }
            });
        });
        
        // Auto-select form
        $('#aipg-auto-select-form').on('submit', function(e) {
            e.preventDefault();
            
            const count = $('input[name="count"]').val();
            
            $('#aipg-auto-select-status').html('<div class="notice notice-info"><p>Analyzing articles and selecting the best one...</p></div>');
            
            $.ajax({
                url: aipgAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aipg_select_article',
                    nonce: aipgAdmin.nonce,
                    count: count
                },
                success: function(response) {
                    if (response.success) {
                        $('#aipg-auto-select-status').html(
                            '<div class="notice notice-success"><p>Selected: <strong>' + 
                            response.data.title + '</strong> (ID: ' + response.data.post_id + 
                            '). Now generating podcast...</p></div>'
                        );
                        
                        // Trigger generation with selected article
                        setTimeout(function() {
                            $('#aipg-post-id').val(response.data.post_id);
                            $('.aipg-tab-btn[data-tab="manual"]').click();
                            $('#aipg-generate-form').submit();
                        }, 2000);
                    } else {
                        $('#aipg-auto-select-status').html(
                            '<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>'
                        );
                    }
                },
                error: function(xhr) {
                    $('#aipg-auto-select-status').html(
                        '<div class="notice notice-error"><p>AJAX error: ' + xhr.statusText + '</p></div>'
                    );
                }
            });
        });
        
        // Dynamic voice mapping based on hosts
        $('#aipg-hosts').on('change', function() {
            const numHosts = parseInt($(this).val());
            const $voiceMapping = $('#aipg-voice-mapping');
            
            $voiceMapping.find('.aipg-voice-row:gt(0)').remove(); // Keep Host 1
            
            for (let i = 2; i <= numHosts; i++) {
                const $row = $('.aipg-voice-row:first').clone();
                $row.find('label').text('Host ' + i + ':');
                $row.find('select').attr('name', 'voice_host' + i);
                $voiceMapping.append($row);
            }
        });
        
        function showStatus(message, type) {
            const $status = $('#aipg-generation-status');
            let className = 'notice ';
            
            switch(type) {
                case 'success':
                    className += 'notice-success';
                    break;
                case 'error':
                    className += 'notice-error';
                    break;
                case 'info':
                default:
                    className += 'notice-info';
                    break;
            }
            
            $status.html('<div class="' + className + '"><p>' + message + '</p></div>').show();
        }
        
        function monitorGeneration(generationId) {
            // This would poll the server for progress updates
            // For now, just show a static message
            setTimeout(function() {
                showStatus(
                    'Generation is processing in the background. Check the <a href="' + 
                    aipgAdmin.dashboardUrl + '">dashboard</a> for updates.',
                    'info'
                );
            }, 3000);
        }
    });
    
})(jQuery);
