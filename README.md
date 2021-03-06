WordPress Github Plugin Updater

This class is meant to be used with your Github hosted WordPress plugins. The purpose of the class is to allow your WordPress plugin to be updated whenever you push out a new version of your plugin; similarly to the experience users know and love with the WordPress.org plugin repository.

Not all plugins can or should be hosted on the WordPress.org plugin repository, or you may chose to host it on github only.

The code is still in it's infancy, but [I am currently using it](https://github.com/jkudish/JigoShop-Software-Add-on) on a production plugin and production website, without any glitches. That being said, please consider this as a beta release. The project started off as a private client request, but is now public for anyone to collaborate on. I am open to any suggestions :)

Usage instructions
===========

* The class should be included somewhere in your plugin. You will need to require the file (example: `include_once( 'updater.php' );`). 
* You will need to initialize the class using something similar to this:
	
	<pre>
	if( is_admin() ){ // note the use of is_admin() to double check that this is happening in the admin
		$config = array(
			'file'		=> __FILE__,				// REQUIRED! This is the path to your plugin. Some configuration settings based on this. 
			'user'		=> 'YourGitHubUserName',	// REQUIRED! Your GitHub username
			'repo'		=> 'YourGitHubRepoName',	// REQUIRED! The name of the repository
			'requires'	=> '3.0',					// Optional: Which version of WordPress does your plugin require?
			'tested'	=> '3.3',					// Optional: Which version of WordPress is your plugin tested up to?
		);
		new WP_GitHub_Updater( $config );
	}
	</pre>	
* Add '-version: xxx' in a blank line to your commit message to inform the plugin updater about a new version

* In your Github repository, you will need to include the following line (formatted exactly like this) anywhere in your Readme file:

	`~Current Version:1.4~`

* You will need to update the version number anytime you update the plugin, this will ultimately let the plugin know that a new version is available.

* **Note**: this class will unfortunately not work with a private repository, your repository needs to be publicly accessible. If anyone knows how to make this work for private repositories, please get in touch!

Changelog
===========

### 1.5
* Support for private repositories added by [@pdclark](http://profiles.wordpress.org/pdclark)
* Additional sslverify fix

### 1.4
* Minor fixes from [@sc0ttkclark](https://github.com/sc0ttkclark)'s use in Pods Framework
* Added readme file into config

### 1.3
* Fixed all php notices
* Fixed minor bugs
* Added an example plugin that's used as a test
* Minor documentation/readme adjustments

### 1.2
* Added phpDoc and minor syntax/readability adjusments, props [@franz-josef-kaiser](https://github.com/franz-josef-kaiser), [@GaryJones](https://github.com/GaryJones)
* Added a die to prevent direct access, props [@franz-josef-kaiser](https://github.com/franz-josef-kaiser)

### 1.0.3
* Fixed sslverify issue, props [@pmichael](https://github.com/pmichael)

### 1.0.2
* Fixed potential timeout

### 1.0.1
* Fixed potential fatal error with wp_error

### 1.0
* Initial Public Release

Credits
===========

This class is built and maintained by [Joachim Kudish](http://jkudish.com "Joachim Kudish")

License
===========

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to:

Free Software Foundation, Inc.
51 Franklin Street, Fifth Floor,
Boston, MA
02110-1301, USA.