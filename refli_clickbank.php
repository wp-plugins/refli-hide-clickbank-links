<?php
/*
Plugin Name: Refli Hide Clickbank Links
Description: Instantly Hide Clickbank links by using ref.li Shortener service to hide referer
Version: 1.1
Author: alisaleem252
Author URI: http://thesetemplates.com/
*/

define('DEFAULT_API_URL', 'http://ref.li/api.php?url=%s');
define('refli_plugin_path', plugin_dir_path(__FILE__) );

/* returns a result from url */
if ( ! function_exists( 'curl_get_url' ) ){
  function curl_get_url($url) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
 }
}

if ( ! function_exists( 'get_refli_url' ) ){ /* what's the odds of that? */
 function get_refli_url($url,$format='txt') {
   $connectURL = 'http://ref.li/api.php?url='.$url;
   return curl_get_url($connectURL);
 }
}

if ( ! function_exists( 'refli_show_url' ) ){
 function refli_show_url($showurl) { /* use with echo statement */
  $url_create = get_refli_url(get_permalink( $id ));

  $kshort .= '<a href="'.$url_create.'" target="_blank">'.$url_create.'</a>';
  return $kshort;
 }
}

if ( ! function_exists( 'refli_shortcode_handler' ) ){
 function refli_shortcode_handler( $atts, $text = null, $code = "" ) {
	extract( shortcode_atts( array( 'u' => null ), $atts ) );
	
	$url = get_refli_url( $u );
	$rurl = refli_show_url($showurl); 

	if( !$u )
		return $rurl;
	if( !$text )
		return '<a href="' .$url. '">' .$url. '</a>';
	
	return '<a href="' .$url. '">' .$text. '</a>';
 }
}
add_shortcode('refli-url', 'refli_shortcode_handler');

class refli_Short_URL
{
    const META_FIELD_NAME='Shorter link';	
	
    /**
     * List of short URL website API URLs (only refli.net for now)
     */
    function api_urls()
    {
        return array(
            array(
                'name' => 'ref.li Safe Url Shortener',
                'url'  => 'http://ref.li/api.php?url=%s',
                )
            );
    }

    /**
     * Create short URL based on post URL
     */
    function create($post_id)
    {
        if (!$apiURL = get_option('refliShortUrlApiUrl')) {
            $apiURL = DEFAULT_API_URL;
        }

        // For some reason the post_name changes to /{id}-autosave/ when a post is autosaved
        $post = get_post($post_id);
        $pos = strpos($post->post_name, 'autosave');
        if ($pos !== false) {
            return false;
        }
        $pos = strpos($post->post_name, 'revision');
        if ($pos !== false) {
            return false;
        }

        $apiURL = str_replace('%s', urlencode(get_permalink($post_id)), $apiURL);

        $result = false;

        if (ini_get('allow_url_fopen')) {
            if ($handle = @fopen($apiURL, 'r')) {
                $result = fread($handle, 4096);
                fclose($handle);
            }
        } elseif (function_exists('curl_init')) {
            $ch = curl_init($apiURL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            $result = @curl_exec($ch);
            curl_close($ch);
        }

        if ($result !== false) {
            delete_post_meta($post_id, 'refliShortURL');
            $res = add_post_meta($post_id, 'refliShortURL', $result, true);
            return true;
        }
    }

    /**
     * Option list (default settings)
     */
    /*
    function options()
    {
        return array(
           'ApiUrl'         => DEFAULT_API_URL,
           'Display'        => 'Y',
           'TwitterLink'    => 'Y',
           );
    }
    */
    /**
     * Plugin settings
     *
     */
    /*
    function settings()
    {
        $apiUrls = $this->api_urls();
        $options = $this->options();
        $opt = array();

        if (!empty($_POST)) {
            foreach ($options AS $key => $val)
            {
                if (!isset($_POST[$key])) {
                    continue;
                }
                update_option('refliShortURL' . $key, $_POST[$key]);
            }
        }
        foreach ($options AS $key => $val)
        {
            $opt[$key] = get_option('refliShortURL' . $key);
        }
        include refli_plugin_path . 'template/settings.tpl.php';
    }
    */
    /**
     *
     */
    /*
    function admin_menu()
    {
        add_options_page('refli Short URL', 'Short URLs', 10, 'refli_shorturl-settings', array(&$this, 'settings'));
    }
    */
    /**
     * Display the short URL
     */
    function display($content)
    {

        global $post;

        if ($post->ID <= 0) {
            return $content;
        }

        //$options = $this->options();
	$options = array();

        foreach ($options AS $key => $val)
        {
            $opt[$key] = get_option('refliShortURL' . $key);
        }

        $shortUrl = get_post_meta($post->ID, 'refliShortURL', true);

        if (empty($shortUrl)) {
            return $content;
        }

        $shortUrlEncoded = urlencode($shortUrl);

        ob_start();
        include refli_plugin_path . 'template/public.tpl.php';
        $content .= ob_get_contents();
        ob_end_clean();

        return $content;
    }

    public function pre_get_shortlink($false, $id, $context=null, $allow_slugs=null) /* Thanks to Rob Allen */
    {
        // get the post id
        global $wp_query;
        if ($id == 0) {
            $post_id = $wp_query->get_queried_object_id();
        } else {
            $post = get_post($id);
            $post_id = $post->ID;
        }

        $short_link = get_post_meta($post_id, self::META_FIELD_NAME, true);
        if('' == $short_link) {
            $short_link = $post_id;
        }

        $url = get_refli_url(get_permalink( $id ));
        if (!empty($url)) {
            $short_link = $url;
        } else {
            $short_link = home_url($short_link);
        }
        return $short_link;
    }

}

$refli = new refli_Short_URL;

if (is_admin()) {
    add_action('edit_post', array(&$refli, 'create'));
    add_action('save_post', array(&$refli, 'create'));
    add_action('publish_post', array(&$refli, 'create'));
    //add_action('admin_menu', array(&$refli, 'admin_menu'));
    add_filter('pre_get_shortlink',  array(&$refli, 'pre_get_shortlink'), 10, 4);
} else {
    add_filter('the_content', array(&$refli, 'display'));
}