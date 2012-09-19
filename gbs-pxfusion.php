<?php
/*
Plugin Name: Group Buying Payment Processor - PxFusion
Version: Beta
Plugin URI: http://sproutventure.com/wordpress/group-buying
Description: PxFusion Add-on.
Author: Sprout Venture
Author URI: http://sproutventure.com/wordpress
Plugin Author: Dan Cameron
Contributors: Dan Cameron
Text Domain: group-buying
Domain Path: /lang
*/

add_action('gb_register_processors', 'gb_load_pxfusion');
function gb_load_pxfusion() {
	require_once('groupBuyingPxFusion.class.php');
}