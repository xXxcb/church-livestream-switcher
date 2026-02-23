<?php
/**
 * Plugin Name: AppleCreek Livestream Switcher
 * Description: Automatically switches a YouTube embed between LIVE, UPCOMING ("Starting soon"), and a fallback playlist, based on schedule windows. Includes schedule import/export JSON.
 * Version: 1.6.0
 * Author: Carlos Burke
 * Author URI: https://xanderstudios.pro
 * Author Email: hello@xanderstudios.pro
 */

if (!defined('ABSPATH')) exit;

if (!defined('CLS_PLUGIN_FILE')) define('CLS_PLUGIN_FILE', __FILE__);
if (!defined('CLS_PLUGIN_DIR')) define('CLS_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('CLS_PLUGIN_URL')) define('CLS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CLS_PLUGIN_DIR . 'includes/class-church-livestream-switcher.php';

Church_Livestream_Switcher::init();
