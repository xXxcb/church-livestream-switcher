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

      <script<?php if (!empty($cspNonce)): ?> nonce="<?php echo esc_attr($cspNonce); ?>"<?php endif; ?>>
        (function(){
          const SWITCHING_ENABLED = <?php echo wp_json_encode($enabled); ?>;
          const SHOW_UPCOMING_CHAT = <?php echo wp_json_encode($showUpcoming); ?>;
          const POLL_SECONDS = <?php echo wp_json_encode($poll); ?>;
          const EMBED_DOMAIN = <?php echo wp_json_encode($embedDomain); ?>;
          const STATUS_PATH = <?php echo wp_json_encode($statusPath); ?>;
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

          function buildStatusUrl() {
            const url = new URL(STATUS_PATH || '/wp-json/church-live/v1/status', window.location.origin);
            url.searchParams.set('_cls', Date.now().toString());
            return url.toString();
          }

          async function refresh() {
            if (!SWITCHING_ENABLED) {
              showOffline();
              return;
            }

            try {
              const res = await fetch(buildStatusUrl(), {
                cache: 'no-store',
                credentials: 'same-origin',
              });
              if (!res.ok) throw new Error('status request failed');
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
          if (POLL_SECONDS) {
            if (POLL_SECONDS > 0) {
              setInterval(refresh, POLL_SECONDS * 1000);
            }
          }
        })();
      </script>
