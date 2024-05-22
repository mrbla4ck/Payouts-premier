jQuery(document).ready(function($) {
    $('#payoutRequestForm').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var formData = form.serialize() + '&action=payouts_premier_submit_payout_request&nonce=' + '<?php echo wp_create_nonce("payouts-premier-nonce"); ?>';

        // Show processing text or a loader
        form.find('button[type="submit"]').prop('disabled', true).text('Processing...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            dataType: 'json',  // Ensuring the response is treated as JSON
            success: function(response) {
                if(response.success) {
                    alert('Payout request submitted successfully.');
                    // Refresh the page to show updated info, or handle the UI update here
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            },
            complete: function() {
                // Re-enable the button after the AJAX call completes
                form.find('button[type="submit"]').prop('disabled', false).text('Request Payout');
            }
        });
    });
});
