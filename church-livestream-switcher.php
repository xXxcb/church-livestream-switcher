<?php
/**
 * Plugin Name: AppleCreek Livestream Switcher
 * Description: Automatically switches a YouTube embed between LIVE, UPCOMING ("Starting soon"), and a fallback playlist, based on schedule windows. Includes schedule import/export JSON.
 * Version: 1.5.0
 * Author: Carlos Burke
 * Author URI: https://xanderstudios.pro
 * Author Email: hello@xanderstudios.pro
 */

if (!defined('ABSPATH')) exit;

class Church_Livestream_Switcher {
  const OPT_KEY = 'cls_settings';
  const TRANSIENT_KEY = 'cls_live_status';
  const LOW_QUOTA_CACHE_TTL_SECONDS = 600;
  const LOW_QUOTA_POLL_SECONDS = 300;
  const LOW_QUOTA_UPLOADS_TTL_SECONDS = 604800;
  const LOW_QUOTA_LOOKBACK_MAX = 10;

  public static function init() {
    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_shortcode('church_livestream', [__CLASS__, 'shortcode']);
    add_shortcode('church_livestream_chat', [__CLASS__, 'shortcode_chat']);
    add_action('rest_api_init', [__CLASS__, 'register_rest']);
  }

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
      'schedule' => [],
      'one_time_events' => [],
      'import_json' => '',
    ];
  }

  public static function get_settings() {
    $saved = get_option(self::OPT_KEY, []);
    return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
  }

  public static function register_settings() {
    register_setting('cls_group', self::OPT_KEY, [
      'type' => 'array',
      'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
      'default' => self::defaults(),
    ]);
  }

  public static function sanitize_settings($input) {
    $d = self::defaults();
    $out = [];

    $out['enabled'] = !empty($input['enabled']) ? 1 : 0;
    $out['timezone'] = isset($input['timezone']) ? sanitize_text_field($input['timezone']) : $d['timezone'];
    $out['channel_id'] = isset($input['channel_id']) ? sanitize_text_field($input['channel_id']) : '';
    $out['playlist_id'] = isset($input['playlist_id']) ? sanitize_text_field($input['playlist_id']) : '';
    $out['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
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
    return wp_parse_args($out, $d);
  }

  private static function sanitize_text_or_default($value, $default) {
    $clean = sanitize_text_field((string) $value);
    return $clean !== '' ? $clean : $default;
  }

  private static function sanitize_choice($value, $allowed, $default) {
    $clean = sanitize_text_field((string) $value);
    return in_array($clean, $allowed, true) ? $clean : $default;
  }

  private static function sanitize_hex_or_default($value, $default) {
    $clean = sanitize_hex_color((string) $value);
    return $clean ? $clean : $default;
  }

  private static function sanitize_aspect_ratio($value, $default = '16:9') {
    $raw = trim((string) $value);
    if (preg_match('/^(\d{1,3})\s*[:\/]\s*(\d{1,3})$/', $raw, $m)) {
      $w = intval($m[1]);
      $h = intval($m[2]);
      if ($w > 0 && $h > 0) return $w . ':' . $h;
    }
    return $default;
  }

  private static function aspect_ratio_to_padding_percent($ratio) {
    if (!is_string($ratio) || !preg_match('/^(\d{1,3}):(\d{1,3})$/', trim($ratio), $m)) return 56.25;
    $w = intval($m[1]);
    $h = intval($m[2]);
    if ($w <= 0 || $h <= 0) return 56.25;
    return round(($h / $w) * 100, 4);
  }

  private static function sanitize_class_list($value) {
    $clean = preg_replace('/[^A-Za-z0-9_\-\s]/', '', (string) $value);
    $clean = preg_replace('/\s+/', ' ', trim((string) $clean));
    return $clean ?: '';
  }

  private static function sanitize_lang_tag($value) {
    $clean = trim((string) $value);
    if ($clean === '') return '';
    if (!preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8}){0,2}$/', $clean)) return '';
    return $clean;
  }

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

  private static function sanitize_origin_url($value) {
    $url = esc_url_raw(trim((string) $value), ['http', 'https']);
    if (!$url) return '';

    $parts = wp_parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return '';

    $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
    if (!empty($parts['port'])) $origin .= ':' . intval($parts['port']);
    return $origin;
  }

  private static function normalize_home_origin() {
    return self::sanitize_origin_url(home_url('/'));
  }

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

  public static function admin_menu() {
    add_options_page(
      'AppleCreek Livestream Switcher',
      'AC Livestream',
      'manage_options',
      'church-livestream-switcher',
      [__CLASS__, 'settings_page']
    );
  }

  public static function settings_page() {
    if (!current_user_can('manage_options')) return;

    $s = self::get_settings();
    $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    ?>
    <div class="wrap">
      <h1>AppleCreek Livestream Switcher</h1>
      <p>
        Video shortcode: <code>[church_livestream]</code> &nbsp;|&nbsp;
        Chat shortcode: <code>[church_livestream_chat]</code> &nbsp;|&nbsp;
        REST status: <code>/wp-json/church-live/v1/status</code>
      </p>

      <style>
        .cls-tab-panel { display: none; margin-top: 16px; }
        .cls-tab-panel.is-active { display: block; }
        .cls-chat-help code { font-size: 12px; }
      </style>

      <form method="post" action="options.php">
        <?php settings_fields('cls_group'); ?>

        <h2 class="nav-tab-wrapper" id="cls_settings_tabs">
          <a href="#cls-tab-general" class="nav-tab cls-tab-link nav-tab-active">General</a>
          <a href="#cls-tab-options" class="nav-tab cls-tab-link">Options</a>
          <a href="#cls-tab-player" class="nav-tab cls-tab-link">Player Appearance</a>
          <a href="#cls-tab-scheduling" class="nav-tab cls-tab-link">Scheduling</a>
          <a href="#cls-tab-live-chat" class="nav-tab cls-tab-link">Live Chat</a>
        </h2>

        <section id="cls-tab-general" class="cls-tab-panel is-active">
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="cls_enabled">Plugin enabled</label></th>
              <td>
                <label>
                  <input id="cls_enabled" name="<?php echo esc_attr(self::OPT_KEY); ?>[enabled]" type="checkbox" value="1" <?php checked(!empty($s['enabled'])); ?> />
                  Enable automatic live switching and YouTube API checks.
                </label>
                <p class="description">When disabled, shortcode only shows the fallback playlist and status checks are skipped.</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_timezone">Timezone</label></th>
              <td>
                <input id="cls_timezone" name="<?php echo esc_attr(self::OPT_KEY); ?>[timezone]" type="text" value="<?php echo esc_attr($s['timezone']); ?>" class="regular-text" />
                <p class="description">Example: <code>America/Toronto</code>, <code>America/New_York</code></p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_channel_id">YouTube Channel ID</label></th>
              <td>
                <input id="cls_channel_id" name="<?php echo esc_attr(self::OPT_KEY); ?>[channel_id]" type="text" value="<?php echo esc_attr($s['channel_id']); ?>" class="regular-text" />
                <p class="description">Must start with <code>UC...</code> (not @handle).</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_playlist_id">Playlist ID (fallback)</label></th>
              <td>
                <input id="cls_playlist_id" name="<?php echo esc_attr(self::OPT_KEY); ?>[playlist_id]" type="text" value="<?php echo esc_attr($s['playlist_id']); ?>" class="regular-text" />
                <p class="description">Shown outside schedule windows, and as fallback if no live/upcoming is found. Must start with <code>PL...</code></p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_api_key">YouTube Data API Key</label></th>
              <td>
                <input id="cls_api_key" name="<?php echo esc_attr(self::OPT_KEY); ?>[api_key]" type="text" value="<?php echo esc_attr($s['api_key']); ?>" class="regular-text" />
                <p class="description">
                  Required for auto-detecting LIVE and UPCOMING. Restrict this key to your server/IP and YouTube Data API v3.
                </p>
              </td>
            </tr>
          </table>
        </section>

        <section id="cls-tab-options" class="cls-tab-panel">
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="cls_cache_ttl">Backend cache TTL (seconds)</label></th>
              <td>
                <input id="cls_cache_ttl" name="<?php echo esc_attr(self::OPT_KEY); ?>[cache_ttl_seconds]" type="number" min="10" value="<?php echo esc_attr($s['cache_ttl_seconds']); ?>" />
                <p class="description">How long live/upcoming detection results are cached during schedule windows.</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_low_quota_mode">Low Quota Mode</label></th>
              <td>
                <label>
                  <input id="cls_low_quota_mode" name="<?php echo esc_attr(self::OPT_KEY); ?>[low_quota_mode]" type="checkbox" value="1" <?php checked(!empty($s['low_quota_mode'])); ?> />
                  Prioritize minimal API usage.
                </label>
                <p class="description">
                  Enforces quota-safe runtime values: backend cache at least <?php echo esc_html((string) self::LOW_QUOTA_CACHE_TTL_SECONDS); ?>s, front-end refresh at least <?php echo esc_html((string) self::LOW_QUOTA_POLL_SECONDS); ?>s, uploads playlist cache at least <?php echo esc_html((string) self::LOW_QUOTA_UPLOADS_TTL_SECONDS); ?>s (7 days), lookback capped at <?php echo esc_html((string) self::LOW_QUOTA_LOOKBACK_MAX); ?>.
                </p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_poll_interval">Front-end refresh (seconds)</label></th>
              <td>
                <input id="cls_poll_interval" name="<?php echo esc_attr(self::OPT_KEY); ?>[poll_interval_seconds]" type="number" min="30" value="<?php echo esc_attr($s['poll_interval_seconds']); ?>" />
                <p class="description">How often the embed re-checks during schedule windows. Recommended 120–300.</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_lookback">Lookback count</label></th>
              <td>
                <input id="cls_lookback" name="<?php echo esc_attr(self::OPT_KEY); ?>[lookback_count]" type="number" min="3" max="25" value="<?php echo esc_attr($s['lookback_count']); ?>" />
                <p class="description">How many recent uploads to scan for live/upcoming.</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_uploads_ttl">Uploads playlist cache TTL (seconds)</label></th>
              <td>
                <input id="cls_uploads_ttl" name="<?php echo esc_attr(self::OPT_KEY); ?>[uploads_cache_ttl_seconds]" type="number" min="3600" value="<?php echo esc_attr($s['uploads_cache_ttl_seconds']); ?>" />
                <p class="description">Caches your channel’s uploads playlist id (default 24h).</p>
              </td>
            </tr>
          </table>
        </section>

        <section id="cls-tab-player" class="cls-tab-panel">
          <h2>Container &amp; Frame</h2>
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="cls_player_max_width">Player max width</label></th>
              <td>
                <input id="cls_player_max_width" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_max_width]" type="text" value="<?php echo esc_attr($s['player_max_width']); ?>" class="regular-text" />
                <p class="description">Examples: <code>100%</code>, <code>1280px</code>, <code>90vw</code>.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_aspect_ratio">Aspect ratio</label></th>
              <td>
                <input id="cls_player_aspect_ratio" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_aspect_ratio]" type="text" value="<?php echo esc_attr($s['player_aspect_ratio']); ?>" class="small-text" />
                <p class="description">Format <code>width:height</code>, e.g. <code>16:9</code>, <code>4:3</code>.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_fixed_height_px">Fixed height (px)</label></th>
              <td>
                <input id="cls_player_fixed_height_px" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_fixed_height_px]" type="number" min="0" max="2160" value="<?php echo esc_attr($s['player_fixed_height_px']); ?>" />
                <p class="description">Set <code>0</code> for responsive ratio mode. Any positive value forces fixed height.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_border_radius_px">Border radius (px)</label></th>
              <td><input id="cls_player_border_radius_px" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_border_radius_px]" type="number" min="0" max="200" value="<?php echo esc_attr($s['player_border_radius_px']); ?>" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_background">Background color</label></th>
              <td><input id="cls_player_background" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_background]" type="text" value="<?php echo esc_attr($s['player_background']); ?>" class="small-text" placeholder="#000000" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_box_shadow">Box shadow</label></th>
              <td>
                <input id="cls_player_box_shadow" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_box_shadow]" type="text" value="<?php echo esc_attr($s['player_box_shadow']); ?>" class="regular-text" />
                <p class="description">Raw CSS value, example: <code>0 10px 40px rgba(0,0,0,.25)</code>.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_wrapper_class">Wrapper CSS class(es)</label></th>
              <td><input id="cls_player_wrapper_class" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_wrapper_class]" type="text" value="<?php echo esc_attr($s['player_wrapper_class']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_iframe_class">IFrame CSS class(es)</label></th>
              <td><input id="cls_player_iframe_class" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_iframe_class]" type="text" value="<?php echo esc_attr($s['player_iframe_class']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_frame_title">IFrame title</label></th>
              <td><input id="cls_player_frame_title" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_frame_title]" type="text" value="<?php echo esc_attr($s['player_frame_title']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_loading">IFrame loading</label></th>
              <td>
                <select id="cls_player_loading" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_loading]">
                  <option value="eager" <?php selected($s['player_loading'], 'eager'); ?>>eager</option>
                  <option value="lazy" <?php selected($s['player_loading'], 'lazy'); ?>>lazy</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_referrerpolicy">Referrer policy</label></th>
              <td>
                <select id="cls_player_referrerpolicy" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_referrerpolicy]">
                  <?php foreach (self::referrer_policies() as $policy): ?>
                    <option value="<?php echo esc_attr($policy); ?>" <?php selected($s['player_referrerpolicy'], $policy); ?>>
                      <?php echo esc_html($policy === '' ? '(none)' : $policy); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_allow">IFrame allow permissions</label></th>
              <td><input id="cls_player_allow" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_allow]" type="text" value="<?php echo esc_attr($s['player_allow']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_allowfullscreen">Allow fullscreen attribute</label></th>
              <td>
                <label>
                  <input id="cls_player_allowfullscreen" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_allowfullscreen]" type="checkbox" value="1" <?php checked(!empty($s['player_allowfullscreen'])); ?> />
                  Output <code>allowfullscreen</code> attribute on iframe.
                </label>
              </td>
            </tr>
          </table>

          <h2>YouTube Player Parameters</h2>
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="cls_player_controls">Show controls</label></th>
              <td><input id="cls_player_controls" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_controls]" type="checkbox" value="1" <?php checked(!empty($s['player_controls'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_autoplay_live">Autoplay live video</label></th>
              <td><input id="cls_player_autoplay_live" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_autoplay_live]" type="checkbox" value="1" <?php checked(!empty($s['player_autoplay_live'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_mute_live">Mute live video</label></th>
              <td><input id="cls_player_mute_live" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_mute_live]" type="checkbox" value="1" <?php checked(!empty($s['player_mute_live'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_autoplay_playlist">Autoplay playlist fallback</label></th>
              <td><input id="cls_player_autoplay_playlist" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_autoplay_playlist]" type="checkbox" value="1" <?php checked(!empty($s['player_autoplay_playlist'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_mute_playlist">Mute playlist fallback</label></th>
              <td><input id="cls_player_mute_playlist" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_mute_playlist]" type="checkbox" value="1" <?php checked(!empty($s['player_mute_playlist'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_loop">Loop playback</label></th>
              <td>
                <label>
                  <input id="cls_player_loop" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_loop]" type="checkbox" value="1" <?php checked(!empty($s['player_loop'])); ?> />
                  Loop playlist and single live embeds.
                </label>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_rel">Show related videos</label></th>
              <td><input id="cls_player_rel" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_rel]" type="checkbox" value="1" <?php checked(!empty($s['player_rel'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_fs">Show fullscreen button</label></th>
              <td><input id="cls_player_fs" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_fs]" type="checkbox" value="1" <?php checked(!empty($s['player_fs'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_modestbranding">Modest branding</label></th>
              <td><input id="cls_player_modestbranding" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_modestbranding]" type="checkbox" value="1" <?php checked(!empty($s['player_modestbranding'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_disablekb">Disable keyboard shortcuts</label></th>
              <td><input id="cls_player_disablekb" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_disablekb]" type="checkbox" value="1" <?php checked(!empty($s['player_disablekb'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_playsinline">Plays inline on mobile</label></th>
              <td><input id="cls_player_playsinline" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_playsinline]" type="checkbox" value="1" <?php checked(!empty($s['player_playsinline'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_iv_load_policy">Annotations / cards policy</label></th>
              <td>
                <select id="cls_player_iv_load_policy" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_iv_load_policy]">
                  <option value="3" <?php selected(intval($s['player_iv_load_policy']), 3); ?>>Hide annotations</option>
                  <option value="1" <?php selected(intval($s['player_iv_load_policy']), 1); ?>>Show annotations</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_cc_load_policy">Show captions by default</label></th>
              <td><input id="cls_player_cc_load_policy" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_cc_load_policy]" type="checkbox" value="1" <?php checked(!empty($s['player_cc_load_policy'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_color">Progress bar color</label></th>
              <td>
                <select id="cls_player_color" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_color]">
                  <option value="red" <?php selected($s['player_color'], 'red'); ?>>red</option>
                  <option value="white" <?php selected($s['player_color'], 'white'); ?>>white</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_start_seconds">Start at seconds</label></th>
              <td><input id="cls_player_start_seconds" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_start_seconds]" type="number" min="0" value="<?php echo esc_attr($s['player_start_seconds']); ?>" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_end_seconds">End at seconds</label></th>
              <td><input id="cls_player_end_seconds" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_end_seconds]" type="number" min="0" value="<?php echo esc_attr($s['player_end_seconds']); ?>" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_hl">Player UI language (`hl`)</label></th>
              <td><input id="cls_player_hl" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_hl]" type="text" value="<?php echo esc_attr($s['player_hl']); ?>" class="small-text" placeholder="en" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_cc_lang_pref">Caption language (`cc_lang_pref`)</label></th>
              <td><input id="cls_player_cc_lang_pref" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_cc_lang_pref]" type="text" value="<?php echo esc_attr($s['player_cc_lang_pref']); ?>" class="small-text" placeholder="en" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_origin_mode">Origin parameter mode</label></th>
              <td>
                <select id="cls_player_origin_mode" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_origin_mode]">
                  <option value="auto" <?php selected($s['player_origin_mode'], 'auto'); ?>>Auto (site origin)</option>
                  <option value="off" <?php selected($s['player_origin_mode'], 'off'); ?>>Off</option>
                  <option value="custom" <?php selected($s['player_origin_mode'], 'custom'); ?>>Custom</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_origin_custom">Custom origin URL</label></th>
              <td>
                <input id="cls_player_origin_custom" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_origin_custom]" type="text" value="<?php echo esc_attr($s['player_origin_custom']); ?>" class="regular-text" placeholder="https://example.com" />
                <p class="description">Used only when mode is <code>Custom</code>.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_custom_params">Advanced custom query params</label></th>
              <td>
                <input id="cls_player_custom_params" name="<?php echo esc_attr(self::OPT_KEY); ?>[player_custom_params]" type="text" value="<?php echo esc_attr($s['player_custom_params']); ?>" class="regular-text" placeholder="vq=hd1080&widget_referrer=https%3A%2F%2Fexample.com" />
                <p class="description">Optional raw query string appended last (overrides earlier params if keys match).</p>
              </td>
            </tr>
          </table>
        </section>

        <section id="cls-tab-scheduling" class="cls-tab-panel">
          <h2>Weekly Schedule Windows</h2>
          <p class="description">Outside these windows the shortcode always shows the playlist (no API calls).</p>

          <table class="widefat fixed" id="cls_schedule_table">
            <thead>
              <tr>
                <th style="width: 35%;">Day</th>
                <th style="width: 25%;">Start (HH:MM)</th>
                <th style="width: 25%;">End (HH:MM)</th>
                <th style="width: 15%;">Remove</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($s['schedule'] as $i => $row): ?>
                <tr>
                  <td>
                    <select name="<?php echo esc_attr(self::OPT_KEY); ?>[schedule][<?php echo intval($i); ?>][day]">
                      <?php foreach ($days as $dIdx => $dName): ?>
                        <option value="<?php echo intval($dIdx); ?>" <?php selected(intval($row['day']), $dIdx); ?>>
                          <?php echo esc_html($dName); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input type="time" name="<?php echo esc_attr(self::OPT_KEY); ?>[schedule][<?php echo intval($i); ?>][start]" value="<?php echo esc_attr($row['start']); ?>" /></td>
                  <td><input type="time" name="<?php echo esc_attr(self::OPT_KEY); ?>[schedule][<?php echo intval($i); ?>][end]" value="<?php echo esc_attr($row['end']); ?>" /></td>
                  <td><button type="button" class="button cls-remove-row">Remove</button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <p>
            <button type="button" class="button" id="cls_add_row">Add schedule row</button>
          </p>

          <h2>One-time Event Windows</h2>
          <p class="description">Use this for special events (holidays, conferences, funerals, etc.) that should trigger live checks only on specific dates.</p>

          <table class="widefat fixed" id="cls_onetime_table">
            <thead>
              <tr>
                <th style="width: 35%;">Date (YYYY-MM-DD)</th>
                <th style="width: 25%;">Start (HH:MM)</th>
                <th style="width: 25%;">End (HH:MM)</th>
                <th style="width: 15%;">Remove</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($s['one_time_events'] as $i => $row): ?>
                <tr>
                  <td><input type="date" name="<?php echo esc_attr(self::OPT_KEY); ?>[one_time_events][<?php echo intval($i); ?>][date]" value="<?php echo esc_attr($row['date']); ?>" /></td>
                  <td><input type="time" name="<?php echo esc_attr(self::OPT_KEY); ?>[one_time_events][<?php echo intval($i); ?>][start]" value="<?php echo esc_attr($row['start']); ?>" /></td>
                  <td><input type="time" name="<?php echo esc_attr(self::OPT_KEY); ?>[one_time_events][<?php echo intval($i); ?>][end]" value="<?php echo esc_attr($row['end']); ?>" /></td>
                  <td><button type="button" class="button cls-remove-onetime-row">Remove</button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <p>
            <button type="button" class="button" id="cls_add_onetime_row">Add one-time event row</button>
          </p>

          <h2>Import / Export Schedule</h2>

          <p><strong>Export JSON</strong></p>
          <textarea readonly class="large-text code" rows="6"><?php
            echo esc_textarea(json_encode([
              'schedule' => $s['schedule'],
              'one_time_events' => $s['one_time_events'],
            ], JSON_PRETTY_PRINT));
          ?></textarea>

          <p><strong>Import JSON</strong> (paste and Save Changes)</p>
          <textarea name="<?php echo esc_attr(self::OPT_KEY); ?>[import_json]" class="large-text code" rows="6" placeholder='{"schedule":[{"day":0,"start":"09:30","end":"13:00"}],"one_time_events":[{"date":"2026-12-24","start":"18:30","end":"21:00"}]}'></textarea>
        </section>

        <section id="cls-tab-live-chat" class="cls-tab-panel">
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="cls_chat_show_upcoming">Show chat for upcoming</label></th>
              <td>
                <label>
                  <input id="cls_chat_show_upcoming" name="<?php echo esc_attr(self::OPT_KEY); ?>[chat_show_upcoming]" type="checkbox" value="1" <?php checked(!empty($s['chat_show_upcoming'])); ?> />
                  Show chat when stream status is <code>upcoming_video</code>.
                </label>
                <p class="description">Turn this off to show chat only when the stream is actually live.</p>
              </td>
            </tr>
          </table>

          <div class="cls-chat-help">
            <p><strong>Chat shortcode:</strong> <code>[church_livestream_chat]</code></p>
            <p><strong>Optional attributes:</strong> <code>height="600"</code>, <code>offline_message="Live chat is available when the stream is live."</code></p>
          </div>
        </section>

        <?php submit_button('Save Changes'); ?>
      </form>
    </div>

    <script>
      (function(){
        const tabLinks = document.querySelectorAll('#cls_settings_tabs .cls-tab-link');
        const tabPanels = document.querySelectorAll('.cls-tab-panel');

        function activateTab(targetId, updateHash) {
          if (!targetId) return;
          const target = document.querySelector(targetId);
          if (!target || !target.classList.contains('cls-tab-panel')) return;

          tabLinks.forEach((link) => {
            const active = link.getAttribute('href') === targetId;
            link.classList.toggle('nav-tab-active', active);
          });
          tabPanels.forEach((panel) => {
            panel.classList.toggle('is-active', panel.id === target.id);
          });

          if (updateHash) {
            if (window.history && window.history.replaceState) {
              window.history.replaceState(null, '', targetId);
            } else {
              window.location.hash = targetId;
            }
          }
        }

        tabLinks.forEach((link) => {
          link.addEventListener('click', (e) => {
            e.preventDefault();
            activateTab(link.getAttribute('href'), true);
          });
        });

        if (window.location.hash) {
          activateTab(window.location.hash, false);
        }

        const scheduleTableBody = document.querySelector('#cls_schedule_table tbody');
        const scheduleAddBtn = document.getElementById('cls_add_row');
        const onetimeTableBody = document.querySelector('#cls_onetime_table tbody');
        const onetimeAddBtn = document.getElementById('cls_add_onetime_row');

        function rowTemplate(index) {
          const days = <?php echo wp_json_encode($days); ?>;
          const dayOptions = days.map((name, i) => `<option value="${i}">${name}</option>`).join('');
          const key = <?php echo wp_json_encode(self::OPT_KEY); ?>;
          return `
            <tr>
              <td>
                <select name="${key}[schedule][${index}][day]">${dayOptions}</select>
              </td>
              <td><input type="time" name="${key}[schedule][${index}][start]" value="09:30"></td>
              <td><input type="time" name="${key}[schedule][${index}][end]" value="13:00"></td>
              <td><button type="button" class="button cls-remove-row">Remove</button></td>
            </tr>
          `;
        }

        function oneTimeRowTemplate(index) {
          const key = <?php echo wp_json_encode(self::OPT_KEY); ?>;
          const now = new Date();
          const yyyy = String(now.getFullYear());
          const mm = String(now.getMonth() + 1).padStart(2, '0');
          const dd = String(now.getDate()).padStart(2, '0');
          const today = `${yyyy}-${mm}-${dd}`;
          return `
            <tr>
              <td><input type="date" name="${key}[one_time_events][${index}][date]" value="${today}"></td>
              <td><input type="time" name="${key}[one_time_events][${index}][start]" value="09:30"></td>
              <td><input type="time" name="${key}[one_time_events][${index}][end]" value="13:00"></td>
              <td><button type="button" class="button cls-remove-onetime-row">Remove</button></td>
            </tr>
          `;
        }

        function nextScheduleIndex() {
          const rows = scheduleTableBody.querySelectorAll('tr');
          return rows.length;
        }

        function nextOneTimeIndex() {
          const rows = onetimeTableBody.querySelectorAll('tr');
          return rows.length;
        }

        scheduleAddBtn?.addEventListener('click', () => {
          scheduleTableBody.insertAdjacentHTML('beforeend', rowTemplate(nextScheduleIndex()));
        });

        onetimeAddBtn?.addEventListener('click', () => {
          onetimeTableBody.insertAdjacentHTML('beforeend', oneTimeRowTemplate(nextOneTimeIndex()));
        });

        scheduleTableBody?.addEventListener('click', (e) => {
          if (e.target && e.target.classList.contains('cls-remove-row')) {
            e.target.closest('tr')?.remove();
          }
        });

        onetimeTableBody?.addEventListener('click', (e) => {
          if (e.target && e.target.classList.contains('cls-remove-onetime-row')) {
            e.target.closest('tr')?.remove();
          }
        });
      })();
    </script>
    <?php
  }

  public static function register_rest() {
    register_rest_route('church-live/v1', '/status', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'rest_status'],
      'permission_callback' => '__return_true',
    ]);
  }

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
      $ttl = intval($s['cache_ttl_seconds']);
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

  private static function apply_low_quota_profile($settings) {
    if (!is_array($settings) || empty($settings['low_quota_mode'])) return $settings;

    $settings['cache_ttl_seconds'] = max(intval($settings['cache_ttl_seconds'] ?? 0), self::LOW_QUOTA_CACHE_TTL_SECONDS);
    $settings['poll_interval_seconds'] = max(intval($settings['poll_interval_seconds'] ?? 0), self::LOW_QUOTA_POLL_SECONDS);
    $settings['uploads_cache_ttl_seconds'] = max(intval($settings['uploads_cache_ttl_seconds'] ?? 0), self::LOW_QUOTA_UPLOADS_TTL_SECONDS);
    $settings['lookback_count'] = min(max(intval($settings['lookback_count'] ?? 0), 3), self::LOW_QUOTA_LOOKBACK_MAX);

    return $settings;
  }

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
        $upcoming[] = ['id' => $vid, 'start' => $start];
      }
    }

    if ($liveId) return ['mode' => 'live_video', 'videoId' => $liveId];

    if (!empty($upcoming)) {
      usort($upcoming, function($a, $b){ return strcmp((string)$a['start'], (string)$b['start']); });
      return ['mode' => 'upcoming_video', 'videoId' => $upcoming[0]['id']];
    }

    return self::playlist_result($debug, 'no live or upcoming video found in current lookback window');
  }

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

  private static function hhmm_to_minutes($hhmm) {
    if (!is_string($hhmm) || !preg_match('/^\d{2}:\d{2}$/', $hhmm)) return null;
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    if ($h < 0 || $h > 23 || $m < 0 || $m > 59) return null;
    return $h * 60 + $m;
  }

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

    ob_start(); ?>
      <div class="<?php echo esc_attr($wrapperClasses); ?>" style="<?php echo esc_attr($wrapperStyle); ?>">
        <iframe
          id="<?php echo esc_attr($frameId); ?>"
          class="<?php echo esc_attr($iframeClasses); ?>"
          style="<?php echo esc_attr($frameStyle); ?>"
          src="about:blank"
          title="<?php echo esc_attr($frameTitle); ?>"
          loading="<?php echo esc_attr($loading); ?>"
          frameborder="0"
          allow="<?php echo esc_attr($allow); ?>"
          <?php if ($referrerPolicy !== ''): ?>referrerpolicy="<?php echo esc_attr($referrerPolicy); ?>"<?php endif; ?>
          <?php if ($allowFullscreen): ?>allowfullscreen<?php endif; ?>
        ></iframe>
      </div>

      <script>
        (function(){
          const FRAME_ID = <?php echo wp_json_encode($frameId); ?>;
          const SWITCHING_ENABLED = <?php echo wp_json_encode($enabled); ?>;
          const PLAYLIST_ID = <?php echo wp_json_encode($playlistId); ?>;
          const POLL_SECONDS = <?php echo wp_json_encode($poll); ?>;
          const LIVE_PARAMS = <?php echo wp_json_encode($liveParams); ?>;
          const PLAYLIST_PARAMS = <?php echo wp_json_encode($playlistParams); ?>;
          const LOOP_ENABLED = <?php echo wp_json_encode($loopEnabled); ?>;
          const CUSTOM_QUERY = <?php echo wp_json_encode($customQuery); ?>;

          const frame = document.getElementById(FRAME_ID);
          if (!frame) return;

          const customParams = new URLSearchParams(CUSTOM_QUERY || '');

          function applyParams(params, source) {
            Object.entries(source || {}).forEach(([k, v]) => {
              if (v === null || typeof v === 'undefined' || v === '') return;
              params.set(k, String(v));
            });
          }

          function applyCustomParams(params) {
            customParams.forEach((v, k) => {
              if (!k) return;
              params.set(k, v);
            });
          }

          function buildSrc(mode, videoId) {
            if (mode === 'playlist') {
              if (!PLAYLIST_ID) return '';
              const params = new URLSearchParams();
              applyParams(params, PLAYLIST_PARAMS);
              if (LOOP_ENABLED) params.set('loop', '1');
              params.set('list', PLAYLIST_ID);
              applyCustomParams(params);
              return `https://www.youtube.com/embed/videoseries?${params.toString()}`;
            }

            if (!videoId) return '';
            const params = new URLSearchParams();
            applyParams(params, LIVE_PARAMS);
            if (LOOP_ENABLED) {
              params.set('loop', '1');
              params.set('playlist', videoId);
            }
            applyCustomParams(params);
            return `https://www.youtube.com/embed/${encodeURIComponent(videoId)}?${params.toString()}`;
          }

          const playlistSrc = buildSrc('playlist');

          if (!SWITCHING_ENABLED) {
            if (playlistSrc && frame.src !== playlistSrc) frame.src = playlistSrc;
            return;
          }

          const YT_IFRAME_API_SRC = 'https://www.youtube.com/iframe_api';
          let ytPlayer = null;
          let ytReadyPromise = null;

          function ensureYouTubeApiReady() {
            if (ytReadyPromise) return ytReadyPromise;
            ytReadyPromise = new Promise((resolve, reject) => {
              if (window.YT && window.YT.Player) {
                resolve(window.YT);
                return;
              }

              const previousReady = window.onYouTubeIframeAPIReady;
              window.onYouTubeIframeAPIReady = function() {
                if (typeof previousReady === 'function') previousReady();
                resolve(window.YT);
              };

              if (!document.querySelector(`script[src="${YT_IFRAME_API_SRC}"]`)) {
                const script = document.createElement('script');
                script.src = YT_IFRAME_API_SRC;
                script.async = true;
                script.onerror = () => reject(new Error('Failed to load YouTube iframe API'));
                document.head.appendChild(script);
              }
            });

            return ytReadyPromise;
          }

          function attachEndedFallbackHandler() {
            if (!playlistSrc || ytPlayer) return;

            ensureYouTubeApiReady()
              .then(() => {
                if (!window.YT || !window.YT.Player || ytPlayer) return;
                ytPlayer = new window.YT.Player(frame, {
                  events: {
                    onStateChange: function(event) {
                      if (event && event.data === window.YT.PlayerState.ENDED) {
                        if (frame.src !== playlistSrc) frame.src = playlistSrc;
                      }
                    }
                  }
                });
              })
              .catch(() => {});
          }

          async function refresh() {
            try {
              const res = await fetch('<?php echo esc_url_raw(rest_url('church-live/v1/status')); ?>', { cache: 'no-store' });
              const data = await res.json();

              let nextSrc = playlistSrc;

              if (data && data.inWindow && (data.mode === 'live_video' || data.mode === 'upcoming_video') && data.videoId) {
                nextSrc = buildSrc('video', data.videoId) || playlistSrc;
              }

              if (nextSrc && frame.src !== nextSrc) {
                frame.src = nextSrc;
              }

              attachEndedFallbackHandler();
            } catch (e) {
              if (playlistSrc && frame.src !== playlistSrc) frame.src = playlistSrc;
            }
          }

          refresh();
          if (POLL_SECONDS && POLL_SECONDS > 0) {
            setInterval(refresh, POLL_SECONDS * 1000);
          }
        })();
      </script>
    <?php
    return ob_get_clean();
  }

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

    ob_start(); ?>
      <div id="<?php echo esc_attr($uid); ?>" style="width:100%;max-width:100%;">
        <iframe
          id="<?php echo esc_attr($frameId); ?>"
          style="width:100%;height:<?php echo esc_attr($height); ?>px;border:0;display:none;"
          src="about:blank"
          loading="lazy"
          frameborder="0"></iframe>
        <div id="<?php echo esc_attr($offlineId); ?>" style="height:<?php echo esc_attr($height); ?>px;border:1px solid #dcdcde;display:flex;align-items:center;justify-content:center;padding:16px;text-align:center;">
          <?php echo esc_html($offlineMessage); ?>
        </div>
      </div>

      <script>
        (function(){
          const SWITCHING_ENABLED = <?php echo wp_json_encode($enabled); ?>;
          const SHOW_UPCOMING_CHAT = <?php echo wp_json_encode($showUpcoming); ?>;
          const POLL_SECONDS = <?php echo wp_json_encode($poll); ?>;
          const EMBED_DOMAIN = <?php echo wp_json_encode($embedDomain); ?>;
          const frame = document.getElementById(<?php echo wp_json_encode($frameId); ?>);
          const offline = document.getElementById(<?php echo wp_json_encode($offlineId); ?>);

          if (!frame || !offline) return;

          function showOffline() {
            if (frame.style.display !== 'none') frame.style.display = 'none';
            if (offline.style.display !== 'flex') offline.style.display = 'flex';
            if (frame.src !== 'about:blank') frame.src = 'about:blank';
          }

          function showChat(videoId) {
            const nextSrc = `https://www.youtube.com/live_chat?v=${encodeURIComponent(videoId)}&embed_domain=${encodeURIComponent(EMBED_DOMAIN)}`;
            if (frame.src !== nextSrc) frame.src = nextSrc;
            if (frame.style.display !== 'block') frame.style.display = 'block';
            if (offline.style.display !== 'none') offline.style.display = 'none';
          }

          function canShowChatForStatus(data) {
            if (!data || !data.inWindow || !data.videoId) return false;
            if (data.mode === 'live_video') return true;
            if (data.mode === 'upcoming_video') return !!SHOW_UPCOMING_CHAT;
            return false;
          }

          async function refresh() {
            if (!SWITCHING_ENABLED) {
              showOffline();
              return;
            }

            try {
              const res = await fetch('<?php echo esc_url_raw(rest_url('church-live/v1/status')); ?>', { cache: 'no-store' });
              const data = await res.json();
              if (canShowChatForStatus(data)) {
                showChat(data.videoId);
              } else {
                showOffline();
              }
            } catch (e) {
              showOffline();
            }
          }

          refresh();
          if (POLL_SECONDS && POLL_SECONDS > 0) {
            setInterval(refresh, POLL_SECONDS * 1000);
          }
        })();
      </script>
    <?php
    return ob_get_clean();
  }
}

Church_Livestream_Switcher::init();
