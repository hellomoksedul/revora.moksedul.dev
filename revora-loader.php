<?php
/**
 * Plugin Name: Revora (Dev Loader)
 * Description: Development loader for Revora. This file allows WordPress to recognize the plugin which is nested in the 'plugin' directory.
 * Version: 1.0.1
 * Author: Moksedul Islam
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the actual plugin file
if ( file_exists( __DIR__ . '/plugin/revora.php' ) ) {
	require_once __DIR__ . '/plugin/revora.php';
}
