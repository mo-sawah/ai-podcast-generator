<?php
/**
 * FIXED Admin Interface - Voice Selection and Guest Support
 * 
 * Use this to replace the render_generate_page() function in class-aipg-admin.php
 */

// Add this JavaScript to admin.js for dynamic voice field visibility
?>
<script>
jQuery(document).ready(function($) {
    // Update voice fields when hosts count changes
    $('#aipg-hosts').on('change', function() {
        const hostCount = parseInt($(this).val());
        
        // Show/hide host voice selectors
        for (let i = 1; i <= 3; i++) {
            const $row = $(`.voice-row-host${i}`);
            if (i <= hostCount) {
                $row.show();
            } else {
                $row.hide();
            }
        }
    });
    
    // Update guest voice field when guest checkbox changes
    $('input[name="include_guest"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('.voice-row-guest').show();
        } else {
            $('.voice-row-guest').hide();
        }
    });
    
    // Trigger initial state
    $('#aipg-hosts').trigger('change');
    $('input[name="include_guest"]').trigger('change');
});
</script>

<?php
// Add this HTML section to the generate page (replace the voice mapping section)
?>

<tr>
    <th scope="row">
        <label>Voice Assignment</label>
    </th>
    <td>
        <div class="voice-mapping-container">
            <p class="description">Assign different voices to each host and guest for variety.</p>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Speaker</th>
                        <th>Voice</th>
                        <th>Preview</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $voices = array(
                        'alloy' => 'Alloy (Neutral, Balanced)',
                        'echo' => 'Echo (Male, Professional)',
                        'fable' => 'Fable (Male, Expressive)',
                        'onyx' => 'Onyx (Male, Authoritative)',
                        'nova' => 'Nova (Female, Energetic)',
                        'shimmer' => 'Shimmer (Female, Soft)',
                        'ballad' => 'Ballad (Female, Warm)',
                        'coral' => 'Coral (Female, Friendly)',
                        'ash' => 'Ash (Male, Clear)',
                        'sage' => 'Sage (Female, Calm)',
                        'verse' => 'Verse (Neutral, Versatile)',
                    );
                    
                    // Host 1
                    ?>
                    <tr class="voice-row-host1">
                        <td><strong>Host 1 (Alex)</strong></td>
                        <td>
                            <select name="voice_host1" class="regular-text">
                                <?php foreach ($voices as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($value, 'echo'); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><span class="description">Main host</span></td>
                    </tr>
                    
                    <!-- Host 2 -->
                    <tr class="voice-row-host2">
                        <td><strong>Host 2 (Sam)</strong></td>
                        <td>
                            <select name="voice_host2" class="regular-text">
                                <?php foreach ($voices as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($value, 'nova'); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><span class="description">Co-host</span></td>
                    </tr>
                    
                    <!-- Host 3 -->
                    <tr class="voice-row-host3" style="display:none;">
                        <td><strong>Host 3 (Jordan)</strong></td>
                        <td>
                            <select name="voice_host3" class="regular-text">
                                <?php foreach ($voices as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($value, 'sage'); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><span class="description">Third host</span></td>
                    </tr>
                    
                    <!-- Guest -->
                    <tr class="voice-row-guest" style="display:none;">
                        <td><strong>Guest Expert</strong></td>
                        <td>
                            <select name="voice_guest" class="regular-text">
                                <?php foreach ($voices as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($value, 'onyx'); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><span class="description">Expert guest</span></td>
                    </tr>
                </tbody>
            </table>
            
            <p class="description" style="margin-top: 10px;">
                <strong>Voice Tips:</strong><br>
                • Mix male and female voices for variety<br>
                • Use distinctive voices (e.g., Echo + Nova) for easy differentiation<br>
                • Professional voices: Echo, Onyx, Sage<br>
                • Energetic voices: Nova, Coral, Fable
            </p>
        </div>
    </td>
</tr>
