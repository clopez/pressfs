<?php
/*
Plugin Name: PressFS
Plugin URI:
Description: API for the PressFS filesystem process
Version: 0.2.0
Author: Joseph Scott
Author URI: http://josephscott.org/
License: MIT
 */

define( 'PRESSFS_VERSION', '0.1.0' );

if ( !class_exists( 'PressFS' ) ) {
	require dirname( __FILE__ ) . '/class-pressfs.php';
}

if ( !defined( 'PRESSFS_CLASS' ) ) {
	define( 'PRESSFS_CLASS', 'PressFS' );
}

$pressfs_class = PRESSFS_CLASS;
$pressfs = new $pressfs_class();
$pressfs->init();
