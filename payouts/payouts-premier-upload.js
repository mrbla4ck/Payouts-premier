jQuery(document).ready(function($) {
    $('.add-limit').on('click', function() {
        var button = $(this);
        var requestId = button.data('request-id');
        var addLimit = parseInt(button.closest('tr').find('input[type="number"]').val());

        if (!addLimit || addLimit <= 0) {
            alert('Please enter a valid number greater than zero.');
            return;
        }

        $.ajax({
            url: ajaxurl, // Ensure this is defined via wp_localize_script
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'update_upgrade_request',
                request_id: requestId,
                add_limit: addLimit,
                nonce: button.data('nonce') // Pass nonce here for security
            },
            success: function(response) {
                alert(response.data.message);
                if (response.success) {
                    location.reload();
                }
            },
            error: function() {
                alert('Failed to communicate with the server.');
                button.prop('disabled', false).text('Add Limit');
            }
        });
    });

    $('.ignore-request').on('click', function() {
        var button = $(this);
        var requestId = button.data('request-id');

        if (!confirm('Are you sure you want to ignore this request?')) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ignore_upgrade_request',
                request_id: requestId,
                nonce: button.data('nonce') // Pass nonce here for security
            },
            success: function(response) {
                alert(response.data.message);
                if (response.success) {
                    location.reload();
                }
            },
            error: function() {
                alert('Failed to communicate with the server.');
                button.prop('disabled', false).text('Ignore');
            }
        });
    });
});
