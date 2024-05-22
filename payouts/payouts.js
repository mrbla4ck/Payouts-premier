jQuery(document).ready(function($) {
    console.log('Script loaded and executing.');

    $('.approve-payout-button, .reject-payout-button').on('click', function(e) {
        e.preventDefault();
        console.log('Button clicked.');

        var button = $(this);
        var payoutId = button.data('payout-id');
        var actionType = button.hasClass('approve-payout-button') ? 'approve_payout' : 'reject_payout';

        console.log('Action:', actionType, 'Payout ID:', payoutId);

        button.prop('disabled', true).text('Processing...');

        $.ajax({
            url: payoutsPremierAdmin.ajax_url,
            type: 'POST',
            data: {
                action: actionType,
                payout_id: payoutId,
                nonce: payoutsPremierAdmin.nonce
            },
            success: function(response) {
                console.log('Response:', response);
                if (response.success) {
                    alert(response.data.message);
                    location.reload(); // Reload the page to reflect changes
                } else {
                    alert('Failed: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                alert('AJAX error: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).text(button.hasClass('approve-payout-button') ? 'Approve' : 'Reject');
            }
        });
    });
});
