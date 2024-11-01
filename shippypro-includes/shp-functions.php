<?php
/**
 * Functions used by plugins
 */
if ( ! class_exists( 'Shp_Dependencies' ) )
	require_once 'class-shp-dependencies.php';

/**
 * WC Detection
 */
if ( ! function_exists( 'shp_is_woocommerce_active' ) ) {
	function shp_is_woocommerce_active() {
		return Shp_Dependencies::woocommerce_active_check();
	}
}