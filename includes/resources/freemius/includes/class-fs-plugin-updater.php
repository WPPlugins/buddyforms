<?php
/**
 * @package     Freemius
 * @copyright   Copyright (c) 2015, Freemius, Inc.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.4
 *
 * @link        https://github.com/easydigitaldownloads/EDD-License-handler/blob/master/EDD_SL_Plugin_Updater.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Uncomment this line for testing.
//	set_site_transient( 'update_plugins', null );

class FS_Plugin_Updater {

	/**
	 * @var Freemius
	 * @since 1.0.4
	 */
	private $_fs;
	/**
	 * @var FS_Logger
	 * @since 1.0.4
	 */
	private $_logger;
	/**
	 * @var object
	 * @since 1.1.8.1
	 */
	private $_update_details;

	function __construct( Freemius $freemius ) {
		$this->_fs = $freemius;

		$this->_logger = FS_Logger::get_logger( WP_FS__SLUG . '_' . $freemius->get_slug() . '_updater', WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

		$this->_filters();
	}

	/**
	 * Initiate required filters.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.0.4
	 */
	private function _filters() {
		// Override request for plugin information
		add_filter( 'plugins_api', array( &$this, 'plugins_api_filter' ), 10, 3 );

		// WP 3.0+
		add_filter( 'pre_set_site_transient_update_plugins', array(
			&$this,
			'pre_set_site_transient_update_plugins_filter'
		) );

		if ( ! $this->_fs->has_active_valid_license() ) {
			/**
			 * If user has the premium plugin's code but do NOT have an active license,
			 * encourage him to upgrade by showing that there's a new release, but instead
			 * of showing an update link, show upgrade link to the pricing page.
			 *
			 * @since 1.1.6
			 *
			 */
			// WP 2.9+
			add_action( "after_plugin_row_{$this->_fs->get_plugin_basename()}", array(
				&$this,
				'catch_plugin_update_row'
			), 9 );
			add_action( "after_plugin_row_{$this->_fs->get_plugin_basename()}", array(
				&$this,
				'edit_and_echo_plugin_update_row'
			), 11, 2 );
		}

		if ( ! WP_FS__IS_PRODUCTION_MODE ) {
			add_filter( 'http_request_host_is_external', array(
				$this,
				'http_request_host_is_external_filter'
			), 10, 3 );
		}

		if ( $this->_fs->is_premium() && $this->is_correct_folder_name() ) {
			add_filter( 'upgrader_post_install', array( &$this, '_maybe_update_folder_name' ), 10, 3 );
		}
	}

	/**
	 * Checks if a given basename has a matching folder name
	 * with the current context plugin.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.2.1.6
	 *
	 * @param string $basename Current plugin's basename.
	 *
	 * @return bool
	 */
	private function is_correct_folder_name( $basename = '' ) {
		if ( empty( $basename ) ) {
			$basename = $this->_fs->get_plugin_basename();
		}

		return ( $this->_fs->get_target_folder_name() != trim( dirname( $basename ), '/\\' ) );
	}

	/**
	 * Capture plugin update row by turning output buffering.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.1.6
	 */
	function catch_plugin_update_row() {
		ob_start();
	}

	/**
	 * Overrides default update message format with "renew your license" message.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.1.6
	 *
	 * @param string $file
	 * @param array $plugin_data
	 */
	function edit_and_echo_plugin_update_row( $file, $plugin_data ) {
		$plugin_update_row = ob_get_clean();

		$current = get_site_transient( 'update_plugins' );
		if ( ! isset( $current->response[ $file ] ) ) {
			echo $plugin_update_row;

			return;
		}

		$r = $current->response[ $file ];

		$plugin_update_row = preg_replace(
			'/(\<div.+>)(.+)(\<a.+\<a.+)\<\/div\>/is',
			'$1 $2 ' . sprintf(
				$this->_fs->get_text( 'renew-license-now' ),
				'<a href="' . $this->_fs->pricing_url() . '">', '</a>',
				$r->new_version ) .
			'$4',
			$plugin_update_row
		);

		echo $plugin_update_row;
	}

	/**
	 * Since WP version 3.6, a new security feature was added that denies access to repository with a local ip.
	 * During development mode we want to be able updating plugin versions via our localhost repository. This
	 * filter white-list all domains including "api.freemius".
	 *
	 * @link   http://www.emanueletessore.com/wordpress-download-failed-valid-url-provided/
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.0.4
	 *
	 * @param bool $allow
	 * @param string $host
	 * @param string $url
	 *
	 * @return bool
	 */
	function http_request_host_is_external_filter( $allow, $host, $url ) {
		return ( false !== strpos( $host, 'freemius' ) ) ? true : $allow;
	}

	/**
	 * Check for Updates at the defined API endpoint and modify the update array.
	 *
	 * This function dives into the update api just when WordPress creates its update array,
	 * then adds a custom API call and injects the custom plugin data retrieved from the API.
	 * It is reassembled from parts of the native WordPress plugin update code.
	 * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.0.4
	 *
	 * @uses   FS_Api
	 *
	 * @param object $transient_data Update array build by WordPress.
	 *
	 * @return object Modified update array with custom plugin data.
	 */
	function pre_set_site_transient_update_plugins_filter( $transient_data ) {
		$this->_logger->entrance();

		if ( empty( $transient_data ) ||
		     defined( 'WP_FS__UNINSTALL_MODE' )
		) {
			return $transient_data;
		}

		if ( ! isset( $this->_update_details ) ) {
			// Get plugin's newest update.
			$new_version = $this->_fs->get_update( false, false );

			$this->_update_details = false;

			if ( is_object( $new_version ) ) {
				$this->_logger->log( 'Found newer plugin version ' . $new_version->version );

				$plugin_details              = new stdClass();
				$plugin_details->slug        = $this->_fs->get_slug();
				$plugin_details->new_version = $new_version->version;
				$plugin_details->url         = WP_FS__ADDRESS;
				$plugin_details->package     = $new_version->url;
				$plugin_details->plugin      = $this->_fs->get_plugin_basename();

				/**
				 * Cache plugin details locally since set_site_transient( 'update_plugins' )
				 * called multiple times and the non wp.org plugins are filtered after the
				 * call to .org.
				 *
				 * @since 1.1.8.1
				 */
				$this->_update_details = $plugin_details;
			}
		}

		if ( is_object( $this->_update_details ) ) {
			// Add plugin to transient data.
			$transient_data->response[ $this->_fs->get_plugin_basename() ] = $this->_update_details;
		}

		return $transient_data;
	}

	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.0.4
	 *
	 * @uses   FS_Api
	 *
	 * @param object $data
	 * @param string $action
	 * @param mixed $args
	 *
	 * @return object
	 */
	function plugins_api_filter( $data, $action = '', $args = null ) {
		$this->_logger->entrance();

		if ( ( 'plugin_information' !== $action ) ||
		     ! isset( $args->slug )
		) {
			return $data;
		}

		$addon    = false;
		$is_addon = false;

		if ( $this->_fs->get_slug() !== $args->slug ) {
			$addon = $this->_fs->get_addon_by_slug( $args->slug );

			if ( ! is_object( $addon ) ) {
				return $data;
			}

			$is_addon = true;
		}

		$plugin_in_repo = false;
		if ( ! $is_addon ) {
			// Try to fetch info from .org repository.
			$data = self::_fetch_plugin_info_from_repository( $action, $args );

			$plugin_in_repo = ( false !== $data );
		}

		if ( ! $plugin_in_repo ) {
			$data = $args;

			// Fetch as much as possible info from local files.
			$plugin_local_data = $this->_fs->get_plugin_data();
			$data->name        = $plugin_local_data['Name'];
			$data->author      = $plugin_local_data['Author'];
			$data->sections    = array(
				'description' => 'Upgrade ' . $plugin_local_data['Name'] . ' to latest.',
			);

			// @todo Store extra plugin info on Freemius or parse readme.txt markup.
			/*$info = $this->_fs->get_api_site_scope()->call('/information.json');

if ( !isset($info->error) ) {
$data = $info;
}*/
		}

		// Get plugin's newest update.
		$new_version = $this->get_latest_download_details( $is_addon ? $addon->id : false );

		if ( ! is_object( $new_version ) || empty( $new_version->version ) ) {
			$data->version = $this->_fs->get_plugin_version();
		} else {
			if ( $is_addon ) {
				$data->name    = $addon->title . ' ' . $this->_fs->get_text( 'addon' );
				$data->slug    = $addon->slug;
				$data->url     = WP_FS__ADDRESS;
				$data->package = $new_version->url;
			}

			if ( ! $plugin_in_repo ) {
				$data->last_updated = ! is_null( $new_version->updated ) ? $new_version->updated : $new_version->created;
				$data->requires     = $new_version->requires_platform_version;
				$data->tested       = $new_version->tested_up_to_version;
			}

			$data->version       = $new_version->version;
			$data->download_link = $new_version->url;
		}

		return $data;
	}

	/**
	 * Try to fetch plugin's info from .org repository.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.0.5
	 *
	 * @param string $action
	 * @param object $args
	 *
	 * @return bool|mixed
	 */
	static function _fetch_plugin_info_from_repository( $action, $args ) {
		$url = $http_url = 'http://api.wordpress.org/plugins/info/1.0/';
		if ( $ssl = wp_http_supports( array( 'ssl' ) ) ) {
			$url = set_url_scheme( $url, 'https' );
		}

		$args = array(
			'timeout' => 15,
			'body'    => array(
				'action'  => $action,
				'request' => serialize( $args )
			)
		);

		$request = wp_remote_post( $url, $args );

		if ( is_wp_error( $request ) ) {
			return false;
		}

		$res = maybe_unserialize( wp_remote_retrieve_body( $request ) );

		if ( ! is_object( $res ) && ! is_array( $res ) ) {
			return false;
		}

		return $res;
	}

	/**
	 * @author Vova Feldman (@svovaf)
	 * @since  1.2.1.7
	 *
	 * @param number|bool $addon_id
	 *
	 * @return object
	 */
	private function get_latest_download_details( $addon_id = false ) {
		return $this->_fs->_fetch_latest_version( $addon_id );
	}

	/**
	 * This is a special after upgrade handler for migrating modules
	 * that didn't use the '-premium' suffix folder structure before
	 * the migration.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.2.1.6
	 *
	 * @param bool $response Install response.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 * @param array $result Installation result data.
	 *
	 * @return bool
	 */
	function _maybe_update_folder_name( $response, $hook_extra, $result ) {
		$basename = $this->_fs->get_plugin_basename();

		if ( true !== $response ||
		     empty( $hook_extra ) ||
		     empty( $hook_extra['plugin'] ) ||
		     $basename !== $hook_extra['plugin']
		) {
			return $response;
		}

		$active_plugins_basenames = get_option( 'active_plugins' );

		for ( $i = 0, $len = count( $active_plugins_basenames ); $i < $len; $i ++ ) {
			if ( $basename === $active_plugins_basenames[ $i ] ) {
				// Get filename including extension.
				$filename = basename( $basename );

				$new_basename = plugin_basename(
					trailingslashit( $this->_fs->get_slug() . ( $this->_fs->is_premium() ? '-premium' : '' ) ) .
					$filename
				);

				// Verify that the expected correct path exists.
				if ( file_exists( fs_normalize_path( WP_PLUGIN_DIR . '/' . $new_basename ) ) ) {
					// Override active plugin name.
					$active_plugins_basenames[ $i ] = $new_basename;
					update_option( 'active_plugins', $active_plugins_basenames );
				}

				break;
			}
		}

		return $response;
	}

	#----------------------------------------------------------------------------------
	#region Auto Activation
	#----------------------------------------------------------------------------------

	/**
	 * Installs and active a plugin when explicitly requested that from a 3rd party service.
	 *
	 * This logic was inspired by the TGMPA GPL licensed library by Thomas Griffin.
	 *
	 * @link   http://tgmpluginactivation.com/
	 *
	 * @author Vova Feldman
	 * @since  1.2.1.7
	 *
	 * @link   https://make.wordpress.org/plugins/2017/03/16/clarification-of-guideline-8-executable-code-and-installs/
	 *
	 * @uses   WP_Filesystem
	 * @uses   WP_Error
	 * @uses   WP_Upgrader
	 * @uses   Plugin_Upgrader
	 * @uses   Plugin_Installer_Skin
	 * @uses   Plugin_Upgrader_Skin
	 *
	 * @param number|bool $plugin_id
	 *
	 * @return array
	 */
	function install_and_activate_plugin( $plugin_id = false ) {
		if ( ! empty( $plugin_id ) && ! FS_Plugin::is_valid_id( $plugin_id ) ) {
			// Invalid plugin ID.
			return array(
				'message' => $this->_fs->get_text( 'auto-install-error-invalid-id' ),
				'code'    => 'invalid_module_id',
			);
		}

		$is_addon = false;
		if ( FS_Plugin::is_valid_id( $plugin_id ) &&
		     $plugin_id != $this->_fs->get_id()
		) {
			$addon = $this->_fs->get_addon( $plugin_id );

			if ( ! is_object( $addon ) ) {
				// Invalid add-on ID.
				return array(
					'message' => $this->_fs->get_text( 'auto-install-error-invalid-id' ),
					'code'    => 'invalid_module_id',
				);
			}

			$slug  = $addon->slug;
			$title = $addon->title . ' ' . $this->_fs->get_text( 'addon' );

			$is_addon = true;
		} else {
			$slug  = $this->_fs->get_slug();
			$title = $this->_fs->get_plugin_title() .
			         ( $this->_fs->is_addon() ? ' ' . $this->_fs->get_text( 'addon' ) : '' );
		}

		if ( $this->is_premium_plugin_active( $plugin_id ) ) {
			// Premium version already activated.
			return array(
				'message' => $this->_fs->get_text(
					$is_addon ?
						'auto-install-error-premium-addon-activated' :
						'auto-install-error-premium-activated'
				),
				'code'    => 'premium_installed',
			);
		}

		$latest_version = $this->get_latest_download_details( $plugin_id );
		$target_folder  = "{$slug}-premium";

		// Prep variables for Plugin_Installer_Skin class.
		$extra         = array();
		$extra['slug'] = $target_folder;
		$source        = $latest_version->url;
		$api           = null;

		$install_url = add_query_arg(
			array(
				'action' => 'install-plugin',
				'plugin' => urlencode( $slug ),
			),
			'update.php'
		);

		if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
			// Include required resources for the installation.
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$skin_args = array(
			'type'   => 'web',
			'title'  => sprintf( fs_text( 'installing-plugin-x', $slug ), $title ),
			'url'    => esc_url_raw( $install_url ),
			'nonce'  => 'install-plugin_' . $slug,
			'plugin' => '',
			'api'    => $api,
			'extra'  => $extra,
		);

//			$skin = new Automatic_Upgrader_Skin( $skin_args );
//			$skin = new Plugin_Installer_Skin( $skin_args );
		$skin = new WP_Ajax_Upgrader_Skin( $skin_args );

		// Create a new instance of Plugin_Upgrader.
		$upgrader = new Plugin_Upgrader( $skin );

		// Perform the action and install the plugin from the $source urldecode().
		add_filter( 'upgrader_source_selection', array( &$this, '_maybe_adjust_source_dir' ), 1, 3 );

		$install_result = $upgrader->install( $source );

		remove_filter( 'upgrader_source_selection', array( &$this, '_maybe_adjust_source_dir' ), 1 );

		if ( is_wp_error( $install_result ) ) {
			return array(
				'message' => $install_result->get_error_message(),
				'code'    => $install_result->get_error_code(),
			);
		} elseif ( is_wp_error( $skin->result ) ) {
			return array(
				'message' => $skin->result->get_error_message(),
				'code'    => $skin->result->get_error_code(),
			);
		} elseif ( $skin->get_errors()->get_error_code() ) {
			return array(
				'message' => $skin->get_error_messages(),
				'code'    => 'unknown',
			);
		} elseif ( is_null( $install_result ) ) {
			global $wp_filesystem;

			$error_code    = 'unable_to_connect_to_filesystem';
			$error_message = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );

			// Pass through the error from WP_Filesystem if one was raised.
			if ( $wp_filesystem instanceof WP_Filesystem_Base &&
			     is_wp_error( $wp_filesystem->errors ) &&
			     $wp_filesystem->errors->get_error_code()
			) {
				$error_message = $wp_filesystem->errors->get_error_message();
			}

			return array(
				'message' => $error_message,
				'code'    => $error_code,
			);
		}

		// Grab the full path to the main plugin's file.
		$plugin_activate = $upgrader->plugin_info();

		// Try to activate the plugin.
		$activation_result = $this->try_activate_plugin( $plugin_activate );

		if ( is_wp_error( $activation_result ) ) {
			return array(
				'message' => $activation_result->get_error_message(),
				'code'    => $activation_result->get_error_code(),
			);
		}

		return $skin->get_upgrade_messages();
	}

	/**
	 * Check if a premium module version is already active.
	 *
	 * @author Vova Feldman
	 * @since  1.2.1.7
	 *
	 * @param number|bool $plugin_id
	 *
	 * @return bool
	 */
	private function is_premium_plugin_active( $plugin_id = false ) {
		if ( $plugin_id != $this->_fs->get_id() ) {
			return $this->_fs->is_addon_activated( $plugin_id, true );
		}

		return is_plugin_active( $this->_fs->premium_plugin_basename() );
	}

	/**
	 * Tries to activate a plugin. If fails, returns the error.
	 *
	 * @author Vova Feldman
	 * @since  1.2.1.7
	 *
	 * @param string $file_path Path within wp-plugins/ to main plugin file.
	 *                          This determines the styling of the output messages.
	 *
	 * @return bool|WP_Error
	 */
	protected function try_activate_plugin( $file_path ) {
		$activate = activate_plugin( $file_path );

		return is_wp_error( $activate ) ?
			$activate :
			true;
	}

	/**
	 * Adjust the plugin directory name if necessary.
	 * Assumes plugin has a folder (not a single file plugin).
	 *
	 * The final destination directory of a plugin is based on the subdirectory name found in the
	 * (un)zipped source. In some cases this subdirectory name is not the same as the expected
	 * slug and the plugin will not be recognized as installed. This is fixed by adjusting
	 * the temporary unzipped source subdirectory name to the expected plugin slug.
	 *
	 * @author Vova Feldman
	 * @since  1.2.1.7
	 *
	 * @param string $source Path to upgrade/zip-file-name.tmp/subdirectory/.
	 * @param string $remote_source Path to upgrade/zip-file-name.tmp.
	 * @param \WP_Upgrader $upgrader Instance of the upgrader which installs the plugin.
	 *
	 * @return string|WP_Error
	 */
	function _maybe_adjust_source_dir( $source, $remote_source, $upgrader ) {
		if ( ! is_object( $GLOBALS['wp_filesystem'] ) ) {
			return $source;
		}

		// Figure out what the slug is supposed to be.
		$desired_slug = $upgrader->skin->options['extra']['slug'];

		$subdir_name = untrailingslashit( str_replace( trailingslashit( $remote_source ), '', $source ) );

		if ( ! empty( $subdir_name ) && $subdir_name !== $desired_slug ) {
			$from_path = untrailingslashit( $source );
			$to_path   = trailingslashit( $remote_source ) . $desired_slug;

			if ( true === $GLOBALS['wp_filesystem']->move( $from_path, $to_path ) ) {
				return trailingslashit( $to_path );
			} else {
				return new WP_Error(
					'rename_failed',
					$this->_fs->get_text( 'module-package-rename-failure' ),
					array(
						'found'    => $subdir_name,
						'expected' => $desired_slug
					) );
			}
		}

		return $source;
	}

	#endregion
}