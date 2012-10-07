<?php
/*
Plugin Name: Lock Pages
Plugin URI: http://wordpress.org/extend/plugins/lock-pages/
Description: Allows admins to lock pages in order to prevent breakage of important URLs.
Author: Steve Taylor
Version: 0.2.2
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
		* PHP 4 Compatible Constructor
		*/
		function SLT_LockPages() { $this->__construct(); }

		/**
		* PHP 5 Constructor
		*/
		function __construct() {

			// Language Setup
			$locale = get_locale();
			$mo = dirname( __FILE__ ) . "/languages/" . $this->localization_domain . "-" . $locale . ".mo";
			load_textdomain( $this->localization_domain, $mo );

			// Initialize the options
			// This is REQUIRED to initialize the options when the plugin is loaded!
			$this->get_options();

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

			// Changes to the page screens
			add_action( 'edit_page_form', array( &$this, 'old_value_fields' ) );
			add_action( 'admin_notices', array( &$this, 'output_page_locked_notice' ) );
			add_action( 'add_meta_boxes', array( &$this, 'remove_slug_meta_box' ) );
			add_action( 'load-post.php', array( &$this, 'load_js_css' ) );
			add_action( 'load-edit.php', array( &$this, 'load_js_css' ) );
			add_filter( 'page_row_actions', array( &$this, 'remove_page_row_actions' ), 10, 2 );
			add_filter( 'get_sample_permalink_html', array( &$this, 'remove_edit_permalink' ), 10, 2 );
			add_filter( 'wp_dropdown_pages', array( &$this, 'remove_parent_selection' ) );
			add_filter( 'admin_body_class', array( &$this, 'admin_body_class' ) );

			// These we only need if the scope is set to only lock specified pages
			if ( $this->options[$this->prefix.'scope'] != "all" ) {
				add_action( 'add_meta_boxes', array( &$this, 'create_meta_box' ) );
				add_action( 'save_post', array( &$this, 'save_meta' ), 1, 2 );
				add_filter( 'manage_pages_columns', array( &$this, 'pages_list_col' ) );
				add_action( 'manage_pages_custom_column', array( &$this, 'pages_list_col_value' ), 10, 2 );
			}

			// Filters to block saving
			add_filter( 'name_save_pre', array( &$this, 'lock_slug' ), 0 );
			add_filter( 'parent_save_pre', array( &$this, 'lock_parent' ), 0 );
			add_filter( 'page_template_pre', array( &$this, 'lock_template' ), 0 );
			add_filter( 'status_save_pre', array( &$this, 'lock_status' ), 0 );
			add_filter( 'password_save_pre', array( &$this, 'lock_password' ), 0 );
			add_filter( 'user_has_cap', array( &$this, 'lock_deletion' ), 0, 3 );

		}

		/**
		* Remove parent selection.
		*
		* @since	0.2
		* @param	string	$output	The wp_dropdown_pages output
		* @global	$post
		* @return	string
		*/
		function remove_parent_selection( $output ) {
			global $post;
			if ( ! $this->user_can_edit( $post->ID ) )
				$output = '';
			return $output;
		}

		/**
		* Remove slug meta box.
		*
		* @since	0.2
		* @global	$post
		* @uses		remove_meta_box()
		*/
		function remove_slug_meta_box() {
			global $post;
			if ( ! $this->user_can_edit( $post->ID ) )
				remove_meta_box( 'slugdiv', 'page', 'normal' );
		}

		/**
		* Remove edit permalink functionality from permalink HTML.
		*
		* @since	0.2
		* @param	string	$return		The edit permalink HTML to return
		* @param	int		$id			The ID of the post being edited
		* @return	string
		*/
		function remove_edit_permalink( $return, $id ) {
			if ( ! $this->user_can_edit( $id ) ) {
				$element_start = strpos( $return, '<span id="edit-slug-buttons"' );
				$element_end = strpos( $return, '</span>', $element_start ) + 8;
				$return = substr_replace( $return, '', $element_start, $element_end - $element_start );
				$return = preg_replace( '#<span id="editable-post-name"[^>]*>([^<]+)</span>#', '$1', $return );
			}
			return $return;
		}

		/**
		* Remove certain page actions from locked pages.
		*
		* @since	0.1.5
		* @param	array		$actions		The page row actions
		* @param	object		$page			The page being listed
		* @return	array
		*/
		function remove_page_row_actions( $actions, $page ) {
			if ( ! $this->user_can_edit( $page->ID ) ) {
				foreach ( array( 'inline', 'inline hide-if-no-js', 'trash' ) as $action_key ) {
					if ( array_key_exists( $action_key, $actions ) )
						unset ( $actions[ $action_key ] );
				}
			}
			return $actions;
		}

		/**
		* Add lock column to admin pages list.
		*
		* @since	0.1.2
		* @param	array		$cols		The columns
		* @return	array
		*/
		function pages_list_col( $cols ) {
			$cols["page-locked"] = "Lock";
			return $cols;
		}

		/**
		* Add lock indicator to admin pages list.
		*
		* @since		0.1.2
		* @param		string		$column_name		The column name
		* @param		int			$id					Page ID
		*/
		function pages_list_col_value( $column_name, $id ) {
			if ( $column_name == "page-locked" )
				echo $this->is_page_locked( $id ) ? '<img src="' . plugins_url( 'lock.png', __FILE__ ) . '" width="16" height="16" alt="Locked" />' : '&nbsp;';
		}

		/**
		* Prevents unauthorized users changing a page's password protection.
		*
		* @since	0.2
		* @return	string
		*/
		function lock_password( $password ) {
			// Can user edit this page?
			if ( ! array_key_exists( 'post_ID', $_POST ) || $this->user_can_edit( $_POST['post_ID'] ) ) {
				return $password;
			} else {
				// Keep old password, user can't change it
				return $_POST[$this->prefix.'old_password'];
			}
		}

		/**
		* Prevents unauthorized users changing a page's status.
		*
		* @since	0.2
		* @return	string
		*/
		function lock_template( $template ) {
			// Can user edit this page?
			if ( ! array_key_exists( 'post_ID', $_POST ) || $this->user_can_edit( $_POST['post_ID'] ) ) {
				return $template;
			} else {
				// Keep old status, user can't change it
				return $_POST[$this->prefix.'old_page_template'];
			}
		}

		/**
		* Prevents unauthorized users changing a page's status.
		*
		* @since	0.2
		* @return	string
		*/
		function lock_status( $status ) {
			// Can user edit this page?
			if ( ! array_key_exists( 'post_ID', $_POST ) || $this->user_can_edit( $_POST['post_ID'] ) ) {
				return $status;
			} else {
				// Keep old status, user can't change it
				return $_POST[$this->prefix.'old_status'];
			}
		}

		/**
		* Prevents unauthorized users saving a new slug.
		*
		* @since	0.1
		* @return	string
		*/
		function lock_slug( $slug ) {
			// Can user edit this page?
			if ( ! array_key_exists( 'post_ID', $_POST ) || $this->user_can_edit( $_POST['post_ID'] ) ) {
				return $slug;
			} else {
				// Keep old slug, user can't change it
				return $_POST[$this->prefix.'old_slug'];
			}
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
				if ( ! array_key_exists( 'post_ID', $_POST ) || $this->user_can_edit( $_POST['post_ID'] ) ) {
					return $parent;
				} else {
					// Keep old parent, user can't change it
					return $_POST[$this->prefix.'old_parent'];
				}
			} else {
				return $parent;
			}
		}

		/**
		* Prevents unauthorized users deleting a locked page.
		*
		* @since	0.1.1
		* @param	array		$allcaps		Capabilities granted to user
		* @param	array		$caps			Capabilities being checked
		* @param	array		$args			Optional arguments being passed
		* @global	$post
		* @return	array
		*/
		function lock_deletion( $allcaps, $caps, $args ) {
			global $post;
			$cap_check = count( $args ) ? $args[0] : '';
			$user_id = count( $args ) > 1 ? $args[1] : 0;
			$post_id = count( $args ) > 2 ? $args[2] : 0;
			// Is the check for deleting a page?
			if ( ( $cap_check == "delete_page" || $cap_check == "delete_post" ) && $post_id && is_object( $post ) && property_exists( $post, 'post_type' ) && $post->post_type == "page" ) {
				// Basic check for "edit locked page" capability
				$user_can = array_key_exists( $this->options[$this->prefix.'capability'], $allcaps ) && $allcaps[ $this->options[$this->prefix.'capability'] ];
				// Override it if page isn't locked and scope isn't all pages
				if ( $this->options[$this->prefix.'scope'] != "all" && ! $this->is_page_locked( $post_id ) )
					$user_can = true;
				// If user isn't able to touch this page, remove delete capabilities
				if ( ! $user_can ) {
					foreach( $allcaps as $cap => $value ) {
						if ( strpos( $cap, "delete_" ) !== false && ( strpos( $cap, "pages" ) !== false || strpos( $cap, "posts" ) !== false ) )
							unset( $allcaps[$cap] );
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
		function old_value_fields() {
			global $post;
			echo '<input type="hidden" name="' . esc_attr( $this->prefix ) . 'old_password" value="' . esc_attr( $post->post_password ) . '" />';
			echo '<input type="hidden" name="' . esc_attr( $this->prefix ) . 'old_status" value="' . esc_attr( $post->post_status ) . '" />';
			echo '<input type="hidden" name="' . esc_attr( $this->prefix ) . 'old_slug" value="' . esc_attr( $post->post_name ) . '" />';
			echo '<input type="hidden" name="' . esc_attr( $this->prefix ) . 'old_parent" value="' . esc_attr( $post->post_parent ) . '" />';
			echo '<input type="hidden" name="' . esc_attr( $this->prefix ) . 'old_page_template" value="' . esc_attr( $post->page_template ) . '" />';
		}

		/**
		* Outputs warning to users who won't be able to change page elements when editing.
		*
		* @since	0.1
		* @global	$post $pagenow
		*/
		function output_page_locked_notice() {
			global $post, $pagenow;
			if (
				$pagenow == 'post.php' &&
				$_GET["action"] == "edit" &&
				! $this->user_can_edit( $post->ID )
			)
				echo '<div class="updated page-locked-notice"><p>' . __( 'Please note that this page is currently locked.', $this->localization_domain ) . '</p></div>';
		}


		/**
 		* Adds the meta box to the page edit screen if the current scope isn't to lock all pages.
 		* Only outputs box for users who have the capability to edit locked pages.
 		*
 		* @since	0.1
 		* @uses		add_meta_box() Creates an additional meta box.
 		*/
		function create_meta_box() {
			if ( current_user_can( $this->options[$this->prefix.'capability'] ) )
				add_meta_box( $this->prefix.'_meta-box', 'Page locking', array( &$this, 'output_meta_box' ), 'page', 'side', 'high' );
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
					<?php _e( 'Lock this page', $this->localization_domain ); ?>
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
			- Users who can't change locked pages
			- Users who can't edit pages
			- Revisions, autoupdates, quick edits, posts etc.
			- Simple Page Ordering plugin
			*/
			if (
				( ! current_user_can( $this->options[$this->prefix.'capability'] ) ) ||
				( ! current_user_can( 'edit_pages', $post_id ) ) ||
				( $post->post_type != 'page' ) ||
				isset( $_POST["_inline_edit"] ) ||
				( isset( $_REQUEST["action"] ) && $_REQUEST["action"] == 'simple_page_ordering'  )
			)
				return;

			// Get list of locked pages
			$locked_pages = $this->options[$this->prefix.'locked_pages'];
			$locked_pages = explode( ',', $locked_pages );
			$update = false;

			if ( isset( $_POST[$this->prefix.'locked'] ) && $_POST[$this->prefix.'locked'] ) {
				// Box was checked, make sure page is added to list of locked pages
				if ( ! in_array( $post_id, $locked_pages ) ) {
					$locked_pages[] = $post_id;
					$update = true;
				}
			} else {
				// Box not checked, make sure page isn't in list of locked pages
				$id_pos = array_search( $post_id, $locked_pages );
				if ( $id_pos !== false ) {
					unset( $locked_pages[$id_pos] );
					$update = true;
				}
			}

			// Need to update?
			if ( $update ) {
				$locked_pages = implode( ',', $locked_pages );
				$this->options[$this->prefix.'locked_pages'] = $locked_pages;
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
			// Basic check for "edit locked page" capability
			$user_can = current_user_can( $this->options[$this->prefix.'capability'] );
			// Override it if page isn't locked, a specific page is being edited, and scope isn't all pages
			if ( $this->options[$this->prefix.'scope'] != "all" && $post_id && ! $this->is_page_locked( $post_id ) )
				$user_can = true;
			return $user_can;
		}

		/**
		* Checks if a specified page is currently locked (irrespective of the scope setting).
		* @return	bool
		* @since	0.1
		*/
		function is_page_locked( $post_id ) {
			if ( $post_id ) {
				$locked_pages = $this->options[$this->prefix.'locked_pages'];
				$locked_pages = explode( ',', $locked_pages );
				return in_array( $post_id, $locked_pages );
			} else {
				return false;
			}
		}

		/**
		* Load JavaScript and CSS
		*
		* @since	0.2
		* @uses	wp_enqueue_script() wp_enqueue_style()
		*/
		function load_js_css() {
			wp_enqueue_script( $this->prefix . '_js', plugins_url( 'lock-pages.js', __FILE__ ), array( 'jquery' ) );
			wp_enqueue_style( $this->prefix . '_css', plugins_url( 'lock-pages.css', __FILE__ ) );
		}

		/**
		* Signal a locked page with a body class
		*
		* @since	0.2
		* @global	$post $pagenow
		*/
		function admin_body_class( $class ) {
			global $post, $pagenow;
			if (
				$pagenow == 'post.php' &&
				$_GET["action"] == "edit" &&
				! $this->user_can_edit( $post->ID )
			)
				$class .= ' page-locked';
   			return $class;
		}


		/**
		* Retrieves the plugin options from the database.
		* @return	array
		* @since	0.1
		* @uses		update_option()
		*/
		function get_options() {
			// Don't forget to set up the default options
			if ( ! $the_options = get_option( $this->options_name ) ) {
				$the_options = array(
					$this->prefix.'capability' => 'manage_options',
					$this->prefix.'scope' => 'locked',
					$this->prefix.'locked_pages' => ''
				);
				update_option($this->options_name, $the_options);
			}
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

			if ( array_key_exists( 'SLT_LockPages_save', $_POST ) && $_POST['SLT_LockPages_save'] ) {
				if (! wp_verify_nonce($_POST['_wpnonce'], 'SLT_LockPages-update-options') )
					die( __( 'Whoops! There was a problem with the data you posted. Please go back and try again.', $this->localization_domain ) );

				$this->options[$this->prefix.'capability'] = $_POST[$this->prefix.'capability'];
				$this->options[$this->prefix.'scope'] = $_POST[$this->prefix.'scope'];
				$this->save_admin_options();

				echo '<div class="updated"><p>'.__( 'Your changes were sucessfully saved.', $this->localization_domain ).'</p></div>';
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
						foreach ( $role->capabilities as $cap => $grant )
							$current_caps[$cap] = $cap;
					}
				}
			}

			// Set alert if necessary
			$cap_alert = "";
			if ( ! in_array( $this->options[$this->prefix.'capability'], $current_caps ) )
				$cap_alert = __( "Warning! The capability you have entered isn't currently granted to any roles in this installation.", $this->localization_domain );

			?>
			<div class="wrap">
				<h2><?php _e( 'Lock Pages', $this->localization_domain ); ?></h2>
				<form method="post" id="SLT_LockPages_options">
					<?php wp_nonce_field('SLT_LockPages-update-options'); ?>
					<table width="100%" cellspacing="2" cellpadding="5" class="form-table">
						<?php
						if ( $cap_alert )
							echo '<div class="error"><p>' . $cap_alert . '</p></div>';
						?>
						<tr valign="top">
							<th width="33%" scope="row"><label for="<?php echo esc_attr( $this->prefix ); ?>capability"><?php _e( 'WP capability needed to edit locked page elements', $this->localization_domain ); ?></label></th>
							<td><input name="<?php echo esc_attr( $this->prefix ); ?>capability" type="text" id="<?php echo esc_attr( $this->prefix ); ?>capability" size="45" value="<?php echo esc_attr( $this->options[$this->prefix.'capability'] ); ?>"/></td>
						</tr>
						<tr valign="top">
							<th width="33%" scope="row"><?php _e( 'Scope for locking', $this->localization_domain ); ?></th>
							<td>
								<input name="<?php echo esc_attr( $this->prefix ); ?>scope" type="radio" id="<?php echo esc_attr( $this->prefix ); ?>scope_locked" value="locked"<?php if ( $this->options[$this->prefix.'scope']=="locked" ) echo ' checked="checked"'; ?> /> <label for="<?php echo esc_attr( $this->prefix ); ?>scope_locked"><?php _e( 'Lock only specified pages', $this->localization_domain ); ?></label><br />
								<input name="<?php echo esc_attr( $this->prefix ); ?>scope" type="radio" id="<?php echo esc_attr( $this->prefix ); ?>scope_locked" value="all"<?php if ( $this->options[$this->prefix.'scope']=="all" ) echo ' checked="checked"'; ?> /> <label for="<?php echo esc_attr( $this->prefix ); ?>scope_all"><?php _e( 'Lock all pages', $this->localization_domain ); ?></label>
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

?>