<?php

if (!defined('ABSPATH')) exit;

class Church_Livestream_Switcher {
  const OPT_KEY = 'cls_settings';
  const TRANSIENT_KEY = 'cls_live_status';
  const GITHUB_RELEASE_TRANSIENT_PREFIX = 'cls_github_release_';
  const ADMIN_PAGE_SLUG = 'church-livestream-switcher';
  const LOW_QUOTA_CACHE_TTL_SECONDS = 600;
  const LOW_QUOTA_POLL_SECONDS = 300;
  const LOW_QUOTA_UPLOADS_TTL_SECONDS = 604800;
  const LOW_QUOTA_LOOKBACK_MAX = 10;
  const UPCOMING_CACHE_MAX_SECONDS = 30;
  const LIVE_CACHE_MAX_SECONDS = 20;
  const UPCOMING_STALE_GRACE_SECONDS = 900;

  // Bootstrap all hooks, shortcodes, and REST wiring for the plugin.
  public static function init() {
    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_shortcode('church_livestream', [__CLASS__, 'shortcode']);
    add_shortcode('church_livestream_chat', [__CLASS__, 'shortcode_chat']);
    add_action('rest_api_init', [__CLASS__, 'register_rest']);
    add_filter('plugin_action_links_' . self::plugin_basename(), [__CLASS__, 'filter_plugin_action_links']);
    add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'filter_update_plugins']);
    add_filter('plugins_api', [__CLASS__, 'filter_plugins_api'], 20, 3);
    add_filter('upgrader_source_selection', [__CLASS__, 'filter_upgrader_source_selection'], 10, 4);
  }

  // Return default settings array used when no user values exist.
  public static function defaults() {
    return [
      'enabled' => 1,
      'timezone' => 'America/Toronto',
      'channel_id' => '',
      'playlist_id' => '',
      'api_key' => '',
      'cache_ttl_seconds' => 120,
      'poll_interval_seconds' => 120,
      'lookback_count' => 15,
      'uploads_cache_ttl_seconds' => 86400,
      'low_quota_mode' => 0,
      'chat_show_upcoming' => 1,
      'player_max_width' => '100%',
      'player_aspect_ratio' => '16:9',
      'player_fixed_height_px' => 0,
      'player_border_radius_px' => 0,
      'player_box_shadow' => '',
      'player_background' => '#000000',
      'player_wrapper_class' => '',
      'player_iframe_class' => '',
      'player_frame_title' => 'YouTube livestream player',
      'player_loading' => 'eager',
      'player_referrerpolicy' => 'strict-origin-when-cross-origin',
      'player_allow' => 'autoplay; encrypted-media; picture-in-picture; fullscreen',
      'player_allowfullscreen' => 1,
      'player_controls' => 1,
      'player_autoplay_live' => 1,
      'player_force_live_autoplay' => 1,
      'player_autoplay_playlist' => 0,
      'player_mute_live' => 1,
      'player_mute_playlist' => 0,
      'player_loop' => 0,
      'player_rel' => 0,
      'player_fs' => 1,
      'player_modestbranding' => 1,
      'player_disablekb' => 0,
      'player_iv_load_policy' => 3,
      'player_cc_load_policy' => 0,
      'player_color' => 'red',
      'player_playsinline' => 1,
      'player_start_seconds' => 0,
      'player_end_seconds' => 0,
      'player_hl' => '',
      'player_cc_lang_pref' => '',
      'player_origin_mode' => 'auto',
      'player_origin_custom' => '',
      'player_custom_params' => '',
      'github_updates_enabled' => 0,
      'github_repo' => '',
      'github_token' => '',
      'github_include_prerelease' => 0,
      'github_cache_ttl_seconds' => 21600,
      'github_asset_name' => '',
      'schedule' => [],
      'one_time_events' => [],
      'import_json' => '',
    ];
  }

  // Fetch saved settings merged with defaults.
  public static function get_settings() {
    $saved = get_option(self::OPT_KEY, []);
    return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
  }

  // Register the settings array with WordPress.
  public static function register_settings() {
    register_setting('cls_group', self::OPT_KEY, [
      'type' => 'array',
      'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
      'default' => self::defaults(),
    ]);
  }

  // Sanitize and normalize all settings fields on save.
  public static function sanitize_settings($input) {
    $d = self::defaults();
    $existing = self::get_settings();
    $out = [];

    $out['enabled'] = !empty($input['enabled']) ? 1 : 0;
    $out['timezone'] = isset($input['timezone']) ? sanitize_text_field($input['timezone']) : $d['timezone'];
    $out['channel_id'] = isset($input['channel_id']) ? sanitize_text_field($input['channel_id']) : '';
    $out['playlist_id'] = isset($input['playlist_id']) ? sanitize_text_field($input['playlist_id']) : '';
    $apiKeyInput = isset($input['api_key']) ? sanitize_text_field((string) $input['api_key']) : '';
    if (!empty($input['api_key_clear'])) {
      $out['api_key'] = '';
    } else {
      $out['api_key'] = trim($apiKeyInput) !== '' ? $apiKeyInput : ((string) ($existing['api_key'] ?? ''));
    }
    $out['cache_ttl_seconds'] = isset($input['cache_ttl_seconds']) ? max(10, intval($input['cache_ttl_seconds'])) : $d['cache_ttl_seconds'];
    $out['poll_interval_seconds'] = isset($input['poll_interval_seconds']) ? max(30, intval($input['poll_interval_seconds'])) : $d['poll_interval_seconds'];
    $out['lookback_count'] = isset($input['lookback_count']) ? max(3, min(25, intval($input['lookback_count']))) : $d['lookback_count'];
    $out['uploads_cache_ttl_seconds'] = isset($input['uploads_cache_ttl_seconds']) ? max(3600, intval($input['uploads_cache_ttl_seconds'])) : $d['uploads_cache_ttl_seconds'];
    $out['low_quota_mode'] = !empty($input['low_quota_mode']) ? 1 : 0;
    $out['chat_show_upcoming'] = !empty($input['chat_show_upcoming']) ? 1 : 0;
    $out['player_max_width'] = isset($input['player_max_width']) ? self::sanitize_text_or_default($input['player_max_width'], $d['player_max_width']) : $d['player_max_width'];
    $out['player_aspect_ratio'] = isset($input['player_aspect_ratio']) ? self::sanitize_aspect_ratio($input['player_aspect_ratio'], $d['player_aspect_ratio']) : $d['player_aspect_ratio'];
    $out['player_fixed_height_px'] = isset($input['player_fixed_height_px']) ? max(0, min(2160, intval($input['player_fixed_height_px']))) : $d['player_fixed_height_px'];
    $out['player_border_radius_px'] = isset($input['player_border_radius_px']) ? max(0, min(200, intval($input['player_border_radius_px']))) : $d['player_border_radius_px'];
    $out['player_box_shadow'] = isset($input['player_box_shadow']) ? sanitize_text_field($input['player_box_shadow']) : $d['player_box_shadow'];
    $out['player_background'] = isset($input['player_background']) ? self::sanitize_hex_or_default($input['player_background'], $d['player_background']) : $d['player_background'];
    $out['player_wrapper_class'] = isset($input['player_wrapper_class']) ? self::sanitize_class_list($input['player_wrapper_class']) : $d['player_wrapper_class'];
    $out['player_iframe_class'] = isset($input['player_iframe_class']) ? self::sanitize_class_list($input['player_iframe_class']) : $d['player_iframe_class'];
    $out['player_frame_title'] = isset($input['player_frame_title']) ? self::sanitize_text_or_default($input['player_frame_title'], $d['player_frame_title']) : $d['player_frame_title'];
    $out['player_loading'] = isset($input['player_loading']) ? self::sanitize_choice($input['player_loading'], ['eager', 'lazy'], $d['player_loading']) : $d['player_loading'];
    $out['player_referrerpolicy'] = isset($input['player_referrerpolicy']) ? self::sanitize_choice($input['player_referrerpolicy'], self::referrer_policies(), $d['player_referrerpolicy']) : $d['player_referrerpolicy'];
    $out['player_allow'] = isset($input['player_allow']) ? self::sanitize_text_or_default($input['player_allow'], $d['player_allow']) : $d['player_allow'];
    $out['player_allowfullscreen'] = !empty($input['player_allowfullscreen']) ? 1 : 0;

    $out['player_controls'] = !empty($input['player_controls']) ? 1 : 0;
    $out['player_autoplay_live'] = !empty($input['player_autoplay_live']) ? 1 : 0;
    $out['player_force_live_autoplay'] = !empty($input['player_force_live_autoplay']) ? 1 : 0;
    $out['player_autoplay_playlist'] = !empty($input['player_autoplay_playlist']) ? 1 : 0;
    $out['player_mute_live'] = !empty($input['player_mute_live']) ? 1 : 0;
    $out['player_mute_playlist'] = !empty($input['player_mute_playlist']) ? 1 : 0;
    $out['player_loop'] = !empty($input['player_loop']) ? 1 : 0;
    $out['player_rel'] = !empty($input['player_rel']) ? 1 : 0;
    $out['player_fs'] = !empty($input['player_fs']) ? 1 : 0;
    $out['player_modestbranding'] = !empty($input['player_modestbranding']) ? 1 : 0;
    $out['player_disablekb'] = !empty($input['player_disablekb']) ? 1 : 0;
    $out['player_iv_load_policy'] = isset($input['player_iv_load_policy']) && intval($input['player_iv_load_policy']) === 1 ? 1 : 3;
    $out['player_cc_load_policy'] = !empty($input['player_cc_load_policy']) ? 1 : 0;
    $out['player_color'] = isset($input['player_color']) ? self::sanitize_choice($input['player_color'], ['red', 'white'], $d['player_color']) : $d['player_color'];
    $out['player_playsinline'] = !empty($input['player_playsinline']) ? 1 : 0;
    $out['player_start_seconds'] = isset($input['player_start_seconds']) ? max(0, intval($input['player_start_seconds'])) : $d['player_start_seconds'];
    $out['player_end_seconds'] = isset($input['player_end_seconds']) ? max(0, intval($input['player_end_seconds'])) : $d['player_end_seconds'];
    $out['player_hl'] = isset($input['player_hl']) ? self::sanitize_lang_tag($input['player_hl']) : $d['player_hl'];
    $out['player_cc_lang_pref'] = isset($input['player_cc_lang_pref']) ? self::sanitize_lang_tag($input['player_cc_lang_pref']) : $d['player_cc_lang_pref'];
    $out['player_origin_mode'] = isset($input['player_origin_mode']) ? self::sanitize_choice($input['player_origin_mode'], ['auto', 'off', 'custom'], $d['player_origin_mode']) : $d['player_origin_mode'];
    $out['player_origin_custom'] = isset($input['player_origin_custom']) ? self::sanitize_origin_url($input['player_origin_custom']) : $d['player_origin_custom'];
    $out['player_custom_params'] = isset($input['player_custom_params']) ? self::sanitize_embed_query_string($input['player_custom_params']) : $d['player_custom_params'];

    // Accessibility safeguard: if live audio starts muted, keep controls visible so viewers can unmute.
    if (!empty($out['player_mute_live'])) {
      $out['player_controls'] = 1;
    }

    $out['github_updates_enabled'] = !empty($input['github_updates_enabled']) ? 1 : 0;
    $out['github_repo'] = isset($input['github_repo']) ? self::sanitize_github_repo($input['github_repo']) : $d['github_repo'];
    $githubTokenInput = isset($input['github_token']) ? sanitize_text_field((string) $input['github_token']) : '';
    if (!empty($input['github_token_clear'])) {
      $out['github_token'] = '';
    } else {
      $out['github_token'] = trim($githubTokenInput) !== '' ? $githubTokenInput : ((string) ($existing['github_token'] ?? ''));
    }
    $out['github_include_prerelease'] = !empty($input['github_include_prerelease']) ? 1 : 0;
    $out['github_cache_ttl_seconds'] = isset($input['github_cache_ttl_seconds']) ? max(300, intval($input['github_cache_ttl_seconds'])) : $d['github_cache_ttl_seconds'];
    $out['github_asset_name'] = isset($input['github_asset_name']) ? sanitize_text_field((string) $input['github_asset_name']) : $d['github_asset_name'];

    if ($out['player_origin_mode'] !== 'custom') {
      $out['player_origin_custom'] = '';
    }

    $import_json = isset($input['import_json']) ? trim((string)$input['import_json']) : '';
    if ($import_json !== '') {
      $decoded = json_decode($import_json, true);
      if (is_array($decoded)) {
        $schedule = isset($decoded['schedule']) ? $decoded['schedule'] : $decoded;
        $oneTimeEvents = isset($decoded['one_time_events']) ? $decoded['one_time_events'] : [];
        $out['schedule'] = self::sanitize_schedule($schedule);
        $out['one_time_events'] = self::sanitize_one_time_events($oneTimeEvents);
      } else {
        $out['schedule'] = self::sanitize_schedule(isset($input['schedule']) ? $input['schedule'] : []);
        $out['one_time_events'] = self::sanitize_one_time_events(isset($input['one_time_events']) ? $input['one_time_events'] : []);
      }
      $out['import_json'] = '';
    } else {
      $out['import_json'] = '';
      $out['schedule'] = self::sanitize_schedule(isset($input['schedule']) ? $input['schedule'] : []);
      $out['one_time_events'] = self::sanitize_one_time_events(isset($input['one_time_events']) ? $input['one_time_events'] : []);
    }

    delete_transient(self::TRANSIENT_KEY);
    self::delete_github_release_cache($d['github_repo'], !empty($d['github_include_prerelease']));
    self::delete_github_release_cache($out['github_repo'], !empty($out['github_include_prerelease']));
    return wp_parse_args($out, $d);
  }

  // Validate GitHub repo string in owner/repo format.
  private static function sanitize_github_repo($value) {
    $clean = trim((string) $value);
    if ($clean === '') return '';
    if (!preg_match('/^[A-Za-z0-9._-]+\/[A-Za-z0-9._-]+$/', $clean)) return '';
    return $clean;
  }

  // Return a masked preview of a secret value, preserving only the tail.
  private static function masked_secret_preview($value, $visibleTail = 4) {
    $raw = trim((string) $value);
    if ($raw === '') return 'Not set';

    $tail = max(0, intval($visibleTail));
    $len = strlen($raw);
    if ($tail <= 0 || $len <= $tail) return str_repeat('*', max(8, $len));

    return str_repeat('*', max(8, $len - $tail)) . substr($raw, -$tail);
  }

  // Sanitize text, falling back to default when empty.
  private static function sanitize_text_or_default($value, $default) {
    $clean = sanitize_text_field((string) $value);
    return $clean !== '' ? $clean : $default;
  }

  // Ensure a value is within an allowed list, else return default.
  private static function sanitize_choice($value, $allowed, $default) {
    $clean = sanitize_text_field((string) $value);
    return in_array($clean, $allowed, true) ? $clean : $default;
  }

  // Sanitize a hex color or fall back to default.
  private static function sanitize_hex_or_default($value, $default) {
    $clean = sanitize_hex_color((string) $value);
    return $clean ? $clean : $default;
  }

  // Parse aspect ratio strings like 16:9; return default on failure.
  private static function sanitize_aspect_ratio($value, $default = '16:9') {
    $raw = trim((string) $value);
    if (preg_match('/^(\d{1,3})\s*[:\/]\s*(\d{1,3})$/', $raw, $m)) {
      $w = intval($m[1]);
      $h = intval($m[2]);
      if ($w > 0 && $h > 0) return $w . ':' . $h;
    }
    return $default;
  }

  // Convert a ratio string to CSS padding-top percentage.
  private static function aspect_ratio_to_padding_percent($ratio) {
    if (!is_string($ratio) || !preg_match('/^(\d{1,3}):(\d{1,3})$/', trim($ratio), $m)) return 56.25;
    $w = intval($m[1]);
    $h = intval($m[2]);
    if ($w <= 0 || $h <= 0) return 56.25;
    return round(($h / $w) * 100, 4);
  }

  // Sanitize a space-delimited list of CSS classes.
  private static function sanitize_class_list($value) {
    $clean = preg_replace('/[^A-Za-z0-9_\-\s]/', '', (string) $value);
    $clean = preg_replace('/\s+/', ' ', trim((string) $clean));
    return $clean ?: '';
  }

  // Validate BCP 47 language tag format.
  private static function sanitize_lang_tag($value) {
    $clean = trim((string) $value);
    if ($clean === '') return '';
    if (!preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8}){0,2}$/', $clean)) return '';
    return $clean;
  }

  // Supported referrer policy values for the player iframe.
  private static function referrer_policies() {
    return [
      '',
      'no-referrer',
      'no-referrer-when-downgrade',
      'origin',
      'origin-when-cross-origin',
      'same-origin',
      'strict-origin',
      'strict-origin-when-cross-origin',
      'unsafe-url',
    ];
  }

  // Extract and normalize origin (scheme://host[:port]) from a URL.
  private static function sanitize_origin_url($value) {
    $url = esc_url_raw(trim((string) $value), ['http', 'https']);
    if (!$url) return '';

    $parts = wp_parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return '';

    $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
    if (!empty($parts['port'])) $origin .= ':' . intval($parts['port']);
    return $origin;
  }

  // Compute the site home origin for use in iframe origin param.
  private static function normalize_home_origin() {
    return self::sanitize_origin_url(home_url('/'));
  }

  // Clean an arbitrary query string for safe embed overrides.
  private static function sanitize_embed_query_string($value) {
    $raw = ltrim(trim((string) $value), '?&');
    if ($raw === '') return '';

    $decoded = [];
    parse_str($raw, $decoded);
    if (!is_array($decoded)) return '';

    $clean = [];
    foreach ($decoded as $key => $val) {
      $k = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $key);
      if ($k === '' || is_array($val)) continue;
      $clean[$k] = sanitize_text_field((string) $val);
    }

    if (empty($clean)) return '';
    return http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
  }

  // Validate and normalize weekly schedule rows.
  private static function sanitize_schedule($schedule) {
    $clean = [];
    if (!is_array($schedule)) return $clean;

    foreach ($schedule as $row) {
      if (!is_array($row)) continue;
      $day = isset($row['day']) ? intval($row['day']) : null;
      $start = isset($row['start']) ? sanitize_text_field($row['start']) : '';
      $end = isset($row['end']) ? sanitize_text_field($row['end']) : '';

      if ($day === null || $day < 0 || $day > 6) continue;
      if (!preg_match('/^\d{2}:\d{2}$/', $start)) continue;
      if (!preg_match('/^\d{2}:\d{2}$/', $end)) continue;

      $clean[] = ['day' => $day, 'start' => $start, 'end' => $end];
    }
    return $clean;
  }

  // Validate and normalize one-time event rows.
  private static function sanitize_one_time_events($events) {
    $clean = [];
    if (!is_array($events)) return $clean;

    foreach ($events as $row) {
      if (!is_array($row)) continue;
      $date = isset($row['date']) ? sanitize_text_field($row['date']) : '';
      $start = isset($row['start']) ? sanitize_text_field($row['start']) : '';
      $end = isset($row['end']) ? sanitize_text_field($row['end']) : '';

      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
      if (!preg_match('/^\d{2}:\d{2}$/', $start)) continue;
      if (!preg_match('/^\d{2}:\d{2}$/', $end)) continue;

      $dt = DateTime::createFromFormat('Y-m-d', $date);
      if (!$dt || $dt->format('Y-m-d') !== $date) continue;

      $clean[] = ['date' => $date, 'start' => $start, 'end' => $end];
    }
    return $clean;
  }

  // Register the settings page under WordPress options.
  public static function admin_menu() {
    add_options_page(
      'AppleCreek Livestream Switcher',
      'AC Livestream',
      'manage_options',
      self::ADMIN_PAGE_SLUG,
      [__CLASS__, 'settings_page']
    );
  }

  // Add Settings link to the plugin row on Plugins page.
  public static function filter_plugin_action_links($links) {
    $settingsUrl = add_query_arg(
      ['page' => self::ADMIN_PAGE_SLUG],
      admin_url('options-general.php')
    );

    array_unshift(
      $links,
      '<a href="' . esc_url($settingsUrl) . '">' . esc_html__('Settings') . '</a>'
    );

    return $links;
  }

  // Render the admin settings page.
  public static function settings_page() {
    if (!current_user_can('manage_options')) return;
    $settings = self::get_settings();

    $templateVars = [
      's' => $settings,
      'days' => ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
      'optKey' => self::OPT_KEY,
      'lowQuotaCacheTtlSeconds' => self::LOW_QUOTA_CACHE_TTL_SECONDS,
      'lowQuotaPollSeconds' => self::LOW_QUOTA_POLL_SECONDS,
      'lowQuotaUploadsTtlSeconds' => self::LOW_QUOTA_UPLOADS_TTL_SECONDS,
      'lowQuotaLookbackMax' => self::LOW_QUOTA_LOOKBACK_MAX,
      'referrerPolicies' => self::referrer_policies(),
      'apiKeyPreview' => self::masked_secret_preview($settings['api_key'] ?? ''),
      'githubTokenPreview' => self::masked_secret_preview($settings['github_token'] ?? ''),
    ];

    self::render_template('admin/settings-page.php', $templateVars, true);
  }

  // Render a template file with extracted variables.
  private static function render_template($relativePath, $vars = [], $echo = false) {
    $templateFile = trailingslashit(CLS_PLUGIN_DIR) . 'templates/' . ltrim((string) $relativePath, '/');
    if (!file_exists($templateFile)) return '';

    if (is_array($vars) && !empty($vars)) {
      extract($vars, EXTR_SKIP);
    }

    ob_start();
    include $templateFile;
    $html = ob_get_clean();

    if ($echo) echo $html;
    return $html;
  }

  // Return plugin basename (path used by WP).
  private static function plugin_basename() {
    return plugin_basename(CLS_PLUGIN_FILE);
  }

  // Compute the plugin slug from basename.
  private static function plugin_slug() {
    $base = self::plugin_basename();
    $dir = dirname($base);
    return ($dir === '.' || $dir === DIRECTORY_SEPARATOR) ? basename($base, '.php') : $dir;
  }

  // Get current plugin version constant or fallback.
  private static function plugin_version() {
    return defined('CLS_PLUGIN_VERSION') ? (string) CLS_PLUGIN_VERSION : '0.0.0';
  }

  // Build array of available plugin icon URLs for updater.
  private static function plugin_icons() {
    $baseDir = trailingslashit(CLS_PLUGIN_DIR) . 'assets/';
    $baseUrl = trailingslashit(CLS_PLUGIN_URL) . 'assets/';
    $icons = [];

    if (file_exists($baseDir . 'icon-128x128.png')) {
      $icons['1x'] = $baseUrl . 'icon-128x128.png';
    }
    if (file_exists($baseDir . 'icon-256x256.png')) {
      $icons['2x'] = $baseUrl . 'icon-256x256.png';
    }
    if (file_exists($baseDir . 'icon.svg')) {
      $icons['svg'] = $baseUrl . 'icon.svg';
    }

    if (empty($icons['1x']) && !empty($icons['svg'])) {
      $icons['1x'] = $icons['svg'];
    }
    if (empty($icons['2x']) && !empty($icons['svg'])) {
      $icons['2x'] = $icons['svg'];
    }
    if (empty($icons['default'])) {
      if (!empty($icons['2x'])) {
        $icons['default'] = $icons['2x'];
      } elseif (!empty($icons['1x'])) {
        $icons['default'] = $icons['1x'];
      } elseif (!empty($icons['svg'])) {
        $icons['default'] = $icons['svg'];
      }
    }

    return $icons;
  }

  // Key helper for GitHub release transient storage.
  private static function github_cache_key($repo, $includePrerelease) {
    return self::GITHUB_RELEASE_TRANSIENT_PREFIX . md5(strtolower(trim((string) $repo)) . '|' . (!empty($includePrerelease) ? '1' : '0'));
  }

  // Clear cached GitHub release metadata for a repo.
  private static function delete_github_release_cache($repo, $includePrerelease) {
    $repo = self::sanitize_github_repo($repo);
    if ($repo === '') return;
    delete_transient(self::github_cache_key($repo, $includePrerelease));
  }

  // Strip leading v and unsafe chars from a release tag.
  private static function normalize_release_version($tag) {
    $version = trim((string) $tag);
    if (preg_match('/^v(?=\d)/i', $version)) {
      $version = substr($version, 1);
    }
    $version = preg_replace('/[^0-9A-Za-z.\-+]/', '', (string) $version);
    return trim((string) $version);
  }

  // Choose a suitable release entry from GitHub API payload.
  private static function pick_github_release($payload, $includePrerelease) {
    if (is_array($payload) && isset($payload['tag_name'])) {
      return $payload;
    }
    if (!is_array($payload)) return null;

    foreach ($payload as $item) {
      if (!is_array($item)) continue;
      if (!empty($item['draft'])) continue;
      if (empty($includePrerelease) && !empty($item['prerelease'])) continue;
      if (empty($item['tag_name'])) continue;
      return $item;
    }
    return null;
  }

  // Pick the appropriate ZIP download URL from a GitHub release.
  private static function select_release_package_url($release, $assetName = '') {
    $assetName = trim((string) $assetName);
    $assets = (is_array($release) && !empty($release['assets']) && is_array($release['assets'])) ? $release['assets'] : [];

    if ($assetName !== '') {
      foreach ($assets as $asset) {
        if (!is_array($asset)) continue;
        $name = (string) ($asset['name'] ?? '');
        if (strtolower($name) !== strtolower($assetName)) continue;
        $url = esc_url_raw((string) ($asset['browser_download_url'] ?? ''), ['http', 'https']);
        if ($url !== '' && preg_match('/\.zip($|\?)/i', $name)) return $url;
      }
    }

    foreach ($assets as $asset) {
      if (!is_array($asset)) continue;
      $name = (string) ($asset['name'] ?? '');
      if (!preg_match('/\.zip($|\?)/i', $name)) continue;
      $url = esc_url_raw((string) ($asset['browser_download_url'] ?? ''), ['http', 'https']);
      if ($url !== '') return $url;
    }

    $zipball = esc_url_raw((string) ($release['zipball_url'] ?? ''), ['http', 'https']);
    return $zipball !== '' ? $zipball : '';
  }

  // Call GitHub API to fetch the newest release metadata.
  private static function fetch_github_release_from_api($settings) {
    $repo = self::sanitize_github_repo($settings['github_repo'] ?? '');
    if ($repo === '') return null;
    [$owner, $project] = explode('/', $repo, 2);

    $includePrerelease = !empty($settings['github_include_prerelease']);
    $url = $includePrerelease
      ? 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($project) . '/releases?per_page=20'
      : 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($project) . '/releases/latest';

    $headers = [
      'Accept' => 'application/vnd.github+json',
      'User-Agent' => 'AppleCreek-Livestream-Switcher/' . self::plugin_version(),
    ];
    $token = trim((string) ($settings['github_token'] ?? ''));
    if ($token !== '') $headers['Authorization'] = 'Bearer ' . $token;

    $resp = wp_remote_get($url, [
      'timeout' => 15,
      'headers' => $headers,
    ]);

    if (is_wp_error($resp)) return null;
    $code = intval(wp_remote_retrieve_response_code($resp));
    if ($code < 200 || $code >= 300) return null;

    $payload = json_decode(wp_remote_retrieve_body($resp), true);
    $release = self::pick_github_release($payload, $includePrerelease);
    if (!is_array($release)) return null;

    $version = self::normalize_release_version($release['tag_name'] ?? '');
    if ($version === '') return null;

    $downloadUrl = self::select_release_package_url($release, (string) ($settings['github_asset_name'] ?? ''));
    if ($downloadUrl === '') return null;

    return [
      'version' => $version,
      'tag' => (string) ($release['tag_name'] ?? ''),
      'name' => (string) ($release['name'] ?? ''),
      'html_url' => esc_url_raw((string) ($release['html_url'] ?? ''), ['http', 'https']),
      'download_url' => $downloadUrl,
      'published_at' => (string) ($release['published_at'] ?? ''),
      'body' => (string) ($release['body'] ?? ''),
      'prerelease' => !empty($release['prerelease']) ? 1 : 0,
    ];
  }

  // Retrieve release metadata from cache or GitHub.
  private static function get_github_release($settings, $force = false) {
    $repo = self::sanitize_github_repo($settings['github_repo'] ?? '');
    if ($repo === '') return null;

    $includePrerelease = !empty($settings['github_include_prerelease']);
    $cacheKey = self::github_cache_key($repo, $includePrerelease);

    if (!$force) {
      $cached = get_transient($cacheKey);
      if (is_array($cached)) return $cached;
      if (is_string($cached) && $cached === '__error__') return null;
    }

    $ttl = max(300, intval($settings['github_cache_ttl_seconds'] ?? 21600));
    $release = self::fetch_github_release_from_api($settings);
    if (is_array($release)) {
      set_transient($cacheKey, $release, $ttl);
      return $release;
    }

    set_transient($cacheKey, '__error__', min($ttl, 900));
    return null;
  }

  // Supply GitHub-based update info to WP core.
  public static function filter_update_plugins($transient) {
    if (!is_object($transient) || empty($transient->checked) || !is_array($transient->checked)) return $transient;

    $settings = self::get_settings();
    if (empty($settings['github_updates_enabled'])) return $transient;
    if (empty($settings['github_repo'])) return $transient;

    $plugin = self::plugin_basename();
    if (empty($transient->checked[$plugin])) return $transient;

    $release = self::get_github_release($settings, false);
    if (!is_array($release) || empty($release['version']) || empty($release['download_url'])) return $transient;

    $currentVersion = (string) $transient->checked[$plugin];
    if (!version_compare((string) $release['version'], $currentVersion, '>')) return $transient;

    $transient->response[$plugin] = (object) [
      'id' => $plugin,
      'slug' => self::plugin_slug(),
      'plugin' => $plugin,
      'new_version' => (string) $release['version'],
      'url' => (string) ($release['html_url'] ?? ''),
      'package' => (string) ($release['download_url'] ?? ''),
      'icons' => self::plugin_icons(),
    ];

    return $transient;
  }

  // Provide plugin info (including changelog) from GitHub to WP installer UI.
  public static function filter_plugins_api($result, $action, $args) {
    if ($action !== 'plugin_information' || !is_object($args)) return $result;

    $slug = isset($args->slug) ? sanitize_text_field((string) $args->slug) : '';
    if ($slug !== self::plugin_slug()) return $result;

    $settings = self::get_settings();
    if (empty($settings['github_updates_enabled']) || empty($settings['github_repo'])) return $result;

    $release = self::get_github_release($settings, false);
    if (!is_array($release)) return $result;

    $changelog = trim((string) ($release['body'] ?? ''));
    if ($changelog === '') $changelog = 'No changelog provided for this release.';

    return (object) [
      'name' => 'AppleCreek Livestream Switcher',
      'slug' => self::plugin_slug(),
      'version' => (string) ($release['version'] ?? self::plugin_version()),
      'author' => '<a href="https://xanderstudios.pro">Carlos Burke</a>',
      'homepage' => (string) ($release['html_url'] ?? ''),
      'download_link' => (string) ($release['download_url'] ?? ''),
      'icons' => self::plugin_icons(),
      'sections' => [
        'description' => 'This plugin receives updates from configured GitHub releases.',
        'changelog' => wpautop(esc_html($changelog)),
      ],
    ];
  }

  // Normalize extracted upgrade directory to expected slug.
  public static function filter_upgrader_source_selection($source, $remote_source, $upgrader, $hook_extra = []) {
    if (!is_array($hook_extra)) return $source;
    if (empty($hook_extra['plugin'])) return $source;
    if (sanitize_text_field((string) $hook_extra['plugin']) !== self::plugin_basename()) return $source;
    if (!is_string($source) || !is_dir($source)) return $source;

    $expected = trailingslashit((string) $remote_source) . self::plugin_slug();
    if (untrailingslashit($source) === untrailingslashit($expected)) return $source;
    if (is_dir($expected)) return $source;

    if (@rename($source, $expected)) return $expected;

    if (!function_exists('copy_dir')) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $copied = copy_dir($source, $expected);
    if (is_wp_error($copied)) return $source;

    return $expected;
  }

  // Register REST endpoint for live status polling.
  public static function register_rest() {
    register_rest_route('church-live/v1', '/status', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'rest_status'],
      'permission_callback' => '__return_true',
    ]);
  }

  // REST callback: returns current live/upcoming/playlist status JSON.
  public static function rest_status() {
    $s = self::apply_low_quota_profile(self::get_settings());
    $debug = isset($_GET['debug']) && sanitize_text_field((string)$_GET['debug']) === '1';

    if (empty($s['enabled'])) {
      return [
        'enabled' => false,
        'inWindow' => false,
        'mode' => 'playlist',
        'videoId' => null,
        'error' => 'Plugin disabled',
      ];
    }

    $inWindow = self::is_in_schedule_window($s);

    if (!$inWindow) {
      return [
        'inWindow' => false,
        'mode' => 'playlist',
        'videoId' => null,
      ];
    }

    if (empty($s['api_key']) || empty($s['channel_id'])) {
      return [
        'inWindow' => true,
        'mode' => 'playlist',
        'videoId' => null,
        'error' => 'Missing API key or channel id',
      ];
    }

    if (!$debug) {
      $cached = get_transient(self::TRANSIENT_KEY);
      if (is_array($cached)) {
        $publicCached = $cached;
        unset($publicCached['__error_type']);
        return array_merge(['inWindow' => true], $publicCached);
      }
    }

    $result = self::check_live_or_upcoming_via_api(
      $s['api_key'],
      $s['channel_id'],
      intval($s['lookback_count']),
      intval($s['uploads_cache_ttl_seconds']),
      $debug
    );

    if (!$debug) {
      $ttl = max(10, intval($s['cache_ttl_seconds']));
      if (($result['mode'] ?? '') === 'upcoming_video') {
        $ttl = max(10, min($ttl, self::UPCOMING_CACHE_MAX_SECONDS));
      } elseif (($result['mode'] ?? '') === 'live_video') {
        $ttl = max(10, min($ttl, self::LIVE_CACHE_MAX_SECONDS));
      }
      if (($result['__error_type'] ?? '') === 'quota') {
        // Quota errors persist until daily reset (midnight PT), so back off aggressively.
        $ttl = max($ttl, self::seconds_until_next_pacific_midnight());
      }
      set_transient(self::TRANSIENT_KEY, $result, $ttl);
    }

    $publicResult = $result;
    unset($publicResult['__error_type']);
    return array_merge(['inWindow' => true], $publicResult);
  }

  // Calculate seconds until the next midnight PT (used for quota backoff).
  private static function seconds_until_next_pacific_midnight() {
    try {
      $pt = new DateTimeZone('America/Los_Angeles');
      $now = new DateTime('now', $pt);
      $next = clone $now;
      $next->setTime(0, 0, 0);
      if ($next <= $now) $next->modify('+1 day');
      $seconds = $next->getTimestamp() - $now->getTimestamp();
      return max(600, intval($seconds));
    } catch (Exception $e) {
      // Safe fallback if timezone creation fails for any reason.
      return 3600;
    }
  }

  // Apply quota-safe minimums/caps when Low Quota Mode is enabled.
  private static function apply_low_quota_profile($settings) {
    if (!is_array($settings) || empty($settings['low_quota_mode'])) return $settings;

    $settings['cache_ttl_seconds'] = max(intval($settings['cache_ttl_seconds'] ?? 0), self::LOW_QUOTA_CACHE_TTL_SECONDS);
    $settings['poll_interval_seconds'] = max(intval($settings['poll_interval_seconds'] ?? 0), self::LOW_QUOTA_POLL_SECONDS);
    $settings['uploads_cache_ttl_seconds'] = max(intval($settings['uploads_cache_ttl_seconds'] ?? 0), self::LOW_QUOTA_UPLOADS_TTL_SECONDS);
    $settings['lookback_count'] = min(max(intval($settings['lookback_count'] ?? 0), 3), self::LOW_QUOTA_LOOKBACK_MAX);

    return $settings;
  }

  // Helper to build a playlist mode response with optional error info.
  private static function playlist_result($debug = false, $error = '', $errorType = '') {
    $out = ['mode' => 'playlist', 'videoId' => null];
    if (is_string($errorType) && $errorType !== '') {
      $out['__error_type'] = $errorType;
    }
    if ($debug && is_string($error) && $error !== '') {
      $out['error'] = $error;
    }
    return $out;
  }

  // Convert an ISO datetime string to a Unix timestamp or null.
  private static function parse_iso_datetime_to_timestamp($value) {
    if (!is_string($value) || $value === '') return null;
    $ts = strtotime($value);
    if ($ts === false) return null;
    return intval($ts);
  }

  // Select the nearest valid upcoming video id, ignoring stale entries.
  private static function pick_best_upcoming_video_id($upcoming) {
    if (!is_array($upcoming) || empty($upcoming)) return null;

    $nowTs = time();
    $futureOrNearNow = [];

    foreach ($upcoming as $item) {
      if (!is_array($item) || empty($item['id'])) continue;
      $startTs = isset($item['startTs']) && is_int($item['startTs']) ? $item['startTs'] : null;
      if ($startTs === null) continue;

      // Ignore stale "upcoming" entries that should have already started long ago.
      if ($startTs >= ($nowTs - self::UPCOMING_STALE_GRACE_SECONDS)) {
        $futureOrNearNow[] = $item;
      }
    }

    if (!empty($futureOrNearNow)) {
      usort($futureOrNearNow, function($a, $b) {
        return intval($a['startTs']) <=> intval($b['startTs']);
      });
      return (string) $futureOrNearNow[0]['id'];
    }

    return null;
  }

  // Query YouTube Data API to detect live or upcoming video for a channel.
  private static function check_live_or_upcoming_via_api($apiKey, $channelId, $lookback = 15, $uploadsCacheTtl = 86400, $debug = false) {
    $uploads_key = 'cls_uploads_playlist_' . md5($channelId);
    $uploadsPlaylistId = get_transient($uploads_key);

    if (!$uploadsPlaylistId) {
      $url = add_query_arg([
        'part' => 'contentDetails',
        'id'   => $channelId,
        'key'  => $apiKey,
      ], 'https://www.googleapis.com/youtube/v3/channels');

      $resp = wp_remote_get($url, ['timeout' => 10]);
      if (is_wp_error($resp)) return self::playlist_result($debug, 'channels request failed: ' . $resp->get_error_message());

      $body = json_decode(wp_remote_retrieve_body($resp), true);
      if (!is_array($body)) return self::playlist_result($debug, 'channels returned invalid JSON');
      if (!empty($body['error'])) {
        $msg = $body['error']['message'] ?? 'unknown error';
        $reason = $body['error']['errors'][0]['reason'] ?? '';
        return self::playlist_result($debug, 'channels API error: ' . $msg, ($reason === 'quotaExceeded' ? 'quota' : 'api'));
      }
      $uploadsPlaylistId = $body['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;

      if (!$uploadsPlaylistId) return self::playlist_result($debug, 'uploads playlist not found for this channel');

      set_transient($uploads_key, $uploadsPlaylistId, intval($uploadsCacheTtl));
    }

    $url = add_query_arg([
      'part'       => 'contentDetails',
      'playlistId' => $uploadsPlaylistId,
      'maxResults' => min(max(intval($lookback), 3), 25),
      'key'        => $apiKey,
    ], 'https://www.googleapis.com/youtube/v3/playlistItems');

    $resp = wp_remote_get($url, ['timeout' => 10]);
    if (is_wp_error($resp)) return self::playlist_result($debug, 'playlistItems request failed: ' . $resp->get_error_message());

    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($body)) return self::playlist_result($debug, 'playlistItems returned invalid JSON');
    if (!empty($body['error'])) {
      $msg = $body['error']['message'] ?? 'unknown error';
      $reason = $body['error']['errors'][0]['reason'] ?? '';
      return self::playlist_result($debug, 'playlistItems API error: ' . $msg, ($reason === 'quotaExceeded' ? 'quota' : 'api'));
    }
    $items = $body['items'] ?? [];

    $ids = [];
    foreach ($items as $it) {
      $vid = $it['contentDetails']['videoId'] ?? null;
      if ($vid) $ids[] = $vid;
    }
    if (empty($ids)) return self::playlist_result($debug, 'no upload video ids found');

    $url = add_query_arg([
      'part' => 'snippet,liveStreamingDetails,status',
      'id'   => implode(',', $ids),
      'key'  => $apiKey,
    ], 'https://www.googleapis.com/youtube/v3/videos');

    $resp = wp_remote_get($url, ['timeout' => 10]);
    if (is_wp_error($resp)) return self::playlist_result($debug, 'videos request failed: ' . $resp->get_error_message());

    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($body)) return self::playlist_result($debug, 'videos returned invalid JSON');
    if (!empty($body['error'])) {
      $msg = $body['error']['message'] ?? 'unknown error';
      $reason = $body['error']['errors'][0]['reason'] ?? '';
      return self::playlist_result($debug, 'videos API error: ' . $msg, ($reason === 'quotaExceeded' ? 'quota' : 'api'));
    }
    $videos = $body['items'] ?? [];

    $liveId = null;
    $upcoming = [];

    foreach ($videos as $v) {
      $vid = $v['id'] ?? null;
      $lbc = $v['snippet']['liveBroadcastContent'] ?? 'none';
      $privacy = $v['status']['privacyStatus'] ?? 'public';
      $embeddable = $v['status']['embeddable'] ?? true;
      $actualStart = $v['liveStreamingDetails']['actualStartTime'] ?? null;
      $actualEnd = $v['liveStreamingDetails']['actualEndTime'] ?? null;

      if ((!in_array($privacy, ['public', 'unlisted'], true)) || !$embeddable) continue;
      if (!$vid) continue;

      $isLiveNow = ($lbc === 'live') || ($actualStart && !$actualEnd);

      if ($isLiveNow) {
        $liveId = $vid;
        break;
      }
      if ($lbc === 'upcoming') {
        $start = $v['liveStreamingDetails']['scheduledStartTime'] ?? null;
        $upcoming[] = [
          'id' => $vid,
          'start' => $start,
          'startTs' => self::parse_iso_datetime_to_timestamp($start),
        ];
      }
    }

    if ($liveId) return ['mode' => 'live_video', 'videoId' => $liveId];

    if (!empty($upcoming)) {
      $bestUpcomingId = self::pick_best_upcoming_video_id($upcoming);
      if ($bestUpcomingId) return ['mode' => 'upcoming_video', 'videoId' => $bestUpcomingId];
    }

    return self::playlist_result($debug, 'no live or upcoming video found in current lookback window');
  }

  // Determine if current time falls inside any schedule/one-time window.
  private static function is_in_schedule_window($s) {
    $tz = !empty($s['timezone']) ? $s['timezone'] : 'UTC';
    try { $dtz = new DateTimeZone($tz); } catch (Exception $e) { $dtz = new DateTimeZone('UTC'); }

    $now = new DateTime('now', $dtz);
    $day = intval($now->format('w'));
    $yesterday = ($day + 6) % 7;
    $currentMinutes = intval($now->format('H')) * 60 + intval($now->format('i'));

    $schedule = is_array($s['schedule']) ? $s['schedule'] : [];
    $oneTimeEvents = is_array($s['one_time_events']) ? $s['one_time_events'] : [];

    // If no windows are configured at all, always check live status.
    if (empty($schedule) && empty($oneTimeEvents)) return true;

    foreach ($oneTimeEvents as $row) {
      if (!is_array($row)) continue;
      $date = isset($row['date']) ? (string) $row['date'] : '';
      $startText = isset($row['start']) ? (string) $row['start'] : '';
      $endText = isset($row['end']) ? (string) $row['end'] : '';
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;

      $start = self::hhmm_to_minutes($startText);
      $end = self::hhmm_to_minutes($endText);
      if ($start === null || $end === null) continue;

      $startAt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $startText, $dtz);
      $endAt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $endText, $dtz);
      if (!$startAt || !$endAt) continue;
      if ($startAt->format('Y-m-d') !== $date || $startAt->format('H:i') !== $startText) continue;
      if ($endAt->format('Y-m-d') !== $date || $endAt->format('H:i') !== $endText) continue;

      // Allow one-time events to cross midnight by setting end earlier than start.
      if ($end < $start) $endAt->modify('+1 day');

      if ($now >= $startAt && $now <= $endAt) return true;
    }

    foreach ($schedule as $row) {
      if (!is_array($row)) continue;
      $rowDay = intval($row['day']);

      $start = self::hhmm_to_minutes($row['start'] ?? '');
      $end = self::hhmm_to_minutes($row['end'] ?? '');
      if ($start === null || $end === null) continue;

      if ($end < $start) {
        // Overnight window (e.g. 23:00 -> 01:00): match both sides of midnight.
        if (($rowDay === $day && $currentMinutes >= $start) || ($rowDay === $yesterday && $currentMinutes <= $end)) return true;
      } else {
        if ($rowDay !== $day) continue;
        if ($currentMinutes >= $start && $currentMinutes <= $end) return true;
      }
    }
    return false;
  }

  // Convert HH:MM string to minutes since midnight.
  private static function hhmm_to_minutes($hhmm) {
    if (!is_string($hhmm) || !preg_match('/^\d{2}:\d{2}$/', $hhmm)) return null;
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    if ($h < 0 || $h > 23 || $m < 0 || $m > 59) return null;
    return $h * 60 + $m;
  }

  // Build a same-origin REST path to avoid host alias mismatches in browser fetch calls.
  private static function rest_status_path() {
    $url = rest_url('church-live/v1/status');
    $path = wp_parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') return '/wp-json/church-live/v1/status';
    $query = wp_parse_url($url, PHP_URL_QUERY);
    if (is_string($query) && $query !== '') $path .= '?' . $query;
    return $path;
  }

  // Video shortcode handler: renders the switching player iframe.
  public static function shortcode($atts) {
    $s = self::apply_low_quota_profile(self::get_settings());
    $playlistId = $s['playlist_id'];
    $poll = intval($s['poll_interval_seconds']);
    $enabled = !empty($s['enabled']);
    $atts = shortcode_atts([
      'height' => 0,
    ], $atts, 'church_livestream');

    $heightOverride = max(0, intval($atts['height']));
    $fixedHeight = max(0, intval($s['player_fixed_height_px'] ?? 0));
    $height = $heightOverride > 0 ? $heightOverride : $fixedHeight;

    $aspectRatio = self::sanitize_aspect_ratio((string) ($s['player_aspect_ratio'] ?? '16:9'), '16:9');
    $aspectPadding = self::aspect_ratio_to_padding_percent($aspectRatio);

    $maxWidth = sanitize_text_field((string) ($s['player_max_width'] ?? '100%'));
    if ($maxWidth === '') $maxWidth = '100%';

    $background = self::sanitize_hex_or_default((string) ($s['player_background'] ?? '#000000'), '#000000');
    $borderRadius = max(0, min(200, intval($s['player_border_radius_px'] ?? 0)));
    $boxShadow = sanitize_text_field((string) ($s['player_box_shadow'] ?? ''));
    $wrapperClass = self::sanitize_class_list((string) ($s['player_wrapper_class'] ?? ''));
    $iframeClass = self::sanitize_class_list((string) ($s['player_iframe_class'] ?? ''));

    $frameTitle = self::sanitize_text_or_default((string) ($s['player_frame_title'] ?? ''), 'YouTube livestream player');
    $loading = self::sanitize_choice((string) ($s['player_loading'] ?? 'eager'), ['eager', 'lazy'], 'eager');
    $referrerPolicy = self::sanitize_choice((string) ($s['player_referrerpolicy'] ?? ''), self::referrer_policies(), 'strict-origin-when-cross-origin');
    $allow = self::sanitize_text_or_default((string) ($s['player_allow'] ?? ''), 'autoplay; encrypted-media; picture-in-picture; fullscreen');
    $allowFullscreen = !empty($s['player_allowfullscreen']);

    $originMode = self::sanitize_choice((string) ($s['player_origin_mode'] ?? 'auto'), ['auto', 'off', 'custom'], 'auto');
    $origin = '';
    if ($originMode === 'auto') {
      $origin = self::normalize_home_origin();
    } elseif ($originMode === 'custom') {
      $origin = self::sanitize_origin_url((string) ($s['player_origin_custom'] ?? ''));
    }

    $startSeconds = max(0, intval($s['player_start_seconds'] ?? 0));
    $endSeconds = max(0, intval($s['player_end_seconds'] ?? 0));
    if ($endSeconds > 0 && $endSeconds <= $startSeconds) $endSeconds = 0;

    $hl = self::sanitize_lang_tag((string) ($s['player_hl'] ?? ''));
    $ccLangPref = self::sanitize_lang_tag((string) ($s['player_cc_lang_pref'] ?? ''));

    $commonParams = [
      'controls' => !empty($s['player_controls']) ? '1' : '0',
      'rel' => !empty($s['player_rel']) ? '1' : '0',
      'fs' => !empty($s['player_fs']) ? '1' : '0',
      'modestbranding' => !empty($s['player_modestbranding']) ? '1' : '0',
      'disablekb' => !empty($s['player_disablekb']) ? '1' : '0',
      'iv_load_policy' => intval($s['player_iv_load_policy']) === 1 ? '1' : '3',
      'cc_load_policy' => !empty($s['player_cc_load_policy']) ? '1' : '0',
      'color' => ((string) ($s['player_color'] ?? '') === 'white') ? 'white' : 'red',
      'playsinline' => !empty($s['player_playsinline']) ? '1' : '0',
      'enablejsapi' => '1',
    ];
    if ($origin !== '') $commonParams['origin'] = $origin;
    if ($hl !== '') $commonParams['hl'] = $hl;
    if ($ccLangPref !== '') $commonParams['cc_lang_pref'] = $ccLangPref;

    $liveParams = $commonParams;
    $liveParams['autoplay'] = !empty($s['player_autoplay_live']) ? '1' : '0';
    $liveParams['mute'] = !empty($s['player_mute_live']) ? '1' : '0';
    if ($startSeconds > 0) $liveParams['start'] = (string) $startSeconds;
    if ($endSeconds > 0) $liveParams['end'] = (string) $endSeconds;

    $playlistParams = $commonParams;
    $playlistParams['autoplay'] = !empty($s['player_autoplay_playlist']) ? '1' : '0';
    $playlistParams['mute'] = !empty($s['player_mute_playlist']) ? '1' : '0';

    $loopEnabled = !empty($s['player_loop']);
    $forceLiveAutoplay = !empty($s['player_force_live_autoplay']);
    $forceControlsOnMutedLive = !empty($s['player_mute_live']);
    $customQuery = self::sanitize_embed_query_string((string) ($s['player_custom_params'] ?? ''));

    $wrapperStyles = [
      'position:relative',
      'width:100%',
      'max-width:' . $maxWidth,
      'margin:0 auto',
      'background:' . $background,
    ];
    if ($height > 0) {
      $wrapperStyles[] = 'height:' . intval($height) . 'px';
    } else {
      $wrapperStyles[] = 'padding-top:' . $aspectPadding . '%';
    }
    if ($borderRadius > 0) {
      $wrapperStyles[] = 'border-radius:' . intval($borderRadius) . 'px';
      $wrapperStyles[] = 'overflow:hidden';
    }
    if ($boxShadow !== '') $wrapperStyles[] = 'box-shadow:' . $boxShadow;
    $wrapperStyle = implode(';', $wrapperStyles) . ';';

    $frameStyle = 'position:absolute;inset:0;width:100%;height:100%;border:0;';
    $uid = function_exists('wp_unique_id') ? wp_unique_id('cls-yt-') : uniqid('cls-yt-', true);
    $frameId = $uid . '-frame';
    $wrapperClasses = trim('cls-yt-wrap ' . $wrapperClass);
    $iframeClasses = trim('cls-yt-frame ' . $iframeClass);

    return self::render_template('shortcodes/video.php', [
      'wrapperClasses' => $wrapperClasses,
      'wrapperStyle' => $wrapperStyle,
      'frameId' => $frameId,
      'iframeClasses' => $iframeClasses,
      'frameStyle' => $frameStyle,
      'frameTitle' => $frameTitle,
      'loading' => $loading,
      'allow' => $allow,
      'referrerPolicy' => $referrerPolicy,
      'allowFullscreen' => $allowFullscreen,
      'enabled' => $enabled,
      'playlistId' => $playlistId,
      'poll' => $poll,
      'liveParams' => $liveParams,
      'playlistParams' => $playlistParams,
      'loopEnabled' => $loopEnabled,
      'forceLiveAutoplay' => $forceLiveAutoplay,
      'forceControlsOnMutedLive' => $forceControlsOnMutedLive,
      'customQuery' => $customQuery,
      'statusPath' => self::rest_status_path(),
    ], false);
  }

  // Chat shortcode handler tied to the same status endpoint.
  public static function shortcode_chat($atts) {
    $s = self::apply_low_quota_profile(self::get_settings());
    $poll = intval($s['poll_interval_seconds']);
    $enabled = !empty($s['enabled']);
    $showUpcoming = !empty($s['chat_show_upcoming']);

    $height = isset($atts['height']) ? max(240, intval($atts['height'])) : 600;
    $offlineMessage = isset($atts['offline_message']) ? sanitize_text_field($atts['offline_message']) : 'Live chat is available when the stream is live.';

    $embedDomain = wp_parse_url(home_url(), PHP_URL_HOST);
    if (!$embedDomain && isset($_SERVER['HTTP_HOST'])) {
      $embedDomain = preg_replace('/:\d+$/', '', sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])));
    }
    if (!$embedDomain) $embedDomain = 'localhost';

    $uid = function_exists('wp_unique_id') ? wp_unique_id('cls-chat-') : uniqid('cls-chat-', true);
    $frameId = $uid . '-frame';
    $offlineId = $uid . '-offline';

    return self::render_template('shortcodes/chat.php', [
      'uid' => $uid,
      'frameId' => $frameId,
      'offlineId' => $offlineId,
      'height' => $height,
      'offlineMessage' => $offlineMessage,
      'enabled' => $enabled,
      'showUpcoming' => $showUpcoming,
      'poll' => $poll,
      'embedDomain' => $embedDomain,
      'statusPath' => self::rest_status_path(),
    ], false);
  }
}
