/*
  Resurrection UI: the nav hotkeys (Phase 5 task 6).

  The page carries its key map as data, <script type="application/json" id="rz-keys">, written by
  resurrection_nav_script() in lib/pageparts.php: a list of [key, action, url, ...], the key uppercase. This file does
  what 1.1.2's inline document.onkeypress script did with the same keys:

  - "go": follow the link (window.location);
  - "open": open the link in a new window (window.open(url));
  - "popup": open it as popup() does (window.open(url, name, features).focus()).

  Like 1.1.2, a key does nothing while typing in an input or a textarea, or with Alt or Ctrl held, and a key that
  goes somewhere is not also typed. Every nav is a real link, so the game works the same without this file.
*/
(function () {
  'use strict';

  var data = document.getElementById('rz-keys');
  if (!data) { return; }
  var keys = {};
  try {
    var list = JSON.parse(data.textContent);
    for (var i = 0; i < list.length; i++) {
      // A key the page used twice goes where the later one does, as in 1.1.2's map.
      keys[list[i][0]] = list[i].slice(1);
    }
  } catch (e) {
    return;
  }

  document.addEventListener('keypress', function (e) {
    var target = e.target;
    var name = target && target.nodeName ? String(target.nodeName).toUpperCase() : '';
    if (name === 'INPUT' || name === 'TEXTAREA' || e.altKey || e.ctrlKey) { return; }
    var c = (typeof e.key === 'string' && e.key.length === 1) ? e.key : String.fromCharCode(e.charCode || e.keyCode || 0);
    c = c.toUpperCase();
    if (!Object.prototype.hasOwnProperty.call(keys, c)) { return; }
    var action = keys[c];
    e.preventDefault();
    if (action[0] === 'go') {
      window.location = action[1];
    } else if (action[0] === 'open') {
      window.open(action[1]);
    } else if (action[0] === 'popup') {
      var popup = window.open(action[1], action[2], action[3]);
      if (popup) { popup.focus(); }
    }
  });
}());
