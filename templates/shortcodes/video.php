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
              const res = await fetch(<?php echo wp_json_encode($statusUrl); ?>, { cache: 'no-store' });
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
