<?php
/*
Plugin name: Batcache Manager
Plugin URI: http://wordpress.org/extend/plugins/batcache/
Description: This optional plugin improves Batcache.
Author: Andy Skelton
Author URI: http://andyskelton.com/
Version: 1.2 (xyu/batcache fork)
*/

// Do not load if our advanced-cache.php isn't loaded
if ( ! isset( $batcache ) || ! is_object($batcache) || ! method_exists( $wp_object_cache, 'incr' ) )
	return;

$batcache->configure_groups();

// Regen home and permalink on posts and pages
add_action('clean_post_cache', 'batcache_post');

// Regen permalink on comments (TODO)
//add_action('comment_post',          'batcache_comment');
//add_action('wp_set_comment_status', 'batcache_comment');
//add_action('edit_comment',          'batcache_comment');

function batcache_post($post_id) {
global $batcache;

// Check if $post_id is not null and if the post exists
if (empty($post_id) || !get_post($post_id)) {
	return;
}

$post = get_post($post_id);

// Check if $post is null
if (!$post || $post->post_type == 'revision' || !in_array(get_post_status($post_id), array('publish', 'trash'))) {
	return;
}

$home = trailingslashit(get_option('home'));
batcache_clear_url($home);
batcache_clear_url($home . 'feed/');
batcache_clear_url(get_permalink($post_id));
}

function batcache_clear_url($url) {
	global $batcache, $wp_object_cache;

	if ( empty($url) )
		return false;

	if ( 0 === strpos( $url, 'https://' ) )
		$url = str_replace( 'https://', 'http://', $url );
	if ( 0 !== strpos( $url, 'http://' ) )
		$url = 'http://' . $url;

	$url_key = md5( $url );
	wp_cache_add("{$url_key}_version", 0, $batcache->group);
	$retval = wp_cache_incr("{$url_key}_version", 1, $batcache->group);

	$batcache_no_remote_group_key = false;

	// Check if the property is set before accessing it
	if ( isset( $wp_object_cache->no_remote_groups ) && is_array( $wp_object_cache->no_remote_groups ) ) {
		$batcache_no_remote_group_key = array_search( $batcache->group, (array) $wp_object_cache->no_remote_groups );

		if ( false !== $batcache_no_remote_group_key ) {
			// The *_version key needs to be replicated remotely, otherwise invalidation won't work.
			// The race condition here should be acceptable.
			unset( $wp_object_cache->no_remote_groups[ $batcache_no_remote_group_key ] );
			$retval = wp_cache_set( "{$url_key}_version", $retval, $batcache->group );
			$wp_object_cache->no_remote_groups[ $batcache_no_remote_group_key ] = $batcache->group;
		}
	} else {
		// Handle the case when $wp_object_cache->no_remote_groups is not set
		// You might want to log a warning or handle it based on your needs
	}

	return $retval;
}
