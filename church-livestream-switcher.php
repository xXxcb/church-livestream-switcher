<?php
/**
 * Plugin Name: AppleCreek Livestream Switcher
 * Description: Automatically switches a YouTube embed between LIVE, UPCOMING ("Starting soon"), and a fallback playlist, based on schedule windows. Includes schedule import/export JSON.
 * Version: 1.2.2
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
        Shortcode: <code>[church_livestream]</code> &nbsp;|&nbsp;
        REST status: <code>/wp-json/church-live/v1/status</code>
      </p>

      <form method="post" action="options.php">
        <?php settings_fields('cls_group'); ?>

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

        <?php submit_button('Save Changes'); ?>
      </form>
    </div>

    <script>
      (function(){
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

    $height = isset($atts['height']) ? intval($atts['height']) : 480;

    ob_start(); ?>
      <div style="position:relative;padding-top:56.25%;">
        <iframe
          id="cls-yt-frame"
          style="position:absolute;inset:0;width:100%;height:100%;"
          width="100%"
          height="<?php echo esc_attr($height); ?>"
          src=""
          frameborder="0"
          allow="autoplay; encrypted-media; picture-in-picture"
          allowfullscreen></iframe>
      </div>

      <script>
        (function(){
          const SWITCHING_ENABLED = <?php echo wp_json_encode($enabled); ?>;
          const PLAYLIST_ID = <?php echo wp_json_encode($playlistId); ?>;
          const POLL_SECONDS = <?php echo wp_json_encode($poll); ?>;

          const frame = document.getElementById('cls-yt-frame');
          if (!frame) return;

          const playlistSrc = PLAYLIST_ID
            ? `https://www.youtube.com/embed/videoseries?list=${encodeURIComponent(PLAYLIST_ID)}&rel=0&enablejsapi=1`
            : '';

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
                nextSrc = `https://www.youtube.com/embed/${encodeURIComponent(data.videoId)}?autoplay=1&mute=1&rel=0&enablejsapi=1`;
              } else {
                nextSrc = playlistSrc;
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
}

Church_Livestream_Switcher::init();
