<?php
/*
Plugin Name: Kupietools Page Cache
Description: Caches full pages as static HTML, saving time on subsequent loads of heavy pages. 
Version: 1.0
Author: Michael Kupietz
Note: Requires an object cache to be installed and my KTWP Caching Toolkit plugin. Also, for the moment, this is experimental, and may be funky. It works for me. 
*/

$frontOnly=false;

function get_current_user_role() {
	
 if( is_user_logged_in() ) { // check if there is a logged in user 
	 
	 $user = wp_get_current_user(); // getting & setting the current user 
	 $roles = ( array ) $user->roles; // obtaining the role 
	 
		return $roles; // return the role for the current user 
	 
	 } else {
		 
		return array("notLoggedIn"); // if there is no logged in user return empty array  
	 
	 }
}


function check_and_serve_cached_page() {
	global $frontOnly;
  $status_code = http_response_code();
	/* TO DO: remove certain parameters, like [&]?XDEBUG_PROFILE[^&]* */
	//error_log("KTWP check_and_serve_cached_page");
    if ((is_front_page() || !$frontOnly) && !is_admin() && $status_code == 200) {
        // Log the attempt
        //error_log("KTWP Cache Check: Attempting for URL: " . $_SERVER['REQUEST_URI']);

        $link = get_current_user_role();
        $link[] = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "?" . $_SERVER['QUERY_STRING'];
        $cache_key_debug = implode('|', $link); // Use a consistent separator for logging
        //error_log("KTWP Cache Check: Generated Key: " . $cache_key_debug);

        $data = getFunctionTransient("ktwpFrontPageCacheEntry", $link);
$frontpageinsert = is_front_page()?getFunctionTransient("headerInsert","frontpage"):""; /* allow inserting dynamic data into pages refturned from cache—in this case, preload images for hero */
        if ($data !== null && $data !== '') {
		
    header("X-ktwp-page-cache: cache-hit");
            // Cache HIT! Log this specifically.
            //error_log("KTWP Cache Check: HIT!  Key: " . $cache_key_debug . ", Outputting cache: " . substr($data,0,50) . ". Exiting now.");
			/* was     echo preg_replace('/(<html)([ >])/i','\1 ktwppagecachetype="cached" ktwpcurrenttemplate="'.get_page_template().'"\2',$data,1); */
            echo preg_replace('/(<head[ >]*)/i','\1<meta name="ktwppagecacheinfo" content="cached; at '.date('m/d/Y h:i:s a', time()).'"/>'.$frontpageinsert,$data,1);
            exit; // Make absolutely sure this is here and reachable
        } else {
			    header("X-ktwp-page-cache: cache-miss");
            // Cache MISS! Log this.
            //error_log("KTWP Cache Check: MISS. Key: " . $cache_key_debug . ". Continuing WP execution.");
        }
    } else {
		    header("X-ktwp-page-cache: cache-skipped");
         //error_log("KTWP Cache Check: Admin request or 404, skipping cache check.");
    }
}
/* PREVIOUS VERSION function check_and_serve_cached_page() {
    if (!is_admin() /~ was is_front_page() to just cache front page ~/) {
       $link =   get_current_user_role() ;
     $link []=  $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ."?".$_SERVER['QUERY_STRING'];
$data = getFunctionTransient("ktwpFrontPageCacheEntry",  $link ,true); if ( $data != null) {echo "<!-- ktwp from transient -->".$data; exit;}
        // This is where you would check your cache
        // If cached content exists, output it and exit
        // Example pseudocode (replace with your actual cache checking code):
        /~
        $cached_content = your_cache_system_check();
        if ($cached_content) {
            echo $cached_content;
            exit;
        }
        ~/
    }
}
*/
function intercept_front_page_output($buffer) {
	global $frontOnly;
	  $status_code = http_response_code();
    if ((is_front_page() || !$frontOnly) && !is_admin() && $status_code== 200 /* was is_front_page() to just cache front page */) {
        // Store the buffer in a variable
       
        
        // Here you could store $captured_output in your cache system
        // Example pseudocode:
        // your_cache_system_store($captured_output);
      $link =   get_current_user_role() ;
		$thisUrl=$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ."?".$_SERVER['QUERY_STRING'];
     $link []= $thisUrl;
setFunctionTransient("ktwpFrontPageCacheEntry", $buffer, $link ,true);
    if ($link[0] != "notLoggedIn") { /* If we get here and the user is logged in, and the logged out version of page isn't cached, let's take a moment to generate and cache the logged out version too, instead of waiting for a logged-out user to load it. */
		$loggedOutLink = array("notLoggedIn",$link[1]);
		  if (getFunctionTransient("ktwpFrontPageCacheEntry", $loggedOutLink, true) === null) {
            // Temporarily force WordPress to think no user is logged in
            $current_user = wp_get_current_user();
            wp_set_current_user(0);
            
            // Get the non-logged-in version of the page
           $response = wp_remote_get($thisUrl);
$non_logged_in_buffer = wp_remote_retrieve_body($response);

            
            // Restore the original user
            wp_set_current_user($current_user->ID);
            if ($non_logged_in_buffer !== null && $non_logged_in_buffer !== '') { //don't cache if result not returned properly
            // Cache the non-logged-in version
            setFunctionTransient("ktwpFrontPageCacheEntry", $non_logged_in_buffer, $loggedOutLink, true);
		
			}
		
		
		
	}
    
    }
	}
           /* was  return preg_replace('/(<html)([ >])/i','\1 ktwppagecachetype="live"\2',$buffer,1); */

   return preg_replace('/(<head[ >]*)/i','\1<meta name="ktwppagecacheinfo" content="live; at '.date('m/d/Y h:i:s a', time()).'"/>',$buffer,1);
	
		
	
		
}

function start_output_buffer() {
	global $frontOnly;
	//error_log("KTWP Cache Check: starting output buffer");
    if ((is_front_page() || !$frontOnly) && !is_admin() /* was is_front_page() to just cache front page */) {
			//error_log("KTWP Cache Check: not admin, running");
        ob_start('intercept_front_page_output');
    }
}




$dontCacheIP= array('1.40.24.243','52.22.66.203'); //IPs never to serve cached version to

function ktwp_pc_getClientIp() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }
    if (!empty($_SERVER['HTTP_X_CLIENT_IP'])) {
        return $_SERVER['HTTP_X_CLIENT_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

$remoteIP = ktwp_pc_getClientIp();				
 if (  !in_array($remoteIP,$dontCacheIP) )
{
  
	//error_log("KTWP Cache Check: starting plugin");
// This hook runs very early, before WordPress starts building the page
add_action('template_redirect', 'check_and_serve_cached_page', 1);
	//error_log("KTWP Cache Check: starting second action");
// This only runs if check_and_serve_cached_page() didn't find cached content
add_action('template_redirect', 'start_output_buffer', 2);
}
