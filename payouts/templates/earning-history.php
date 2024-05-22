<?php
/**
 * Dokan Dashboard Earning History Template
 *
 * Ensures that the vendor views only their financial details and products.
 */
?>
<div class="dokan-dashboard-wrap">
    <?php
        do_action('dokan_dashboard_content_before');
    ?>

    <div class="dokan-dashboard-content">
        <?php
            do_action('dokan_help_content_inside_before');
        ?>

        <article class="help-content-area">
            <h1>Earning History</h1>
            
            <!-- Earnings Cards -->
            <div class="dokan-earnings-summary">
                <div class="earning-card">
                    <h3>Total Earning</h3>
                    <p>$<?php echo number_format(get_total_earnings(), 2); ?></p>
                </div>
                <div class="earning-card">
                    <h3>Available for Withdrawal</h3>
                    <p>$<?php echo number_format(get_available_for_withdrawal(), 2); ?></p>
                </div>
                <div class="earning-card">
                    <h3>Total Withdrawn</h3>
                    <p>$<?php echo number_format(get_total_withdrawn(), 2); ?></p>
                </div>
            </div>
			
			  <h2>Approved Products</h2>
<table class="dokan-table">
    <thead>
        <tr>
            <th>Product Name</th>
            <th>Image</th>
            <th>Product ID</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
       <?php 
$approved_products = get_approved_products();
if (!empty($approved_products)) : ?>
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Image</th>
                <th>Product ID</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($approved_products as $product) : ?>
                <tr>
                    <td><?php echo esc_html($product->product_name); ?></td>
                    <td><img src="<?php echo esc_url($product->image_url); ?>" alt="<?php echo esc_attr($product->product_name); ?>" style="width:100px;"></td>
                    <td><?php echo esc_html($product->product_id); ?></td>
                    <td><?php echo esc_html($product->status); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else : ?>
    <p>No approved products found.</p>
<?php endif; ?>
    </tbody>
</table>

        </article>

        <?php
            do_action('dokan_dashboard_content_inside_after');
        ?>
    </div>

    <?php
        do_action('dokan_dashboard_content_after');
    ?>
</div>
