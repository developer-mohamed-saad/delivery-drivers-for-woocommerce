<?php
/**
 * PHPUnit bootstrap file for Delivery Drivers for WooCommerce.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

// Load this plugin.
tests_add_filter( 'muplugins_loaded', function () {
    require dirname( __DIR__ ) . '/delivery-drivers-for-woocommerce.php';
} );

require $_tests_dir . '/includes/bootstrap.php';
