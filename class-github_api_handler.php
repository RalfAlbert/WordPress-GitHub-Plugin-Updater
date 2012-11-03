<?php
/*
 * The GitHub-Handler
 *
 * A class to easily use the GitHub-API (early development!)
 *
 * TODO: Add an option to setup the branch if the version will be read from file
 * TODO: Add an option to setup the branch for the zip-url
 *
 */
if( ! class_exists( 'GitHub_Api_Handler' ) ){

class GitHub_Api_Handler extends WP_GitHub_Updater
{
	/**
	 * The basic api-url from GitHub
	 * @var	string
	 */
	const APIURL = 'https://api.github.com/';

	/**
	 * Urls for requesting GitHub
	 * @var array
	 */
	public $api_urls = array(

			'apiurl' 		=> 'https://api.github.com/repos/%user%/%repo%',
			'rawurl' 		=> 'https://raw.github.com/%user%/%repo%/master',
			'giturl' 		=> 'https://github.com/%user%/%repo%',
			'zipurl' 		=> 'https://github.com/%user%/%repo%/zipball/master',
			//'ratelimit'		=> 'https://api.github.com/users/%user%',
			'ratelimit'		=> 'https://api.github.com/rate_limit', // checking rate-limit without incurring the API

			'basic'			=> 'repos/%user%/%repo%',
			'all_repos' 	=> 'users/%user%/repos',
			'repo'			=> 'repos/%user%/%extra%',
			'ref'			=> 'repos/%user%/%repo%/git/refs/%extra%',
			'last_commit'	=> 'repos/%user%/%repo%/git/commits/%extra%',
			'tag'			=> 'repos/%user%/%repo%/git/tags/%extra%',

	);

	/**
	 * Cache for data from GitHub
	 * @var array
	 */
	public $cache = array();

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
	 * Token for accessing private repos
	 * @var string
	 */
	public $access_token = '';

	/**
	 * Filename where to search for the version-string
	 * @var string
	 */
	public $file_contains_version = 'readme.md';

	/**
	 * Serach pattern for version string in PCRE notation WITHOUT delimiters
	 * @var string
	 */
	public $search_pattern_version = '-version:\s?(.+)';

	/**
	 * Internal usage for preg_match
	 * @var string
	 */
	private $search_pattern = '';

	/**
	 * The place where the version can be found
	 * @var string
	 */
	public $method = 'commit-message';

	/**
	 * Valid places to search for the version
	 * @var array
	 */
	protected $search_places = array(
			'commit-message', 'file', 'tag'
	);

	/**
	 * Predefined configuration
	 * @var array
	 */
	public $config = array(

			'sslverify'					=> FALSE,
			'access_token'				=> '',
			'file_contains_version'		=> 'readme.md',
			'search_pattern_version'	=> '-version:\s?(.+)',
			'method'					=> 'commit-message'

	);

	/**
	 * Flag for errors
	 */
	public $error = FALSE;

	/**
	 * Constructor
	 *
	 */
	public function __construct(){
		// avoid calling parent::__construct()
	}

	/**
	 * Setup the class
	 *
	 * @param	string	$user	GitHub username
	 * @param	string	$repo	GitHub repository
	 * @param	array	$config	Extra configuration
	 */
	public function setup( $user, $repo, $config = array() ){

		$this->user = (string) $user;
		$this->repo = (string) $repo;

		$this->_init_handler( $config );

		return TRUE;

	}

	/**
	 * Initialize and setup the class-vars
	 * @param	array	$config	Configuration
	 */
	protected function _init_handler( $config = array() ){

		// setup configuration defaults
		$config = wp_parse_args( $config, $this->config );

		// copy configuration
		foreach( $config as $key => $value )
			$this->$key = $value;

		// some tests
		if( ! in_array( $this->method, $this->search_places ) )
			$this->method = 'commit-message';

		// be nice, cleanup
		unset( $config );

		// init urls
		foreach( $this->api_urls as &$url ){
			$url = str_replace( '%user%', $this->user, $url );
			$url = str_replace( '%repo%', $this->repo, $url );
		}

		// clear reference
		unset( $url );

		// See Downloading a zipball (private repo) https://help.github.com/articles/downloading-files-from-the-command-line
		if( ! empty( $this->access_token ) ){

			$this->api_urls['apiurl'] = add_query_arg( array( 'access_token' => $this->access_token ), $this->api_urls['apiurl'] );
			$this->api_urls['zipurl'] = add_query_arg( array( 'access_token' => $this->access_token ), $this->api_urls['zipurl'] );

		}

		// fill the cache
		$this->get_github_data( 'basic' );

		// complete PCRE search pattern
		$this->search_pattern = sprintf( '/%s/iu', $this->search_pattern_version );

		return TRUE;

	}

	/**
	 * Returns basic information about the repo
	 * @return	array	Repo data
	 */
	public function get_repo_data(){

		return $this->get_repo();

	}

	/**
	 * Get the basic url to the repo
	 * @return	string	String with the url to the repo
	 */
	public function get_url(){

		return $this->api_urls['giturl'];

	}

	/**
	 * Get the zip-url
	 * @return string	String with the url to the zip-file
	 */
	public function get_zipurl(){

		return $this->api_urls['zipurl'];

	}

	/**
	 * Returns the version by specified method (commit-message, file, tag)
	 * @return	string	$version	Version string
	 */
	public function get_version(){

		switch( $this->method ){

			case 'commit-message':
			default:
				return $this->get_version_from_commit_message();
			break;

			case 'file':
				return $this->get_version_from_file();
			break;

			case 'tag':
				return $this->get_version_from_tag();
			break;
		}

	}

	/**
	 * Get plugin version from commit message
	 * @return	string	$version	Plugin version from commit message
	 */
	protected function get_version_from_commit_message(){

		$result = '';

		$repo = $this->get_repo();

		if( FALSE === $this->error )
			$ref = $this->get_ref( $repo->master_branch);

		if( FALSE === $this->error )
			$commit	= $this->get_last_commit( $ref->object->sha );

		if( FALSE === $this->error ){

			preg_match_all( $this->search_pattern, $commit->message, $matches );

			$result = &$matches[1][0];

		} else {

			$this->set_error( 'warning', __( 'Error while fetching version from commit-message', self::LANG ) );

		}

		return ( isset( $result ) && ! empty( $result ) ) ? trim( $result ) : NULL;

	}

	/**
	 * Get plugin version from file
	 * @return	string	$version	Plugin version from file
	 */
	protected function get_version_from_file(){

		$result = '';

		if( empty( $this->file_contains_version ) )
			$this->file_contains_version = 'readme.md';

		$query = $this->api_urls['rawurl'] . '/' . $this->file_contains_version;
		$query = add_query_arg( array( 'access_token' => $this->access_token ), $query );

		$raw_response = wp_remote_get( $query, array('sslverify' => $this->config['sslverify']) );

		if ( is_wp_error( $raw_response ) ){

			$this->error = TRUE;
			$this->set_error(
					'warning',
					sprintf( '%s: %s', __( 'Error while fetching version from file', self::LANG ), $raw_response->get_error_message() )
			);

		} else {

			preg_match( $this->serach_pattern, $raw_response['body'], $version );

			$result = &$version[1];

		}

		return ( isset( $result ) && ! empty( $result ) ) ? trim( $result ) : NULL;

	}

	/**
	 * Get plugin version from tag
	 * @return	string	$version	Plugin version from tag
	 */
	protected function get_version_from_tag(){

		$tag = new stdClass;

		$repo = $this->get_repo();

		if( FALSE === $this->error )
			$ref = $this->get_ref( $repo->master_branch);

		if( FALSE === $this->error )
			$tag = $this->get_tag( $ref->object->sha );
		else
			$this->set_error( 'warning', __( 'Error while fetching version from tag', self::LANG ) );

		return ( isset( $tag->tag ) && ! empty( $tag->tag ) ) ? $tag->tag : NULL;

	}

	/**
	 * Retriving data from GitHub
	 *
	 * @param	string	$id			ID of the action to be taken
	 * @param	string	$extra_arg	Extra information like reference or sha-key
	 */
	protected function get_github_data( $id = 'basic', $extra_arg = '' ){

		$response = '';
		$cache = array();

		if( defined('WP_GITHUB_FORCE_UPDATE') && TRUE == WP_GITHUB_FORCE_UPDATE )
			delete_site_transient( parent::$slug . '_github_data' );

		$cache = get_site_transient( parent::$slug . '_github_data' );

		if( isset( $cache[$id] ) && ! empty( $cache[$id] ) )
			return $cache[$id];


		if( ! function_exists( 'wp_remote_get' ) )
			require_once ABSPATH . '/wp-includes/http.php';

// 		if( FALSE === $this->check_rate_limit() )
// 			return NULL;
		$this->check_rate_limit();

		if( ! key_exists( $id, $this->api_urls ) )
			$id = 'basic';

		$url = self::APIURL . $this->api_urls[$id];
		$url = str_replace( '%extra%', $extra_arg, $url );

		if( ! empty( $this->access_token ) )
			$url = add_query_arg( array( 'access_token' => $this->access_token ), $url );


		$raw_response = wp_remote_get( $url, array( 'sslverify' => $this->sslverify ) );

		if( is_wp_error( $raw_response ) ){

			$this->error = TRUE;
			$this->set_error(
					'warning',
					sprintf( '%s: %s', __( 'Try to get data from the GitHub repo, but an error occurred', self::LANG ), $raw_response->get_error_message() )
			);

		} else {

			if( 200 !== (int) $raw_response['response']['code'] ){

				$body = (object) json_decode( $raw_response['body'] );
				if( ! isset( $body->message ) )
					$body->message = 'No message';

				$this->error = TRUE;
				$this->set_error(
					'warning',
					sprintf( '%s: %s', __( 'Try to get data from the GitHub repo, but an error occurred', self::LANG ), $body->message )
				);

			} else {

				$response = json_decode( $raw_response['body'], $this->return_as_array );
				$cache[$id] = $response;
				set_site_transient( parent::$slug . '_github_data', $cache, self::HOUR );

			}

		}

		return ( isset( $response ) && ! empty( $response ) ) ? $response : NULL;

	}

	/**
	 * Checking if the rate limit is exceeded
	 * @return	boolean		anonymous	True if the rate limit is not exceeded, else false.
	 */
	protected function check_rate_limit(){

		// check rate-limiting (per IP)
		$raw_response = wp_remote_get( $this->api_urls['ratelimit'], array( 'sslverify' => $this->sslverify ) );

		if( is_wp_error( $raw_response ) ){

			$this->error = TRUE;
			$this->set_error(
					'warning',
					sprintf( '%s: %s', __( 'An error occurred while fetching the rate-limit', self::LANG ), $raw_response->get_error_message() )
			);

		} else {

			$headers = &$raw_response['headers'];

			$remaining = $headers['x-ratelimit-remaining'];
			$ratelimit = $headers['x-ratelimit-limit'];

			if( 0 >= $remaining ){

				$this->error = TRUE;
				$this->set_error( 'warning', sprintf( __( 'Rate limit of %d api-calls is exceeded.', self::LANG ), $ratelimit ) );
				return FALSE;

			}

		}

		return TRUE;

	}

	/**
	 * Retriving all repositories of an user
	 *
	 * @return	object|array	$repos	A list with all available repositories from an user
	 */
	protected function get_all_repos(){

		return $this->get_github_data( 'all_repos' );

	}

	/**
	 * Get data from a single repository
	 *
	 * @return	object|array	$repo	Repository data
	 */
	protected function get_repo( $repo = '' ){

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
	protected function get_ref( $ref = '' ){

		if( empty( $ref ) )
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
	protected function get_last_commit( $sha ){

		return $this->get_github_data( 'last_commit', $sha );

	}

	/**
	 * Get tag by sha-key
	 * @param	string	$sha		SHA-Key
	 * @return	array	anonymous	Array with tags
	 */
	protected function get_tag( $sha ){

		return $this->get_github_data( 'tag', $sha );

	}

}

}; // end if class exists