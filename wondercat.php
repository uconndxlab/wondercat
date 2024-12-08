<?php
/**
 * WonderCat
 *
 * @package       WONDERCAT
 * @author        Brian Daley
 * @version       1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:   WonderCat
 * Plugin URI:    https://mydomain.com
 * Description:   Allows clients to download WonderCat data through REST endpoints.
 * Version:       1.0.0
 * Author:        Brian Daley
 * Author URI:    https://dxgroup.core.uconn.edu/
 * Text Domain:   wondercat
 * Domain Path:   /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// function my_acf_form_submission_handler($form_name, $form_data)
// {
//     log_it('plugin running');
//     // // Check if the submitted form matches your desired form name (e.g., "my-form")
//     // if ($form_name === 'my-form') {
//     //     // Get the form data
//     //     $data = array();
//     //     foreach ($form_data as $field => $value) {
//     //         $data[$field] = $value;
//     //     }

//     //     // Use the form data to query another API (e.g., using cURL or a library like Guzzle)
//     //     $api_url = 'https://example.com/api/endpoint';
//     //     $response = wp_remote_get($api_url, array('query' => $data));

//     //     if (!is_wp_error($response)) {
//     //         // Process the response data
//     //         $result_data = json_decode($response['body'], true);
//     //         // Do something with the result data (e.g., store it in a database or display it on a page)
//     //     }
//     // }
//     log_it($form_name);

//     // do_action('qm/debug', [$form_name, $form_data]);
// }

// Register the ACF form submission handler
// add_action('acf_form_submit', 'my_acf_form_submission_handler');


// function my_acf_update_value_handler($field, $value)
// {
//     // // Alter the field value as needed (e.g., trim whitespace)
//     // if ($field->name === 'my_field') { // Replace with your desired field name
//     //     $value = trim($value);
//     // }

//     // return $value;
//     do_action('qm/debug', $field, $value);

// }

$qid_key = 'field_66ec85b50b8f1';
$wikidata_key = 'field_67528b4e4f253';

// add_filter('acf/update_value', 'my_acf_update_value_handler');
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

add_action('acf/save_post', 'my_acf_save_post');
function my_acf_save_post($post_id)
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
    if (isset($values['wikidata-qid'])) {

        // Get Wikidata
        log_it($values['wikidata-qid']);

        // @url https://www.advancedcustomfields.com/resources/update_field/
        $wikidata = fetch_wikidata($values['wikidata-qid']);
        // log_it($wikidata);
        log_it('updating field');
        log_it(update_field('wikidata', $wikidata));
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