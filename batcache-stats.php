<?php

global $batcache;

if ( ! include_once( WP_CONTENT_DIR . '/object-cache.php' ) )
	return;

wp_cache_init();

if ( ! is_object( $wp_object_cache ) )
	return;

wp_cache_add_global_groups( array( 'cache_incrementors' ) );

$prefix = isset( $batcache['group'] ) ? $batcache['group'] : 'batcache';
$batcache['group'] = $prefix . '_' . batcache_get_incr();

function batcache_flush_all() {
	return wp_cache_incr( 'batcache', 1, 'cache_incrementors' );
}

function batcache_get_incr() {
	$incr = wp_cache_get( 'batcache', 'cache_incrementors', true );
	if ( ! is_numeric( $incr ) ) {
		$incr = time();
		wp_cache_set( 'batcache', $incr, 'cache_incrementors' );
	}

	return $incr;
}
