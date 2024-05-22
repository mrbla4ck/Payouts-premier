jQuery(document).ready(function($) {
    // Delegate click event to dynamic approve buttons
    $(document).on('click', '.approve-upload-button', function() {
        var button = $(this);
        var uploadId = button.data('upload-id');
        button.prop('disabled', true).text('Processing...');

        $.ajax({
            url: payoutsPremierUpload.ajax_url,
            type: 'POST',
            data: {
                action: 'admin_approve_upload',
                upload_id: uploadId,
                nonce: payoutsPremierUpload.approve_upload_nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Upload approved successfully.');
                    location.reload(); // Reload the page to update the list
                } else {
                    alert('Failed to approve upload: ' + response.data.message);
                    button.prop('disabled', false).text('Approve');
                }
            },
            error: function() {
                alert('AJAX error: Could not contact server.');
                button.prop('disabled', false).text('Approve');
            }
        });
    });

    // Delegate click event to dynamic reject buttons
    $(document).on('click', '.reject-upload-button', function() {
        var button = $(this);
        var uploadId = button.data('upload-id');
        button.prop('disabled', true).text('Processing...');

        $.ajax({
            url: payoutsPremierUpload.ajax_url,
            type: 'POST',
            data: {
                action: 'admin_reject_upload',
                upload_id: uploadId,
                nonce: payoutsPremierUpload.reject_upload_nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Upload rejected successfully.');
                    location.reload(); // Reload the page to update the list
                } else {
                    alert('Failed to reject upload: ' + response.data.message);
                    button.prop('disabled', false).text('Reject');
                }
            },
            error: function() {
                alert('AJAX error: Could not contact server.');
                button.prop('disabled', false).text('Reject');
            }
        });
    });
});
