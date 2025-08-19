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
        $order_id = isset( $_GET['orderid'] ) ? absint( $_GET['orderid'] ) : 0;
        if ( ! $order_id ) {
                return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
                return;
        }

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
                // Send SMS notification to customer.
                $customer_phone = $order->get_billing_phone();
                $dispatch_phone = get_option( 'ddwc_settings_dispatch_phone_number' );
                if ( $customer_phone && $dispatch_phone ) {
                        $twilio  = new Delivery_Drivers_Twilio();
                        $message = sprintf( __( 'Your order #%s is out for delivery.', 'ddwc' ), $order->get_id() );
                        $twilio->send_sms( $customer_phone, $dispatch_phone, $message );
                }

		// Redirect so the new order details show on the page.
                wp_redirect( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . 'driver-dashboard/?orderid=' . $order_id );
                exit;

	}

	// Update order status if marked COMPLETED by Driver.
	if ( isset( $_POST['ordercompleted'] ) ) {

		// Update order status.
		$order->update_status( "completed" );

		// Run additional functions.
		do_action( 'ddwc_email_admin_order_status_completed' );
                // Send SMS notification to customer.
                $customer_phone = $order->get_billing_phone();
                $dispatch_phone = get_option( 'ddwc_settings_dispatch_phone_number' );
                if ( $customer_phone && $dispatch_phone ) {
                        $twilio  = new Delivery_Drivers_Twilio();
                        $message = sprintf( __( 'Your order #%s has been delivered.', 'ddwc' ), $order->get_id() );
                        $twilio->send_sms( $customer_phone, $dispatch_phone, $message );
                }

		// Redirect so the new order details show on the page.
                wp_redirect( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . 'driver-dashboard/?orderid=' . $order_id );
                exit;

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
        $order_id = isset( $_GET['orderid'] ) ? absint( $_GET['orderid'] ) : 0;
        if ( ! $order_id ) {
                return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
                return;
        }

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
* codex/trigger-email-and-sms-on-driver-assignment
 * Notify a delivery driver when assigned to an order.
 *
 * Sends both an email and an SMS message using Twilio when a driver is
 * assigned to an order. This hooks into both manual and automatic driver
 * assignment paths via the `ddwc_driver_assigned` action.
 *
 * @since 2.4.3
 *
 * @param int $order_id  The order ID.
 * @param int $driver_id The user ID of the assigned driver.
 */
function ddwc_notify_driver_assignment( $order_id, $driver_id ) {

    // Get order and driver objects.
    $order  = wc_get_order( $order_id );
    $driver = get_userdata( $driver_id );

    if ( ! $order || ! $driver ) {
        return;
    }

    $order_number = $order->get_order_number();
    $subject      = sprintf( __( 'New delivery assignment for order #%s', 'ddwc' ), $order_number );
    $message      = sprintf( __( 'You have been assigned to order #%s.', 'ddwc' ), $order_number );

    // Send email to driver.
    wp_mail( $driver->user_email, $subject, $message );

    // Send SMS via Twilio if credentials are available.
    $phone = get_user_meta( $driver_id, 'billing_phone', true );
    $sid   = get_option( 'ddwc_twilio_account_sid' );
    $token = get_option( 'ddwc_twilio_auth_token' );
    $from  = get_option( 'ddwc_twilio_phone_number' );

    if ( class_exists( '\\Twilio\\Rest\\Client' ) && $phone && $sid && $token && $from ) {
        try {
            $client = new \Twilio\Rest\Client( $sid, $token );
            $client->messages->create( $phone, array( 'from' => $from, 'body' => $message ) );
        } catch ( \Exception $e ) {
            // Fail silently if SMS could not be sent.
        }
    }
}
add_action( 'ddwc_driver_assigned', 'ddwc_notify_driver_assignment', 10, 2 );
add_action( 'ddwc_auto_assign_driver', 'ddwc_notify_driver_assignment', 10, 2 );


 codex/implement-driver-selection-for-new-orders
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

 * Send admin notifications when a driver completes an order.
 *
 * Sends an email and SMS to the administrator and logs the results for
 * troubleshooting purposes.
 *
 * @since 2.4.2
 */
function ddwc_notify_admin_order_completed() {

        // Ensure an order ID is available.
        if ( ! isset( $_GET['orderid'] ) ) {
                return;
        }

        $order_id   = absint( $_GET['orderid'] );
        $admin_email = get_option( 'admin_email' );
        $subject     = sprintf( __( 'Order #%d completed', 'ddwc' ), $order_id );
        $message     = sprintf( __( 'Order #%d has been marked as completed by the driver.', 'ddwc' ), $order_id );

        $logger = function_exists( 'wc_get_logger' ) ? wc_get_logger() : false;

        $email_sent = wp_mail( $admin_email, $subject, $message );

        if ( $logger ) {
                if ( $email_sent ) {
                        $logger->info( 'Admin completion email sent for order #' . $order_id, array( 'source' => 'ddwc' ) );
                } else {
                        $logger->error( 'Failed to send admin completion email for order #' . $order_id, array( 'source' => 'ddwc' ) );
                }
        }

        $admin_phone  = get_option( 'ddwc_settings_dispatch_phone_number' );
        $twilio_sid   = get_option( 'ddwc_settings_twilio_account_sid' );
        $twilio_token = get_option( 'ddwc_settings_twilio_auth_token' );
        $twilio_from  = get_option( 'ddwc_settings_twilio_phone_number' );

        if ( $admin_phone && $twilio_sid && $twilio_token && $twilio_from ) {
                $twilio_url = 'https://api.twilio.com/2010-04-01/Accounts/' . $twilio_sid . '/Messages.json';
                $sms_args   = array(
                        'body'    => array(
                                'From' => $twilio_from,
                                'To'   => $admin_phone,
                                'Body' => $message,
                        ),
                        'headers' => array(
                                'Authorization' => 'Basic ' . base64_encode( $twilio_sid . ':' . $twilio_token ),
                        ),
                );

                $response = wp_remote_post( $twilio_url, $sms_args );

                if ( $logger ) {
                        if ( is_wp_error( $response ) ) {
                                $logger->error( 'Failed to send admin completion SMS for order #' . $order_id . ': ' . $response->get_error_message(), array( 'source' => 'ddwc' ) );
                        } elseif ( 201 === wp_remote_retrieve_response_code( $response ) ) {
                                $logger->info( 'Admin completion SMS sent for order #' . $order_id, array( 'source' => 'ddwc' ) );
                        } else {
                                $logger->error( 'Failed to send admin completion SMS for order #' . $order_id, array( 'source' => 'ddwc' ) );
                        }
                }
        } elseif ( $logger ) {
                $logger->warning( 'Admin completion SMS not sent for order #' . $order_id . ' due to missing Twilio configuration or phone number.', array( 'source' => 'ddwc' ) );
        }
}
add_action( 'ddwc_email_admin_order_status_completed', 'ddwc_notify_admin_order_completed' );
