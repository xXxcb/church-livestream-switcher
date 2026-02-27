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
          const FORCE_LIVE_AUTOPLAY = <?php echo wp_json_encode(!empty($forceLiveAutoplay)); ?>;
          const FORCE_CONTROLS_ON_MUTED_LIVE = <?php echo wp_json_encode(!empty($forceControlsOnMutedLive)); ?>;
          const CUSTOM_QUERY = <?php echo wp_json_encode($customQuery); ?>;
          const STATUS_PATH = <?php echo wp_json_encode($statusPath); ?>;
          const LIVE_AUTOPLAY_ENABLED = FORCE_LIVE_AUTOPLAY && <?php echo wp_json_encode(!empty($liveParams['autoplay']) && (string) $liveParams['autoplay'] === '1'); ?>;

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

          function buildSrc(mode, videoId, streamMode) {
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
            if (streamMode === 'live_video' && FORCE_CONTROLS_ON_MUTED_LIVE) {
              params.set('controls', '1');
            }
            if (streamMode === 'live_video') {
              params.set('_cls_mode', 'live');
            } else if (streamMode === 'upcoming_video') {
              params.set('_cls_mode', 'upcoming');
            }
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
          let lastMode = 'playlist';
          let pendingLiveAutoplay = false;

          function attemptLiveAutoplay() {
            if (!LIVE_AUTOPLAY_ENABLED || !frame || !frame.contentWindow) return;
            try {
              frame.contentWindow.postMessage(JSON.stringify({
                event: 'command',
                func: 'playVideo',
                args: []
              }), '*');
            } catch (e) {}
          }

          frame.addEventListener('load', function() {
            if (!pendingLiveAutoplay) return;
            setTimeout(attemptLiveAutoplay, 250);
            setTimeout(attemptLiveAutoplay, 1000);
          });

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

          function buildStatusUrl() {
            const url = new URL(STATUS_PATH || '/wp-json/church-live/v1/status', window.location.origin);
            url.searchParams.set('_cls', Date.now().toString());
            return url.toString();
          }

          async function refresh() {
            try {
              const res = await fetch(buildStatusUrl(), {
                cache: 'no-store',
                credentials: 'same-origin',
              });
              if (!res.ok) throw new Error('status request failed');
              const data = await res.json();

              let nextSrc = playlistSrc;
              let currentMode = 'playlist';

              if (data && data.inWindow && (data.mode === 'live_video' || data.mode === 'upcoming_video') && data.videoId) {
                currentMode = data.mode;
                nextSrc = buildSrc('video', data.videoId, currentMode) || playlistSrc;
              }

              if (nextSrc) {
                const modeChanged = currentMode !== lastMode;
                const srcChanged = frame.src !== nextSrc;

                if (srcChanged || modeChanged) {
                  if (!srcChanged && modeChanged) frame.src = 'about:blank';
                  frame.src = nextSrc;
                }
              }

              pendingLiveAutoplay = currentMode === 'live_video';
              if (pendingLiveAutoplay) {
                setTimeout(attemptLiveAutoplay, 500);
                setTimeout(attemptLiveAutoplay, 1500);
              }
              lastMode = currentMode;

              attachEndedFallbackHandler();
            } catch (e) {
              if (playlistSrc && frame.src !== playlistSrc) frame.src = playlistSrc;
              lastMode = 'playlist';
            }
          }

          refresh();
          if (POLL_SECONDS && POLL_SECONDS > 0) {
            setInterval(refresh, POLL_SECONDS * 1000);
          }
        })();
      </script>
