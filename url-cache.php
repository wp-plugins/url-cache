<?php

/*

Plugin Name: URL Cache
Version: 0.1
Plugin URI: http://mcnicks.org/plugins/url-cache/
Description: Given a URL, the url_cache() function will attempt to download the file it represents and return a URL pointing to this locally cached version.
Author: David McNicol
Author URI: http://mcnicks.org/

Copyright (c) 2005
Released under the GPL license
http://www.gnu.org/licenses/gpl.txt

This file is part of WordPress.
WordPress is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

/* Load the config file. */

include_once( ABSPATH . "wp-content/plugins/url-cache.conf" );

/* url_cache
 *  - $remote_url is the remote URL that the function will attempt
 *    to cache
 *  - returns a URL to the locally cached file, or $url if there was
 *    a failure
 */

function url_cache ( $remote_url = "" ) {

  // Return if no URL was given.

  if ( $remote_url == "" ) return;

  // Calculate a hash of the URL and extract the URLs file extension. These
  // will be used to locate and name the cache file.

  $hash = md5( $remote_url );
  $extension = preg_replace( '/^.*\.([^\.]+)$/', '$1', $remote_url );

  // Return the original URL if we did not get sensible values for either
  // of these variables.

  if ( ! $hash || ! $extension ) return $remote_url;

  // Work out the local file name and the URL associated with it.

  $local_file = UC_CACHE_DIR . "/$hash.$extension";
  $local_url = UC_CACHE_URL . "/$hash.$extension";

  // Check whether we have a cached file available that is not stale.

  if ( @file_exists( $local_file ) && ( ( @filemtime( $local_file ) + UC_CACHE_TIMEOUT ) > ( time() ) ) ) {

    // If so, return the URL of the cached file.

    return $local_url;

  } else {

    // Attempt to cache the file locally.
    
    if ( $remote = fopen( $remote_url, "rb" ) ) {

      if ( $local = fopen( $local_file, "wb" ) ) {

        while ( $data = fread( $remote, 8192 ) ) {
          fwrite( $local, $data, 8192 );
        }

        fclose( $local );
      }
      
      fclose( $remote );
    }
  }

  // If we reach this point, then an attempt has been made to
  // cache the file locally. We can check whether the local
  // file exists to determine which URL to return.

  if ( @file_exists( $local_file ) )
    return $local_url;
  else
    return $remote_url;
}
  
?>
