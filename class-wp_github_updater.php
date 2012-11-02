<?php
/**
 * @version		1.1
 * @author		Ralf Albert <me@neun12.de>
 * @link		https://github.com/RalfAlbert/WordPress-GitHub-Plugin-Updater
 * @package		WordPress
 * @subpackage	WordPress-GitHub-Plugin-Updater
 * @license		http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * GNU General Public License, Free Software Foundation
 * <http://creativecommons.org/licenses/GPL/2.0/>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

// Prevent loading this file directly - Busted!
if( ! defined( 'ABSPATH' ) )
	die( 'Sorry Dave. I am afraid I could not do that!' );

if( ! class_exists( 'WP_GitHub_Updater' ) ){

class WP_GitHub_Updater
{

	/**
	 * Constant for L10n
	 * @var string
	 */
	const LANG = 'github_plugin_updater';

	/**
	 * Time constant one hour
	 * @var integer
	 */
	const HOUR = 3600;

	/**
	 * Array for configuration
	 * @var	array	$config
	 */
	public $config = array();

	/**
	 * Plugin-slug
	 */
	public $slug = '';

	/**
	 * Array for error messages
	 * @var array
	 */
	public static $errors = array();

	/**
	 * Flag for stopping the update process
	 * @var bool
	 */
	protected $abort_update = FALSE;

	/**
	 * Cache for handler
	 * @var object
	 */
	private $handler = NULL;

	/**
	 * Url to the zip-archive
	 */
	private $zipurl = '';

	/**
	 * Class Constructor
	 *
	 * @since	1.0
	 * @param	array	$config	Configuration array
	 * @return	void
	*/
	public function __construct( $config = array() ) {

		// check if all needed config-settings are set
		if( ! isset( $config['user'] ) || empty( $config['user'] ) )
			$this->set_error( 'fatal', __( 'Empty username in configuration. Aborting!', self::LANG ), TRUE );

		if( ! isset( $config['repo'] ) || empty( $config['repo'] ) )
			$this->set_error( 'fatal', __( 'Empty repository in configuration. Aborting!', self::LANG ), TRUE );

		if( ! isset( $config['file'] ) || empty( $config['file'] ) || ! is_file( $config['file'] ) )
			$this->set_error( 'fatal', __( 'Empty or not valid file-parameter in configuration. Aborting!', self::LANG ), TRUE );

		// let's init the class
		$this->init( $config );

		// add the hooks&filters
		$this->add_hooks();

	}

	/**
	 * Simple error handling
	 *
	 * @since	1.6
	 * @param	string	$type	Type of the error (fatal, warning, notice, etc)
	 * @param	string	$msg	Error message
	 * @param	bool	$abort	Wether the script should stop or not.
	 */
	public function set_error( $type = 'notice', $msg = '', $abort = FALSE ){

		$type = in_array( $type, array( 'notice', 'warning', 'fatal' ) ) ? $type : 'notice';

		if( isset( self::$errors[$type] ) && is_array( self::$errors[$type] ) )
			array_push( self::$errors[$type], $msg );
		else
			self::$errors[$type] = array( $msg );

		if( TRUE === $abort )
			$this->abort_update = TRUE;

	}

	/**
	 * Simple error handling. Outputs the errors
	 *
	 * @since	1.6
	 * @param	string	$type	Specified an error-type (fatal, warning, notice, etc) or return all errors if not set.
	 * @return	array	$errors	An array with error messages
	 */
	public function get_errors( $type = '' ){

		if( empty( $type ) )
			return self::$errors;

		$type = in_array( $type, array( 'notice', 'warning', 'fatal' ) ) ? $type : 'notice';

		if( isset( self::$errors[$type] ) && is_array( self::$errors[$type] ) )
			return self::$errors[$type];

	}

	/**
	 * Initialize the configuration array
	 *
	 * @since	1.6
	 * @param	array	$config	Array with configuration
	 */
	protected function init( $config = array() ){

		if( TRUE === $this->abort_update )
			return FALSE;

		global $wp_version;

		$this->init_handler( $config );

		$plugin_data = $this->get_plugin_data( $config['file'] );

		if( ! isset( $config['slug'] ) || empty( $config['slug'] ) )
			$this->slug = plugin_basename( $config['file'] );
		else
			$this->slug = $config['slug'];

		$defaults =	array(
						'handler'					=> 'GitHub',
						'proper_folder_name'		=> dirname( plugin_basename( $config['file'] ) ),
						'requires'					=> $wp_version,
						'tested'					=> $wp_version,
						'new_version'				=> $this->get_version(),
						'last_updated'				=> $this->get_date(),
						'description'				=> $plugin_data['Description'],
						'plugin_name'				=> $plugin_data['Name'],
						'version'					=> $plugin_data['Version'],
						'author'					=> $plugin_data['Author'],
						'homepage'					=> $plugin_data['PluginURI'],
				);

		$this->config = wp_parse_args( $config, $defaults );

		// be nice, cleanup
		unset( $config );

	}

	/**
	 * Initialize the handler
	 * @param	array	$config		Initial configuration array
	 * @return	object	$handler	Setup the handler-object
	 */
	protected function init_handler( $config ){

		if( ! isset( $config['handler'] ) || ! is_array( $config['handler'] ) )
			$config['handler'] = array();

		//TODO: Handler-Switch / Handler-Factory

		if( ! class_exists( 'GitHub_Api_Handler' ) )
			require_once 'class-github_api_handler.php';

		$this->handler = new GitHub_Api_Handler();

		$handler_config = wp_parse_args( $config['handler'], $this->handler->config );

		$this->handler->setup( $config['user'], $config['repo'], $handler_config );

		$this->zipurl = $this->handler->get_zipurl();

	}

	/**
	 * Adding the needed hooks&filters
	 *
	 * @since	1.6
	 */
	protected function add_hooks(){

		if( TRUE === $this->abort_update )
			return FALSE;

		if( defined('WP_GITHUB_FORCE_UPDATE') && TRUE == WP_GITHUB_FORCE_UPDATE )
			add_action( 'admin_init', array( $this, 'delete_transients' ), 11 );

		add_filter( 'pre_set_site_transient_update_plugins', array( &$this, 'api_check' ) );

		// Hook into the plugin details screen
		add_filter( 'plugins_api', array( &$this, 'get_plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( &$this, 'upgrader_post_install' ), 10, 3 );

		// set timeout
		add_filter( 'http_request_timeout', array( &$this, 'http_request_timeout' ) );

		// set sslverify for zip download
		add_filter( 'http_request_args', array( &$this, 'http_request_sslverify' ), 10, 2 );

	}

	/**
	 * Callback for the http_request_timeout filter
	 *
	 * @since	1.0
	 * @return	int	$timeout	Timeout value
	 */
	public function http_request_timeout(){

		return 2;

	}

	/**
	 * Callback fn for the http_request_args filter
	 *
	 * @param	array	$args	Arguments for verifying https
	 * @param	string	$url	Url to be requested
	 * @return	array	$args	Result of modification
	 */
	public function http_request_sslverify( $args, $url ){

		if( $this->zipurl == $url )
			$args['sslverify'] = $this->config['sslverify'];

		return $args;

	}

	/**
	 * Delete transients (runs when WP_DEBUG is on)
	 * For testing purposes the site transient will be reset on each page load
	 *
	 * @since	1.0
	 */
	public function delete_transients(){

		delete_site_transient( 'update_plugins' );
		delete_site_transient( $this->slug . '_new_version' );
		delete_site_transient( $this->slug . '_repo_data' );
		delete_site_transient( $this->slug . '_changelog' );

		delete_site_transient( $this->slug . '_github_data' );

	}

	/**
	 * Get GitHub Data from the specified repository
	 *
	 * @since	1.0
	 * @return	array	$github_data	Data received from GitHub
	 */
	public function get_repo_data(){

		$repo_data = get_site_transient( $this->slug.'_repo_data' );

		if( ! isset( $repo_data ) || empty( $repo_data ) ){

			$repo_data = $this->handler->get_repo_data();

			if( empty( $repo_data) )
				return FALSE;

			// refresh every 6 hours
			set_site_transient( $this->slug.'_repo_data', $repo_data, 60*60*6 );
		}

		return $repo_data;

	}

	/**
	 * Get new plugin version
	 *
	 * @since	1.6
	 * @return	string	$version	Plugin version
	 */
	public function get_version(){

		$version = get_site_transient( $this->slug . '_new_version' );

		if( ! isset( $version ) || empty( $version ) ){

			$version = $this->handler->get_version();

			// refresh every 6 hours
			set_site_transient( $this->slug . '_new_version', $version, self::HOUR * 6 );

		}

		return $version;

	}

	/**
	 * Get update date
	 *
	 * @since	1.0
	 * @return	string	$date	Last update of the repository
	 */
	public function get_date(){

		$date = $this->get_repo_data();

		return ( ! empty( $date->updated_at ) ) ? date( 'Y-m-d', strtotime( $date->updated_at ) ) : FALSE;

	}

	/**
	 * Get repository description
	 *
	 * @since	1.0
	 * @return	string	$description	Description of the repository
	 */
	public function get_description(){

		$description = $this->get_repo_data();

		return ( ! empty( $description->description ) ) ? $description->description : FALSE;

	}

	/**
	 * Get Plugin data
	 *
	 * @since	1.0
	 * @return	object	$data	The plugin-data from the plugin-header
	 */
	public function get_plugin_data( $file ) {

		if( ! function_exists( 'get_plugin_data' ) )
			include_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		return get_plugin_data( WP_PLUGIN_DIR . '/' . plugin_basename( $file ) );

	}

	/**
	 * Hook into the plugin update check and connect to github
	 *
	 * @since	1.0
	 * @param	object	$transient	Plugin data transient
	 * @return	object	$transient	Updated plugin data transient
	 */
	public function api_check( $transient ){

		// Check if the transient contains the 'checked' information
		// If not, just return its value without hacking it
		if( empty( $transient->checked ) )
			return $transient;

		// check the version and decide if it's newer
		$update = version_compare( $this->config['new_version'], $this->config['version'] );

		if( 1 === $update ){

			$response = new stdClass;
			$response->new_version	= $this->config['new_version'];
			$response->slug			= $this->config['proper_folder_name'];
			$response->url			= $this->handler->get_url();
			$response->package		= $this->handler->get_zipurl();

			// If response is false, don't alter the transient
			if( FALSE !== $response )
				$transient->response[ $this->slug ] = $response;
		}

		return $transient;

	}

	/**
	 * Get Plugin info
	 *
	 * @since	1.0
	 * @param	bool	$false		Always false
	 * @param	string	$action		The API function being performed
	 * @param	object	$args		Plugin arguments
	 * @return	object	$response	The plugin info
	 */
	public function get_plugin_info( $false, $action, $response ){

		// Check if this call API is for the right plugin
		if( $response->slug != $this->slug )
			return FALSE;

		$response->slug				= $this->slug;
		$response->plugin_name		= $this->config['plugin_name'];
		$response->version			= $this->config['new_version'];
		$response->author			= $this->config['author'];
		$response->homepage			= $this->config['homepage'];
		$response->requires			= $this->config['requires'];
		$response->tested			= $this->config['tested'];
		$response->downloaded		= 0;
		$response->last_updated		= $this->config['last_updated'];
		$response->sections			= array( 'description' => $this->config['description'] );
		$response->download_link	= $this->handler->get_zipurl();

		return $response;

	}

	/**
	 * Upgrader/Updater
	 * Move & activate the plugin, echo the update message
	 *
	 * @since	1.0
	 * @param	boolean	$true			Always true
	 * @param	mixed	$hook_extra		Not used
	 * @param 	array	$result			The result of moving files
	 * @return	array	$result			The result of moving files
	 */
	public function upgrader_post_install( $true, $hook_extra, $result ){

		global $wp_filesystem;

		// Move & Activate
		$proper_destination = WP_PLUGIN_DIR . '/' . $this->config['proper_folder_name'];
		$wp_filesystem->move( $result['destination'], $proper_destination );

		$result['destination'] = $proper_destination;
		$activate = activate_plugin( WP_PLUGIN_DIR . '/' . $this->slug );

		// Output the update message
		echo is_wp_error( $activate ) ?
		'<p>' . __( 'Plugin failed to reactivate due to a fatal error.', self::LANG ) . '</p>' :
		'<p>' . __( 'Plugin reactivated successfully.', self::LANG ) . '</p>';

		return $result;

	}

}

}; // endif class exists