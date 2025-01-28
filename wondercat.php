<?php

/**
 * WonderCat
 *
 * @wordpress-plugin
 * Plugin Name:   WonderCat
 * Plugin URI:    https://github.com/uconndxlab/wondercat
 * Description:   Allows clients to download WonderCat data through REST endpoints.
 * Version:       1.0.1-rc.1
 * Author:        Brian Daley
 * Author URI:    https://dxgroup.core.uconn.edu/
 * Text Domain:   wondercat
 * Domain Path:   /languages
 * GitHub Plugin URI: uconndxlab/wondercat
 * GitHub Plugin URI: https://github.com/uconndxlab/wondercat
 * Primary Branch: main
 * Requires Plugin: advanced-custom-fields
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// require_once 'vendor/autoload.php';


define('WC_LOGFILE', plugin_dir_path(__FILE__) . 'log' . DIRECTORY_SEPARATOR . 'wondercat.log');


// @todo Use this library instead github-updater dependency: https://github.com/YahnisElsts/plugin-update-checker
// @todo Options page for WonderCat settings?: https://devs.redux.io/guides/basics/install.html
// @toto Remove dependecy on ACF? Something like carbon fields?: https://docs.carbonfields.net/


const WC_QID_FIELD = 'wikidata-qid';
const WC_JSON_FIELD = 'wikidata';
const WC_WIKIDATA_LAST_UPDATED = 'wikidata_last_update';


// Check if ACF is active
if ( ! class_exists('ACF') ) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>WonderCat requires the Advanced Custom Fields plugin to be installed and activated.</p></div>';
    });
    return;
}else{
    require plugin_dir_path(__FILE__) . 'acf' . DIRECTORY_SEPARATOR . '2025-01-27.php';

}

if (!function_exists('wc_log')) {
    function wc_log($message)
    {
        if (WP_DEBUG === true) {
            $logfile = WC_LOGFILE;
            $timestamp = date("Y-m-d H:i:s");
            $formatted_message = is_array($message) || is_object($message) ? print_r($message, true) : $message;
            @error_log("[$timestamp] $formatted_message\n", 3, $logfile);
        }
    }
}

add_action('acf/save_post', 'wondercat_acf_save_post');

/**
 * @param $post_id
 * @return void
 * @todo This should run before the post is saved or you won't see the changes in the new editor
 */
function wondercat_acf_save_post($post_id): void
{
    wc_log($post_id);



    // Get previous values.
    // $prev_values = get_fields($post_id);



    // Get submitted values.
    $values = get_fields($post_id);

    wc_log($values);



    // Check if a specific value was updated.
    if (isset($values[WC_QID_FIELD])) {

        // Get Wikidata
        wc_log($values[WC_QID_FIELD]);

        // @url https://www.advancedcustomfields.com/resources/update_field/
        $wikidata = fetch_wikidata($values[WC_QID_FIELD]);
        
        if($wikidata !== false){
            // Update the field
            update_field(WC_JSON_FIELD, $wikidata);

            update_field(WC_WIKIDATA_LAST_UPDATED, current_time(DATE_RFC822));
        }
    }
}
wc_log('plugin running');




// @url https://developer.wordpress.org/reference/functions/wp_remote_get/
// @example https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=Q223880
function fetch_wikidata($qid){
    $response = wp_remote_get("https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=$qid");
    if (is_array($response) && ! is_wp_error($response)) {
        return $response['body']; // use the content
    }else {
        wc_log($response);
        return false;
    }
}


// Hook into the REST API response for a specific endpoint
add_filter('rest_prepare_user_experience', 'wondercat_modify_json_api_response', 10, 3);

/**
 * Modify the JSON API response before it's rendered.
 *
 * @param WP_REST_Response $response The response object.
 * @param WP_Post $post The post object.
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The modified response object.
 */
function wondercat_modify_json_api_response($response, $post, $request)
{
    // Get the response data
    $data = $response->get_data();

    // The wikidata is stored as a string. Convert it to JSON.
    $data['acf'][WC_JSON_FIELD] = json_decode($data['acf'][WC_JSON_FIELD]);


    // NOTE: This is probably not be the most efficient way to do this.
    // I believe this results in redundant queries to the database.
    $taxonomies = get_post_taxonomies($post);

    foreach ($taxonomies as $taxonomy) {
        $terms = get_the_terms($post->ID, $taxonomy);
        if (!is_wp_error($terms) && !empty($terms)) {
            $data[$taxonomy] = [];
            $data['acf'][$taxonomy] = [];
            foreach ($terms as $term) {
                $term_record = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description,
                ];

                // Sets the terms on the main object
                $data[$taxonomy][] = $term_record;

                // Sets the terms on the acf object (just in case)
                $data['acf'][$taxonomy][] = $term_record;

            }
        }
    }    

    // Set the modified data back to the response
    $response->set_data($data);

    return $response;
}

// Hook into the ACF render field action for a specific textarea field
add_action( "acf/load_field", 'set_fields_readonly');
/**
 * Customize the HTML output for a specific ACF textarea field to make it read-only.
 *
 * @param array $field The field array containing all settings.
 */
function set_fields_readonly($field) {

    if ($field['_name'] === WC_JSON_FIELD) {
        $field['readonly'] = 1; // Set the field to read-only
        // $field['rows'] = 20; // rows
    }

    if ($field['_name'] === WC_WIKIDATA_LAST_UPDATED) {
        $field['readonly'] = 1; // Set the field to read-only
    }

    return $field;

}


add_action('init', 'wondercat_add_terms_from_json');

// Add terms from JSON file on plugin activation
function wondercat_add_terms_from_json() {

    wc_log('Adding terms from JSON files');

    $json_files = [
        'technology',
        'experience',
        'benefits'
    ];

    foreach($json_files as $json_file){
        $json_file = plugin_dir_path(__FILE__) . 'terms' . DIRECTORY_SEPARATOR . $json_file . '.json';
        if (file_exists($json_file)) {
            $json_data = file_get_contents($json_file);
            $terms = json_decode($json_data, true);

            if (is_array($terms)) {
                foreach ($terms as $term) {
                    if (!term_exists($term['name'], $term['taxonomy'])) {
                        wc_log('Adding term: ' . $term['name'] . ' in ' . $term['taxonomy']);
                        wp_insert_term(
                            $term['name'],
                            $term['taxonomy'],
                            [
                                'description' => $term['description'],
                                'slug' => $term['slug']
                            ]
                        );
                    }
                }
            }
        }else{
            wc_log('File not found: ' . $json_file);
        }

    }
}

