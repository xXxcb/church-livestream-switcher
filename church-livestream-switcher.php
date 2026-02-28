<?php
/**
 * Plugin Name: AppleCreek Livestream Switcher
 * Description: Automatically switches a YouTube embed between LIVE, UPCOMING ("Starting soon"), and a fallback playlist, based on schedule windows. Includes schedule import/export JSON.
 * Version: 1.8.12
 * Update URI: https://xanderstudios.pro/plugins/church-livestream-switcher
 * Author: Carlos Burke
 * Author URI: https://xanderstudios.pro
 * Author Email: hello@xanderstudios.pro
 */

if (!defined('ABSPATH')) exit;

if (!defined('CLS_PLUGIN_FILE')) define('CLS_PLUGIN_FILE', __FILE__);
if (!defined('CLS_PLUGIN_DIR')) define('CLS_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('CLS_PLUGIN_URL')) define('CLS_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('CLS_PLUGIN_VERSION')) {
  $clsPluginVersion = '0.0.0';
  $clsPluginHeader = @file_get_contents(__FILE__, false, null, 0, 8192);
  if (is_string($clsPluginHeader) && preg_match('/^[ \t\/*#@]*Version:\s*([^\r\n]+)$/mi', $clsPluginHeader, $m)) {
    $parsedVersion = trim((string) $m[1]);
    if ($parsedVersion !== '') $clsPluginVersion = $parsedVersion;
  }
  define('CLS_PLUGIN_VERSION', $clsPluginVersion);
}

require_once CLS_PLUGIN_DIR . 'includes/class-church-livestream-switcher.php';

if (!function_exists('cls_get_csp_nonce')) {
  function cls_get_csp_nonce() {
    return Church_Livestream_Switcher::current_csp_nonce();
  }
}

Church_Livestream_Switcher::init();
