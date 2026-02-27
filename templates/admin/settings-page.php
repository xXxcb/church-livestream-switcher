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
          <a href="#cls-tab-updates" class="nav-tab cls-tab-link">Updates</a>
          <a href="#cls-tab-options" class="nav-tab cls-tab-link">Options</a>
          <a href="#cls-tab-player" class="nav-tab cls-tab-link">Player Appearance</a>
          <a href="#cls-tab-scheduling" class="nav-tab cls-tab-link">Scheduling</a>
          <a href="#cls-tab-live-chat" class="nav-tab cls-tab-link">Live Chat</a>
          <a href="#cls-tab-live-status" class="nav-tab cls-tab-link">Live Status</a>
        </h2>

        <section id="cls-tab-general" class="cls-tab-panel is-active">
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="cls_enabled">Plugin enabled</label></th>
              <td>
                <label>
                  <input id="cls_enabled" name="<?php echo esc_attr($optKey); ?>[enabled]" type="checkbox" value="1" <?php checked(!empty($s['enabled'])); ?> />
                  Enable automatic live switching and YouTube API checks.
                </label>
                <p class="description">When disabled, shortcode only shows the fallback playlist and status checks are skipped.</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_timezone">Timezone</label></th>
              <td>
                <input id="cls_timezone" name="<?php echo esc_attr($optKey); ?>[timezone]" type="text" value="<?php echo esc_attr($s['timezone']); ?>" class="regular-text" />
                <p class="description">Example: <code>America/Toronto</code>, <code>America/New_York</code></p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_channel_id">YouTube Channel ID</label></th>
              <td>
                <input id="cls_channel_id" name="<?php echo esc_attr($optKey); ?>[channel_id]" type="text" value="<?php echo esc_attr($s['channel_id']); ?>" class="regular-text" />
                <p class="description">Must start with <code>UC...</code> (not @handle).</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_playlist_id">Playlist ID (fallback)</label></th>
              <td>
                <input id="cls_playlist_id" name="<?php echo esc_attr($optKey); ?>[playlist_id]" type="text" value="<?php echo esc_attr($s['playlist_id']); ?>" class="regular-text" />
                <p class="description">Shown outside schedule windows, and as fallback if no live/upcoming is found. Must start with <code>PL...</code></p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_api_key">YouTube Data API Key</label></th>
              <td>
                <input id="cls_api_key" name="<?php echo esc_attr($optKey); ?>[api_key]" type="password" value="" class="regular-text" autocomplete="new-password" />
                <p class="description">
                  Required for auto-detecting LIVE and UPCOMING. Restrict this key to your server/IP and YouTube Data API v3. Leave blank to keep existing key.
                </p>
                <p class="description">Saved key: <code><?php echo esc_html($apiKeyPreview); ?></code></p>
                <label>
                  <input name="<?php echo esc_attr($optKey); ?>[api_key_clear]" type="checkbox" value="1" />
                  Clear saved API key on next save.
                </label>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_upcoming_video_id_override">Manual Upcoming Video ID (override)</label></th>
              <td>
                <input id="cls_upcoming_video_id_override" name="<?php echo esc_attr($optKey); ?>[upcoming_video_id_override]" type="text" value="<?php echo esc_attr($s['upcoming_video_id_override']); ?>" class="regular-text" />
                <p class="description">Optional: force a specific upcoming/live video id. If this video is currently <code>upcoming</code> or <code>live</code>, it will be used before auto-detection.</p>
              </td>
            </tr>
          </table>
        </section>

        <section id="cls-tab-updates" class="cls-tab-panel">
          <h2>GitHub Updates</h2>
          <p class="description">Configure WordPress plugin updates from GitHub Releases.</p>

          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="cls_github_updates_enabled">Enable GitHub updates</label></th>
              <td>
                <label>
                  <input id="cls_github_updates_enabled" name="<?php echo esc_attr($optKey); ?>[github_updates_enabled]" type="checkbox" value="1" <?php checked(!empty($s['github_updates_enabled'])); ?> />
                  Let WordPress check GitHub for new plugin versions.
                </label>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_github_repo">GitHub repo</label></th>
              <td>
                <input id="cls_github_repo" name="<?php echo esc_attr($optKey); ?>[github_repo]" type="text" value="<?php echo esc_attr($s['github_repo']); ?>" class="regular-text" placeholder="owner/repo" />
                <p class="description">Format: <code>owner/repo</code> (example: <code>xanderstudios/church-livestream-switcher</code>).</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_github_token">GitHub token (optional)</label></th>
              <td>
                <input id="cls_github_token" name="<?php echo esc_attr($optKey); ?>[github_token]" type="password" value="" class="regular-text" autocomplete="new-password" />
                <p class="description">Use a token for private repos or higher API limits. Leave blank to keep existing token.</p>
                <p class="description">Saved token: <code><?php echo esc_html($githubTokenPreview); ?></code></p>
                <label>
                  <input name="<?php echo esc_attr($optKey); ?>[github_token_clear]" type="checkbox" value="1" />
                  Clear saved GitHub token on next save.
                </label>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_github_include_prerelease">Include pre-releases</label></th>
              <td>
                <label>
                  <input id="cls_github_include_prerelease" name="<?php echo esc_attr($optKey); ?>[github_include_prerelease]" type="checkbox" value="1" <?php checked(!empty($s['github_include_prerelease'])); ?> />
                  Allow updates from GitHub prerelease tags.
                </label>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_github_cache_ttl_seconds">Update metadata cache TTL (seconds)</label></th>
              <td>
                <input id="cls_github_cache_ttl_seconds" name="<?php echo esc_attr($optKey); ?>[github_cache_ttl_seconds]" type="number" min="300" value="<?php echo esc_attr($s['github_cache_ttl_seconds']); ?>" />
                <p class="description">How long release metadata stays cached before querying GitHub again.</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_github_asset_name">Release asset filename (optional)</label></th>
              <td>
                <input id="cls_github_asset_name" name="<?php echo esc_attr($optKey); ?>[github_asset_name]" type="text" value="<?php echo esc_attr($s['github_asset_name']); ?>" class="regular-text" placeholder="church-livestream-switcher.zip" />
                <p class="description">If set, updater will prefer this exact ZIP asset name from each release.</p>
              </td>
            </tr>
          </table>

          <p class="description">Release tags should use versions like <code>v1.7.0</code> or <code>1.7.0</code>.</p>
        </section>

        <section id="cls-tab-options" class="cls-tab-panel">
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="cls_cache_ttl">Backend cache TTL (seconds)</label></th>
              <td>
                <input id="cls_cache_ttl" name="<?php echo esc_attr($optKey); ?>[cache_ttl_seconds]" type="number" min="10" value="<?php echo esc_attr($s['cache_ttl_seconds']); ?>" />
                <p class="description">How long live/upcoming detection results are cached during schedule windows.</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_low_quota_mode">Low Quota Mode</label></th>
              <td>
                <label>
                  <input id="cls_low_quota_mode" name="<?php echo esc_attr($optKey); ?>[low_quota_mode]" type="checkbox" value="1" <?php checked(!empty($s['low_quota_mode'])); ?> />
                  Prioritize minimal API usage.
                </label>
                <p class="description">
                  Enforces quota-safe runtime values: backend cache at least <?php echo esc_html((string) $lowQuotaCacheTtlSeconds); ?>s, front-end refresh at least <?php echo esc_html((string) $lowQuotaPollSeconds); ?>s, uploads playlist cache at least <?php echo esc_html((string) $lowQuotaUploadsTtlSeconds); ?>s (7 days), lookback capped at <?php echo esc_html((string) $lowQuotaLookbackMax); ?>.
                </p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_poll_interval">Front-end refresh (seconds)</label></th>
              <td>
                <input id="cls_poll_interval" name="<?php echo esc_attr($optKey); ?>[poll_interval_seconds]" type="number" min="30" value="<?php echo esc_attr($s['poll_interval_seconds']); ?>" />
                <p class="description">How often the embed re-checks during schedule windows. Recommended 120–300.</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_precheck_minutes">Pre-check before start (minutes)</label></th>
              <td>
                <input id="cls_precheck_minutes" name="<?php echo esc_attr($optKey); ?>[precheck_minutes]" type="number" min="0" max="720" value="<?php echo esc_attr($s['precheck_minutes']); ?>" />
                <p class="description">Start schedule-window API checks this many minutes before each scheduled start (weekly and one-time).</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_lookback">Lookback count</label></th>
              <td>
                <input id="cls_lookback" name="<?php echo esc_attr($optKey); ?>[lookback_count]" type="number" min="3" max="25" value="<?php echo esc_attr($s['lookback_count']); ?>" />
                <p class="description">How many recent uploads to scan for live/upcoming.</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label for="cls_uploads_ttl">Uploads playlist cache TTL (seconds)</label></th>
              <td>
                <input id="cls_uploads_ttl" name="<?php echo esc_attr($optKey); ?>[uploads_cache_ttl_seconds]" type="number" min="3600" value="<?php echo esc_attr($s['uploads_cache_ttl_seconds']); ?>" />
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
                <input id="cls_player_max_width" name="<?php echo esc_attr($optKey); ?>[player_max_width]" type="text" value="<?php echo esc_attr($s['player_max_width']); ?>" class="regular-text" />
                <p class="description">Examples: <code>100%</code>, <code>1280px</code>, <code>90vw</code>.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_aspect_ratio">Aspect ratio</label></th>
              <td>
                <input id="cls_player_aspect_ratio" name="<?php echo esc_attr($optKey); ?>[player_aspect_ratio]" type="text" value="<?php echo esc_attr($s['player_aspect_ratio']); ?>" class="small-text" />
                <p class="description">Format <code>width:height</code>, e.g. <code>16:9</code>, <code>4:3</code>.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_fixed_height_px">Fixed height (px)</label></th>
              <td>
                <input id="cls_player_fixed_height_px" name="<?php echo esc_attr($optKey); ?>[player_fixed_height_px]" type="number" min="0" max="2160" value="<?php echo esc_attr($s['player_fixed_height_px']); ?>" />
                <p class="description">Set <code>0</code> for responsive ratio mode. Any positive value forces fixed height.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_border_radius_px">Border radius (px)</label></th>
              <td><input id="cls_player_border_radius_px" name="<?php echo esc_attr($optKey); ?>[player_border_radius_px]" type="number" min="0" max="200" value="<?php echo esc_attr($s['player_border_radius_px']); ?>" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_background">Background color</label></th>
              <td><input id="cls_player_background" name="<?php echo esc_attr($optKey); ?>[player_background]" type="text" value="<?php echo esc_attr($s['player_background']); ?>" class="small-text" placeholder="#000000" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_box_shadow">Box shadow</label></th>
              <td>
                <input id="cls_player_box_shadow" name="<?php echo esc_attr($optKey); ?>[player_box_shadow]" type="text" value="<?php echo esc_attr($s['player_box_shadow']); ?>" class="regular-text" />
                <p class="description">Raw CSS value, example: <code>0 10px 40px rgba(0,0,0,.25)</code>.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_wrapper_class">Wrapper CSS class(es)</label></th>
              <td><input id="cls_player_wrapper_class" name="<?php echo esc_attr($optKey); ?>[player_wrapper_class]" type="text" value="<?php echo esc_attr($s['player_wrapper_class']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_iframe_class">IFrame CSS class(es)</label></th>
              <td><input id="cls_player_iframe_class" name="<?php echo esc_attr($optKey); ?>[player_iframe_class]" type="text" value="<?php echo esc_attr($s['player_iframe_class']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_frame_title">IFrame title</label></th>
              <td><input id="cls_player_frame_title" name="<?php echo esc_attr($optKey); ?>[player_frame_title]" type="text" value="<?php echo esc_attr($s['player_frame_title']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_loading">IFrame loading</label></th>
              <td>
                <select id="cls_player_loading" name="<?php echo esc_attr($optKey); ?>[player_loading]">
                  <option value="eager" <?php selected($s['player_loading'], 'eager'); ?>>eager</option>
                  <option value="lazy" <?php selected($s['player_loading'], 'lazy'); ?>>lazy</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_referrerpolicy">Referrer policy</label></th>
              <td>
                <select id="cls_player_referrerpolicy" name="<?php echo esc_attr($optKey); ?>[player_referrerpolicy]">
                  <?php foreach ($referrerPolicies as $policy): ?>
                    <option value="<?php echo esc_attr($policy); ?>" <?php selected($s['player_referrerpolicy'], $policy); ?>>
                      <?php echo esc_html($policy === '' ? '(none)' : $policy); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_allow">IFrame allow permissions</label></th>
              <td><input id="cls_player_allow" name="<?php echo esc_attr($optKey); ?>[player_allow]" type="text" value="<?php echo esc_attr($s['player_allow']); ?>" class="regular-text" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_allowfullscreen">Allow fullscreen attribute</label></th>
              <td>
                <label>
                  <input id="cls_player_allowfullscreen" name="<?php echo esc_attr($optKey); ?>[player_allowfullscreen]" type="checkbox" value="1" <?php checked(!empty($s['player_allowfullscreen'])); ?> />
                  Output <code>allowfullscreen</code> attribute on iframe.
                </label>
              </td>
            </tr>
          </table>

          <h2>YouTube Player Parameters</h2>
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="cls_player_controls">Show controls</label></th>
              <td>
                <input id="cls_player_controls" name="<?php echo esc_attr($optKey); ?>[player_controls]" type="checkbox" value="1" <?php checked(!empty($s['player_controls'])); ?> />
                <p class="description">When <code>Mute live video</code> is enabled, controls are forced on for live streams so viewers can unmute.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_autoplay_live">Autoplay live video</label></th>
              <td><input id="cls_player_autoplay_live" name="<?php echo esc_attr($optKey); ?>[player_autoplay_live]" type="checkbox" value="1" <?php checked(!empty($s['player_autoplay_live'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_force_live_autoplay">Force autoplay on live transition</label></th>
              <td>
                <label>
                  <input id="cls_player_force_live_autoplay" name="<?php echo esc_attr($optKey); ?>[player_force_live_autoplay]" type="checkbox" value="1" <?php checked(!empty($s['player_force_live_autoplay'])); ?> />
                  Send a JS play command when status switches to <code>live_video</code>.
                </label>
                <p class="description">Use this if autoplay is inconsistent on transition from upcoming to live. Works best when live video is muted.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_mute_live">Mute live video</label></th>
              <td><input id="cls_player_mute_live" name="<?php echo esc_attr($optKey); ?>[player_mute_live]" type="checkbox" value="1" <?php checked(!empty($s['player_mute_live'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_autoplay_playlist">Autoplay playlist fallback</label></th>
              <td><input id="cls_player_autoplay_playlist" name="<?php echo esc_attr($optKey); ?>[player_autoplay_playlist]" type="checkbox" value="1" <?php checked(!empty($s['player_autoplay_playlist'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_mute_playlist">Mute playlist fallback</label></th>
              <td><input id="cls_player_mute_playlist" name="<?php echo esc_attr($optKey); ?>[player_mute_playlist]" type="checkbox" value="1" <?php checked(!empty($s['player_mute_playlist'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_loop">Loop playback</label></th>
              <td>
                <label>
                  <input id="cls_player_loop" name="<?php echo esc_attr($optKey); ?>[player_loop]" type="checkbox" value="1" <?php checked(!empty($s['player_loop'])); ?> />
                  Loop playlist and single live embeds.
                </label>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_rel">Show related videos</label></th>
              <td><input id="cls_player_rel" name="<?php echo esc_attr($optKey); ?>[player_rel]" type="checkbox" value="1" <?php checked(!empty($s['player_rel'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_fs">Show fullscreen button</label></th>
              <td><input id="cls_player_fs" name="<?php echo esc_attr($optKey); ?>[player_fs]" type="checkbox" value="1" <?php checked(!empty($s['player_fs'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_modestbranding">Modest branding</label></th>
              <td><input id="cls_player_modestbranding" name="<?php echo esc_attr($optKey); ?>[player_modestbranding]" type="checkbox" value="1" <?php checked(!empty($s['player_modestbranding'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_disablekb">Disable keyboard shortcuts</label></th>
              <td><input id="cls_player_disablekb" name="<?php echo esc_attr($optKey); ?>[player_disablekb]" type="checkbox" value="1" <?php checked(!empty($s['player_disablekb'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_playsinline">Plays inline on mobile</label></th>
              <td><input id="cls_player_playsinline" name="<?php echo esc_attr($optKey); ?>[player_playsinline]" type="checkbox" value="1" <?php checked(!empty($s['player_playsinline'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_iv_load_policy">Annotations / cards policy</label></th>
              <td>
                <select id="cls_player_iv_load_policy" name="<?php echo esc_attr($optKey); ?>[player_iv_load_policy]">
                  <option value="3" <?php selected(intval($s['player_iv_load_policy']), 3); ?>>Hide annotations</option>
                  <option value="1" <?php selected(intval($s['player_iv_load_policy']), 1); ?>>Show annotations</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_cc_load_policy">Show captions by default</label></th>
              <td><input id="cls_player_cc_load_policy" name="<?php echo esc_attr($optKey); ?>[player_cc_load_policy]" type="checkbox" value="1" <?php checked(!empty($s['player_cc_load_policy'])); ?> /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_color">Progress bar color</label></th>
              <td>
                <select id="cls_player_color" name="<?php echo esc_attr($optKey); ?>[player_color]">
                  <option value="red" <?php selected($s['player_color'], 'red'); ?>>red</option>
                  <option value="white" <?php selected($s['player_color'], 'white'); ?>>white</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_start_seconds">Start at seconds</label></th>
              <td><input id="cls_player_start_seconds" name="<?php echo esc_attr($optKey); ?>[player_start_seconds]" type="number" min="0" value="<?php echo esc_attr($s['player_start_seconds']); ?>" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_end_seconds">End at seconds</label></th>
              <td><input id="cls_player_end_seconds" name="<?php echo esc_attr($optKey); ?>[player_end_seconds]" type="number" min="0" value="<?php echo esc_attr($s['player_end_seconds']); ?>" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_hl">Player UI language (`hl`)</label></th>
              <td><input id="cls_player_hl" name="<?php echo esc_attr($optKey); ?>[player_hl]" type="text" value="<?php echo esc_attr($s['player_hl']); ?>" class="small-text" placeholder="en" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_cc_lang_pref">Caption language (`cc_lang_pref`)</label></th>
              <td><input id="cls_player_cc_lang_pref" name="<?php echo esc_attr($optKey); ?>[player_cc_lang_pref]" type="text" value="<?php echo esc_attr($s['player_cc_lang_pref']); ?>" class="small-text" placeholder="en" /></td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_origin_mode">Origin parameter mode</label></th>
              <td>
                <select id="cls_player_origin_mode" name="<?php echo esc_attr($optKey); ?>[player_origin_mode]">
                  <option value="auto" <?php selected($s['player_origin_mode'], 'auto'); ?>>Auto (site origin)</option>
                  <option value="off" <?php selected($s['player_origin_mode'], 'off'); ?>>Off</option>
                  <option value="custom" <?php selected($s['player_origin_mode'], 'custom'); ?>>Custom</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_origin_custom">Custom origin URL</label></th>
              <td>
                <input id="cls_player_origin_custom" name="<?php echo esc_attr($optKey); ?>[player_origin_custom]" type="text" value="<?php echo esc_attr($s['player_origin_custom']); ?>" class="regular-text" placeholder="https://example.com" />
                <p class="description">Used only when mode is <code>Custom</code>.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="cls_player_custom_params">Advanced custom query params</label></th>
              <td>
                <input id="cls_player_custom_params" name="<?php echo esc_attr($optKey); ?>[player_custom_params]" type="text" value="<?php echo esc_attr($s['player_custom_params']); ?>" class="regular-text" placeholder="vq=hd1080&widget_referrer=https%3A%2F%2Fexample.com" />
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
                    <select name="<?php echo esc_attr($optKey); ?>[schedule][<?php echo intval($i); ?>][day]">
                      <?php foreach ($days as $dIdx => $dName): ?>
                        <option value="<?php echo intval($dIdx); ?>" <?php selected(intval($row['day']), $dIdx); ?>>
                          <?php echo esc_html($dName); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input type="time" name="<?php echo esc_attr($optKey); ?>[schedule][<?php echo intval($i); ?>][start]" value="<?php echo esc_attr($row['start']); ?>" /></td>
                  <td><input type="time" name="<?php echo esc_attr($optKey); ?>[schedule][<?php echo intval($i); ?>][end]" value="<?php echo esc_attr($row['end']); ?>" /></td>
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
                  <td><input type="date" name="<?php echo esc_attr($optKey); ?>[one_time_events][<?php echo intval($i); ?>][date]" value="<?php echo esc_attr($row['date']); ?>" /></td>
                  <td><input type="time" name="<?php echo esc_attr($optKey); ?>[one_time_events][<?php echo intval($i); ?>][start]" value="<?php echo esc_attr($row['start']); ?>" /></td>
                  <td><input type="time" name="<?php echo esc_attr($optKey); ?>[one_time_events][<?php echo intval($i); ?>][end]" value="<?php echo esc_attr($row['end']); ?>" /></td>
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
          <textarea name="<?php echo esc_attr($optKey); ?>[import_json]" class="large-text code" rows="6" placeholder='{"schedule":[{"day":0,"start":"09:30","end":"13:00"}],"one_time_events":[{"date":"2026-12-24","start":"18:30","end":"21:00"}]}'></textarea>
        </section>

        <section id="cls-tab-live-chat" class="cls-tab-panel">
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="cls_chat_show_upcoming">Show chat for upcoming</label></th>
              <td>
                <label>
                  <input id="cls_chat_show_upcoming" name="<?php echo esc_attr($optKey); ?>[chat_show_upcoming]" type="checkbox" value="1" <?php checked(!empty($s['chat_show_upcoming'])); ?> />
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

        <section id="cls-tab-live-status" class="cls-tab-panel">
          <h2>YouTube API Live Status</h2>
          <p class="description">Direct server-side YouTube Data API check for current <code>live_video</code> and <code>upcoming_video</code> candidates.</p>
          <p>
            <a href="<?php echo esc_url($liveStatusRefreshUrl); ?>" class="button button-secondary">Refresh from YouTube API</a>
          </p>

          <?php
            $statusTimezone = !empty($liveStatus['timezone']) ? (string) $liveStatus['timezone'] : 'UTC';
            try { $statusTz = new DateTimeZone($statusTimezone); } catch (Exception $e) { $statusTz = new DateTimeZone('UTC'); $statusTimezone = 'UTC'; }
            $formatStatusTime = static function($iso) use ($statusTz) {
              $raw = trim((string) $iso);
              if ($raw === '') return '—';
              try {
                $dt = new DateTime($raw);
                $dt->setTimezone($statusTz);
                return $dt->format('Y-m-d H:i:s T');
              } catch (Exception $e) {
                return $raw;
              }
            };
            $fetchedAtText = !empty($liveStatus['fetchedAt']) ? wp_date('Y-m-d H:i:s T', intval($liveStatus['fetchedAt'])) : '—';
            $liveRows = is_array($liveStatus['live'] ?? null) ? $liveStatus['live'] : [];
            $upcomingRows = is_array($liveStatus['upcoming'] ?? null) ? $liveStatus['upcoming'] : [];
          ?>

          <table class="widefat striped" style="max-width:980px;margin-bottom:16px;">
            <tbody>
              <tr>
                <th style="width:260px;">Fetched at</th>
                <td><?php echo esc_html($fetchedAtText); ?></td>
              </tr>
              <tr>
                <th>Timezone</th>
                <td><code><?php echo esc_html($statusTimezone); ?></code></td>
              </tr>
              <tr>
                <th>In schedule window</th>
                <td><?php echo !empty($liveStatus['inWindow']) ? 'Yes' : 'No'; ?></td>
              </tr>
              <tr>
                <th>Lookback used</th>
                <td><?php echo esc_html((string) intval($liveStatus['lookback'] ?? 0)); ?></td>
              </tr>
              <tr>
                <th>Uploads playlist id</th>
                <td><code><?php echo esc_html((string) ($liveStatus['uploadsPlaylistId'] ?? '—')); ?></code></td>
              </tr>
              <tr>
                <th>Videos scanned</th>
                <td><?php echo esc_html((string) intval($liveStatus['scannedCount'] ?? 0)); ?></td>
              </tr>
              <tr>
                <th>Playlist items returned</th>
                <td><?php echo esc_html((string) intval($liveStatus['playlistItemsCount'] ?? 0)); ?></td>
              </tr>
              <tr>
                <th>Candidate ids queried</th>
                <td><?php echo esc_html((string) intval($liveStatus['candidateCount'] ?? 0)); ?></td>
              </tr>
              <tr>
                <th>Search live candidates</th>
                <td><?php echo esc_html((string) intval($liveStatus['searchLiveCount'] ?? 0)); ?></td>
              </tr>
              <tr>
                <th>Search upcoming candidates</th>
                <td><?php echo esc_html((string) intval($liveStatus['searchUpcomingCount'] ?? 0)); ?></td>
              </tr>
              <tr>
                <th>Detected live videos</th>
                <td><?php echo esc_html((string) count($liveRows)); ?></td>
              </tr>
              <tr>
                <th>Detected upcoming videos</th>
                <td><?php echo esc_html((string) count($upcomingRows)); ?></td>
              </tr>
            </tbody>
          </table>

          <?php if (!empty($liveStatus['error'])): ?>
            <div class="notice notice-error inline">
              <p>
                <strong>YouTube API error:</strong>
                <?php echo esc_html((string) $liveStatus['error']); ?>
              </p>
            </div>
          <?php endif; ?>

          <h3 style="margin-top:20px;">Live Streams</h3>
          <table class="widefat striped">
            <thead>
              <tr>
                <th style="width:36%;">Video</th>
                <th style="width:14%;">Broadcast flag</th>
                <th style="width:18%;">Scheduled start</th>
                <th style="width:18%;">Actual start</th>
                <th style="width:7%;">Privacy</th>
                <th style="width:7%;">Embed</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($liveRows)): ?>
                <?php foreach ($liveRows as $row): ?>
                  <tr>
                    <td>
                      <a href="<?php echo esc_url((string) ($row['url'] ?? '')); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html((string) ($row['title'] ?? '(untitled)')); ?>
                      </a>
                      <div><code><?php echo esc_html((string) ($row['videoId'] ?? '')); ?></code></div>
                    </td>
                    <td><code><?php echo esc_html((string) ($row['liveBroadcastContent'] ?? '')); ?></code></td>
                    <td><?php echo esc_html($formatStatusTime($row['scheduledStartTime'] ?? '')); ?></td>
                    <td><?php echo esc_html($formatStatusTime($row['actualStartTime'] ?? '')); ?></td>
                    <td><?php echo esc_html((string) ($row['privacyStatus'] ?? '')); ?></td>
                    <td><?php echo !empty($row['embeddable']) ? 'Yes' : 'No'; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6">No live streams returned by YouTube API in the current lookback window.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>

          <h3 style="margin-top:20px;">Upcoming Streams</h3>
          <table class="widefat striped">
            <thead>
              <tr>
                <th style="width:40%;">Video</th>
                <th style="width:18%;">Scheduled start</th>
                <th style="width:18%;">Broadcast flag</th>
                <th style="width:12%;">Privacy</th>
                <th style="width:12%;">Embed</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($upcomingRows)): ?>
                <?php foreach ($upcomingRows as $row): ?>
                  <tr>
                    <td>
                      <a href="<?php echo esc_url((string) ($row['url'] ?? '')); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html((string) ($row['title'] ?? '(untitled)')); ?>
                      </a>
                      <div><code><?php echo esc_html((string) ($row['videoId'] ?? '')); ?></code></div>
                    </td>
                    <td><?php echo esc_html($formatStatusTime($row['scheduledStartTime'] ?? '')); ?></td>
                    <td><code><?php echo esc_html((string) ($row['liveBroadcastContent'] ?? '')); ?></code></td>
                    <td><?php echo esc_html((string) ($row['privacyStatus'] ?? '')); ?></td>
                    <td><?php echo !empty($row['embeddable']) ? 'Yes' : 'No'; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="5">No upcoming streams returned by YouTube API in the current lookback window.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
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
          const key = <?php echo wp_json_encode($optKey); ?>;
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
          const key = <?php echo wp_json_encode($optKey); ?>;
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
