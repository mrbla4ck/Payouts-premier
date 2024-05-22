<?php
/*
Plugin Name: Payouts Premier
Plugin URI: https://sendbro.xyz/
Description: Manage payouts for vendors based on their uploads.
Version: 1.8
Author: Shahriar Rahman
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

register_activation_hook(__FILE__, 'payouts_premier_create_table');
register_activation_hook(__FILE__, 'payouts_premier_create_package_table');
register_activation_hook(__FILE__, 'payouts_premier_create_vendor_data_table');
register_activation_hook(__FILE__, 'create_upgrade_requests_table');
register_activation_hook(__FILE__, 'payouts_premier_update_database');




function payouts_premier_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'uploads';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        vendor_id mediumint(9) NOT NULL,
        product_id mediumint(9) NOT NULL,
        vendor_email varchar(255) NOT NULL,
        product_name varchar(255) NOT NULL,
        download_link varchar(255) NOT NULL,
        image_url varchar(255) NOT NULL,
        status varchar(20) DEFAULT 'pending' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

	



// Table for payout requests
    $table_name_payouts = $wpdb->prefix . 'payoutsreq';
    $sql_payouts = "CREATE TABLE $table_name_payouts (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        vendor_id mediumint(9) NOT NULL,
        vendor_email varchar(255) NOT NULL,
        amount decimal(10,2) NOT NULL,
        method varchar(50) NOT NULL,
        request_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        status varchar(20) DEFAULT 'pending' NOT NULL,
        PRIMARY KEY  (id)
    ) " . $wpdb->get_charset_collate() . ";";
    dbDelta($sql_payouts);
}

function payouts_premier_create_package_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vendor_packages';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        package_name varchar(255) NOT NULL,
        product_number mediumint(9) NOT NULL,
        price_per_product decimal(10,2) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}





function payouts_premier_create_vendor_data_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vendor_data';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        vendor_id mediumint(9) NOT NULL,
        package_id mediumint(9) NOT NULL,
        product_limit mediumint(9) DEFAULT 0 NOT NULL,
        price_per_product decimal(10,2) DEFAULT 0.00 NOT NULL,
        uploaded_products mediumint(9) DEFAULT 0 NOT NULL,
        remaining_products mediumint(9) DEFAULT 0 NOT NULL,
        total_earnings decimal(10,2) DEFAULT 0.00 NOT NULL,
        available_for_withdrawal decimal(10,2) DEFAULT 0.00 NOT NULL,
        total_withdrawn decimal(10,2) DEFAULT 0.00 NOT NULL,
        PRIMARY KEY  (vendor_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Hook the above function to the plugin activation action

function create_upgrade_requests_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'upgradereq';

    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            vendor_id mediumint(9) NOT NULL,
            current_limit mediumint(9) NOT NULL,
            requested_addition mediumint(9) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}




function payouts_premier_update_database() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'uploads';

    // Check if the column exists, and if not, add it
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$table_name}' AND column_name = 'min_payout'");
    if(empty($row)){
       $wpdb->query("ALTER TABLE $table_name ADD min_payout DECIMAL(10,2) DEFAULT 0.00 NOT NULL");
    }
}

add_action('init', 'payouts_premier_init');

function payouts_premier_init() {
    add_filter('dokan_get_dashboard_nav', 'payouts_premier_add_dokan_menus');
    add_filter('dokan_query_var_filter', 'dokan_load_document_menu');
    add_action('dokan_load_custom_template', 'payouts_premier_load_template');
    add_action('admin_enqueue_scripts', 'payouts_premier_enqueue_scripts');
    add_action('dokan_new_product_added', 'payouts_premier_save_upload', 10, 1);
    add_action('dokan_product_updated', 'payouts_premier_save_upload', 10, 1);
    add_action('admin_menu', 'payouts_premier_admin_menus');
    add_action('wp_ajax_admin_approve_upload', 'payouts_premier_handle_admin_approve');
    add_action('wp_ajax_admin_reject_upload', 'payouts_premier_handle_admin_reject');
    add_action('wp_ajax_fetch_latest_payout_data', 'fetch_latest_payout_data');
    add_action('wp_ajax_approve_payout', 'handle_approve_payout'); // Corrected action to use the handle_approve_payout function
    add_action('wp_ajax_reject_payout', 'handle_reject_payout'); // Corrected action to use the handle_reject_payout function
    add_action('admin_menu', 'payouts_premier_add_package_menu');
    add_action('init', 'add_user_earnings_meta');
    add_action('admin_enqueue_scripts', 'payouts_premier_enqueue_scripts');
    add_action('init', 'handle_package_selection'); // Hook the package selection handling to the init action
    add_action('vendor_package_selected', 'update_vendor_data_on_package_selection', 10, 2);
    add_action('update_package', 'update_vendor_data_on_package_change', 10, 3);
    add_action('add_package', 'update_vendor_data_on_package_change', 10, 3);
    add_action('woocommerce_product_approved', 'update_vendor_stats_on_product_approval');
    add_action('wp_ajax_approve_payout', 'update_financials_on_payout_approval');
    add_action('woocommerce_product_approved', 'update_earnings_and_products_on_approval'); // Adjust the hook to your specific event
    add_action('wp_ajax_payouts_premier_submit_payout_request', 'payouts_premier_submit_payout_request');
	add_action('wp_ajax_fetch_vendor_financial_data', 'fetch_vendor_financial_data');
	add_action('wp_ajax_request_upgrade', 'handle_upgrade_request');
	add_action('wp_ajax_update_upgrade_request', 'handle_update_upgrade_request');
	add_action('wp_ajax_ignore_upgrade_request', 'handle_ignore_upgrade_request');

}





function dokan_load_document_menu( $query_vars ) {
    $query_vars['payouts'] = 'payouts';
    $query_vars['earning-history'] = 'earning-history';
    $query_vars['payouts-history'] = 'payouts-history';
    $query_vars['upload-history'] = 'upload-history';
    // Add more submenu query vars as needed
    return $query_vars;
}


function payouts_premier_add_dokan_menus($urls) {
    $urls['payouts'] = array(
        'title' => __('Payouts', 'payouts-premier'),
        'icon' => '<i class="fa fa-money"></i>',
        'url' => dokan_get_navigation_url('payouts'),
        'pos' => 56,
        'submenu' => array(
            'earning-history' => array(
                'title' => __('Earning History', 'payouts-premier'),
                'url' => dokan_get_navigation_url('earning-history')
            ),
            'payouts-history' => array(
                'title' => __('Payouts History', 'payouts-premier'),
                'url' => dokan_get_navigation_url('payouts-history')
            ),
            'upload-history' => array(
                'title' => __('Upload History', 'payouts-premier'),
                'url' => dokan_get_navigation_url('upload-history')
            )
        )
    );
    return $urls;
}

function payouts_premier_load_template( $query_vars ) {
    // Define the template directory path
    $template_dir = plugin_dir_path( __FILE__ ) . 'templates/';

    // Load the template based on the query variable
    if ( isset( $query_vars['payouts'] ) ) {
        // Load payouts template
        require_once $template_dir . 'payouts.php';
    } elseif ( isset( $query_vars['earning-history'] ) ) {
        // Load earning history template
        require_once $template_dir . 'earning-history.php';
    } elseif ( isset( $query_vars['payouts-history'] ) ) {
        // Load payouts history template
        require_once $template_dir . 'payouts-history.php';
    } elseif ( isset( $query_vars['upload-history'] ) ) {
        // Load upload history template
        require_once $template_dir . 'upload-history.php';
    }
    // Add more elseif conditions for additional submenu templates
}


function payouts_premier_enqueue_scripts() {
  //  // General scripts for admin and vendor dashboards
    wp_enqueue_script('payouts-premier-admin-js', plugin_dir_url(__FILE__) . 'payouts-premier-upload.js', array('jquery'), '1.0', true);
	wp_localize_script('payouts-premier', 'payoutsPremier', array(
    	'ajax_url' => admin_url('admin-ajax.php'),
    	'update_upgrade_nonce' => wp_create_nonce('update-upgrade-request'),
    	'ignore_upgrade_nonce' => wp_create_nonce('ignore-upgrade-request')
	));

//
    wp_enqueue_script('payouts-premier', plugin_dir_url(__FILE__) . 'payouts-premier.js', array('jquery'), '1.3', true);
    wp_localize_script('payouts-premier', 'payoutsPremierUpload', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'approve_upload_nonce' => wp_create_nonce('approve_upload_nonce'),
        'reject_upload_nonce' => wp_create_nonce('reject_upload_nonce')
    ));
    // Scripts for handling payout submissions
    wp_enqueue_script('payouts-premier-admin', plugin_dir_url(__FILE__) . 'admin-script.js', array('jquery'), '1.0', true);
    wp_localize_script('payouts-premier-admin', 'payoutsPremierAdmin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('payouts-admin-nonce')
    ));

    // Scripts for handling payout approve/reject
    wp_enqueue_script('payouts-js', plugin_dir_url(__FILE__) . 'payouts.js', array('jquery'), '1.0', true);
    wp_localize_script('payouts-js', 'payoutsAction', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('payouts-admin-nonce')  // This nonce is used in AJAX request handlers
    ));

    // Bootstrap CSS and JS
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js', array('jquery'), '4.3.1', true);
}




/** end Code added by Md Sabbir Ahmed */


function payouts_premier_save_upload($product_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'uploads';

    $product = wc_get_product($product_id);
    if (!$product) return;

    $vendor_id = get_post_field('post_author', $product_id);
    $vendor = get_userdata($vendor_id);
    if (!$vendor) return;

    $data = array(
        'vendor_id' => $vendor_id,
        'product_id' => $product_id,
        'vendor_email' => $vendor->user_email,
        'product_name' => $product->get_name(),
        'download_link' => get_permalink($product_id),
        'image_url' => wp_get_attachment_url($product->get_image_id()),
        'status' => 'pending'
    );

    $wpdb->insert($table_name, $data);
}

function payouts_premier_admin_menus() {
    add_menu_page('Payouts Admin', 'Payouts Admin', 'manage_options', 'payouts-admin', null, 'dashicons-admin-tools', 6);
    add_submenu_page('payouts-admin', 'Upload Requests', 'Upload Requests', 'manage_options', 'upload-requests', 'payouts_premier_upload_requests');
    add_submenu_page('payouts-admin', 'Payout Requests', 'Payout Requests', 'manage_options', 'payout-requests', 'payouts_premier_payout_requests');
    add_submenu_page('payouts-admin', 'Upgrade Requests', 'Upgrade Requests', 'manage_options', 'upgrade-requests', 'payouts_premier_upgrade_requests');
    add_submenu_page('payouts-admin', 'Admin Area', 'Admin Area', 'manage_options', 'admin-area', 'payouts_premier_admin_area');
}


function payouts_premier_add_package_menu() {
    add_submenu_page(
        'payouts-admin', // Parent slug
        'Manage Packages', // Page title
        'Packages', // Menu title
        'manage_options', // Capability
        'manage-packages', // Menu slug
        'payouts_premier_manage_packages' // Function
    );
}


function payouts_premier_manage_packages() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vendor_packages';

    // Handle form submission for adding or updating packages
    if (isset($_POST['submit'])) {
        $package_name = sanitize_text_field($_POST['package_name']);
        $product_number = intval($_POST['product_number']);
        $price_per_product = floatval($_POST['price_per_product']);

        if (isset($_POST['package_id'])) { // Update existing package
            $package_id = intval($_POST['package_id']);
            $result = $wpdb->update(
                $table_name,
                array(
                    'package_name' => $package_name,
                    'product_number' => $product_number,
                    'price_per_product' => $price_per_product
                ),
                array('id' => $package_id)
            );
            if ($result !== false) {
                echo '<div class="updated"><p>Package updated successfully.</p></div>';
            } else {
                echo '<div class="error"><p>Failed to update package. Error: ' . $wpdb->last_error . '</p></div>';
            }
        } else { // Add new package
            $result = $wpdb->insert(
                $table_name,
                array(
                    'package_name' => $package_name,
                    'product_number' => $product_number,
                    'price_per_product' => $price_per_product
                )
            );
            if ($result !== false) {
                echo '<div class="updated"><p>Package added successfully.</p></div>';
            } else {
                echo '<div class="error"><p>Failed to add package. Error: ' . $wpdb->last_error . '</p></div>';
            }
        }
    }

    // Handle package deletion
    if (isset($_GET['delete'])) {
        $package_id = intval($_GET['delete']);
        $result = $wpdb->delete($table_name, array('id' => $package_id));
        if ($result !== false) {
            echo '<div class="updated"><p>Package deleted successfully.</p></div>';
        } else {
            echo '<div class="error"><p>Failed to delete package. Error: ' . $wpdb->last_error . '</p></div>';
        }
    }

    // Display the form for adding or editing packages
    $packages = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC");

    echo '<div class="wrap">';
    echo '<h1>' . (isset($_GET['edit']) ? 'Edit Package' : 'Add New Package') . '</h1>';
    echo '<form method="post" action="">';
    if (isset($_GET['edit'])) {
        $package = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['edit'])));
        if ($package) {
            echo '<input type="hidden" name="package_id" value="' . esc_attr($package->id) . '">';
        }
    }
    echo '<table class="form-table">';
    echo '<tr><th scope="row"><label for="package_name">Package Name</label></th>';
    echo '<td><input type="text" id="package_name" name="package_name" value="' . (isset($package) ? esc_attr($package->package_name) : '') . '" class="regular-text" required></td></tr>';
    echo '<tr><th scope="row"><label for="product_number">Number of Products</label></th>';
    echo '<td><input type="number" id="product_number" name="product_number" value="' . (isset($package) ? intval($package->product_number) : '') . '" class="regular-text" required></td></tr>';
    echo '<tr><th scope="row"><label for="price_per_product">Price Per Product ($)</label></th>';
    echo '<td><input type="text" id="price_per_product" name="price_per_product" value="' . (isset($package) ? esc_attr($package->price_per_product) : '') . '" class="regular-text" required></td></tr>';
    echo '</table>';
    echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . (isset($_GET['edit']) ? 'Update Package' : 'Add Package') . '"></p>';
    echo '</form>';
    echo '<hr>';
    echo '<h2>Existing Packages</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Package Name</th><th>Number of Products</th><th>Price Per Product</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    foreach ($packages as $pkg) {
        echo '<tr>';
        echo '<td>' . $pkg->id . '</td>';
        echo '<td>' . esc_html($pkg->package_name) . '</td>';
        echo '<td>' . $pkg->product_number . '</td>';
        echo '<td>' . $pkg->price_per_product . '</td>';
        echo '<td><a href="?page=' . $_REQUEST['page'] . '&edit=' . $pkg->id . '">Edit</a> | <a href="?page=' . $_REQUEST['page'] . '&delete=' . $pkg->id . '" onclick="return confirm(\'Are you sure you want to delete this package?\')">Delete</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}







function payouts_premier_upload_requests() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'uploads';
    $uploads = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'pending'");

    echo '<table class="table">';
    echo '<thead><tr><th>Email</th><th>Product Name</th><th>Download Link</th><th>Image</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    foreach ($uploads as $upload) {
        echo '<tr>';
        echo '<td>' . esc_html($upload->vendor_email) . '</td>';
        echo '<td>' . esc_html($upload->product_name) . '</td>';
        echo '<td><a href="' . esc_url($upload->download_link) . '">Download</a></td>';
        echo '<td><img src="' . esc_url($upload->image_url) . '" alt="Product Image" style="width:100px;"></td>';
        echo '<td><button class="button action-button approve-upload-button" data-upload-id="' . esc_attr($upload->id) . '">Approve</button>';
        echo '<button class="button action-button reject-upload-button" data-upload-id="' . esc_attr($upload->id) . '">Reject</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}


/**
 * Handle admin approve upload via AJAX.
 */
         
function payouts_premier_handle_admin_approve() {
    if (!check_ajax_referer('approve_upload_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Nonce verification failed']);
        return;
    }

    if (!current_user_can('edit_products')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    $upload_id = isset($_POST['upload_id']) ? intval($_POST['upload_id']) : 0;
    if (!$upload_id) {
        wp_send_json_error(['message' => 'Invalid upload ID']);
        return;
    }

    global $wpdb;
    $uploads_table = $wpdb->prefix . 'uploads';
    $vendor_data_table = $wpdb->prefix . 'vendor_data';

    // Retrieve the upload details
    $upload = $wpdb->get_row($wpdb->prepare("SELECT * FROM $uploads_table WHERE id = %d", $upload_id));
    if (!$upload) {
        wp_send_json_error(['message' => 'Upload not found']);
        return;
    }

    // Update the product post status to 'publish'
    wp_update_post(['ID' => $upload->product_id, 'post_status' => 'publish']);

    // Update the upload status to 'approved' in the database
    $updated = $wpdb->update($uploads_table, ['status' => 'approved'], ['id' => $upload_id]);
    if ($updated === false) {
        wp_send_json_error(['message' => 'Failed to update upload status']);
        return;
    }

    // Fetch and update vendor data
    $vendor_id = $upload->vendor_id;
    $vendor_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $vendor_data_table WHERE vendor_id = %d", $vendor_id));
    if ($vendor_data) {
        $new_uploaded_products = $vendor_data->uploaded_products + 1;
        $new_remaining_products = $vendor_data->remaining_products - 1; // Decrement remaining products
        $new_total_earnings = $vendor_data->total_earnings + $vendor_data->price_per_product;
        $new_available_for_withdrawal = $vendor_data->available_for_withdrawal + $vendor_data->price_per_product;

        $result = $wpdb->update($vendor_data_table, [
            'uploaded_products' => $new_uploaded_products,
            'remaining_products' => $new_remaining_products,
            'total_earnings' => $new_total_earnings,
            'available_for_withdrawal' => $new_available_for_withdrawal
        ], ['vendor_id' => $vendor_id]);

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to update vendor data: ' . $wpdb->last_error]);
        } else {
            wp_send_json_success(['message' => 'Upload approved and vendor data updated']);
        }
    } else {
        wp_send_json_error(['message' => 'Vendor data not found']);
    }
}




function payouts_premier_handle_admin_reject() {
    if (!check_ajax_referer('reject_upload_nonce', 'nonce', false) || !current_user_can('delete_products')) {
        wp_send_json_error(['message' => 'Unauthorized access']);
        return;
    }

    $upload_id = isset($_POST['upload_id']) ? intval($_POST['upload_id']) : 0;
    global $wpdb;
    $uploads_table = $wpdb->prefix . 'uploads';

    if ($upload_id > 0) {
        $product_id = $wpdb->get_var($wpdb->prepare("SELECT product_id FROM $uploads_table WHERE id = %d", $upload_id));
        if ($product_id && wc_get_product($product_id)) {
            wp_trash_post($product_id); // Move the product to trash
            $updated = $wpdb->update($uploads_table, ['status' => 'rejected'], ['id' => $upload_id]); // Update status to 'rejected'
            if ($updated) {
                wp_send_json_success(['message' => 'Upload rejected and product trashed']);
            } else {
                wp_send_json_error(['message' => 'Database update failed']);
            }
        } else {
            wp_send_json_error(['message' => 'Product not found']);
        }
    } else {
        wp_send_json_error(['message' => 'Invalid upload ID']);
    }
}




function payouts_premier_payout_requests() {
    global $wpdb;
    $payout_requests = $wpdb->get_results("
        SELECT pr.*, u.user_email as vendor_email 
        FROM {$wpdb->prefix}payoutsreq pr
        JOIN {$wpdb->users} u ON pr.vendor_id = u.ID
        ORDER BY pr.request_date DESC
    ", OBJECT);

    echo '<h1>Payout Requests</h1>';
    echo '<table class="table"><thead><tr><th>Date</th><th>Requested by</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody>';

    if (!empty($payout_requests)) {
        foreach ($payout_requests as $request) {
            $status = $request->status === 'pending' ? 
                '<button class="button action-button approve-payout-button" data-payout-id="' . esc_attr($request->id) . '">Approve</button> ' . 
                '<button class="button action-button reject-payout-button" data-payout-id="' . esc_attr($request->id) . '">Reject</button>' : 
                esc_html($request->status);
            echo '<tr><td>' . esc_html($request->request_date) . '</td>';
            echo '<td>' . esc_html($request->vendor_email) . '</td>';
            echo '<td>$' . number_format($request->amount, 2) . '</td>';
            echo '<td>' . $status . '</td></tr>';
        }
    } else {
        echo '<tr><td colspan="5">No payout requests found.</td></tr>';
    }

    echo '</tbody></table>';
}




function payouts_premier_upgrade_requests() {
    // Placeholder for Upgrade Requests content
        global $wpdb;
    $requests = $wpdb->get_results("
        SELECT ur.*, u.user_email 
        FROM {$wpdb->prefix}upgradereq ur 
        JOIN {$wpdb->users} u ON ur.vendor_id = u.ID 
        WHERE ur.status = 'pending'
    ");

    echo '<div class="wrap"><h1>Upgrade Requests</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Vendor Email</th><th>Current Limit</th><th>Add Limit</th><th>Status</th><th>Action</th></tr></thead><tbody>';

    foreach ($requests as $request) {
        echo '<tr>';
        echo '<td>' . esc_html($request->user_email) . '</td>';
        echo '<td>' . intval($request->current_limit) . '</td>';
        echo '<td><input type="number" min="1" name="add_limit[' . esc_attr($request->id) . ']" /></td>';
        echo '<td>' . esc_html($request->status) . '</td>';
        echo '<td>
                <button class="button button-primary add-limit" data-request-id="<?php echo $request->id; ?>">Add Limit</button>
<button class="button button-secondary ignore-request" data-request-id="<?php echo $request->id; ?>">Ignore</button>

              </td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';

    // Inline JavaScript to handle button actions
    ?>
    <?php echo '</div>'; // Close wrap div

}


function payouts_premier_admin_area() {

}




function get_total_earnings() {
    global $wpdb;
    $vendor_id = get_current_user_id();

    $total_earnings = $wpdb->get_var($wpdb->prepare(
        "SELECT total_earnings FROM {$wpdb->prefix}vendor_data WHERE vendor_id = %d",
        $vendor_id
    ));

    return $total_earnings ? $total_earnings : 0;  // Return the total earnings or 0 if none found
}

function get_available_for_withdrawal() {
    global $wpdb;
    $vendor_id = get_current_user_id();

    $available_for_withdrawal = $wpdb->get_var($wpdb->prepare(
        "SELECT available_for_withdrawal FROM {$wpdb->prefix}vendor_data WHERE vendor_id = %d",
        $vendor_id
    ));

    return $available_for_withdrawal ? $available_for_withdrawal : 0;  // Return the available amount or 0 if none found
}

function get_total_withdrawn() {
    global $wpdb;
    $vendor_id = get_current_user_id();

    $total_withdrawn = $wpdb->get_var($wpdb->prepare(
        "SELECT total_withdrawn FROM {$wpdb->prefix}vendor_data WHERE vendor_id = %d",
        $vendor_id
    ));

    return $total_withdrawn ? $total_withdrawn : 0;  // Return the total withdrawn or 0 if none found
}


function get_approved_products() {
    global $wpdb, $current_user;
    wp_get_current_user();
    $vendor_id = $current_user->ID;

    $query = $wpdb->prepare("
        SELECT p.ID as product_id, p.post_title as product_name, pm.meta_value as image_url, u.status
        FROM {$wpdb->prefix}uploads u
        JOIN {$wpdb->prefix}posts p ON u.product_id = p.ID
        JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
        WHERE u.vendor_id = %d AND u.status = 'approved'
        ORDER BY u.id DESC
    ", $vendor_id);

    $products = $wpdb->get_results($query);

    if (!$products) {
        error_log('SQL Error: ' . $wpdb->last_error);  // Log SQL errors if any.
        return [];
    }

    foreach ($products as $key => $product) {
        $products[$key]->image_url = wp_get_attachment_url($product->image_url);
    }

    return $products;
}





function payouts_premier_submit_payout_request() {
    if (!check_ajax_referer('payouts-premier-nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    global $wpdb;
    $vendor_id = get_current_user_id();
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';

    // Insert into database
    $result = $wpdb->insert(
        $wpdb->prefix . 'payoutsreq', 
        array(
            'vendor_id' => $vendor_id,
            'amount' => $amount,
            'method' => $method,
            'status' => 'pending'  // Default status
        ),
        array('%d', '%f', '%s', '%s')
    );

    if ($result) {
        wp_send_json_success(['message' => 'Payout request submitted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to submit payout request']);
    }
}



function fetch_vendor_financial_data() {
    check_ajax_referer('fetch-vendor-financial-data-nonce', 'nonce');
    
    $vendor_id = get_current_user_id();
    global $wpdb;
    $vendor_data = $wpdb->get_row($wpdb->prepare(
        "SELECT available_for_withdrawal, remaining_products FROM {$wpdb->prefix}vendor_data WHERE vendor_id = %d", 
        $vendor_id
    ));

    if ($vendor_data) {
        wp_send_json_success([
            'availableForWithdrawal' => $vendor_data->available_for_withdrawal,
            'remainingProducts' => $vendor_data->remaining_products
        ]);
    } else {
        wp_send_json_error(['message' => 'Vendor data not found']);
    }
}


function fetch_latest_payout_data() {
    if (!check_ajax_referer('payouts-premier-nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    global $wpdb;
    $vendor_id = get_current_user_id();
    $payouts = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}payoutsreq WHERE vendor_id = %d ORDER BY request_date DESC",
        $vendor_id
    ));

    if (!empty($payouts)) {
        ob_start();
        foreach ($payouts as $payout) {
            echo '<tr><td>' . esc_html($payout->request_date) . '</td><td>$' . esc_html($payout->amount) . '</td><td>' . esc_html($payout->status) . '</td></tr>';
        }
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    } else {
        wp_send_json_error(['message' => 'No payouts found']);
    }
}






function display_vendor_payout_requests() {
    global $wpdb, $current_user;
    get_currentuserinfo();
    $vendor_id = $current_user->ID;

    $requests = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}payoutsreq WHERE vendor_id = %d ORDER BY request_date DESC", $vendor_id),
        ARRAY_A
    );

    $output = '<table><tr><th>Date</th><th>Amount</th><th>Status</th></tr>';
    foreach ($requests as $request) {
        $output .= "<tr><td>{$request['request_date']}</td><td>{$request['amount']}</td><td>{$request['status']}</td></tr>";
    }
    $output .= '</table>';

    return $output;
}



function handle_approve_payout() {
    if (!check_ajax_referer('payouts-admin-nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    $payout_id = isset($_POST['payout_id']) ? intval($_POST['payout_id']) : 0;
    global $wpdb;

    // Retrieve the payout request
    $payout = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}payoutsreq WHERE id = %d", $payout_id));
    if (!$payout) {
        wp_send_json_error(['message' => 'Invalid Payout ID']);
        return;
    }

    // Update payout request to 'approved'
    $updated = $wpdb->update(
        $wpdb->prefix . 'payoutsreq',
        ['status' => 'approved'],
        ['id' => $payout_id]
    );

    if ($updated) {
        // Update the vendor_data table
        $vendor_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vendor_data WHERE vendor_id = %d", $payout->vendor_id));
        if ($vendor_data) {
            $new_total_withdrawn = $vendor_data->total_withdrawn + $payout->amount;
            $new_available_for_withdrawal = $vendor_data->available_for_withdrawal - $payout->amount;

            $wpdb->update(
                "{$wpdb->prefix}vendor_data",
                [
                    'total_withdrawn' => $new_total_withdrawn,
                    'available_for_withdrawal' => $new_available_for_withdrawal
                ],
                ['vendor_id' => $payout->vendor_id]
            );
            wp_send_json_success(['message' => 'Payout approved successfully']);
        } else {
            wp_send_json_error(['message' => 'Vendor data not found']);
        }
    } else {
        wp_send_json_error(['message' => 'Failed to approve payout']);
    }
}



function handle_reject_payout() {
    if (!check_ajax_referer('payouts-premier-nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    $payout_id = isset($_POST['payout_id']) ? intval($_POST['payout_id']) : 0;
    global $wpdb;

    // Update payout request to 'rejected'
    $updated = $wpdb->update(
        $wpdb->prefix . 'payoutsreq',
        ['status' => 'rejected'],
        ['id' => $payout_id]
    );

    if ($updated) {
        wp_send_json_success(['message' => 'Payout rejected successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to reject payout']);
    }
}


function get_vendor_uploaded_products_count($vendor_id) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT approved_count FROM {$wpdb->prefix}vendor_approved_products WHERE vendor_id = %d",
        $vendor_id
    ));
}





function update_approved_product_count($product_id) {
    global $wpdb;
    $vendor_id = get_post_field('post_author', $product_id);

    $current_info = $wpdb->get_row($wpdb->prepare(
        "SELECT approved_count, product_number FROM {$wpdb->prefix}vendor_approved_products 
        JOIN {$wpdb->prefix}vendor_package_selection ON {$wpdb->prefix}vendor_approved_products.vendor_id = {$wpdb->prefix}vendor_package_selection.vendor_id
        JOIN {$wpdb->prefix}vendor_packages ON {$wpdb->prefix}vendor_package_selection.package_id = {$wpdb->prefix}vendor_packages.id
        WHERE {$wpdb->prefix}vendor_approved_products.vendor_id = %d",
        $vendor_id
    ));

    if (!empty($current_info) && $current_info->approved_count < $current_info->product_number) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}vendor_approved_products SET approved_count = approved_count + 1
            WHERE vendor_id = %d",
            $vendor_id
        ));
    }
}

function check_upload_limits_before_upload($vendor_id) {
    global $wpdb;

    $package_details = $wpdb->get_row($wpdb->prepare(
        "SELECT vp.product_number FROM {$wpdb->prefix}vendor_packages vp
        JOIN {$wpdb->prefix}vendor_package_selection vps ON vp.id = vps.package_id
        WHERE vps.vendor_id = %d", $vendor_id
    ));

    $uploaded_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}uploads WHERE vendor_id = %d AND status = 'approved'",
        $vendor_id
    ));

    if ($uploaded_count >= $package_details->product_number) {
        return false; // Block the upload
    }
    return true; // Allow the upload
}





function can_request_payout($vendor_id) {
    global $wpdb;

    $min_withdrawal_limit = $wpdb->get_var($wpdb->prepare(
        "SELECT vp.product_number * vp.price_per_product AS min_withdrawal FROM {$wpdb->prefix}vendor_packages vp
        JOIN {$wpdb->prefix}vendor_package_selection vps ON vp.id = vps.package_id
        WHERE vps.vendor_id = %d", $vendor_id
    ));

    $current_earnings = get_current_earnings($vendor_id); // Implement this function based on your system

    return $current_earnings >= $min_withdrawal_limit;
}

function enforce_product_upload_limits($vendor_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vendor_package_selection';

    $limit_info = $wpdb->get_row($wpdb->prepare(
        "SELECT product_limit, uploaded_products FROM $table_name WHERE vendor_id = %d",
        $vendor_id
    ));

    if ($limit_info && ($limit_info->uploaded_products >= $limit_info->product_limit)) {
        wp_die('You have reached the limit of allowed product uploads for your package.');
    }
}
add_action('dokan_new_product_added', function($product_id) {
    $vendor_id = get_post_field('post_author', $product_id);
    enforce_product_upload_limits($vendor_id);
});



function update_earnings_on_product_approval($product_id) {
    global $wpdb;
    $vendor_id = get_post_field('post_author', $product_id);
    $earnings = get_price_per_product_for_vendor($vendor_id);  // Fetch price per product

    if (!$earnings) return;

    // Update the total earnings and available for withdrawal in the balance table
    $balance = $wpdb->get_row($wpdb->prepare(
        "SELECT total_earnings, available_for_withdrawal FROM {$wpdb->prefix}ppvendor_balance WHERE vendor_id = %d",
        $vendor_id
    ));

    if ($balance) {
        $new_total_earnings = $balance->total_earnings + $earnings;
        $new_available_for_withdrawal = $balance->available_for_withdrawal + $earnings;

        $wpdb->update(
            "{$wpdb->prefix}ppvendor_balance",
            array(
                'total_earnings' => $new_total_earnings,
                'available_for_withdrawal' => $new_available_for_withdrawal
            ),
            array('vendor_id' => $vendor_id),
            array('%f', '%f'),
            array('%d')
        );
    }
}


function get_vendor_earnings($vendor_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT total_earnings FROM {$wpdb->prefix}ppvendor_balance WHERE vendor_id = %d",
        $vendor_id
    ));
}




function can_vendor_request_payout($vendor_id, $requested_amount) {
    global $wpdb;
    $vendor_table = $wpdb->prefix . 'vendor_package_selection';
    $balance_table = $wpdb->prefix . 'ppvendor_balance';

    $limits = $wpdb->get_row($wpdb->prepare(
        "SELECT product_limit, price_per_product FROM $vendor_table WHERE vendor_id = %d", 
        $vendor_id
    ));

    $balance = $wpdb->get_row($wpdb->prepare(
        "SELECT available_for_withdrawal FROM $balance_table WHERE vendor_id = %d",
        $vendor_id
    ));

    $required_earnings = $limits->product_limit * $limits->price_per_product;

    return ($balance->available_for_withdrawal >= $requested_amount && $balance->available_for_withdrawal >= $required_earnings);
}

// Hook this check into the payout request process
add_action('init', function() {
    if (isset($_POST['submit_payout_request'])) {
        $vendor_id = get_current_user_id();
        if (!can_vendor_request_payout($vendor_id)) {
            wp_die('You have not met the minimum requirements for a payout.');
        }

        // Process the payout request here
    }
});







function update_product_upload_count($vendor_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ppvendor_limits';

    $wpdb->query($wpdb->prepare(
        "UPDATE $table_name SET products_uploaded = products_uploaded + 1 WHERE vendor_id = %d",
        $vendor_id
    ));
}

function check_payout_eligibility($vendor_id, $requested_amount) {
    global $wpdb;
    $balance_table = $wpdb->prefix . 'ppvendor_balance';
    $limits_table = $wpdb->prefix . 'ppvendor_limits';

    $balance = $wpdb->get_row($wpdb->prepare(
        "SELECT available_for_withdrawal, total_earnings FROM $balance_table WHERE vendor_id = %d",
        $vendor_id
    ));

    $limits = $wpdb->get_row($wpdb->prepare(
        "SELECT min_payout FROM $limits_table WHERE vendor_id = %d",
        $vendor_id
    ));

    // Check if the available balance and minimum payout requirements are met
    if ($balance->available_for_withdrawal >= $requested_amount && $balance->total_earnings >= $limits->min_payout) {
        return true;
    }
    return false;
}

/*/
function get_price_per_product_for_vendor($vendor_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vendor_packages';
    $selection_table = $wpdb->prefix . 'vendor_package_selection';

    $price_per_product = $wpdb->get_var($wpdb->prepare(
        "SELECT vp.price_per_product FROM $table_name AS vp
        JOIN $selection_table AS vps ON vp.id = vps.package_id
        WHERE vps.vendor_id = %d",
        $vendor_id
    ));

    return $price_per_product;
}
/*/





function update_vendor_limits($vendor_id, $package_id) {
    global $wpdb;
    $package = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vendor_packages WHERE id = %d", $package_id));

    $wpdb->update(
        "{$wpdb->prefix}vendor_limits",
        ['product_number' => $package->product_number, 'price_per_product' => $package->price_per_product],
        ['vendor_id' => $vendor_id],
        ['%d', '%f'],
        ['%d']
    );
}


function enforce_payout_conditions($vendor_id, $requested_amount) {
    global $wpdb;

    $limits = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vendor_limits WHERE vendor_id = %d", $vendor_id));
    $balance = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vendor_balances WHERE vendor_id = %d", $vendor_id));

    if ($balance->available_for_withdrawal < $requested_amount) {
        return false;
    }

    if (($limits->product_number * $limits->price_per_product) > $balance->available_for_withdrawal) {
        return false;
    }

    return true;
}

// Hook this into your payout request handler
add_action('init', function() {
    if (isset($_POST['submit_payout_request'])) {
        $vendor_id = get_current_user_id();
        $requested_amount = $_POST['amount'];

        if (!enforce_payout_conditions($vendor_id, $requested_amount)) {
            wp_die('Your payout request does not meet the necessary conditions.');
        }

        // Proceed with the payout processing here
    }
});

function update_vendor_limits_on_package_selection($vendor_id, $package_id, $product_limit, $price_per_product) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vendor_package_selection';

    $wpdb->replace(
        $table_name,
        array(
            'vendor_id' => $vendor_id,
            'package_id' => $package_id,
            'product_limit' => $product_limit,
            'price_per_product' => $price_per_product,
            'uploaded_products' => 0,
            'remaining_products' => $product_limit
        ),
        array('%d', '%d', '%d', '%f', '%d', '%d')
    );
}


function update_balance_on_product_approval($vendor_id, $earnings) {
    global $wpdb;
    $current_balance = $wpdb->get_var($wpdb->prepare(
        "SELECT available_for_withdrawal FROM {$wpdb->prefix}ppvendor_balance WHERE vendor_id = %d",
        $vendor_id
    ));

    $new_balance = $current_balance + $earnings;

    $wpdb->update(
        "{$wpdb->prefix}ppvendor_balance",
        array('available_for_withdrawal' => $new_balance),
        array('vendor_id' => $vendor_id),
        array('%f'),
        array('%d')
    );
}

function get_price_per_product_for_vendor($vendor_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT price_per_product FROM {$wpdb->prefix}ppvendor_limits WHERE vendor_id = %d",
        $vendor_id
    ));
}


function update_vendor_data_on_package_selection($vendor_id, $package_id) {
    global $wpdb;

    // Fetch package details
    $package = $wpdb->get_row($wpdb->prepare(
        "SELECT product_number, price_per_product FROM {$wpdb->prefix}vendor_packages WHERE id = %d", 
        $package_id
    ));

    if ($package) {
        // Update or insert vendor data
        $result = $wpdb->replace(
            "{$wpdb->prefix}vendor_data",
            array(
                'vendor_id' => $vendor_id,
                'package_id' => $package_id,
                'product_limit' => $package->product_number,
                'price_per_product' => $package->price_per_product,
                'uploaded_products' => 0, // Reset the count
                'remaining_products' => $package->product_number, // Set remaining products equal to limit initially
                'total_earnings' => 0.00, // Optionally reset earnings if needed
                'available_for_withdrawal' => 0.00, // Optionally reset available funds
                'total_withdrawn' => 0.00  // Optionally reset withdrawn amount
            ),
            array('%d', '%d', '%d', '%f', '%d', '%d', '%f', '%f', '%f')
        );

        if ($result === false) {
            error_log("Database Error: " . $wpdb->last_error);
        } else {
            error_log("Package selection updated successfully.");
        }
    }
}




function handle_package_selection() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_package'])) {
        $vendor_id = get_current_user_id(); // Get the current user's ID
        $package_id = intval($_POST['package_id']); // Get the selected package ID from the form

        // Update vendor data based on selected package
        update_vendor_data_on_package_selection($vendor_id, $package_id);

        // Redirect to the same page to reflect the updates in the frontend
        wp_redirect(your_dashboard_url());
        exit;
    }
}

function your_dashboard_url() {
    // Returns the full URL of the current page to reload it
    return home_url($_SERVER['REQUEST_URI']);
}




function update_product_count_on_approval($product_id) {
    global $wpdb;
    $vendor_id = get_post_field('post_author', $product_id);
    $table_name = $wpdb->prefix . 'vendor_package_selection';

    $wpdb->query($wpdb->prepare(
        "UPDATE $table_name SET uploaded_products = uploaded_products + 1, remaining_products = remaining_products - 1 WHERE vendor_id = %d",
        $vendor_id
    ));
}


function handle_payout_request($vendor_id, $requested_amount) {
    global $wpdb;

    // First, check if the vendor meets the conditions to request a payout
    if (!can_vendor_request_payout($vendor_id, $requested_amount)) {
        wp_die('Cannot process payout request. Requirements not met.');
    }

    // Get the current balance details from the database
    $balance_table = $wpdb->prefix . 'ppvendor_balance';
    $balance_info = $wpdb->get_row($wpdb->prepare(
        "SELECT available_for_withdrawal, total_withdrawn FROM $balance_table WHERE vendor_id = %d",
        $vendor_id
    ));

    // Ensure the requested amount does not exceed the available balance
    if ($balance_info->available_for_withdrawal >= $requested_amount) {
        // Calculate the new balance and the total amount withdrawn
        $new_available = $balance_info->available_for_withdrawal - $requested_amount;
        $new_withdrawn = $balance_info->total_withdrawn + $requested_amount;

        // Update the vendor's balance in the database
        $wpdb->update(
            $balance_table,
            array(
                'available_for_withdrawal' => $new_available,
                'total_withdrawn' => $new_withdrawn
            ),
            array('vendor_id' => $vendor_id),
            array('%f', '%f'),  // Format placeholders
            array('%d')         // Where format
        );

        // Optionally, you could add a log entry or send a notification here
        return true;  // Indicate that the payout was successfully processed
    } else {
        wp_die('Requested amount exceeds available balance.');
    }

    return false;  // Indicate failure to process the payout
}



function initialize_vendor_data($vendor_id, $package_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vendor_data';

    // Check if the record exists
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE vendor_id = %d", $vendor_id));

    // Insert or update the record
    if ($exists) {
        $wpdb->update(
            $table_name,
            array('package_id' => $package_id, /* other fields as needed */),
            array('vendor_id' => $vendor_id)
        );
    } else {
        $wpdb->insert(
            $table_name,
            array(
                'vendor_id' => $vendor_id,
                'package_id' => $package_id,
                'product_limit' => 0, // Default value or fetch from package details
                'price_per_product' => 0.00, // Default value or fetch from package details
                'uploaded_products' => 0,
                'remaining_products' => 0,
                'total_earnings' => 0.00,
                'available_for_withdrawal' => 0.00,
                'total_withdrawn' => 0.00
            )
        );
    }
}

function update_earnings_and_products($vendor_id, $earnings_increase) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vendor_data';

    // Update earnings and product counts
    $wpdb->query($wpdb->prepare(
        "UPDATE $table_name SET 
            uploaded_products = uploaded_products + 1, 
            total_earnings = total_earnings + %f,
            available_for_withdrawal = available_for_withdrawal + %f
         WHERE vendor_id = %d",
        $earnings_increase, $earnings_increase, $vendor_id
    ));
}





function update_vendor_data_on_package_change($package_id, $product_number, $price_per_product) {
    global $wpdb;
    $vendor_data_table = $wpdb->prefix . 'vendor_data';
    $vendor_package_selection_table = $wpdb->prefix . 'vendor_package_selection';

    // Get all vendor_ids using the updated package
    $vendors = $wpdb->get_results($wpdb->prepare(
        "SELECT vendor_id FROM $vendor_package_selection_table WHERE package_id = %d",
        $package_id
    ));

    // Update each vendor's data who is using the updated package
    foreach ($vendors as $vendor) {
        $wpdb->update(
            $vendor_data_table,
            array(
                'product_limit' => $product_number,
                'price_per_product' => $price_per_product
            ),
            array('vendor_id' => $vendor->vendor_id)
        );
    }
}


function update_vendor_stats_on_product_approval($product_id) {
    global $wpdb;

    $vendor_id = get_post_field('post_author', $product_id);
    $vendor_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}vendor_data WHERE vendor_id = %d", $vendor_id
    ));

    if ($vendor_data) {
        $new_uploaded = $vendor_data->uploaded_products + 1;
        $new_remaining = $vendor_data->remaining_products - 1;
        $new_total_earnings = $vendor_data->total_earnings + $vendor_data->price_per_product;
        $new_available_for_withdrawal = $new_total_earnings - $vendor_data->total_withdrawn;

        // Update vendor data
        $wpdb->update(
            "{$wpdb->prefix}vendor_data",
            [
                'uploaded_products' => $new_uploaded,
                'remaining_products' => $new_remaining,
                'total_earnings' => $new_total_earnings,
                'available_for_withdrawal' => $new_available_for_withdrawal
            ],
            ['vendor_id' => $vendor_id],
            ['%d', '%d', '%f', '%f']
        );
    }
}

function update_financials_on_payout_approval($payout_id) {
    global $wpdb;
    $payout_details = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}payoutsreq WHERE id = %d", $payout_id
    ));

    if ($payout_details && $payout_details->status === 'approved') {
        $vendor_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vendor_data WHERE vendor_id = %d", $payout_details->vendor_id
        ));

        if ($vendor_data) {
            $new_total_withdrawn = $vendor_data->total_withdrawn + $payout_details->amount;
            $new_available_for_withdrawal = $vendor_data->total_earnings - $new_total_withdrawn;

            // Update vendor financials
            $wpdb->update(
                "{$wpdb->prefix}vendor_data",
                [
                    'total_withdrawn' => $new_total_withdrawn,
                    'available_for_withdrawal' => $new_available_for_withdrawal
                ],
                ['vendor_id' => $payout_details->vendor_id],
                ['%f', '%f']
            );
        }
    }
}

function update_earnings_and_products_on_approval($product_id) {
    global $wpdb;
    $vendor_id = get_post_field('post_author', $product_id);
    $product_price = get_post_meta($product_id, '_price', true); // Assuming the product price is stored here

    // Fetch current data
    $vendor_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}vendor_data WHERE vendor_id = %d", 
        $vendor_id
    ));

    if ($vendor_data) {
        $new_uploaded_products = $vendor_data->uploaded_products + 1;
        $new_total_earnings = $vendor_data->total_earnings + $product_price;
        $new_available_for_withdrawal = $vendor_data->available_for_withdrawal + $product_price;

        // Update database
        $wpdb->update(
            "{$wpdb->prefix}vendor_data",
            [
                'uploaded_products' => $new_uploaded_products,
                'total_earnings' => $new_total_earnings,
                'available_for_withdrawal' => $new_available_for_withdrawal
            ],
            ['vendor_id' => $vendor_id]
        );
    }
}


function handle_upgrade_request() {
    global $wpdb;
    $vendor_id = intval($_POST['vendor_id']);
    $current_limit = intval($_POST['current_limit']);

    $result = $wpdb->insert($wpdb->prefix . 'upgradereq', [
        'vendor_id' => $vendor_id,
        'current_limit' => $current_limit,
        'status' => 'pending'
    ]);

    if ($result) {
        wp_send_json_success(['message' => 'Upgrade request submitted successfully.']);
    } else {
        wp_send_json_error(['message' => 'Failed to submit upgrade request.']);
    }
}

/**
 * Handle the "Add Limit" request via AJAX.
 */
function handle_update_upgrade_request() {
    // Verify nonce for security
    check_ajax_referer('update-upgrade-request', 'nonce');

    global $wpdb;
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $add_limit = isset($_POST['add_limit']) ? intval($_POST['add_limit']) : 0;

    // Validate request ID and additional limit
    if ($request_id <= 0 || $add_limit <= 0) {
        wp_send_json_error(['message' => 'Invalid data. Please ensure all inputs are correct.']);
        return;
    }

    // Fetch the current vendor's limit from the upgrade requests table
    $upgrade_request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}upgradereq WHERE id = %d",
        $request_id
    ));

    if (!$upgrade_request) {
        wp_send_json_error(['message' => 'No upgrade request found for the given ID.']);
        return;
    }

    // Calculate the new limit
    $new_limit = $upgrade_request->current_limit + $add_limit;

    // Update the upgrade request to reflect the new limit
    $result = $wpdb->update(
        "{$wpdb->prefix}upgradereq",
        ['current_limit' => $new_limit, 'status' => 'completed'],  // Assuming you change the status after updating
        ['id' => $request_id]
    );

    if (false !== $result) {
        wp_send_json_success(['message' => 'Limit updated successfully.']);
    } else {
        wp_send_json_error(['message' => 'Failed to update the limit.']);
    }
}

/**
 * Handle the "Ignore" request via AJAX.
 */
function handle_ignore_upgrade_request() {
    // Verify nonce for security
    check_ajax_referer('ignore-upgrade-request', 'nonce');

    global $wpdb;
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;

    // Validate request ID
    if ($request_id <= 0) {
        wp_send_json_error(['message' => 'Invalid request ID.']);
        return;
    }

    // Update the upgrade request to mark it as ignored
    $result = $wpdb->update(
        "{$wpdb->prefix}upgradereq",
        ['status' => 'ignored'],
        ['id' => $request_id]
    );

    if (false !== $result) {
        wp_send_json_success(['message' => 'Request ignored successfully.']);
    } else {
        wp_send_json_error(['message' => 'Failed to ignore the request.']);
    }
}