/*
  Resurrection UI: the phone's bottom tab bar (Phase 5 task 8). Each tab is a plain in-page link and works without
  this script; the script only marks the tab whose section is in view (class rz-tab-current, aria-current). A section
  counts as in view when its top has passed 40% of the window's height and its bottom has not; the last such tab wins.
*/
(function () {
  'use strict';
  var bar = document.querySelector('.rz-tabbar');
  if (!bar) { return; }
  var tabs = Array.prototype.slice.call(bar.querySelectorAll('a.rz-tab'));
  var targets = tabs.map(function (tab) { return document.getElementById(tab.getAttribute('href').slice(1)); });
  var pending = false;

  function mark() {
    pending = false;
    var line = window.innerHeight * 0.4;
    var current = -1;
    targets.forEach(function (target, n) {
      if (!target) { return; }
      var box = target.getBoundingClientRect();
      if (box.top <= line && box.bottom > 0) { current = n; }
    });
    tabs.forEach(function (tab, n) {
      tab.classList.toggle('rz-tab-current', n === current);
      if (n === current) { tab.setAttribute('aria-current', 'location'); } else { tab.removeAttribute('aria-current'); }
    });
  }

  function schedule() {
    if (!pending) { pending = true; window.requestAnimationFrame(mark); }
  }

  window.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule);
  mark();
}());
