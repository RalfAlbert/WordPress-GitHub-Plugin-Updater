<?php
/*
Plugin Name: WP Github Plugin Updater Test
Plugin URI: https://github.com/RalfALbert/WordPress-GitHub-Plugin-Updater
Description: Test for the Github Plugin Updater
Version: 0.1
Author: Ralf Albert
Author URI: http://yoda.neun12.de/
License: GPLv2
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// force update
//define( 'WP_GITHUB_FORCE_UPDATE', TRUE );

add_action( 'init', 'github_plugin_updater_test_init', 10, 0 );

add_action( 'plugins_loaded', 'GitHubUpdaterTest_DebugOutput', 10, 0 );

function github_plugin_updater_test_init() {

	if( is_admin() ){

		global $githubupdater;

		require_once 'update_lib/class-wp_github_updater.php';

		$config = array(

			// required data
			'file'		=> __FILE__,
			'user'		=> 'RalfAlbert',
			'repo'		=> 'UpdateTestRepo',

			// optional data
			'requires'	=> '3.0',
			'tested'	=> '3.4',

		);

		$githubupdater = new  WP_GitHub_Updater( $config );

	}

}

function GitHubUpdaterTest_DebugOutput (){
	new UpdateTestPlugin_DebugOutput;
}

class UpdateTestPlugin_DebugOutput
{

	public function __construct(){

		add_action( 'wp_dashboard_setup', array( &$this, 'add_dashboard_widget' ) );

	}

	public function add_dashboard_widget(){

		wp_add_dashboard_widget(
			'debug-widget',
			'Debug Widget',
			array( &$this, 'dashboard_widget' ),
			$control_callback = null
		);

	}

	public function dashboard_widget(){

		global $githubupdater;

		echo '<div class="wrap">';
		echo '<h2>GitHubPluginUpdater Test Output</h2>';
		echo '<p>This is the testing output from <a href="https://github.com/RalfAlbert/WordPress-GitHub-Plugin-Updater">WordPress GitHub Plugin Updater</a>.</p>';

		$plugindata = $this->get_plugin_info();

		printf( '<p>Current version is <strong>%s</strong></p>', $plugindata['Version'] );

		$errors = $githubupdater->get_errors();

		if( ! empty( $errors ) ){

			echo '<div class="widget"><h3>Some errors occurred :(</h3><p>&nbsp;</p>';

			$types			= array( 'warning', 'notice', 'fatal' );
			$error_format	= '<h4>Type: %s</h4><ol>%s</ol>';
			$error_list		= '';

			foreach( array_keys( $errors ) as $error_type ){

				if( isset( $errors[$error_type] ) && is_array( $errors[$error_type] ) ){

					foreach( $errors[$error_type] as $error )
						$error_list .= sprintf( '<li>%s</li>', $error );

					printf( $error_format, $error_type, $error_list );

				}

			}

			echo '</div>';

		}

		echo '</div>';
	}

	protected function get_plugin_info( $file = __FILE__ ){

		if( ! function_exists( 'get_plugin_data' ) )
			include_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		return get_plugin_data( WP_PLUGIN_DIR . '/' . plugin_basename( $file ) );

	}

}
