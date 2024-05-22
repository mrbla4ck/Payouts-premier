<?php
/**
 * Dokan Dashboard Template
 *
 * Dokan Payouts/payout-history Dashboard template for Front-end
 *
 * @since 2.4
 * @package dokan
 */
?>
<div class="dokan-dashboard-wrap">
    <?php
        /**
         * dokan_dashboard_content_before hook
         * @hooked get_dashboard_side_navigation
         * @since 2.4
         */
        do_action('dokan_dashboard_content_before');
    ?>

    <div class="dokan-dashboard-content">
        <?php
            /**
             * dokan_help_content_inside_before hook
             * @hooked show_seller_dashboard_notice
             * @since 2.4
             */
            do_action('dokan_help_content_inside_before');
        ?>

        <article class="help-content-area">
            <h2>Request Payout</h2>
            <div class="payout-request-form">
                <form id="payoutRequestForm">
                    <input type="number" name="amount" placeholder="Enter payout amount" required>
                    <select name="method">
                        <option value="paypal">PayPal</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                    <button type="submit">Request Payout</button>
                </form>
            </div>

            <script type="text/javascript">
jQuery(document).ready(function($) {
    // Function to fetch vendor financial data
    function fetchVendorFinancialData() {
        return $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'fetch_vendor_financial_data',
                nonce: '<?php echo wp_create_nonce("fetch-vendor-financial-data-nonce"); ?>'
            }
        });
    }

    // Fetch financial data on document ready and store it
    var availableForWithdrawal = 0;
    var remainingProducts = 0;
    
    fetchVendorFinancialData().done(function(response) {
        if (response.success) {
            availableForWithdrawal = parseFloat(response.data.availableForWithdrawal);
            remainingProducts = parseInt(response.data.remainingProducts, 10);
        } else {
            alert('Failed to fetch financial data: ' + response.data.message);
        }
    });

    $('#payoutRequestForm').on('submit', function(e) {
        e.preventDefault();

        var requestedAmount = parseFloat($('input[name="amount"]').val());

        // Check remaining products
        if (remainingProducts > 0) {
            alert('You cannot request a payout until all your products are approved.');
            return;
        }

        // Check if the requested amount is within the available balance
        if (requestedAmount > availableForWithdrawal) {
            alert('Requested amount exceeds your available balance.');
            return;
        }

        var data = $(this).serialize() + '&action=payouts_premier_submit_payout_request&nonce=' + '<?php echo wp_create_nonce("payouts-premier-nonce"); ?>';

        $.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {
            if (response.success) {
                alert('Payout request submitted successfully.');
                location.reload(); // Optionally reload or update the table
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
});
</script>


            <h2>Payout History</h2>
            <table class="dokan-table">
                <thead>
                    <tr>
                        
                    </tr>
                </thead>
                <tbody>
                    <?php echo display_vendor_payout_requests(); ?>
                </tbody>
            </table>
        </article><!-- .dashboard-content-area -->

        <?php
            /**
             * dokan_dashboard_content_inside_after hook
             * @since 2.4
             */
            do_action('dokan_dashboard_content_inside_after');
        ?>
    </div><!-- .dokan-dashboard-content -->

    <?php
        /**
         * dokan_dashboard_content_after hook
         * @since 2.4
         */
        do_action('dokan_dashboard_content_after');
    ?>
</div><!-- .dokan-dashboard-wrap -->
