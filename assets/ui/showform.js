/*
  Resurrection UI: forms (Phase 5 task 9). Replaces the inline scripts 1.1.2 printed into its forms; none of this
  file's markup carries an event handler.

  Tabs: showform() prints its sections' tab list hidden, with the first section's tab aria-selected, and every
  section shown. This script shows the list, shows only the chosen section, and keeps aria-selected, the tab order
  and showFormTabIndex in step: a hidden field the script adds to the form, as 1.1.2's script did, so a save returns
  to the same section. Click a tab, or use Left, Right, Home and End on the focused tab.
  Without the script the list stays hidden and every section stays shown, so the form still works.

  Resize: a resizeable textarea's + and - buttons (data-rz-resize, data-rz-step) change its rows, between 4 and 49
  as 1.1.2's increase() did.

  Confirm: a submit button with data-rz-confirm asks first, and the form is not sent unless the player agrees.
*/
(function () {
  'use strict';

  function setUpTabs(list) {
    var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
    if (tabs.length === 0) { return; }
    var index = document.createElement('input');
    index.type = 'hidden';
    index.name = 'showFormTabIndex';
    list.parentNode.insertBefore(index, list.nextSibling);
    var panels = tabs.map(function (tab) { return document.getElementById(tab.getAttribute('aria-controls')); });

    function select(chosen, focus) {
      tabs.forEach(function (tab, n) {
        var on = n === chosen;
        tab.setAttribute('aria-selected', on ? 'true' : 'false');
        tab.tabIndex = on ? 0 : -1;
        if (panels[n]) { panels[n].hidden = !on; }
      });
      index.value = tabs[chosen].getAttribute('data-rz-tab');
      if (focus) { tabs[chosen].focus(); }
    }

    var current = 0;
    tabs.forEach(function (tab, n) {
      if (tab.getAttribute('aria-selected') === 'true') { current = n; }
      tab.addEventListener('click', function () { select(n, false); });
      tab.addEventListener('keydown', function (e) {
        var next = -1;
        if (e.key === 'ArrowRight') { next = (n + 1) % tabs.length; }
        else if (e.key === 'ArrowLeft') { next = (n - 1 + tabs.length) % tabs.length; }
        else if (e.key === 'Home') { next = 0; }
        else if (e.key === 'End') { next = tabs.length - 1; }
        if (next < 0) { return; }
        e.preventDefault();
        select(next, true);
      });
    });
    list.hidden = false;
    select(current, false);
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-rz-tabs]'), setUpTabs);

  Array.prototype.forEach.call(document.querySelectorAll('[data-rz-resize]'), function (button) {
    button.addEventListener('click', function () {
      var box = document.getElementById(button.getAttribute('data-rz-resize'));
      var step = parseInt(button.getAttribute('data-rz-step'), 10) || 0;
      if (box && box.rows + step > 3 && box.rows + step < 50) { box.rows = box.rows + step; }
    });
  });

  Array.prototype.forEach.call(document.querySelectorAll('[data-rz-confirm]'), function (button) {
    button.addEventListener('click', function (e) {
      if (!window.confirm(button.getAttribute('data-rz-confirm'))) { e.preventDefault(); }
    });
  });
}());
