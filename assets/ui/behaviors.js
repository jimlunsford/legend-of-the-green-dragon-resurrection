/*
  Resurrection UI: page behaviors (Phase 6 task 3, S1; docs/PHASE6-TASK3-HARDENING-20261106.md).

  Every page and popup loads this file. It does what 1.1.2's inline scripts and inline event handlers (onclick,
  onchange, onsubmit, onkeyup) did, so the pages pass a Content Security Policy that runs only the game's own files.
  The markup carries data attributes instead, and this file reads them:

  - data-rz-confirm="text": a link, a button or a form asks first; a "no" stops the click or the submit.
    data-rz-confirm-when-zero="id" asks only while the field with that id holds 0 (the ban form's permanent ban).
  - data-rz-popup="WxH": the link opens as popup() opened it, a window named after the page with everything but
    letters and digits removed (popup_window()). data-rz-popup-media="desktop" does that only on a desktop with a
    mouse (the header controls); anywhere else, or if the window is blocked, the link is followed.
  - data-rz-focus="id" (a hidden marker): the field with that id gets the focus.
  - data-rz-autosubmit: a select sends its form when it changes.
  - data-rz-copy-to="id": a field copies its value to another when it changes.
  - data-rz-uncheck-other="id": a checkbox disables the other one while it is ticked and unticks it (the companion
    editor's fighter and defender).
  - data-rz-reveal-area: hidden when the page loads; data-rz-reveal="id" shows it again (the clan's Edit Clan Info).
  - data-rz-fill="id", data-rz-fill-value, data-rz-fill-check="id": a link writes the value into a field and ticks a
    radio button (the ban form's "Use this filter").
  - data-rz-append="id", data-rz-append-html: a link adds the markup to the end of an element (the MoTD poll's
    Add Poll Item).
  - data-rz-userinfo="id": a link loads its own XML list of names and writes them into the element (the ban list's
    and the mount editor's owners).
  - data-rz-close-window (a hidden marker): the popup closes itself (the translator tool after a save).
  - data-rz-mail-warnings='["login", ...]' on the mail form's warning: shown while the recipient (data-rz-mail-to)
    is one of those players.
  - data-rz-points-left="N" on the dragon point form: the "points left to spend" counter (data-rz-points-target,
    data-rz-points-text).
  - data-rz-preview="name" on a text field: previewfield()'s live preview of the color codes and the characters left.
  - data-rz-copy="id" on a button (hidden in the markup): shown when the page loads; a click copies the value of the
    field with that id to the clipboard, and the button says data-rz-copied for a moment (Game Settings' web
    maintenance address, Phase 6 task 5a). Without this file the field stays readonly and selectable.

  Every one of these was an enhancement in 1.1.2 too: the game works without this file.
*/
(function () {
  'use strict';

  function byId(id) { return id ? document.getElementById(id) : null; }

  function closest(node, selector) {
    while (node && node.nodeType === 1) {
      if (node.matches(selector)) { return node; }
      node = node.parentNode;
    }
    return null;
  }

  function each(selector, fn) { Array.prototype.forEach.call(document.querySelectorAll(selector), fn); }

  // A popup window, as popup() and popup_window() in lib/pageparts.php name and size it.
  function openPopup(link) {
    var size = String(link.getAttribute('data-rz-popup') || '550x300').split('x');
    var name = String(link.getAttribute('href') || '').replace(/[^A-Za-z0-9]/g, '');
    var features = 'scrollbars=yes,resizable=yes,width=' + size[0] + ',height=' + size[1];
    var popup = window.open(link.href, name, features);
    if (popup) { popup.focus(); }
    return popup;
  }

  var DESKTOP = '(min-width: 900px) and (hover: hover) and (pointer: fine)';

  document.addEventListener('click', function (e) {
    var target = e.target;

    var asking = closest(target, 'a[data-rz-confirm], button[data-rz-confirm], input[data-rz-confirm]');
    if (asking) {
      var field = byId(asking.getAttribute('data-rz-confirm-when-zero'));
      var ask = !asking.hasAttribute('data-rz-confirm-when-zero') || (field && field.value == 0);
      if (ask && !window.confirm(asking.getAttribute('data-rz-confirm'))) {
        e.preventDefault();
        e.stopImmediatePropagation();
        return;
      }
    }

    var link = closest(target, 'a[data-rz-popup]');
    if (link) {
      var media = link.getAttribute('data-rz-popup-media');
      if (media === 'desktop' && !(window.matchMedia && window.matchMedia(DESKTOP).matches)) { return; }
      if (openPopup(link)) { e.preventDefault(); }
      return;
    }

    var reveal = closest(target, '[data-rz-reveal]');
    if (reveal) {
      var area = byId(reveal.getAttribute('data-rz-reveal'));
      if (area) {
        area.style.visibility = 'visible';
        area.style.display = 'inline';
      }
      e.preventDefault();
      return;
    }

    var fill = closest(target, '[data-rz-fill]');
    if (fill) {
      var input = byId(fill.getAttribute('data-rz-fill'));
      var check = byId(fill.getAttribute('data-rz-fill-check'));
      if (input) { input.value = fill.getAttribute('data-rz-fill-value'); }
      if (check) { check.checked = true; }
      e.preventDefault();
      return;
    }

    var append = closest(target, '[data-rz-append]');
    if (append) {
      var into = byId(append.getAttribute('data-rz-append'));
      if (into) { into.insertAdjacentHTML('beforeend', append.getAttribute('data-rz-append-html') || ''); }
      e.preventDefault();
      return;
    }

    var info = closest(target, 'a[data-rz-userinfo]');
    if (info) {
      var box = byId(info.getAttribute('data-rz-userinfo'));
      if (!box) { return; }
      e.preventDefault();
      var request = new XMLHttpRequest();
      request.onload = function () {
        var xml = request.responseXML;
        if (!xml || !xml.documentElement) { return; }
        var names = '';
        for (var x = 0; x < xml.documentElement.childNodes.length; x++) {
          var node = xml.documentElement.childNodes[x];
          if (node.nodeType !== 1) { continue; }
          names += unescape(String(node.getAttribute('name')).replace(/\+/g, ' ')) + '<br>';
        }
        box.innerHTML = names;
      };
      request.open('GET', info.href);
      request.send();
      return;
    }

    var copier = closest(target, 'button[data-rz-copy]');
    if (copier) {
      var source = byId(copier.getAttribute('data-rz-copy'));
      if (!source) { return; }
      e.preventDefault();
      var label = copier.getAttribute('data-rz-label') || copier.textContent;
      copier.setAttribute('data-rz-label', label);
      var done = function () {
        copier.textContent = copier.getAttribute('data-rz-copied') || label;
        setTimeout(function () { copier.textContent = label; }, 2000);
      };
      var fallback = function () {
        source.focus();
        source.select();
        try { if (document.execCommand('copy')) { done(); } } catch (err) { /* the field stays selected */ }
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(source.value).then(done, fallback);
      } else {
        fallback();
      }
      return;
    }

    var toggle = closest(target, 'input[data-rz-uncheck-other]');
    if (toggle) {
      var other = byId(toggle.getAttribute('data-rz-uncheck-other'));
      if (other) {
        other.disabled = toggle.checked;
        if (other.disabled) { other.checked = false; }
      }
    }
  });

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form && form.nodeName === 'FORM' && form.hasAttribute('data-rz-confirm')
        && !window.confirm(form.getAttribute('data-rz-confirm'))) {
      e.preventDefault();
    }
  });

  document.addEventListener('change', function (e) {
    var target = e.target;
    if (!target || target.nodeType !== 1) { return; }
    if (target.hasAttribute('data-rz-autosubmit') && target.form) { target.form.submit(); }
    var copy = byId(target.getAttribute('data-rz-copy-to'));
    if (copy) { copy.value = target.value; }
  });

  // previewfield() in lib/forms.php: 1.1.2's previewtext<name>() for each field, the same output.
  var COLORS = {
    '1': 'colDkBlue', '2': 'colDkGreen', '3': 'colDkCyan', '4': 'colDkRed', '5': 'colDkMagenta', '6': 'colDkYellow',
    '7': 'colDkWhite', 'q': 'colDkOrange', '!': 'colLtBlue', '@': 'colLtGreen', '#': 'colLtCyan', '$': 'colLtRed',
    '%': 'colLtMagenta', '^': 'colLtYellow', '&': 'colLtWhite', 'Q': 'colLtOrange', ')': 'colLtBlack', 'r': 'colRose',
    'R': 'colRose', 'v': 'coliceviolet', 'V': 'colBlueViolet', 'g': 'colXLtGreen', 'G': 'colXLtGreen',
    'T': 'colDkBrown', 't': 'colLtBrown', '~': 'colBlack', 'j': 'colMdGrey', 'J': 'colMdBlue', 'e': 'colDkRust',
    'E': 'colLtRust', 'l': 'colDkLinkBlue', 'L': 'colLtLinkBlue', 'x': 'colburlywood', 'X': 'colbeige', 'y': 'colkhaki',
    'Y': 'coldarkkhaki', 'k': 'colaquamarine', 'K': 'coldarkseagreen', 'p': 'collightsalmon', 'P': 'colsalmon',
    'm': 'colwheat', 'M': 'coltan'
  };

  function preview(field) {
    var name = field.getAttribute('data-rz-preview');
    var data = function (key) { return field.getAttribute('data-rz-preview-' + key) || ''; };
    var t = field.value;
    var l = parseInt(data('limit'), 10) || 200;
    var out = "<span class='colLtWhite'>" + data('start') + ' ';
    var end = '</span>';
    var x = 0;
    if (data('talk') === '1') {
      if (t.substr(0, 2) === '::') {
        x = 2;
        out += "</span><span class='colLtWhite'>";
      } else if (t.substr(0, 1) === ':') {
        x = 1;
        out += "</span><span class='colLtWhite'>";
      } else if (t.substr(0, 3) === '/me') {
        x = 3;
        out += "</span><span class='colLtWhite'>";
      } else if (data('gm') === '1' && t.substr(0, 5) === '/game') {
        x = 5;
        out = "<span class='colLtWhite'>";
      } else {
        out += "</span><span class='colDkCyan'>" + data('talkline') + ", \"</span><span class='colLtCyan'>";
        end += "</span><span class='colDkCyan'>\"";
      }
    }
    if (data('chars') === '1') {
      if (x !== 0) {
        if (field.maxLength !== 200) { field.maxLength = 200; }
        l = 200;
      } else {
        field.maxLength = l;
      }
      var charsleft = '';
      if (l - t.length < 0) { charsleft += "<span class='colLtRed'>"; }
      charsleft += data('youhave') + (l - t.length) + data('charsleft') + '<br>';
      if (l - t.length < 0) { charsleft += '</span>'; }
      var counter = byId('charsleft' + name);
      if (counter) { counter.innerHTML = charsleft + '<br/>'; }
    }
    for (; x < t.length; x++) {
      var y = t.substr(x, 1);
      if (y === '<') {
        out += '&lt;';
      } else if (y === '>') {
        out += '&gt;';
      } else if (y === '\n') {
        out += '<br />';
      } else if (y === '`') {
        if (x < t.length - 1) {
          var z = t.substr(x + 1, 1);
          if (z === '0') {
            out += '</span>';
          } else if (Object.prototype.hasOwnProperty.call(COLORS, z)) {
            out += "</span><span class='" + COLORS[z] + "'>";
          }
          x++;
        }
      } else {
        out += y;
      }
    }
    var shown = byId('previewtext' + name);
    if (shown) { shown.innerHTML = out + end + '<br/>'; }
  }

  document.addEventListener('keyup', function (e) {
    var field = e.target;
    if (field && field.nodeType === 1 && field.hasAttribute('data-rz-preview')) { preview(field); }
  });

  function ready() {
    each('[data-rz-reveal-area]', function (area) {
      area.style.visibility = 'hidden';
      area.style.display = 'none';
    });

    each('[data-rz-mail-warnings]', function (warning) {
      var logins;
      try { logins = JSON.parse(warning.getAttribute('data-rz-mail-warnings')); } catch (err) { return; }
      var to = byId(warning.getAttribute('data-rz-mail-to'));
      if (!to || !Array.isArray(logins)) { return; }
      var update = function () { warning.hidden = logins.indexOf(to.value) < 0; };
      to.addEventListener('change', update);
      update();
    });

    each('form[data-rz-points-left]', function (form) {
      var counter = byId(form.getAttribute('data-rz-points-target'));
      var text = form.getAttribute('data-rz-points-text') || '';
      var update = function () {
        var left = Number(form.getAttribute('data-rz-points-left')) || 0;
        Array.prototype.forEach.call(form.querySelectorAll('input[type=number]'), function (input) {
          left -= Number(input.value) || 0;
        });
        if (counter) { counter.textContent = left + text; }
      };
      form.addEventListener('input', update);
      form.addEventListener('reset', function () { setTimeout(update, 0); });
      update();
    });

    each('button[data-rz-copy]', function (button) { button.hidden = false; });

    each('[data-rz-focus]', function (marker) {
      var field = byId(marker.getAttribute('data-rz-focus'));
      if (field && field.focus) { field.focus(); }
    });

    if (document.querySelector('[data-rz-close-window]')) { window.close(); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready);
  } else {
    ready();
  }
}());
