<?php
/*
Plugin Name: Lock Pages
Plugin URI: http://wordpress.org/extend/plugins/lock-pages/
Description: Allows admins to lock pages in order to prevent breakage of important URLs.
Author: Steve Taylor
Version: 0.3.1
Author URI: http://sltaylor.co.uk
Based on: http://pressography.com/plugins/wordpress-plugin-template/
*/

/*  Copyright 2009

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! class_exists('SLT_LockPages') ) {

	class SLT_LockPages {

		/**
		* @var	string	$prefix	The prefix for any form fields etc.
		* Note that this has had to be hard-coded into lock-pages.js
		*/
		var $prefix = 'slt_lockpages_';
		/**
		* @var	string	$options_name	The options string name for this plugin
		*/
		var $options_name = 'SLT_LockPages_options';
		/**
		* @var	string	$localization_domain	Domain used for localization
		*/
		var $localization_domain = "SLT_LockPages";
		/**
		* @var	array		$options	Stores the options for this plugin
		*/
		var $options = array();

		/**
		* PHP 5 Constructor
		*/
		function __construct() {

			// Language Setup
			$locale = get_locale();
			$mo = dirname( __FILE__ ) . "/languages/" . $this->localization_domain . "-" . $locale . ".mo";
			load_textdomain( $this->localization_domain, $mo );

			// Load the options
			// This is REQUIRED to initialize the options when the plugin is loaded!
			$this->load_options();

			// Initialize hooks
			add_action( 'admin_init', array( &$this, 'add_hooks' ) );
			add_action( 'admin_menu', array( &$this, 'admin_menu_link' ) );

		}

		/**
		* Add hooks
		*
		* @since	0.2
		*/
		function add_hooks() {

			// Changes to the edit screens
			add_action( 'edit_page_form', array( &$this, 'old_value_fields' ) );
			add_action( 'edit_form_advanced', array( &$this, 'old_value_fields' ) ); // non-page post types
			add_action( 'admin_notices', array( &$this, 'output_page_locked_notice' ) );
			add_action( 'add_meta_boxes', array( &$this, 'remove_slug_meta_box' ), 10, 2 );
			add_action( 'load-post.php', array( &$this, 'load_js_css' ) );
			add_action( 'load-edit.php', array( &$this, 'load_js_css' ) );
			add_filter( 'page_row_actions', array( &$this, 'remove_page_row_actions' ), 10, 2 );
			add_filter( 'post_row_actions', array( &$this, 'remove_page_row_actions' ), 10, 2 ); // non-page post types
			add_filter( 'get_sample_permalink_html', array( &$this, 'remove_edit_permalink' ), 10, 2 );
			add_filter( 'admin_body_class', array( &$this, 'admin_body_class' ) );

			add_action( 'add_meta_boxes', array( &$this, 'create_meta_box' ), 10, 2 );
			add_action( 'save_post', array( &$this, 'save_meta' ), 1, 2 );
			add_filter( 'manage_pages_columns', array( &$this, 'pages_list_col' ) );
			add_action( 'manage_pages_custom_column', array( &$this, 'pages_list_col_value' ), 10, 2 );
			add_filter( 'manage_posts_columns', array( &$this, 'pages_list_col' ), 10, 2 );
			add_action( 'manage_posts_custom_column', array( &$this, 'pages_list_col_value' ), 10, 2 );

			// Filters to block saving
			add_filter( 'name_save_pre', array( &$this, 'lock_slug' ), 0 );
			add_filter( 'parent_save_pre', array( &$this, 'lock_parent' ), 0 );
			add_filter( 'page_template_pre', array( &$this, 'lock_template' ), 0 );
			add_filter( 'status_save_pre', array( &$this, 'lock_status' ), 0 );
			add_filter( 'password_save_pre', array( &$this, 'lock_password' ), 0 );
			add_filter( 'user_has_cap', array( &$this, 'lock_deletion' ), 0, 4 );

		}

		/**
		* Remove slug meta box.
		*
		* @since	0.2
		* @uses		remove_meta_box()
		* @param	string	$post_type
		* @param	object	$post
		* @return	void
		*/
		function remove_slug_meta_box( $post_type, $post ) {
			if ( isset( $post->ID ) && ! $this->user_can_edit( $post->ID ) ) {
				remove_meta_box( 'slugdiv', $post_type, 'normal' );
			}
		}

		/**
		* Remove edit permalink functionality from permalink HTML.
		*
		* @since	0.2
		* @param	string	$html		The edit permalink HTML to return
		* @param	int		$post_id		The ID of the post being edited
		* @return	string
		*/
		function remove_edit_permalink( $html, $post_id ) {
			if ( ! $this->user_can_edit( $post_id ) ) {
				$element_start	= strpos( $html, '<span id="edit-slug-buttons"' );
				$element_end	= strpos( $html, '</span>', $element_start ) + 8;
				$html			= substr_replace( $html, '', $element_start, $element_end - $element_start );
				$html			= preg_replace( '#<span id="editable-post-name"[^>]*>([^<]+)</span>#', '$1', $html );
			}
			return $html;
		}

		/**
		* Remove certain page actions from locked pages.
		*
		* @since	0.1.5
		* @param	array		$actions		The page row actions
		* @param	object		$post			The page/post being listed
		* @return	array
		*/
		function remove_page_row_actions( $actions, $post ) {
			if ( $this->is_post_type_lockable( get_post_type( $post ) ) && ! $this->user_can_edit( $post->ID ) ) {
				foreach ( array( 'inline', 'inline hide-if-no-js', 'trash' ) as $action_key ) {
					if ( array_key_exists( $action_key, $actions ) ) {
						unset ( $actions[ $action_key ] );
					}
				}
			}
			return $actions;
		}

		/**
		* Add lock column to admin lists.
		*
		* @since	0.1.2
		* @param	array		$cols		The columns
		 * @param	array		$post_type	(Not sent by manage_pages_columns)
		* @return	array
		*/
		function pages_list_col( $cols, $post_type = 'page' ) {
			if (
				$this->is_post_type_lockable( $post_type ) &&
				( $post_type != 'page' || $this->options[$this->prefix.'scope'] != "all" )
			) {
				$cols["post-locked"] = __( 'Lock', $this->localization_domain );
			}
			return $cols;
		}

		/**
		* Add lock indicator to admin lists.
		*
		* @since		0.1.2
		* @param		string		$column_name		The column name
		* @param		int			$id					Post ID
		 * @return		void
		*/
		function pages_list_col_value( $column_name, $id ) {
			if ( $column_name == "post-locked" ) {
				echo $this->is_page_locked( $id ) ? '<span class="dashicons dashicons-lock" title="' . __( 'Locked', $this->localization_domain ) . '"></span>' : '&nbsp;';
			}
		}

		/**
		* Prevents unauthorized users changing a page's password protection.
		*
		* @since	0.2
		* @return	string
		*/
		function lock_password( $password ) {
			// Can user edit this page?
			if ( ! $this->user_can_edit_submitted_post() ) {
				// Keep old password, user can't change it
				$password = $_POST[ $this->prefix . 'old_password' ];
			}
			return $password;
		}

		/**
		* Prevents unauthorized users changing a page's template.
		*
		* @since	0.2
		* @return	string
		*/
		function lock_template( $template ) {
			// Can user edit this page?
			if ( ! $this->user_can_edit_submitted_post() ) {
				// Keep old template, user can't change it
				$template = $_POST[ $this->prefix . 'old_page_template' ];
			}
			return $template;
		}

		/**
		* Prevents unauthorized users changing a page's status.
		*
		* @since	0.2
		* @return	string
		*/
		function lock_status( $status ) {
			// Can user edit this page?
			if ( ! $this->user_can_edit_submitted_post() ) {
				// Keep old status, user can't change it
				$status = $_POST[ $this->prefix . 'old_status' ];
			}
			return $status;
		}

		/**
		* Prevents unauthorized users saving a new slug.
		*
		* @since	0.1
		* @return	string
		*/
		function lock_slug( $slug ) {
			// Can user edit this page?
			if ( ! $this->user_can_edit_submitted_post() ) {
				// Keep old slug, user can't change it
				$slug = $_POST[ $this->prefix . 'old_slug' ];
			}
			return $slug;
		}

		/**
		* Prevents unauthorized users saving a new parent.
		*
		* @since	0.1
		* @return	string
		*/
		function lock_parent( $parent ) {
			// Make sure this isn't an uploaded attachment
			if ( ! isset( $_POST["attachments"] ) && ! isset( $_POST["html-upload"] ) ) {
				// Can user edit this page?
				if ( ! $this->user_can_edit_submitted_post() ) {
					// Keep old parent, user can't change it
					$parent = $_POST[ $this->prefix . 'old_parent' ];
				}
			}
			return $parent;
		}

		/**
		* Prevents unauthorized users deleting a locked page.
		*
		* @since	0.1.1
		* @param	array		$allcaps		Capabilities granted to user
		* @param	array		$caps			Capabilities being checked
		* @param	array		$args			Optional arguments being passed
		* @param	object		$user			The user object
		* @return	array
		*/
		function lock_deletion( $allcaps, $caps, $args, $user ) {
			$cap_check	= count( $args ) ? $args[0] : array();
			$user_id	= count( $args ) > 1 ? $args[1] : 0;
			$object_id	= count( $args ) > 2 ? $args[2] : 0;

			// $cap_check may be a single cap or an array of caps
			if ( ! is_array( $cap_check ) ) :
				$cap_check = array( $cap_check );
			endif;

			// Is the check for deleting a post?
			$deleting_a_post = false;
			foreach ( $cap_check as $cap_being_checked ) :
				if ( strlen( $cap_being_checked ) > 7 && substr( $cap_being_checked, 0, 7 ) == "delete_" ) :
					$deleting_a_post = true;
					break;
				endif;
			endforeach;

			if ( $deleting_a_post ) {

				// Go through all lockable post types and see if the cap check is for deleting one in some way
				$post_deletion_check = false;
				foreach ( $this->get_lockable_post_types() as $lockable_post_type ) :
					foreach ( $cap_check as $cap_being_checked ) :
						if ( strpos( $cap_being_checked, $lockable_post_type ) !== false ) :
							$post_deletion_check = true;
							break;
						endif;
					endforeach;
					if ( $post_deletion_check ) :
						break;
					endif;
				endforeach;

				if ( $post_deletion_check && $object_id ) {

					// Is post type lockable, and is post locked?
					if ( $this->is_post_type_lockable( get_post_type( $object_id ) ) && ! $this->user_can_edit( $object_id ) ) {

						// Remove delete capabilities
						foreach( $allcaps as $cap => $value ) {
							if ( strpos( $cap, "delete_" ) !== false ) {
								// Go through it all again
								foreach ( $this->get_lockable_post_types() as $lockable_post_type ) {
									if ( strpos( $cap, $lockable_post_type ) !== false ) {
										unset( $allcaps[ $cap ] );
										break;
									}
								}
							}
						}

					}

				}
			}

			return $allcaps;
		}

		/**
		* Stores old values for locked fields in hidden fields on the page edit form.
		*
		* @since	0.1
		* @global	$post
		*/
		function old_value_fields( $post ) {
			if ( $this->is_post_type_lockable( get_post_type( $post ) ) ) {
				echo '<input type="hidden" name="' . esc_attr( $this->prefix ) . 'old_password" value="' . esc_attr( $post->post_password ) . '" />';
				echo '<input type="hidden" name="' . esc_attr( $this->prefix ) . 'old_status" value="' . esc_attr( $post->post_status ) . '" />';
				echo '<input type="hidden" name="' . esc_attr( $this->prefix ) . 'old_slug" value="' . esc_attr( $post->post_name ) . '" />';
				if ( is_post_type_hierarchical( get_post_type( $post ) ) ) {
					echo '<input type="hidden" name="' . esc_attr( $this->prefix ) . 'old_parent" value="' . esc_attr( $post->post_parent ) . '" />';
				}
				if ( get_post_type( $post ) == 'page' ) {
					echo '<input type="hidden" name="' . esc_attr( $this->prefix ) . 'old_page_template" value="' . esc_attr( $post->page_template ) . '" />';
				}
			}
		}

		/**
		* Outputs warning to users who won't be able to change page elements when editing.
		*
		* @since	0.1
		* @global	$post $pagenow
		*/
		function output_page_locked_notice() {
			if ( $this->is_page_locked_for_current_user() ) {
				if ( get_post_type() == 'page' ) {
					echo '<div class="updated page-locked-notice"><p><span class="dashicons dashicons-lock"></span>' . __( 'Please note that this page is locked, and certain changes are restricted.', $this->localization_domain ) . '</p></div>';
				} else {
					echo '<div class="updated page-locked-notice"><p><span class="dashicons dashicons-lock"></span>' . __( 'Please note that this item is locked, and certain changes are restricted.', $this->localization_domain ) . '</p></div>';
				}
			}
		}

		/**
 		* Adds the meta box to the page edit screen if the current scope isn't to lock all pages.
 		* Only outputs box for users who have the capability to edit locked pages.
 		*
 		* @since	0.1
 		* @uses		add_meta_box() Creates an additional meta box.
 		*/
		function create_meta_box( $post_type, $post ) {
			if (
				$this->is_post_type_lockable( $post_type ) &&
				( $post_type != 'page' || $this->options[$this->prefix.'scope'] != "all" ) &&
				current_user_can( $this->options[$this->prefix.'capability'] )
			) {
				add_meta_box( $this->prefix.'_meta-box', __( 'Lock Pages', $this->localization_domain ), array( &$this, 'output_meta_box' ), $post_type, 'side', 'high' );
			}
		}

		/**
 		* Controls the display of the page locking meta box.
 		*
 		* @since	0.1
 		* @global	$post
 		*/
		function output_meta_box() {
			if ( current_user_can( $this->options[$this->prefix.'capability'] ) ) {
				global $post; ?>

				<input type="hidden" name="<?php echo esc_attr( $this->prefix ); ?>meta_nonce" value="<?php echo wp_create_nonce(plugin_basename(__FILE__)); ?>" />
				<label for="<?php echo esc_attr( $this->prefix ); ?>locked">
					<input type="checkbox" name="<?php echo esc_attr( $this->prefix ); ?>locked" id="<?php echo $this->prefix; ?>locked"<?php if ( $this->is_page_locked( $post->ID ) ) echo ' checked="checked"'; ?> value="true" />
					<?php
					if ( get_post_type() == 'page' ) {
						_e( 'Lock this page', $this->localization_domain );
					} else {
						_e( 'Lock this item', $this->localization_domain );
					}
					?>
				</label>

				<?php
			}
		}

		/**
 		* Saves the page locking metabox data to a custom field.
 		*
 		* @since	0.1
 		* @uses		current_user_can()
 		*/
		function save_meta( $post_id, $post ) {

			/* Block:
			- Users who can't change locked posts
			- Users who can't edit the current post type
			- Revisions, autoupdates, quick edits, etc.
			- Simple Page Ordering plugin
			*/
			if (
				! current_user_can( $this->options[$this->prefix.'capability'] ) ||
				! current_user_can( 'edit_' . get_post_type( $post ) . 's', $post_id ) ||
				! $this->is_post_type_lockable( get_post_type( $post ) ) ||
				isset( $_POST["_inline_edit"] ) ||
				( isset( $_REQUEST["action"] ) && $_REQUEST["action"] == 'simple_page_ordering'  )
			)
				return;

			// Get list of locked posts
			$locked_posts = $this->options[$this->prefix.'locked_pages'];
			$update = false;

			if ( isset( $_POST[$this->prefix.'locked'] ) && $_POST[$this->prefix.'locked'] ) {
				// Box was checked, make sure page is added to list of locked pages
				if ( ! in_array( $post_id, $locked_posts ) ) {
					$locked_posts[] = $post_id;
					$update = true;
				}
			} else {
				// Box not checked, make sure page isn't in list of locked pages
				$id_pos = array_search( $post_id, $locked_posts );
				if ( $id_pos !== false ) {
					unset( $locked_posts[$id_pos] );
					$update = true;
				}
			}

			// Need to update?
			if ( $update ) {
				$this->options[$this->prefix.'locked_pages'] = $locked_posts;
				$this->save_admin_options();
			}

		}

		/**
		* Checks whether current user can edit page elements according to plugin settings
		* (and maybe page being edited).
		*
		* @since	0.1
		* @param	int	$post_id		Optional ID of post being edited
		* @uses		current_user_can()
		* @return	bool
		*/
		function user_can_edit( $post_id = 0 ) {

			// Basic check for "edit locked page" capability and "protect from all"
			$user_can = current_user_can( $this->options[$this->prefix.'capability'] ) && ! $this->options[$this->prefix.'protect_from_all'];

			/*
			 * Override (with user CAN edit) if:
			 * - We've got a post ID to work with
			 * - It's not a page, or scope for pages isn't for all, AND
			 * - The page isn't locked
			 */
			if (
				$post_id &&
				( get_post_type( $post_id ) != 'page' || $this->options[$this->prefix.'scope'] != "all" ) &&
				! $this->is_page_locked( $post_id )
			) {
				$user_can = true;
			}

			return $user_can;
		}

		/**
		 * Does edit check based on submitted post ID instead of passed ID
		 *
		 * @since	0.3
		 * @uses	$_POST
		 * @return	bool
		 */
		function user_can_edit_submitted_post() {
			return ( ! array_key_exists( 'post_ID', $_POST ) || $this->user_can_edit( $_POST['post_ID'] ) );
		}

		/**
		* Checks if a specified page is currently locked (irrespective of the scope setting).
		* @return	bool
		* @since	0.1
		*/
		function is_page_locked( $post_id ) {
			$page_is_locked = false;
			if ( $post_id ) {
				$page_is_locked	= in_array( $post_id, $this->options[$this->prefix.'locked_pages'] );
			}
			return $page_is_locked;
		}

		/**
		 * Checks if the current admin page is editing an item that is locked for the current user
		 *
		 * @return	bool
		 * @since	0.3
		 */
		function is_page_locked_for_current_user() {
			global $post;
			$screen = get_current_screen();
			return (
				$screen->base == 'post' &&
				( isset( $_GET['action'] ) && $_GET['action'] == 'edit' ) &&
				! $this->user_can_edit( $post->ID )
			);
		}

		/**
		 * Returns array of post types available for locking
		 *
		 * @since	0.3
		 * @return	array
		 */
		function get_lockable_post_types() {
			$lockable_post_types = $this->options[$this->prefix.'post_types'];
			if ( ! is_array( $lockable_post_types ) ) {
				$lockable_post_types = array();
			}
			array_push( $lockable_post_types, 'page' );
			return $lockable_post_types;
		}

		/**
		 * Checks if a post type is available for locking
		 *
		 * @param	string	$post_type
		 * @return	bool
		 * @since	0.3
		 */
		function is_post_type_lockable( $post_type ) {
			return in_array( $post_type, $this->get_lockable_post_types() );
		}

		/**
		* Load JavaScript and CSS
		*
		* @since	0.2
		* @uses	wp_enqueue_script() wp_enqueue_style()
		*/
		function load_js_css() {
			wp_enqueue_script( $this->prefix . '_js', plugins_url( 'lock-pages.js', __FILE__ ), array( 'jquery' ), '3.0' );
			wp_enqueue_style( $this->prefix . '_css', plugins_url( 'lock-pages.css', __FILE__ ), array(), '3.0' );
		}

		/**
		* Signal a locked page with a body class
		*
		* @since	0.2
		* @global	$post $pagenow
		*/
		function admin_body_class( $class ) {
			if ( $this->is_page_locked_for_current_user() ) {
				$class .= ' page-locked';
			}
   			return $class;
		}


		/**
		* Loads the plugin options from the database.
		* @return	array
		* @since	0.1
		* @uses		update_option()
		*/
		function load_options() {

			// Defaults
			$defaults = array(
				$this->prefix.'capability'			=> 'manage_options',
				$this->prefix.'protect_from_all'	=> false,
				$this->prefix.'scope'				=> 'locked',
				$this->prefix.'post_types'			=> array(),
				$this->prefix.'locked_pages'		=> array(),
			);
			$do_update = false;

			// Are the options present?
			if ( ! $the_options = get_option( $this->options_name ) ) {

				// Set to defaults
				$the_options = $defaults;
				$do_update = true;

			} else {

				/**
				 * Merge with defaults
				 * @since 0.3.1
				 */
				if ( count( $the_options ) < count( $defaults ) ) {
					$the_options = wp_parse_args( $the_options, $defaults );
					$do_update = true;
				}

				/**
				 * Convert locked pages list to array if necessary (used to be comma-delimited string)
				 * @since	0.3
				 */
				if ( ! is_array( $the_options[ $this->prefix . 'locked_pages' ] ) ) {
					$the_options[ $this->prefix . 'locked_pages' ] = explode( ',', $the_options[ $this->prefix . 'locked_pages' ] );
					$do_update = true;
				}

			}

			// Do update?
			if ( $do_update ) {
				update_option( $this->options_name, $the_options );
			}

			// Set options
			$this->options = $the_options;
		}

		/**
		* Saves the admin options to the database.
		* @since	0.1
		* @uses		update_option()
		*/
		function save_admin_options(){
			return update_option( $this->options_name, $this->options );
		}

		/**
		* @desc Adds the options subpanel
		* @since	0.1
		* @uses		add_options_page() add_filter()
		*/
		function admin_menu_link() {
			// If you change this from add_options_page, MAKE SURE you change the filter_plugin_actions function (below) to
			// reflect the page filename (ie - options-general.php) of the page your plugin is under!
			add_options_page( 'Lock Pages', 'Lock Pages', 'update_core', basename( __FILE__ ), array( &$this, 'admin_options_page' ) );
			add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'filter_plugin_actions'), 10, 2 );
		}

		/**
		* @desc Adds the Settings link to the plugin activate/deactivate page
		* @since	0.1
		*/
		function filter_plugin_actions($links, $file) {
			// If your plugin is under a different top-level menu than Settings (IE - you changed the function above to something other than add_options_page)
			// Then you're going to want to change options-general.php below to the name of your top-level page
			$settings_link = '<a href="options-general.php?page=' . basename(__FILE__) . '">' . __('Settings') . '</a>';
			array_unshift( $links, $settings_link ); // before other links

			return $links;
		}

		/**
		* Adds settings/options page
		* @since	0.1
		*/
		function admin_options_page() {
			$updated = false;

			if ( array_key_exists( 'SLT_LockPages_save', $_POST ) && $_POST['SLT_LockPages_save'] ) {
				if (! wp_verify_nonce($_POST['_wpnonce'], 'SLT_LockPages-update-options') )
					die( __( 'Whoops! There was a problem with the data you posted. Please go back and try again.', $this->localization_domain ) );

				$this->options[$this->prefix.'capability'] = $_POST[$this->prefix.'capability'];
				$this->options[$this->prefix.'protect_from_all'] = isset( $_POST[$this->prefix.'protect_from_all'] );
				$this->options[$this->prefix.'scope'] = $_POST[$this->prefix.'scope'];
				$this->options[$this->prefix.'post_types'] = empty( $_POST[$this->prefix.'post_types'] ) ? array() : $_POST[$this->prefix.'post_types'];
				$this->save_admin_options();
				$updated = true;

			}

			/**
			* @todo	Check against capabilities from roles that have active users
			*/
			// Need to check if the capability entered actually exists
			if ( function_exists( 'members_get_capabilities' ) ) {
				// Use Members plugin function
				$current_caps = members_get_capabilities();
			} else {
				// Just get capabilities from all current roles
				// Code based on Members plugin function members_get_role_capabilities()
				global $wp_roles;
				$current_caps = array();
				/* Loop through each role object because we need to get the caps. */
				foreach ( $wp_roles->role_objects as $key => $role ) {
					/* Roles without capabilities will cause an error,
					so we need to check if $role->capabilities is an array. */
					if ( is_array( $role->capabilities ) ) {
						/* Loop through the role's capabilities and add them to the $current_caps array. */
						foreach ( $role->capabilities as $cap => $grant ) {
							$current_caps[$cap] = $cap;
						}
					}
				}
			}

			// Set alert if necessary
			$cap_alert = null;
			if ( ! in_array( $this->options[$this->prefix.'capability'], $current_caps ) ) {
				$cap_alert = __( "Warning! The capability you have entered isn't currently granted to any roles in this installation.", $this->localization_domain );
			}

			?>
			<div class="wrap">

				<h1><?php _e( 'Lock Pages', $this->localization_domain ); ?></h1>

				<?php if ( $updated ) { ?>
					<div class="updated"><p><?php _e( 'Your changes were sucessfully saved.', $this->localization_domain ); ?></p></div>
				<?php } ?>

				<form method="post" id="SLT_LockPages_options">

					<?php

					wp_nonce_field('SLT_LockPages-update-options');

					if ( $cap_alert ) {
						echo '<div class="error"><p>' . $cap_alert . '</p></div>';
					}

					?>

					<table width="100%" cellspacing="2" cellpadding="5" class="form-table">

						<tr valign="top">
							<th width="33%" scope="row"><label for="<?php echo esc_attr( $this->prefix ); ?>capability"><?php _e( 'Capability needed to manage locking', $this->localization_domain ); ?></label></th>
							<td><input name="<?php echo esc_attr( $this->prefix ); ?>capability" type="text" id="<?php echo esc_attr( $this->prefix ); ?>capability" size="45" value="<?php echo esc_attr( $this->options[$this->prefix.'capability'] ); ?>"/></td>
						</tr>

						<tr valign="top">
							<th width="33%" scope="row"><label for="<?php echo esc_attr( $this->prefix ); ?>protect_from_all"><?php _e( 'Protect locked posts even from users with locking capability', $this->localization_domain ); ?></label></th>
							<td style="vertical-align: top;"><input name="<?php echo esc_attr( $this->prefix ); ?>protect_from_all" type="checkbox" id="<?php echo esc_attr( $this->prefix ); ?>protect_from_all" value="1" <?php checked( $this->options[$this->prefix.'protect_from_all'] ); ?>></td>
						</tr>

						<tr valign="top">
							<th width="33%" scope="row"><?php _e( 'Scope for locking pages', $this->localization_domain ); ?></th>
							<td>
								<input name="<?php echo esc_attr( $this->prefix ); ?>scope" type="radio" id="<?php echo esc_attr( $this->prefix ); ?>scope_locked" value="locked"<?php if ( $this->options[$this->prefix.'scope']=="locked" ) echo ' checked="checked"'; ?> /> <label for="<?php echo esc_attr( $this->prefix ); ?>scope_locked"><?php _e( 'Lock only specified pages', $this->localization_domain ); ?></label><br />
								<input name="<?php echo esc_attr( $this->prefix ); ?>scope" type="radio" id="<?php echo esc_attr( $this->prefix ); ?>scope_locked" value="all"<?php if ( $this->options[$this->prefix.'scope']=="all" ) echo ' checked="checked"'; ?> /> <label for="<?php echo esc_attr( $this->prefix ); ?>scope_all"><?php _e( 'Lock all pages', $this->localization_domain ); ?></label>
							</td>
						</tr>

						<tr valign="top">
							<th width="33%" scope="row"><?php _e( 'Other post types available for locking', $this->localization_domain ); ?></th>
							<td>
								<?php
								$post_types = get_post_types( array( 'public' => true ) );
								unset( $post_types['attachment'] );
								unset( $post_types['page'] );
								foreach ( $post_types as $post_type ) {
									echo '<div><label for="' . esc_attr( $this->prefix ) . 'post_types_' . $post_type . '"><input type="checkbox" name="' . esc_attr( $this->prefix ) . 'post_types[]" id="' . esc_attr( $this->prefix ) . 'post_types_' . $post_type . '" value="' . $post_type . '" ' . checked( is_array( $this->options[$this->prefix.'post_types'] ) && in_array( $post_type, $this->options[$this->prefix.'post_types'] ), true, false ) . '> ' . $post_type . '</label></div>';
								}
								?>
							</td>
						</tr>

					</table>

					<p class="submit"><input type="submit" value="<?php _e( 'Save Changes', $this->localization_domain ); ?>" class="button-primary" name="SLT_LockPages_save" /></p>

				</form>

			<?php
		}


	} // End Class

} // End if class exists statement

// Instantiate the class
if ( class_exists('SLT_LockPages') ) {
	$SLT_LockPages_var = new SLT_LockPages();
}
