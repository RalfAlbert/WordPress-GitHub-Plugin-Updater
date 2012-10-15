<?php
/**
 * @version		1.6
 * @author		Joachim Kudish <info@jkudish.com>, Ralf Albert <me@neun12.de>
 * @link		http://jkudish.com
 * @package		GithubUpdater
 * @license		http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright	Copyright (c) 2011, Joachim Kudish
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

if( ! class_exists( 'WP_GitHub_Updater' ) ) :

class WP_GitHub_Updater
{
	
	/**
	 * Constant for L10n
	 * @var string
	 */
	const LANG = 'github_plugin_updater';
	
	/**
	 * Array for configuration
	 * @var	array	$config
	 */
	public $config = array();
	
	/**
	 * Urls for requesting GitHub
	 * @var array
	 */
	public $urls = array(
			'apiurl' => 'https://api.github.com/repos/%s/%s',
			'rawurl' => 'https://raw.github.com/%s/%s/master',
			'giturl' => 'https://github.com/%s/%s',
			'zipurl' => 'https://github.com/%s/%s/zipball/master',
	);
	
	/**
	 * Array for error messages
	 * @var array
	 */
	public $errors = array(); 
	
	/**
	 * Flag for stopping the update process
	 * @var bool
	 */
	protected $abort_update = FALSE;
	 
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
			$this->set_error( 'fatal', __( 'Empty GitHub username in configuration. Aborting!', self::LANG ), TRUE );
		
		if( ! isset( $config['repo'] ) || empty( $config['repo'] ) )
			$this->set_error( 'fatal', __( 'Empty GitHub repository in configuration. Aborting!', self::LANG ), TRUE );
				
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
	
		if( isset( $this->errors[$type] ) && is_array( $this->errors[$type] ) )
			array_push( $this->errors[$type], $msg );
		else
			$this->errors[$type] = array( $msg );

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
		
		if( isset( $this->errors[$type] ) && is_array( $this->errors[$type] ) )
			return $this->errors[$type];
		else
			return $this->errors;
		
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
		
		// init urls
		foreach( $this->urls as $key => $url_format )
			$this->urls[$key] = sprintf( $url_format, $config['user'], $config['repo'] );
		
		$plugin_data = $this->get_plugin_data( $config['file'] );
		
		$this->config = wp_parse_args(
		
				$config,
				array(
						'method'				=> 'commit_message',
						'slug'					=> plugin_basename( $config['file'] ),
						'proper_folder_name'	=> dirname( plugin_basename( $config['file'] ) ),
						'sslverify'				=> true,
						'requires'				=> $wp_version,
						'tested'				=> $wp_version,
						'access_token'			=> '',
						'new_version'			=> $this->get_new_version(),
						'last_updated'			=> $this->get_date(),
						'description'			=> $plugin_data['Description'],
						'plugin_name'			=> $plugin_data['Name'],
						'version'				=> $plugin_data['Version'],
						'author'				=> $plugin_data['Author'],
						'homepage'				=> $plugin_data['PluginURI'],
						'readme' 				=> 'README.md',
				)
		
		);
		
		// be nice, cleanup
		unset( $config );
		
		// See Downloading a zipball (private repo) https://help.github.com/articles/downloading-files-from-the-command-line
		if( ! empty( $this->config['access_token'] ) )
			$this->urls['zipurl'] = add_query_arg( array( 'access_token' => $this->config['access_token'] ), $this->urls['zipurl'] );
		
	}
	
	/**
	 * Adding the needed hooks&filters
	 * 
	 * @since	1.6
	 */
	protected function add_hooks(){
		
		if( TRUE === $this->abort_update )
			return FALSE;
		
		if( ( defined('WP_DEBUG') && TRUE == WP_DEBUG ) || ( defined('WP_GITHUB_FORCE_UPDATE') && TRUE == WP_GITHUB_FORCE_UPDATE ) )
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
	 * Callback fn for the http_request_timeout filter
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
		
		if( $this->urls['zipurl'] == $url )
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
		delete_site_transient( $this->config['slug'].'_new_version' );
		delete_site_transient( $this->config['slug'].'_github_data' );
		delete_site_transient( $this->config['slug'].'_changelog' );
		
	}
	
	/**
	 * Get new plugin version depending on method (commit message or readme.md)
	 * 
	 * @since	1.6
	 * @return	string	$version	Plugin version
	 */
	public function get_new_version(){

		$version = get_site_transient( $this->config['slug'].'_new_version' );
		
		if( ! isset( $version ) || empty( $version ) ){
			
			$version = ( 'commit_message' == $this->config['method'] ) ?
				$this->get_new_version_from_commit_message() : $this->get_new_version_from_readme(); 

			// refresh every 6 hours
			set_site_transient( $this->config['slug'].'_new_version', $version, 60*60*6 );
				
		}
		
		return $version;
				
	}
	
	/**
	 * Get plugin version from commit message with fallback to readme.md if no version was found in commit message
	 * 
	 * @since	1.6
	 * @return	string	$version	Plugin version from commit message
	 */
	protected function get_new_version_from_commit_message(){
		
		$git = new GitHub_Api_Wrapper( $this->config['user'], $this->config['repo'] );
			
		$repo	= $git->get_repo();
		$ref	= $git->get_ref( $repo->master_branch);
		$sha	= $ref->object->sha;
		$commit	= $git->get_last_commit( $sha );
		
		preg_match_all( '#-version:\s?(.+)#', $commit->message, $matches );
		
		if( isset( $matches[1] ) && ! empty( $matches[1] ) )
			return $matches[1];
		else
			return $this->get_new_version_from_readme();
		
	}
	
	/**
	 * Get plugin version from readme.md
	 *
	 * @since	1.0
	 * @return	string	$version	Plugin version from readme.md
	 */
	protected function get_new_version_from_readme(){
		
	
		$query = trailingslashit( $this->urls['rawurl'] ) . $this->config['readme'];
		$query = add_query_arg( array('access_token' => $this->config['access_token']), $query );

		$raw_response = wp_remote_get( $query, array('sslverify' => $this->config['sslverify']) );

		if ( is_wp_error( $raw_response ) )
			return false;

		$__version	= explode( '~Current Version:', $raw_response['body'] );

		if( ! isset( $__version['1'] ) )
			return FALSE;

		$_version	= explode( '~', $__version['1'] );
		$version	= $_version[0];
		
		return $version;
		
	}
	
	
	/**
	 * Get GitHub Data from the specified repository
	 *
	 * @since	1.0
	 * @return	array	$github_data	Data received from GitHub
	 */
	public function get_github_data(){
		
		$github_data = get_site_transient( $this->config['slug'].'_github_data' );
		
		if( ! isset( $github_data ) || empty( $github_data ) ){

			$query = add_query_arg( array('access_token' => $this->config['access_token']), $this->urls['apiurl'] );
			
			$github_data = wp_remote_get( $query, array('sslverify' => $this->config['sslverify']) );
			
			if ( is_wp_error( $github_data ) )
				return FALSE;
			
			$github_data = json_decode( $github_data['body'] );
			
			// refresh every 6 hours
			set_site_transient( $this->config['slug'].'_github_data', $github_data, 60*60*6 );
		}
	
		return $github_data;
		
	}
	
	
	/**
	 * Get update date
	 *
	 * @since	1.0
	 * @return	string	$date	Last update of the repository
	 */
	public function get_date(){
		
		$_date = $this->get_github_data();
		
		return ( ! empty( $_date->updated_at ) ) ? date( 'Y-m-d', strtotime( $_date->updated_at ) ) : FALSE;
		
	}
	
	
	/**
	 * Get repository description
	 *
	 * @since	1.0
	 * @return	string	$description	Description of the repository
	 */
	public function get_description(){
		
		$_description = $this->get_github_data();
	
		return ( ! empty( $_description->description ) ) ? $_description->description : FALSE;
		
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
			$response->url			= add_query_arg( array('access_token' => $this->config['access_token']), $this->urls['giturl'] );
			$response->package		= $this->urls['zipurl'];
			
			// If response is false, don't alter the transient
			if ( FALSE !== $response )
				$transient->response[ $this->config['slug'] ] = $response;
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
		if ( $response->slug != $this->config['slug'] )
			return FALSE;
		
		$response->slug				= $this->config['slug'];
		$response->plugin_name		= $this->config['plugin_name'];
		$response->version			= $this->config['new_version'];
		$response->author			= $this->config['author'];
		$response->homepage			= $this->config['homepage'];
		$response->requires			= $this->config['requires'];
		$response->tested			= $this->config['tested'];
		$response->downloaded		= 0;
		$response->last_updated		= $this->config['last_updated'];
		$response->sections			= array( 'description' => $this->config['description'] );
		$response->download_link	= $this->config['zip_url'];
		
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
		$activate = activate_plugin( WP_PLUGIN_DIR . '/' . $this->config['slug'] );
		
		// Output the update message
		echo is_wp_error( $activate ) ?
		'<p>' . __( 'Plugin failed to reactivate due to a fatal error.', self::LANG ) . '</p>' :
		'<p>' . __( 'Plugin reactivated successfully.', self::LANG ) . '</p>';
		
		return $result;
	
	}

}

endif; // endif class exists


/*
 * GitHub API Wrapper
 * A class to easily use the GitHub-API (early development!)
 */
if( ! class_exists( 'GitHub_Api_Wrapper' ) ) :

class GitHub_Api_Wrapper
{
	/**
	 * The basic api-url from GitHub
	 * @var	string
	 */
	const APIURL = 'https://api.github.com/';

	/**
	 * GitHub username
	 * @var	string	$user
	 */
	public $user = '';

	/**
	 * A single repository
	 * @var	string	$repo
	 */
	public $repo = '';

	/**
	 * Flag to define if the data should be returned as array or object
	 * @var	bool	$return_as_array
	 */
	public $return_as_array = FALSE;
	
	/**
	 * Flag for verifying ssl-connection
	 * @var	bool	$sslverify
	 */
	public $sslverify = FALSE;

	/**
	 * Constructor
	 *
	 * @param	string	$user	GitHub username
	 * @param	string	$repo	GitHub repository
	 */
	public function __construct( $user, $repo ){

		$this->user = (string) $user;
		$this->repo = (string) $repo;

	}

	/**
	 * Retriving data from GitHub
	 *
	 * @param	string	$id			ID of the action to be taken
	 * @param	string	$extra_arg	Extra information like reference or sha-key
	 */
	public function get_github_data( $id = 'all_repos', $extra_arg = '' ){

		if( empty( $this->user ) || empty( $this->repo ) )
			return NULL;
		
		if( ! function_exists( 'wp_remote_get' ) )
			require_once ABSPATH . '/wp-includes/http.php';

		$github_api_urls = array(
					
				'all_repos' 	=> "users/{$this->user}/repos",
				'repo'			=> "repos/{$this->user}/{$extra_arg}",
				'ref'			=> "repos/{$this->user}/{$this->repo}/git/refs/{$extra_arg}",
				'last_commit'	=> "repos/{$this->user}/{$this->repo}/git/commits/{$extra_arg}",

				);

		if( ! key_exists( $id, $github_api_urls ) )
			$id = 'all_repos';

		$raw_response = wp_remote_get( self::APIURL . $github_api_urls[$id], array( 'sslverify' => $this->sslverify ) );
		
		if( is_wp_error( $raw_response ) )
			return NULL;
		else
			return json_decode( $raw_response, $this->return_as_array );

	}

	/**
	 * Retriving all repositories of an user
	 *
	 * @return	object|array	$repos	A list with all available repositories from an user
	 */
	public function get_all_repos(){

		return $this->get_github_data( 'all_repos' );

	}

	/**
	 * Get data from a single repository
	 *
	 * @return	object|array	$repo	Repository data
	 */
	public function get_repo( $repo = '' ){
			
		if( empty( $repo ) )
			$repo = $this->repo;

		return $this->get_github_data( 'repo', $repo );

	}

	/**
	 * Get information from a single reference
	 *
	 * @param	string			$ref	Name of the reference
	 * @return	objec|array		$ref	The reference
	 */
	public function get_ref( $ref = '' ){
			
		if( '' == $ref )
			$ref = 'heads/master';
		else
			$ref = sprintf( 'heads/%s', str_replace( 'heads/', '', $ref ) );

		return $this->get_github_data( 'ref', $ref );

	}

	/**
	 * Get the data from a given sha-key (commit)
	 *
	 * @param	string			$sha	SHA-key (commit-identifier)
	 * @return	object|array	$data	Commit data
	 */
	public function get_last_commit( $sha ){

		return $this->get_github_data( 'last_commit', $sha );

	}

}

endif; // end if class exists