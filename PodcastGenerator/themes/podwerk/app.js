/* Podwerk theme — inline play in the episode list.
 * Clicking a track's play button reveals a native audio player in that row
 * and starts it. Only one plays at a time. No dependencies. */
(function () {
  'use strict';
  var current = null; // { btn, row, audio }

  function stop() {
    if (!current) return;
    current.audio.pause();
    current.row.remove();
    current.btn.classList.remove('is-playing');
    current.btn.setAttribute('aria-pressed', 'false');
    current = null;
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.play') : null;
    if (!btn || btn.classList.contains('play-link') || !btn.dataset.src) return;
    e.preventDefault();

    if (current && current.btn === btn) { stop(); return; }
    stop();

    var track = btn.closest('.track');
    if (!track) return;
    var row = document.createElement('div');
    row.className = 'track-player';
    var audio = document.createElement('audio');
    audio.controls = true;
    audio.preload = 'none';
    var source = document.createElement('source');
    source.src = btn.dataset.src;
    if (btn.dataset.type) source.type = btn.dataset.type;
    audio.appendChild(source);
    row.appendChild(audio);
    track.insertAdjacentElement('afterend', row);

    btn.classList.add('is-playing');
    btn.setAttribute('aria-pressed', 'true');
    current = { btn: btn, row: row, audio: audio };
    audio.play().catch(function () { /* user can press play in the controls */ });
    audio.addEventListener('ended', stop);
  });
})();
