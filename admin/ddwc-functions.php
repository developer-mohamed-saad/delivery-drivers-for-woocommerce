<?php
/**
 * Helper functions.
 *
 * @link       https://www.deviodigital.com
 * @since      2.0
 *
 * @package    DDWC
 * @subpackage DDWC/admin
 */

/**
 * Change order statuses
 *
 * @since 2.0
 */
function ddwc_driver_dashboard_change_statuses() {

	// Get an instance of the WC_Order object.
	$order        = wc_get_order( $_GET['orderid'] );
	$order_data   = $order->get_data();
	$order_status = $order_data['status'];

	do_action( 'ddwc_driver_dashboard_change_statuses_top' );

	// Update order status if marked OUT FOR DELIVERY by Driver.
	if ( isset( $_POST['outfordelivery'] ) ) {

		// Update order status.
		$order->update_status( "out-for-delivery" );

		// Add driver note (if added).
		if ( isset( $_POST['outfordeliverymessage'] ) && ! empty( $_POST['outfordeliverymessage'] ) ) {
			// The text for the note.
			$note = __( 'Driver Note', 'ddwc' ) . ': ' . esc_html( $_POST['outfordeliverymessage'] );
			// Add the note
			$order->add_order_note( $note );
			// Save the data
			$order->save();
		}

		// Run additional functions.
		do_action( 'ddwc_email_customer_order_status_out_for_delivery' );

		// Redirect so the new order details show on the page.
		wp_redirect( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . 'driver-dashboard/?orderid=' . $_GET['orderid'] );

	}

	// Update order status if marked COMPLETED by Driver.
	if ( isset( $_POST['ordercompleted'] ) ) {

		// Update order status.
		$order->update_status( "completed" );

		// Run additional functions.
		do_action( 'ddwc_email_admin_order_status_completed' );

		// Redirect so the new order details show on the page.
		wp_redirect( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . 'driver-dashboard/?orderid=' . $_GET['orderid'] );

	}

	do_action( 'ddwc_driver_dashboard_change_statuses_bottom' );

}
add_action( 'ddwc_driver_dashboard_change_status_forms_top', 'ddwc_driver_dashboard_change_statuses' );

/**
 * Change order status forms
 * 
 * Displayed on the driver dashboard, allowing the driver to change
 * the status of an order as they deliver to customer.
 * 
 * @since 2.0
 */
function ddwc_driver_dashboard_change_status_forms() {

	// Get an instance of the WC_Order object.
	$order        = wc_get_order( $_GET['orderid'] );
	$order_data   = $order->get_data();
	$order_status = $order_data['status'];

	do_action( 'ddwc_driver_dashboard_change_status_forms_top' );

	// Create variable.
	$change_status = '';

	if ( 'driver-assigned' == $order_status ) {
		$change_status  = '<h4>' . __( "Change Status", 'ddwc' ) . '</h4>';
		$change_status .= '<form method="post">';
		$change_status .= '<p><strong>' . __( 'Message for shop manager / administrator (optional)', 'ddwc' ) . '</strong></p>';
		$change_status .= '<input type="text" name="outfordeliverymessage" value="" placeholder="' . __( 'Add a message to the order', 'ddwc' ) . '" class="ddwc-ofdmsg" />';
		$change_status .= '<input type="hidden" name="outfordelivery" value="out-for-delivery" />';
		$change_status .= '<input type="submit" value="' . __( 'Out for Delivery', 'ddwc' ) . '" class="button ddwc-change-status" />';
		$change_status .= wp_nonce_field( 'ddwc_out_for_delivery_nonce_action', 'ddwc_out_for_delivery_nonce_field' ) . '</form>';
	}
	
	if ( 'out-for-delivery' == $order_status ) {
		$change_status  = '<h4>' . __( "Change Status", 'ddwc' ) . '</h4>';
		$change_status .= '<form method="post">';
		$change_status .= '<input type="hidden" name="ordercompleted" value="completed" />';
		$change_status .= '<input type="submit" value="' . __( 'Completed', 'ddwc' ) . '" class="button ddwc-change-status" />';
		$change_status .= wp_nonce_field( 'ddwc_order_completed_nonce_action', 'ddwc_order_completed_nonce_field' ) . '</form>';
	}

	do_action( 'ddwc_driver_dashboard_change_status_forms_bottom' );

	echo apply_filters( 'ddwc_driver_dashboard_change_status', $change_status, $order_status );

}

/**
 * Checks if a particular user has one or more roles.
 *
 * Returns true on first matching role. Returns false if no roles match.
 *
 * @uses get_userdata()
 * @uses wp_get_current_user()
 *
 * @param array|string $roles Role name (or array of names).
 * @param int $user_id (Optional) The ID of a user. Defaults to the current user.
 * @return bool
 */
function ddwc_check_user_roles( $roles, $user_id = null ) {

    if ( is_numeric( $user_id ) )
	$user = get_userdata( $user_id );
    else
	$user = wp_get_current_user();

    if ( empty( $user ) )
	return false;

    $user_roles = (array) $user->roles;

    foreach ( (array) $roles as $role ) {
	if ( in_array( $role, $user_roles ) )
	    return true;
    }

    return false;
}

/**
 * Auto-assign a delivery driver to new orders.
 *
 * Selects an available driver based on the configured algorithm and stores
 * the driver ID in the order's meta data.
 *
 * @since 2.5.0
 *
 * @param int $order_id Order ID.
 */
function ddwc_auto_assign_driver_to_order( $order_id ) {

	// Bail if a driver is already assigned.
	if ( get_post_meta( $order_id, 'ddwc_driver_id', true ) ) {
		return;
	}

	// Get available drivers.
	$driver_args = array(
		'role'       => 'driver',
		'meta_key'   => 'ddwc_driver_availability',
		'meta_value' => 'on',
	);
	$drivers     = get_users( $driver_args );

	if ( empty( $drivers ) ) {
		return;
	}

	// Determine assignment algorithm.
	$algorithm = get_option( 'ddwc_settings_assignment_algorithm', 'least_orders' );
	$driver_id = 0;

	if ( 'random' === $algorithm ) {
		$driver    = $drivers[ array_rand( $drivers ) ];
		$driver_id = $driver->ID;
	} else {
		$least_orders = null;
		foreach ( $drivers as $driver ) {
			$order_args = array(
				'post_type'      => 'shop_order',
				'post_status'    => array( 'wc-driver-assigned', 'wc-out-for-delivery' ),
				'meta_key'       => 'ddwc_driver_id',
				'meta_value'     => $driver->ID,
				'fields'         => 'ids',
				'posts_per_page' => -1,
			);
			$open_orders = get_posts( $order_args );
			$count       = count( $open_orders );

			if ( is_null( $least_orders ) || $count < $least_orders ) {
				$least_orders = $count;
				$driver_id    = $driver->ID;
			}
		}
	}

	if ( $driver_id ) {
		update_post_meta( $order_id, 'ddwc_driver_id', $driver_id );

		// Update order status to show driver assignment.
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->update_status( 'driver-assigned' );
		}
	}
}
add_action( 'woocommerce_new_order', 'ddwc_auto_assign_driver_to_order' );
