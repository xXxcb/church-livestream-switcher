# Church Livestream Switcher

WordPress plugin that automatically switches a YouTube embed between:

- live stream
- upcoming stream
- fallback playlist

The switch is controlled by schedule windows so API checks only happen when needed.

## Features

- Weekly schedule windows (`day + start + end`)
- One-time event windows (`date + start + end`)
- Timezone-aware window matching
- Supports public and unlisted embeddable streams
- Detects live and upcoming from your channel uploads
- Fallback to playlist when no live/upcoming stream is found
- Separate shortcode for YouTube live chat
- Auto-return to playlist when a live video ends
- Built-in caching to reduce quota usage
- Low Quota Mode for safer defaults
- Global plugin enable/disable switch (no API calls when disabled)
- Tabbed admin settings (General, Options, Scheduling, Live Chat)
- Chat visibility toggle for upcoming streams
- REST status endpoint for frontend switching and diagnostics

## How It Works

The frontend shortcode calls:

- `/wp-json/church-live/v1/status`

Backend flow:

1. Check if plugin is enabled.
2. Check if current time is inside a weekly or one-time window.
3. If outside window, return playlist mode (no YouTube API calls).
4. If inside window, use cached result if available.
5. If cache miss, call YouTube Data API v3:
   - `channels.list` to get uploads playlist ID
   - `playlistItems.list` to get recent video IDs
   - `videos.list` to detect live/upcoming status
6. Cache result and return status JSON.

Frontend then switches iframe source:

- `live_video` or `upcoming_video` -> embed that video
- everything else -> fallback playlist

## Requirements

- WordPress site with this plugin activated
- YouTube channel ID (`UC...`, not handle format)
- YouTube Data API v3 key
- Fallback playlist ID (recommended)

## Installation

1. Put the plugin folder in `wp-content/plugins/church-livestream-switcher`.
2. Activate **Church Livestream Switcher** in WordPress plugins.
3. Open **Settings -> AC Livestream**.
4. Configure all required fields.
5. Place shortcode on a page:
   - `[church_livestream]`
   - Optional: `[church_livestream height="480"]`
   - Chat: `[church_livestream_chat]`
   - Optional chat attrs: `[church_livestream_chat height="600" offline_message="Chat is available during live services."]`

## Shortcodes

### Video

- `[church_livestream]`
- Optional:
  - `height` (pixels, default `480`)

### Live Chat

- `[church_livestream_chat]`
- Optional:
  - `height` (pixels, minimum `240`, default `600`)
  - `offline_message` (text shown when no live/upcoming stream is active)

Chat behavior:

- Uses the same status endpoint and polling interval as the video switcher
- Always shows chat for `live_video`
- Optionally shows chat for `upcoming_video` (controlled by `Show chat for upcoming`)
- Shows offline message outside window / when not live / when plugin is disabled
- Makes no status fetch calls when global `Plugin enabled` is off

## Settings Reference

- Tabs:
  - `General`: global plugin state and YouTube identifiers
  - `Options`: quota/performance controls
  - `Scheduling`: weekly windows, one-time events, import/export
  - `Live Chat`: chat-specific behavior

- `Plugin enabled`
  - Global on/off switch.
  - Off = no live checks, no API polling, playlist only.

- `Timezone`
  - Used for weekly and one-time windows.
  - Example: `America/Toronto`.

- `YouTube Channel ID`
  - Must be channel ID (`UC...`).

- `Playlist ID (fallback)`
  - Used outside windows and whenever no stream is found.

- `YouTube Data API Key`
  - Required for live/upcoming detection.

- `Backend cache TTL (seconds)`
  - How long status result is cached during active windows.

- `Low Quota Mode`
  - Enforces safer runtime minimums/caps:
    - Backend cache TTL >= 600s
    - Front-end refresh >= 300s
    - Uploads playlist cache TTL >= 604800s (7 days)
    - Lookback count <= 10

- `Front-end refresh (seconds)`
  - How often browser checks status endpoint.

- `Lookback count`
  - Number of recent uploads scanned.

- `Uploads playlist cache TTL (seconds)`
  - How long uploads playlist ID is cached.

- `Show chat for upcoming`
  - When enabled, chat appears for `upcoming_video`.
  - When disabled, chat appears only for `live_video`.

- `Weekly Schedule Windows`
  - Day + start + end.
  - Supports overnight windows (end earlier than start).

- `One-time Event Windows`
  - Date + start + end.
  - Supports overnight windows (end earlier than start).

## Window Logic Notes

- If both weekly and one-time lists are empty, plugin treats this as "always in window."
- If any one-time event window matches now, stream checks are allowed.
- If weekly window matches now, stream checks are allowed.
- Otherwise status returns playlist mode without API calls.

## Import / Export JSON

Export includes both collections:

```json
{
  "schedule": [
    { "day": 0, "start": "09:30", "end": "13:00" }
  ],
  "one_time_events": [
    { "date": "2026-12-24", "start": "18:30", "end": "21:00" }
  ]
}
```

`day` mapping:

- `0` Sunday
- `1` Monday
- `2` Tuesday
- `3` Wednesday
- `4` Thursday
- `5` Friday
- `6` Saturday

## REST Endpoint

### Status

- `GET /wp-json/church-live/v1/status`

Typical response:

```json
{
  "inWindow": true,
  "mode": "live_video",
  "videoId": "abc123"
}
```

`mode` values:

- `live_video`
- `upcoming_video`
- `playlist`

### Debug Mode

- `GET /wp-json/church-live/v1/status?debug=1`

Debug mode includes backend error details and bypasses status cache.

Use only for short troubleshooting sessions. Repeated debug calls can increase quota usage.

## YouTube API Key Setup (Important)

This plugin calls YouTube from your **server (PHP)**, not browser JavaScript.

Use these steps:

1. Open Google Cloud Console.
2. Create/select a project.
3. Enable **YouTube Data API v3**.
4. Create an API key.
5. Set restrictions:
   - `API restrictions`: **YouTube Data API v3**
   - `Application restrictions`:
     - Production: **IP addresses** (your server egress IP)
     - Local testing: **None** (temporary) is simplest

Do **not** use HTTP referrer restriction for this plugin.

### Why

Server-side requests usually have no browser referrer header, which causes:

- `Requests from referer <empty> are blocked.`

## Quota Best Practices (10,000 units/day default)

1. Keep schedule windows tight (only around service times).
2. Enable Low Quota Mode.
3. Keep one-time events only for real special dates.
4. Avoid running `debug=1` continuously.
5. Keep plugin disabled when not needed using `Plugin enabled`.

Recommended values if not using Low Quota Mode:

- Backend cache TTL: `600`
- Front-end refresh: `300`
- Lookback count: `8-10`
- Uploads playlist cache TTL: `604800`

## Quota Exceeded Behavior

When YouTube returns `quotaExceeded`, the plugin backs off by caching playlist fallback until next Pacific midnight (or current cache TTL, whichever is longer).

This prevents constant retries and additional quota waste.

## Troubleshooting

### `Requests from referer <empty> are blocked`

Cause:

- API key restricted by HTTP referrer.

Fix:

- Use server-compatible key restriction (`IP addresses` or temporary `None`) and keep API restricted to YouTube Data API v3.

### `quotaExceeded`

Cause:

- Project daily quota exhausted.

Fix:

- Wait for reset at midnight Pacific.
- Or test with a different Google Cloud project.
- Avoid repeated debug endpoint calls.

### `inWindow: false` while service is active

Check:

- Correct timezone
- Correct day/time schedule
- One-time event date/time if using one-time windows

### Playlist shows unrelated recommendations after stream ends

The plugin now listens for player ended state and switches back to playlist immediately.

If you still see unrelated content, verify fallback `Playlist ID` is set correctly.

### Plugin not switching at all

Check:

- `Plugin enabled` is on
- API key and channel ID are set
- Stream is embeddable and public/unlisted
- Stream falls within schedule/one-time window

## Version

Current plugin header version: `1.4.0`
