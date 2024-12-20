<?php
/**
 * WonderCat
 *
 * @wordpress-plugin
 * Plugin Name:   WonderCat
 * Plugin URI:    https://github.com/uconndxlab/wondercat
 * Description:   Allows clients to download WonderCat data through REST endpoints.
 * Version:       1.0.1-beta.2
 * Author:        Brian Daley
 * Author URI:    https://dxgroup.core.uconn.edu/
 * Text Domain:   wondercat
 * Domain Path:   /languages
 * GitHub Plugin URI: uconndxlab/wondercat
 * GitHub Plugin URI: https://github.com/uconndxlab/wondercat
 * Primary Branch: main
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

const QID_FIELD = 'wikidata-qid';
const JSON_FIELD = 'wikidata';


if (!function_exists('log_it')) {
    function log_it($message)
    {
        if (WP_DEBUG === true) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
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
    global $qid_key, $wikidata_key;
    log_it($post_id);

    do_action('qm/debug', $post_id);


    // Get previous values.
    // $prev_values = get_fields($post_id);



    // Get submitted values.
    $values = get_fields($post_id);

    log_it($values);



    // Check if a specific value was updated.
    if (isset($values[QID_FIELD])) {

        // Get Wikidata
        log_it($values[QID_FIELD]);

        // @url https://www.advancedcustomfields.com/resources/update_field/
        $wikidata = fetch_wikidata($values[QID_FIELD]);
        // log_it($wikidata);
        log_it('updating field');
        log_it(update_field(JSON_FIELD, $wikidata));
    }
}
log_it('plugin running');

do_action('qm/debug', 'plugin running');



// function my_plugin_enqueue_styles()
// {
//     global $wikidata_key;
//     log_it('adding styles');
//     $handle = 'wondercat-styles';
//     wp_register_style($handle, '', array(), false);
//     wp_add_inline_style($handle, '[data-key="' . $wikidata_key . '"]{ display: none }');
//     // wp_add_inline_style($handle, 'body { background-color: red !important }');
//     wp_enqueue_style($handle);

//     // Load on admin side as well
//     add_action('admin_enqueue_scripts', function () use ($handle) {
//         wp_enqueue_style($handle);
//     });
// }
// add_action('wp_enqueue_scripts', 'my_plugin_enqueue_styles');


// @url https://developer.wordpress.org/reference/functions/wp_remote_get/
// @example https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=Q223880
function fetch_wikidata($qid){
    $response = wp_remote_get("https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=$qid");
    if (is_array($response) && ! is_wp_error($response)) {
        return $response['body']; // use the content
    }else {
        log_it($response);
        return '';
    }
}


// Hook into the REST API response for a specific endpoint
add_filter('rest_prepare_experience', 'wondercat_modify_json_api_response', 10, 3);

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
    $data['acf'][JSON_FIELD] = json_decode($data['acf'][JSON_FIELD]);


    // NOTE: This is probably not be the most efficient way to do this.
    // I believe this results in redundant queries to the database.
    $taxonomies = ['benefit', 'experience', 'technique'];
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