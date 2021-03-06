<?php

namespace StudyChurch;

class Study {

	/**
	 * @var
	 */
	protected static $_instance;

	/**
	 * @var Study\Edit
	 */
	public $edit;

	public $answers;

	/**
	 * Only make one instance of the Study
	 *
	 * @return Study
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Study ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Add Hooks and Actions
	 */
	protected function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_setup_study_group' ) );
		add_action( 'template_redirect', array( $this, 'redirect_on_empty' ) );
		add_action( 'wp_head', array( $this, 'print_styles' ) );
		add_action( 'pre_get_posts', array( $this, 'study_archive' ) );

		add_filter( 'private_title_format', array( $this, 'private_title_format' ), 10, 2 );
		add_filter( 'user_has_cap', array( $this, 'private_study_cap' ), 10, 4 );
		add_filter( 'get_page_uri', array( $this, 'allow_private_parent' ), 10, 2 );

		// CPT
		add_action( 'init', array( $this, 'study_cpt' ) );

		// Groups
		add_action( 'bp_init', array( $this, 'register_group_extension' ) );

		$this->edit = Study\Edit::get_instance();
		$this->answers = Study\Answers::get_instance();
	}

	public function register_group_extension() {
		// if we aren't in a group, don't bother
		if ( ! bp_is_group() || ! bp_is_active( 'groups' ) || ! class_exists( 'BP_Group_Extension' ) ) {
			return;
		}

		bp_register_group_extension( 'StudyChurch\Study\Group' );
	}

	/**
	 * If the study does not have an introduction, redirect to the first chapter
	 */
	public function redirect_on_empty() {
		if ( ! is_singular( 'sc_study' ) ) {
			return;
		}

		// if we are not on the main study page continue
		if ( get_the_ID() != sc_get_study_id( get_the_ID() ) ) {
			return;
		}

		if ( get_the_content() ) {
			return;
		}

		$nav = sc_study_get_navigation( get_the_ID() );

		// if we have no content for this page, redirect to the first item
		if ( ! empty( $nav[0] ) ) {
			wp_safe_redirect( get_the_permalink( $nav[0]->ID ) );
			die();
		}

	}

	/**
	 * Setup the group attached to this study.
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_setup_study_group() {

		if ( ! is_singular( 'sc_study' ) ) {
			return;
		}

		$study_id = sc_get_study_id();

		// if the group was setup successfully, return
		if ( $this->setup_study_group() ) {
			return;
		}

		// allow editors and up to proceed
		if ( current_user_can( 'edit_post', $study_id ) ) {
			return;
		}

		// if we are allowing personal studies, we don't care if the group was setup
		if ( apply_filters( 'sc_allow_personal_studies', false, $study_id ) ) {
			return;
		}

		wp_safe_redirect( bp_loggedin_user_domain() );
		die();

	}

	/**
	 * Setup global for current group and redirect if user does not have access to this
	 * study
	 *
	 * @param bool $group_id
	 *
	 * @return bool|int
	 * @author Tanner Moushey
	 */
	public function setup_study_group( $group_id = false ) {

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( empty( $group_id ) ) {

			if ( ! empty( $_REQUEST['sc-group'] ) ) {
				$group_id = absint( $_REQUEST['sc-group'] );

				if ( empty( $_COOKIE['sc-group'] ) || $group_id != $_COOKIE['sc-group'] ) {
					@setcookie( 'sc-group', $group_id, time() + MONTH_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
				}
			} else if ( ! empty( $_COOKIE['sc-group'] ) ) {
				$group_id = absint( $_COOKIE['sc-group'] );
			}

		}

		if ( empty( $group_id ) ) {
			return false;
		}

		bp_has_groups( 'include=' . $group_id );
		bp_groups();
		bp_the_group();

		return bp_get_group_id();

	}

	/**
	 * Remove "Private:" label from private sc_study posts
	 *
	 * @param $format
	 * @param $post
	 *
	 * @return string
	 */
	public function private_title_format( $format, $post ) {
		if ( 'sc_study' != $post->post_type ) {
			return $format;
		}

		return '%s';
	}

	public function private_study_cap( $allcaps, $caps, $args, $user ) {
		if ( empty( $user->ID ) ) {
			return $allcaps;
		}

		// we are only interested in private posts capability
		if ( ! in_array( 'read_private_posts', $caps ) ) {
			return $allcaps;
		}

		// this user can already ready private posts
		if ( isset( $allcaps['read_private_posts'] ) && $allcaps['read_private_posts'] ) {
			return $allcaps;
		}

		// make sure this is a study
		if ( empty( $args[2] ) || 'sc_study' != get_post_type( absint( $args[2] ) ) ) {
			return $allcaps;
		}

		// make sure this user has access to this study
		if ( ! self::user_can_access( absint( $args[2] ), $user->ID ) ) {
			return $allcaps;
		}

		$allcaps['read_private_posts'] = true;

		return $allcaps;
	}

	public function print_styles() {
		if ( ! is_singular( 'sc_study' ) ) {
			return;
		} ?>
		<style>
			@page {
				size: 8.5in 11in;
				margin: 10%;
			}
		</style>
		<?php
	}

	public function study_archive( $query ) {
		if ( is_admin() ) {
			return;
		}

		if ( ! $query->is_main_query() ) {
			return;
		}

		if ( 'sc_study' != $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! $query->is_archive ) {
			return;
		}

		$query->set( 'post_parent', 0 );
	}

	public function allow_private_parent( $uri, $page ) {
		if ( 'sc_study' != $page->post_type ) {
			return $uri;
		}

		$uri = $page->post_name;

		foreach ( $page->ancestors as $parent ) {
			$parent = get_post( $parent );
			if ( in_array( $parent->post_status, array( 'publish', 'private' ) ) ) {
				$uri = $parent->post_name . '/' . $uri;
			}
		}

		return $uri;
	}

	public function study_cpt() {
		$labels = array(
			'name'               => _x( 'Studies', 'post type general name', 'sc' ),
			'singular_name'      => _x( 'Study', 'post type singular name', 'sc' ),
			'add_new_item'       => __( 'Add New Study', 'sc' ),
			'new_item'           => __( 'New Study', 'sc' ),
			'edit_item'          => __( 'Edit Study', 'sc' ),
			'view_item'          => __( 'View Study', 'sc' ),
			'all_items'          => __( 'All Studies', 'sc' ),
			'search_items'       => __( 'Search Studies', 'sc' ),
			'not_found'          => __( 'No studies found.', 'sc' ),
			'not_found_in_trash' => __( 'No studies found in Trash.', 'sc' )
		);

		$args = array(
			'labels'        => $labels,
			'public'        => true,
			'rewrite'       => array(
				'slug'       => 'studies',
				'with_front' => false,
			),
			'hierarchical'  => true,
			'has_archive'   => true,
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-welcome-write-blog',
			'show_in_rest'  => true,
			'rest_base'     => 'studies',
			'rest_controller_class' => '\StudyChurch\API\Studies',
			'supports'      => array(
				'title',
				'editor',
				'author',
				'thumbnail',
				'excerpt',
				'comments',
				'page-attributes'
			)
		);

		register_post_type( 'sc_study', $args );

		register_taxonomy( 'sc_category', 'sc_study', array(
			'hierarchical' => true,
		) );

	}

	/** Study Helper Functions */

	/**
	 * @param null $group_id
	 *
	 * @return array
	 * @author Tanner Moushey
	 */
	public static function get_group_studies( $group_id = null ) {
		if ( ! $group_id ) {
			$group_id = bp_get_current_group_id();
		}

		return (array) groups_get_groupmeta( $group_id, '_sc_study', true );
	}

	/**
	 * Customize Study link to include group parameter for study answers
	 *
	 * @param      $study_id
	 * @param null $group_id
	 *
	 * @return string
	 * @author Tanner Moushey
	 */
	public static function get_group_link( $study_id, $group_id = null ) {
		if ( ! $group_id ) {
			$group_id = bp_get_current_group_id();
		}

		$study_id = self::get_study_id( $study_id );

		return add_query_arg( 'sc-group', $group_id, get_permalink( $study_id ) );
	}

	/**
	 * Get the top level id for this study
	 *
	 * @param null $id
	 *
	 * @return bool|int|mixed|null
	 */
	public static function get_study_id( $id = null ) {

		if ( ! $id ) {
			$id = get_the_ID();
		}

		if ( $parent_id = get_post_meta( $id, '_sc_study_id', true ) ) {
			return $parent_id;
		}

		$this_id = $id;

		// keep getting parents until there are no more to get.
		while ( $parent_id = wp_get_post_parent_id( $id ) ) {
			$id = $parent_id;
		}

		// cache results
		update_post_meta( $this_id, '_sc_study_id', $id );

		return $id;
	}

	/**
	 * Can the user access this study?
	 *
	 * @param null $study_id
	 * @param null $user_id
	 *
	 * @return bool
	 */
	public static function user_can_access( $study_id = null, $user_id = null ) {
		if ( ! $study_id ) {
			$study_id = get_the_ID();
		}

		$study_id = sc_get_study_id( $study_id );

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( empty( $study_id ) || empty( $user_id ) ) {
			return false;
		}

		if ( $study_access = get_user_meta( $user_id, '_studies', true ) ) {
			if ( ! empty( $study_access[ $study_id ] ) ) {
				return true;
			}
		} else {
			$study_access = array();
		}

		foreach ( groups_get_groups( 'show_hidden=true&user_id=' . $user_id )['groups'] as $group ) {
			if ( in_array( $study_id, studychurch()->study::get_group_studies( $group->id ) ) ) {
				$study_access[] = $study_id;
				update_user_meta( $user_id, '_studies', $study_access );
				return true;
			}
		}

		return false;
	}

}
