<?php
/**
 * Note: This file should live in wp-content, next to the advanced-cache
 * drop-in.
 * 
 * This file is included from within the advanced-cache.php drop-in,
 * before the object cache has been initialised, so we have to
 * initialise it manually.
 */
global $batcache;

if ( ! include_once( WP_CONTENT_DIR . '/object-cache.php' ) )
	return;

wp_cache_init();

if ( ! is_object( $wp_object_cache ) )
	return;

wp_cache_add_global_groups( array( 'cache_incrementors' ) );

/**
 * The cache group is in the format <group>_<incrementor>, where <group> 
 * is the value of the defined batcache group and <incrementor> is a 
 * generated integer value which we increment to invalidate the cache.
 */
$prefix = isset( $batcache['group'] ) ? $batcache['group'] : 'batcache';
$batcache['group'] = $prefix . '_' . batcache_get_incr();

/**
 * Increment the batcache group incrementor value, invalidating the cache.
 * @return false|int False on failure, the item's new value on success.
 */
function batcache_flush_all() {
	return wp_cache_incr( 'batcache', 1, 'cache_incrementors' );
}

/**
 * Get the current batcache group incrementor value. If a value doesn't
 * exist inside the object cache, use the current unix time and set it.
 * @return int The incrementor value
 */
function batcache_get_incr() {
	$incr = wp_cache_get( 'batcache', 'cache_incrementors', true );
	if ( ! is_numeric( $incr ) ) {
		$incr = time();
		wp_cache_set( 'batcache', $incr, 'cache_incrementors' );
	}
	return $incr;
}
