<?php
/*
 * Plugin name: Batcache Manager
 * Plugin URI: http://www.github.com/spacedmonkey/batcache-manager
 * Description: Cache clearing for batcache
 * Author: Jonathan Harris
 * Author URI: http://www.jonathandavidharris.co.uk
 * Version: 2.0.0
*/

/**
 * Class Batcache_Manager
 */
class Batcache_Manager {

	/**
	 * List of feeds
	 *
	 * @since    2.0.0
	 *
	 * @var array
	 */
	private $feeds = array( 'rss', 'rss2', 'rdf', 'atom' );

	/**
	 * List of links to process
	 *
	 * @since    2.0.0
	 *
	 * @var array
	 */
	private $links = array();

	/**
	 * Instance of this class.
	 *
	 * @since    2.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 *
	 */
	private function __construct() {

		global $batcache, $wp_object_cache;

		// Do not load if our advanced-cache.php isn't loaded
		if ( ! isset( $batcache ) || ! is_object( $batcache ) || ! method_exists( $wp_object_cache, 'incr' ) ) {
			return;
		}

		$batcache->configure_groups();

		// Posts
		add_action( 'clean_post_cache', array( $this, 'action_clean_post_cache' ), 15 );
		// Terms
		add_action( 'clean_term_cache', array( $this, 'action_clean_term_cache' ), 10, 3 );
		//Comments
		add_action( 'clean_comment_cache', array( $this, 'action_update_comment' ) ); // Only supported in 4.5
		add_action( 'comment_post', array( $this, 'action_update_comment' ) );
		add_action( 'wp_set_comment_status', array( $this, 'action_update_comment' ) );
		add_action( 'edit_comment', array( $this, 'action_update_comment' ) );
		// Users
		add_action( 'clean_user_cache', array( $this, 'action_update_user' ) );
		add_action( 'profile_update', array( $this, 'action_update_user' ) );
		// Widgets
		add_filter( 'widget_update_callback', array( $this, 'action_update_widget' ), 50 );
		// Customiser
		add_action( 'customize_save_after', array( $this, 'flush_all' ) );
		// Theme
		add_action( 'switch_theme', array( $this, 'flush_all' ) );
		// Nav
		add_action( 'wp_update_nav_menu', array( $this, 'flush_all' ) );

		// Add site aliases to list of links
		add_filter( 'batcache_manager_links', array( $this, 'add_site_alias' ) );

		// Do the flush of the urls on shutdown
		add_action( 'shutdown', array( $this, 'clear_urls' ) );
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     2.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Determines whether a post type is considered "viewable".
	 *
	 * For built-in post types such as posts and pages, the 'public' value will be evaluated.
	 * For all others, the 'publicly_queryable' value will be used.
	 *
	 *
	 * @param string $post_type Post type.
	 *
	 * @return bool Whether the post type should be considered viewable.
	 */
	public function is_post_type_viewable( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		if ( empty( $post_type_object ) ) {
			return false;
		}

		return $post_type_object->publicly_queryable || ( $post_type_object->_builtin && $post_type_object->public );
	}

	/**
	 * Whether the taxonomy object is public.
	 *
	 * Checks to make sure that the taxonomy is an object first. Then Gets the
	 * object, and finally returns the public value in the object.
	 *
	 * A false return value might also mean that the taxonomy does not exist.
	 *
	 * @since 2.0.0
	 *
	 * @param string $taxonomy Name of taxonomy object.
	 *
	 * @return bool Whether the taxonomy is public.
	 */
	function is_taxonomy_viewable( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}

		$taxonomy = get_taxonomy( $taxonomy );

		return $taxonomy->public;
	}

	/**
	 * Clear post on post update
	 *
	 * @param $post_id
	 */
	public function action_clean_post_cache( $post_id ) {

		$post = get_post( $post_id );
		if ( ! $this->is_post_type_viewable( $post->post_type ) || ! in_array( get_post_status( $post_id ), array(
				'publish',
				'trash'
			) )
		) {
			return;
		}
		$this->setup_post_urls( $post );
		$this->setup_author_urls( $post->post_author );
		$this->setup_site_urls();
	}

	/**
	 * Clear terms on term update
	 *
	 * @param array $ids Single or list of Term IDs.
	 * @param string $taxonomy
	 * @param bool $clean_taxonomy Optional. Whether to clean taxonomy wide caches (true), or just individual
	 *                                       term object caches (false). Default true. Only support in WP 4.5
	 */
	public function action_clean_term_cache( $ids, $taxonomy, $clean_taxonomy = true ) {
		// Clear taxonomy global caches. If false, lets not both.
		if ( ! $clean_taxonomy ) {
			return;
		}
		// If not a public taxonomy, don't clear caches.
		if ( ! $this->is_taxonomy_viewable( $taxonomy ) ) {
			return;
		}

		foreach ( $ids as $term ) {
			$this->setup_term_urls( $term, $taxonomy );
		}
	}

	/**
	 * Clear post page on comment update
	 *
	 * @param $comment_id
	 */
	public function action_update_comment( $comment_id ) {
		$comment = get_comment( $comment_id );
		$post_id = $comment->comment_post_ID;
		$this->setup_post_urls( $post_id );
		$this->setup_post_comment_urls( $post_id, $comment_id );
	}

	/**
	 * Clear author links on update user.
	 *
	 * @param $user_id
	 */
	public function action_update_user( $user_id ) {
		$this->setup_author_urls( $user_id );
	}

	public function flush_all() {
		if ( function_exists( 'batcache_flush_all' ) ) {
			batcache_flush_all();
		}
	}

	/**
	 * Flush all of the caches when a widget is updated.
	 *
	 * @param  array $instance The current widget instance's settings.
	 *
	 * @return array $instance
	 */
	public function action_update_widget( $instance ) {
		$this->flush_all();

		return $instance;
	}

	/**
	 * Get term archive and feed links for each term
	 *
	 * @param $term
	 * @param $taxonomy
	 */
	public function setup_term_urls( $term, $taxonomy ) {

		$term_link = get_term_link( $term, $taxonomy );
		if ( ! is_wp_error( $term_link ) ) {
			$this->links[] = $term_link;
		}
		foreach ( $this->feeds as $feed ) {
			$term_link_feed = get_term_feed_link( $term, $taxonomy, $feed );
			if ( $term_link_feed ) {
				$this->links[] = $term_link_feed;
			}
		}

		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( $taxonomy_object->show_in_rest && $taxonomy_object->rest_base ) {
			$base = $taxonomy_object->rest_base;
			$this->links[] = get_rest_url( null, '/wp/v2/' . $base );
			$this->links[] = get_rest_url( null, '/wp/v2/' . $base . '/'. $term );
		}

	}

	/**
	 * Home page / blog page and feed links
	 */
	public function setup_site_urls() {
		if ( get_option( 'show_on_front' ) == 'page' ) {
			$this->links[] = get_permalink( get_option( 'page_for_posts' ) );
		}

		$this->links[] = home_url( '/' );

		foreach ( $this->feeds as $feed ) {
			$this->links[] = get_feed_link( $feed );
		}
	}

	/**
	 * Get permalink, date archives and custom post type links
	 *
	 * @param $post
	 */
	public function setup_post_urls( $post ) {
		$post = get_post( $post );

		$this->links[] = get_permalink( $post );
		if ( $post->post_type == 'post' ) {
			$year          = get_the_time( "Y", $post );
			$month         = get_the_time( "m", $post );
			$day           = get_the_time( "d", $post );
			$this->links[] = get_year_link( $year );
			$this->links[] = get_month_link( $year, $month );
			$this->links[] = get_day_link( $year, $month, $day );
		} else if ( ! in_array( $post->post_type, get_post_types( array( 'public' => true ) ) ) ) {
			if ( $archive_link = get_post_type_archive_link( $post->post_type ) ) {
				$this->links[] = $archive_link;
			}
			foreach ( $this->feeds as $feed ) {
				if ( $archive_link_feed = get_post_type_archive_feed_link( $post->post_type, $feed ) ) {
					$this->links[] = $archive_link_feed;
				}
			}
		}
		$post_type = get_post_type_object( $post->post_type );
		if ( $post_type->show_in_rest && $post_type->rest_base ) {
			$base = $post_type->rest_base;
			$this->links[] = get_rest_url( null, '/wp/v2/' . $base );
			$this->links[] = get_rest_url( null, '/wp/v2/' . $base . '/'. $post->ID );
		}
	}

	/**
	 * Author profile and feed links
	 *
	 * @param $author_id
	 */
	public function setup_author_urls( $author_id ) {
		$this->links[] = get_author_posts_url( $author_id );
		foreach ( $this->feeds as $feed ) {
			$this->links[] = get_author_feed_link( $author_id, $feed );
		}
		$this->links[] = get_rest_url( null, '/wp/v2/users' );
		$this->links[] = get_rest_url( null, '/wp/v2/users/' . $author_id );
	}

	/**
	 * Get feed urls for comments for single posts
	 *
	 * @param $post_id
	 */
	public function setup_post_comment_urls( $post_id, $comment_id = 0 ) {
		foreach ( $this->feeds as $feed ) {
			$this->links[] = get_post_comments_feed_link( $post_id, $feed );
		}

		foreach ( $this->feeds as $feed ) {
			$this->links[] = get_feed_link( "comments_" . $feed );
		}
		$this->links[] = get_rest_url( null, '/wp/v2/comments' );
		$this->links[] = get_rest_url( null, '/wp/v2/comments/' . $comment_id );
	}


	/**
	 * Work around for those using Domain mapping or have CMS on different url.
	 *
	 * @param $links
	 */
	public function add_site_alias( $links ) {
		$home = parse_url( home_url(), PHP_URL_HOST );

		$compare_urls = array(
			parse_url( get_option( 'home' ), PHP_URL_HOST ),
			parse_url( get_option( 'siteurl' ), PHP_URL_HOST ),
			parse_url( site_url(), PHP_URL_HOST )
		);

		// Compare home, site urls with filtered home url
		foreach ( $compare_urls as $compare_url ) {
			if ( $compare_url != $home ) {
				foreach ( $links as $url ) {
					$links[] = str_replace( $home, $compare_url, $url );
				}
			}
		}

		return $links;
	}

	/**
	 * Loop around all urls and clear
	 */
	public function clear_urls() {
		if ( empty ( $this->get_links() ) ) {
			return;
		}

		foreach ( $this->get_links() as $url ) {
			self::clear_url( $url );
		}
		// Clear out links
		$this->links = array();
	}

	/**
	 *
	 * @param $url
	 *
	 * @return bool|false|int
	 */
	public static function clear_url( $url ) {
		global $batcache, $wp_object_cache;

		$url = apply_filters( 'batcache_manager_link', $url );

		if ( empty( $url ) ) {
			return false;
		}

		do_action( 'batcache_manager_before_flush', $url );

		// Force to http
		$url = set_url_scheme( $url, 'http' );

		$url_key = md5( $url );

		wp_cache_add( "{$url_key}_version", 0, $batcache->group );
		$retval = wp_cache_incr( "{$url_key}_version", 1, $batcache->group );

		$batcache_no_remote_group_key = array_search( $batcache->group, (array) $wp_object_cache->no_remote_groups );
		if ( false !== $batcache_no_remote_group_key ) {
			// The *_version key needs to be replicated remotely, otherwise invalidation won't work.
			// The race condition here should be acceptable.
			unset( $wp_object_cache->no_remote_groups[ $batcache_no_remote_group_key ] );
			$retval                                                             = wp_cache_set( "{$url_key}_version", $retval, $batcache->group );
			$wp_object_cache->no_remote_groups[ $batcache_no_remote_group_key ] = $batcache->group;
		}

		do_action( 'batcache_manager_after_flush', $url, $retval );

		return $retval;
	}

	/**
	 * Filter links
	 *
	 * @return array
	 */
	public function get_links() {
		$this->links = apply_filters( 'batcache_manager_links', $this->links );

		return array_unique( $this->links );
	}

}

global $batcache_manager;

$batcache_manager = Batcache_Manager::get_instance();
