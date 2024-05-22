<?php
/**
 * Dokan Dashboard Payouts Template
 *
 * Handles the display of package selection and details on the vendor dashboard.
 *
 * @since 2.4
 * @package Dokan
 */
defined('ABSPATH') || exit; // Exit if accessed directly

global $wpdb;
$vendor_id = get_current_user_id();

// Fetch the selected package details from the database
$selected_package = $wpdb->get_row($wpdb->prepare(
    "SELECT vp.*, vd.uploaded_products, (vp.product_number - vd.uploaded_products) AS remaining_products
     FROM {$wpdb->prefix}vendor_packages vp
     JOIN {$wpdb->prefix}vendor_data vd ON vp.id = vd.package_id
     WHERE vd.vendor_id = %d",
    $vendor_id
));

// Handle POST request for package selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_package'])) {
    $package_id = intval($_POST['package_id']); // Get the selected package ID from the form

    // Update the vendor data based on selected package
    update_vendor_data_on_package_selection($vendor_id, $package_id);

    // Refresh the page to update displayed data
    wp_redirect($_SERVER['REQUEST_URI']);
    exit;
}

?>
<div class="dokan-dashboard-wrap">
    <?php do_action('dokan_dashboard_content_before'); ?>

    <div class="dokan-dashboard-content">
        <?php do_action('dokan_help_content_inside_before'); ?>

        <article class="help-content-area">
            <h1>Payouts and Packages</h1>
            <?php if (empty($selected_package)): ?>
                <form method="post" action="">
                    <label for="package_id">Select a package:</label>
                    <select name="package_id" id="package_id" required>
                        <?php
                        $packages = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}vendor_packages");
                        foreach ($packages as $package) {
                            echo '<option value="' . esc_attr($package->id) . '">' . esc_html($package->package_name) . ' (Limit: ' . $package->product_number . ', Price/Product: $' . number_format($package->price_per_product, 2) . ')</option>';
                        }
                        ?>
                    </select>
                    <input type="submit" name="select_package" value="Select Package" class="dokan-btn dokan-btn-theme">
                </form>
            <?php else: ?>
                <div>
                    <h2>Selected Package: <?php echo esc_html($selected_package->package_name); ?></h2>
                    <p>Product Limit: <?php echo intval($selected_package->product_number); ?></p>
                    <p>Price per Product: $<?php echo number_format($selected_package->price_per_product, 2); ?></p>
                    <p>Uploaded Products: <?php echo intval($selected_package->uploaded_products); ?></p>
                    <p>Remaining Uploads: <?php echo intval($selected_package->remaining_products); ?></p>
                    <?php if (intval($selected_package->remaining_products) === 0): ?>
                        <button id="request-upgrade" class="dokan-btn dokan-btn-danger">Request Upgrade</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>

        <?php do_action('dokan_dashboard_content_inside_after'); ?>
    </div>

    <?php do_action('dokan_dashboard_content_after'); ?>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#request-upgrade').on('click', function() {
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'post',
                data: {
                    action: 'request_upgrade',
                    vendor_id: '<?php echo $vendor_id; ?>',
                    current_limit: '<?php echo $selected_package->product_number; ?>'
                },
                success: function(response) {
                    alert(response.data.message);
                },
                error: function(response) {
                    alert('Failed to send request: ' + response.data.message);
                }
            });
        });
    });
</script>
