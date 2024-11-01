<?php
/*
Plugin Name: WP-QDA Marked Text
Author: Zachary Smith
Description: Add text to your custom category
Version: 1.0
License: GPLv2 or later
 */

$markedtext_page_title = "Marked Text";

$markedtext_pageid = null; 

register_activation_hook( __FILE__, 'markedtext_activate' );

add_shortcode( 'markedtext', 'markedtext_shortcode' );

add_action( 'init', 'markedtext_add_page');

function markedtext_activate(){
	global $wpdb;
	
	$table = $wpdb->prefix . "code_text";
	
	$markedtext_sql = "CREATE TABLE IF NOT EXISTS `$table` (
			  `id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `category_id` bigint(20) NOT NULL,
			  `post_id` bigint(20) NOT NULL,
			  `code_text` text NOT NULL,
			  PRIMARY KEY (`id`) )";
	$wpdb->query($markedtext_sql);
	
} 

	
function markedtext_add_page(){
	global $markedtext_page_title, $markedtext_pageid;
	
	$the_page = get_page_by_title($markedtext_page_title);	
	
	if (!$the_page) {
		// Create post object
		$_p = array(
	        'post_title' => $markedtext_page_title,
	        'post_content' => "[markedtext]",
	        'post_status'=> 'publish',
	        'post_type'=> 'page',
		);
		
	    $markedtext_pageid = wp_insert_post($_p);
	} else {
		$markedtext_pageid = $the_page->ID;
	}	
}

function code_text_add_pages() {
    add_menu_page(__('Code Text Manager','code_text_menu'), __('Code Text Manager','code_text_menu'), 'manage_options', 'code_text_menu_toplevel_menu', 'code_text_menu_toplevel_page' );
}

add_action('admin_menu', 'code_text_add_pages');

function code_text_menu_toplevel_page()
{
	if($_REQUEST['action']=='delete'):
	global $wpdb;
	
	$table = $wpdb->prefix . "code_text";
	
	$markedtext_delete_sql = "DELETE FROM {$table} WHERE id=".$_REQUEST['id'];
	$wpdb->query($markedtext_delete_sql);
	echo "Text deleted from category";
	endif;
}

function my_admin_styles() {
wp_enqueue_style('codetext', WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)).'codetext.css');
}

add_action('init', 'my_admin_styles');
add_action("widgets_init", 'markedtext_widget_register');

function markedtext_widget_control(){
	echo 'I am a control panel';
}

function markedtext_widget($args){
	global $markedtext_pageid;
	echo $args['before_widget'];
	echo $args['before_title'] . 'Mark Text Category' . $args['after_title'];
	$category_ids = get_all_category_ids();
	$html = "";
	if($category_ids){
		$html .= "<ul>";
			$html .= '<li class="cat-item cat-item-'.$cat_id.'">
						<a href="'.get_page_link($markedtext_pageid).'">All</a>
						</li>';
		foreach($category_ids as $cat_id) {
			$cat_name = get_cat_name($cat_id);
			$html .= '<li class="cat-item cat-item-'.$cat_id.'">
						<a href="'.get_page_link($markedtext_pageid).'/&category='.$cat_id.'">'.$cat_name.'</a>
					</li>';
		}
		$html .= "</ul>";
	}
	echo $html;
	echo $args['after_widget'];
}

function markedtext_widget_register(){
	register_sidebar_widget('Marked Text Category', 'markedtext_widget');
 	//register_widget_control('Marked Text Category', ' markedtext_widget_control');
}
 

// WP 3.0+
// add_action('add_meta_boxes', 'myplugin_add_custom_box');

// backwards compatible
add_action('admin_init', 'markedtext_add_custom_box', 1);

/* Do something with the data entered */
add_action('save_post', 'markedtext_save_postdata');

/* Adds a box to the main column on the Post and Page edit screens */
function markedtext_add_custom_box() {
	add_meta_box(
        'markedtext_sectionid',
	__( 'Add Marked Text', 'markedtext_textdomain' ),
        'markedtext_inner_custom_box',
        'post',
        'side',
        'high'
        );
        add_meta_box(
        'markedtext_sectionid',
        __( 'Add Marked Text', 'markedtext_textdomain' ),
        'markedtext_inner_custom_box',
        'page',
        'side',
        'high'
        );
}


/* Prints the box content */
function markedtext_inner_custom_box() {
	$html = '';
	
	// Use nonce for verification
	wp_nonce_field( plugin_basename(__FILE__), 'markedtext_noncename' );
	
	$category_ids = get_all_category_ids();
	if($category_ids){
		$html .='<script>function saveText(obj){document.post.submit();obj.value="";}</script>';
		$html .= "<table><tboby>";
		foreach($category_ids as $cat_id) {
			$cat_name = get_cat_name($cat_id);
			$html .= "<tr>";
			$html .= '<td><input type="text" style="border:none;background-color:#CCCCCC;width:40px;"  
						id="markedtext_text_'.$cat_id.'" name="markedtext_text['.$cat_id.']" value="" onmouseover="javascript:saveText(this);"/></td>';
						$html .= '<td><label>'.$cat_name.'</label></td>';
			$html .= "</tr>";
		}
		$html .= "</tboby></table>";
	}
	
	echo $html;
}


/* When the post is saved, saves our custom data */
function markedtext_save_postdata( $post_id) {
	
	global $wpdb;
	
	$post = get_post( $post_id );
	
	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times

	if ( !wp_verify_nonce( $_POST['markedtext_noncename'], plugin_basename(__FILE__) ) )
	return $post_id;

	// verify if this is an auto save routine.
	// If it is our form has not been submitted, so we dont want to do anything
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
	return $post_id;


	// Check permissions
	if ( 'page' == $_POST['post_type'] )
	{
		if ( !current_user_can( 'edit_page', $post_id ) )
		return $post_id;
	}
	else
	{
		if ( !current_user_can( 'edit_post', $post_id ) )
		return $post_id;
	}
	
	if ($post->post_type == 'revision') {
		return $post_id;
	}

	// OK, we're authenticated: we need to find and save the data

	$text_data = $_POST['markedtext_text'];
	
	if(isset($text_data) && is_array($text_data) ) {
		foreach ($text_data as $category_id => $code_text):
			if($code_text != ''):
				
				$data = array(
								"category_id"	=> $category_id,
								"post_id"		=> $post_id,
								"code_text"		=> $code_text
							);
				$wpdb->insert( $wpdb->prefix . "code_text" , $data, array( '%d', '%d', '%s' ) );
			endif;
		endforeach;
	}
}

//Short code to display
function markedtext_shortcode( $atts ) {
	global $wpdb, $markedtext_pageid;
	$html = "";
	$sql = "SELECT id, category_id, post_id, code_text 
			FROM " . $wpdb->prefix . "code_text ";
	if($_GET['category']) {
		$sql.= "WHERE category_id = ".$_GET['category'];
		$title = "Category : " . get_cat_name( $_GET['category']);
	} else {
		$title = "Category : All";
	}
	
	$code_text_result = $wpdb->get_results($sql);
	
	$html.="<h2><span style=\"color: #333333;\">$title</span></h2>";
	
	if($code_text_result):
		$html .="<ul>";
		foreach ($code_text_result as $text) :
			$html .= "<div class=\"entry clearfix post codetext\">";
			$html .= "<li>".$text->code_text."</li>";
			$html .= "<a href=\"".get_site_url()."/?p=".$text->post_id."\">Read more about the post</a>";
			//$html .= " | <a href=\"".get_site_url()."/?cat=".$text->category_id."\">". get_cat_name( $text->category_id)."</a>";
			$html .= "</div>";
			if ( is_user_logged_in() ) {
			$html .="<a href=wp-admin/admin.php?page=code_text_menu_toplevel_menu&action=delete&id=".$text->id." class='delete-link'>(Remove this from the category)</a>";
			} 
		endforeach;
		$html .="</ul>";
	endif;
	
	return $html;
}
