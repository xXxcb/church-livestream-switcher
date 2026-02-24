<?php
/**
 * Plugin Name: AppleCreek Livestream Switcher
 * Description: Automatically switches a YouTube embed between LIVE, UPCOMING ("Starting soon"), and a fallback playlist, based on schedule windows. Includes schedule import/export JSON.
 * Version: 1.7.3
 * Update URI: https://xanderstudios.pro/plugins/church-livestream-switcher
 * Author: Carlos Burke
 * Author URI: https://xanderstudios.pro
 * Author Email: hello@xanderstudios.pro
 */

if (!defined('ABSPATH')) exit;

if (!defined('CLS_PLUGIN_FILE')) define('CLS_PLUGIN_FILE', __FILE__);
if (!defined('CLS_PLUGIN_DIR')) define('CLS_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('CLS_PLUGIN_URL')) define('CLS_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('CLS_PLUGIN_VERSION')) define('CLS_PLUGIN_VERSION', '1.7.1');

require_once CLS_PLUGIN_DIR . 'includes/class-church-livestream-switcher.php';

Church_Livestream_Switcher::init();