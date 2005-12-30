<?php

/*

Plugin Name: URL Cache
Version: 1.1
Plugin URI: http://mcnicks.org/wordpress/url-cache/
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



/* The user agent to use for URL transfers */

$url_cache_ua = "url-cache 1.1 -- http://mcnicks.org/wordpress/url-cache/";

// Work out some settings.

$cache_dir = ABSPATH . "/wp-content/cache/url-cache/";
$cache_url = get_option( 'siteurl' ) . "/wp-content/cache/url-cache/";



/*
 * url_cache
 *  - $remote_url is the remote URL that the function will attempt
 *    to cache
 *  - returns a URL to the locally cached file, or $url if there was
 *    a failure
 */

function url_cache ( $url, $username = "", $password = "", $timeout = "" ) {
  global $cache_dir, $cache_url;

  // Return if no URL was given.

  if ( $url == "" ) return;

  // Set the timeout.

  if ( ! $timeout )
    $timeout = 3600;

  // Calculate a hash of the URL and extract the file extension. These
  // will be used to locate and name the cache file.

  $hash = md5( $url );
  $extension = preg_replace( '/^.*\.([^\.]+)$/', '$1', $url );

  // Return the original URL if we did not get sensible values for either
  // of these variables.

  if ( ! $hash || ! $extension ) return $url;

  // Work out the local file name and the URL associated with it.

  $local_file = $cache_dir . "$hash.$extension";
  $local_url = $cache_url . "$hash.$extension";

  // Check whether we have a cached file available that is not stale.

  $expires = @filemtime( $local_file ) + $timeout;

  if ( @file_exists( $local_file ) && ( expires > ( time() ) ) ) {

    // If so, return the URL of the cached file.

    return $local_url;

  } else {

    // Attempt to cache the file locally.
    
    $contents = uc_get_url( $url, $username, $password );

    if ( $contents ) {

      if ( $local = fopen( $local_file, "wb" ) )
        fwrite( $local, $contents );

      fclose( $local );
    }
  }

  // If we reach this point, then an attempt has been made to
  // cache the file locally. We can check whether the local
  // file exists to determine which URL to return.

  if ( @file_exists( $local_file ) )
    return $local_url;
  else
    return $url;
}
  


/*
 * uc_get_url
 *  - $url is the URL to fetch.
 *  - $username and $password are the authentication details to use.
 *  - returns the contents of the URL.
 */

function uc_get_url ( $url, $username, $password ) {
  global $url_cache_ua;
 
  $handle = curl_init( $url );

  if ( $username && $password )
    curl_setopt( $handle, CURLOPT_USERPWD, "$username:$password" );

  curl_setopt( $handle, CURLOPT_RETURNTRANSFER, 1 );
  curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 1 );
  curl_setopt( $handle, CURLOPT_TIMEOUT, 4 );
  curl_setopt( $handle, CURLOPT_USERAGENT, $url_cache_ua );

  $buffer = curl_exec( $handle );

  curl_close( $handle );

  return $buffer;
}



/* 
 * uc_get_rest_response
 *  - $method is the REST method to be cached
 *  - $slug is used to make the cached response more unique
 *  - (optional) $timeout is a specific timeout value to use
 *  - (optional) $any will return any available cached
 *    response, regardless of how stale it is.
 *
 * This function uses $method and $cache to make up a unique filename
 * which is used to store REST responses. If the file associated with
 * $request and $slug exists, its contents are returned. If the file
 * is stale, then nothing is returned, which should prompt a refresh.
 */

function uc_get_rest_response( $method, $slug, $timeout = 0, $any = 0 ) {
  global $cache_dir;

  // Return if the method and slug are not specified.

  if ( ! $method || ! $slug ) return;

  // Set the timeout to the default value if none has been specified.

  if ( ! $timeout )
    $timeout = 3600;

  // Work out the file name that the response should be cached in.

  $filename = $cache_dir."rest--$method--$slug";

  // Return if the file does not exist.

  if ( ! @file_exists( $filename ) ) return;

  // Check whether the cached response is stale, unless we have been
  // told to return anything that is available.

  if ( ! $any )
    if ( ( @filemtime( $filename ) + $timeout ) < ( time() ) )
      return;

  // Otherwise, open it and return the contents.

  $handle = @fopen( $filename, "r" );

  if ( $handle ) {

    $cached_response = "";

    while ( $part = @fread( $handle, 8192 ) ) {
      $cached_response .= $part;
    }

    @fclose( $handle );

    return $cached_response;
  }
}



/*
 * uc_cache_rest_response
 *  - $method is the REST method to be cached
 *  - $slug is used to make the cached response more unique
 *  - $response is the actual response that we should cache
 *
 * This function uses $method and $cache to make up a unique filename
 * which is used to store REST responses. When called, the function
 * writes the given response to that filename.
 */

function uc_cache_rest_response( $method, $slug, $response ) {
  global $cache_dir;

  // Return if the arguments are not specified.

  if ( ! $method || ! $slug  || ! $response ) return;

  // Work out the file name that the response should be cached in.

  $filename = $cache_dir."rest--$method--$slug";

  // Open it for writing and dump the response in.

  $handle = @fopen( $filename, "w+" );

  if ( $handle ) {

    @fwrite( $handle, $response );
    @fclose( $handle );
  }

  // That almost seemed too simple.
}



/*
 * uc_cache_value
 *  - $name the name of the value to cache
 *  - $value the value itself
 *
 * This function uses the standard caching functions above to cache
 * name/value pairs. At the moment this is a bit of a fudge, making
 * use of the special method, "value". */

function uc_cache_value( $name, $value ) {
  return uc_cache_rest_response( "value", $name, $value );
}



/*
 * uc_get_cached_value
 *  - $name the name of the value to cache
 *  - returns the cached value associated with $name
 *
 * This function uses the standard caching functions above to return
 * a previously cached value associated with a name/value pair. At the
 * moment this is a bit of a fudge, making use of the special method,
 * "value".
 */

function uc_get_value( $name, $timeout = 0 ) {
  return uc_get_rest_response( "value", $name, $timeout );
}

?>
