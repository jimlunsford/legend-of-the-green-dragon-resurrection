// Phase 6 task 19: the tap-target crawl. Run by tap_targets.py, which installs the game, starts it and checks the result;
// CI has no browser, so this is a local check (CLAUDE.md, "No test may skip in CI"). Every page four roles reach from
// their start pages (signed out, a living player, a dead player, a superuser; the popups included), at 390 x 844 or
// 1440 x 900: every visible link, button, select, checkbox and radio (those two by their largest label), with its box.
// With OLD_CSS, each page is measured a second time with that file in place of assets/ui/resurrection.css (the same
// response, so only the stylesheet differs), and every element's box is compared: what the change moved.
// node tap-targets.js BASE OUT.json [WIDTH] [OLD_CSS]
const { chromium } = require('playwright');
const fs = require('fs');
const [base, out, widthArg, oldCssPath] = process.argv.slice(2);
const oldCss = oldCssPath ? fs.readFileSync(oldCssPath, 'utf8') : null;
const WIDTH = Number(widthArg || 390), HEIGHT = WIDTH === 390 ? 844 : 900;
const SKIP = /(logout|delete|\bdel\b|op=del|remove|ban\b|buy|sell|op=fight|op=run|attack|suicide|quit|op=search|kill|op=challenge|op=autochallenge|resurrect|setnewday|op=enter|op=leave|sacrifice|torment|op=haunt|forgive|op=pay|op=bribe|op=deposit|op=withdraw|op=borrow|op=transfer|op=hire|op=train|op=bet|op=play|op=give|op=steal|source\.php|installer\.php|setup\.php|webcron\.php|translatortool|untranslated|op=read&id=\d+&del|op=address)/i;
const POPUPS = ['mail.php', 'mail.php?op=address', 'petition.php', 'motd.php'];
const roles = [
  { name: 'signed-out', login: null, starts: ['home.php', 'create.php', 'about.php', 'list.php', 'news.php', 'hof.php', 'referral.php', 'petition.php', 'motd.php', 'account.php?op=forgot'] },
  { name: 'player', login: ['CrawlPlayer', 'Synthetic crawl player password'], id: 2, starts: ['village.php', ...POPUPS, 'mail.php?op=read&id=1', 'mail.php?op=write', 'bio.php?char=1'] },
  { name: 'dead', login: ['CrawlDead', 'Synthetic crawl player password'], id: 3, starts: ['shades.php', 'graveyard.php', ...POPUPS] },
  { name: 'superuser', login: ['CrawlAdmin', 'Synthetic crawl admin password'], id: 1, starts: ['village.php', 'superuser.php', ...POPUPS, 'mail.php?op=read&id=2', 'viewpetition.php', 'viewpetition.php?op=view&id=1'] },
];
const pageKey = (href) => {
  const [script, query = ''] = href.split('?');
  const params = new URLSearchParams(query); params.delete('c');
  const names = [...params.keys()].sort();
  const keep = ['op', 'subop', 'module', 'act', 'type', 'cat', 'area', 'action', 'id', 'userid', 'skill', 'l', 'what', 'sort', 'char'];
  return script + '?' + names.map(n => keep.includes(n) && !/^\d{3,}$/.test(params.get(n)) ? n + '=' + params.get(n) : n).join('&');
};
// Every visible link, button, select, checkbox and radio (those two by their label), with its box and a selector.
const MEASURE = () => {
  const describe = (el) => {
    const part = (e) => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\s+/).join('.') : '');
    const chain = []; let e = el;
    while (e && e !== document.body && chain.length < 4) { chain.unshift(part(e)); if (e.id) break; e = e.parentElement; }
    return chain.join(' > ');
  };
  const shown = (el) => {
    const cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none') return null;
    const r = el.getBoundingClientRect();
    if (r.width <= 1 || r.height <= 1) return null;           // empty, or visually hidden (1 x 1 clip)
    if (r.right <= 0 || r.bottom <= -window.scrollY || r.left >= document.documentElement.scrollWidth) return null; // off screen (skip links)
    for (let p = el; p; p = p.parentElement) { const c = getComputedStyle(p); if (c.display === 'none' || Number(c.opacity) === 0) return null; }
    return r;
  };
  // Running text: the link's block holds text of its own outside every link and control.
  const prose = (el) => {
    let block = el.parentElement;
    while (block && getComputedStyle(block).display.startsWith('inline')) block = block.parentElement;
    if (!block) return false;
    let own = '';
    const walk = (n) => { for (const c of n.childNodes) { if (c.nodeType === 3) own += c.textContent; else if (c.nodeType === 1 && !c.matches('a,button,select,input,label,script,style')) walk(c); } };
    walk(block);
    return own.replace(/[\s\[\]|()·•:,.\-]+/g, '').length >= 12;
  };
  const items = [];
  const add = (target, control, kind) => {
    const r = shown(target); if (!r) return;
    items.push({ kind, w: Math.round(r.width * 10) / 10, h: Math.round(r.height * 10) / 10, x: Math.round(r.left), y: Math.round(r.top + window.scrollY),
      sel: describe(control), text: (control.innerText || control.value || control.getAttribute('aria-label') || control.getAttribute('title') || '').trim().replace(/\s+/g, ' ').slice(0, 40),
      href: control.getAttribute('href') || '', prose: kind === 'link' ? prose(control) : false });
  };
  for (const a of document.querySelectorAll('a[href]')) add(a, a, 'link');
  for (const b of document.querySelectorAll('button, input[type=submit], input[type=button], input[type=reset], input[type=image], summary')) add(b, b, 'button');
  for (const s of document.querySelectorAll('select')) add(s, s, 'select');
  for (const c of document.querySelectorAll('input[type=checkbox], input[type=radio]')) {
    // Any of its labels toggles it, so the largest shown label is its tap target; with none, the control itself.
    const labels = [...(c.labels || [])].filter(l => shown(l));
    labels.sort((a, b) => { const ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect(); return Math.min(rb.width, rb.height) - Math.min(ra.width, ra.height); });
    if (labels.length) add(labels[0], c, c.type + '-label'); else add(c, c, c.type);
  }
  const boxes = [];
  for (const el of document.body.querySelectorAll('*')) {
    const r = el.getBoundingClientRect();
    boxes.push([Math.round(r.left), Math.round(r.top + window.scrollY), Math.round(r.width), Math.round(r.height)].join(','));
  }
  return { title: document.title, boxes, popup: !!document.querySelector('.rz-popup'), scrollWidth: document.documentElement.scrollWidth, scrollHeight: document.documentElement.scrollHeight, items };
};
// Measures a page once it has settled: layout forced, fonts loaded, and two measures 150 ms apart the same (at most 3 s).
// The waits run here, not in the page, which may be a sandbox with scripts off.
const settled = async (page) => {
  let last = null;
  for (let t = 0; t < 20; t++) {
    await page.evaluate(() => { void document.body.offsetHeight; return document.fonts.ready.then(() => true); });
    const m = await page.evaluate(MEASURE);
    const sig = m.boxes.join(';') + '|' + m.scrollHeight;
    if (last && last.sig === sig) return m;
    last = { sig, m };
    await new Promise(r => setTimeout(r, 150));
  }
  return last.m;
};
(async () => {
  let browser = await chromium.launch();
  const results = [];
  const phone = WIDTH <= 600;
  // A session for one role: a context, signed in, its crawl header set. Opened again (and the browser relaunched if it
  // died) when a page, its renderer or the browser crashes, so one crash costs one page, not the crawl.
  const open = async (role) => {
    if (!browser.isConnected()) browser = await chromium.launch();
    const context = await browser.newContext({ viewport: { width: WIDTH, height: HEIGHT }, deviceScaleFactor: 1, isMobile: phone, hasTouch: phone });
    const page = await context.newPage();
    page.on('dialog', d => d.dismiss().catch(() => {}));
    if (oldCss) await context.route(/resurrection\.css\?.*rz-old=1/, r => r.fulfill({ status: 200, contentType: 'text/css', body: oldCss }));
    if (role.login) {
      await page.goto(base + 'home.php');
      if (!(await page.$('input[name=name]'))) {
        process.stderr.write(`no sign-in form for ${role.name}: ${page.url()} ${(await page.content()).replace(/\s+/g, ' ').slice(0, 1500)}\n`);
      }
      await page.fill('input[name=name]', role.login[0]);
      await page.fill('input[name=password]', role.login[1]);
      await Promise.all([page.waitForNavigation(), page.click('form[action*="login.php"] [type=submit], form[action*="login.php"] button')]);
    }
    if (role.id) await context.setExtraHTTPHeaders({ 'X-Crawl-Acct': String(role.id) });
    return { context, page };
  };
  const reopen = async (role, s, always = false) => {
    if (!always && !s.page.isClosed() && browser.isConnected()) {
      try { await s.page.evaluate(() => 1); return s; } catch (e) { /* the page is gone: open a new session */ }
    }
    await s.context.close().catch(() => {});
    process.stderr.write(`${role.name}: session reopened\n`);
    return open(role);
  };
  for (const role of roles.filter(r => !process.env.TAP_ROLES || process.env.TAP_ROLES.split(',').includes(r.name))) {
    let s = await open(role);
    const seen = new Set(); const queue = [...role.starts]; let count = 0;
    while (queue.length && count < 800) {
      const path = queue.shift();
      const key = pageKey(path);
      if (seen.has(key)) continue; seen.add(key); count++;
      const page = s.page;
      let status = 0;
      try { const r = await page.goto(base + path, { waitUntil: 'load', timeout: 20000 }); status = r ? r.status() : 0; }
      catch (e) {
        results.push({ role: role.name, path, error: String(e).slice(0, 200) });
        s = await reopen(role, s);
        continue;
      }
      let links, slow, stuck;
      // A page that takes over 30 s is named on stderr; one that takes over 120 s is recorded as an error and its session
      // reopened, so a page that never settles cannot stop the crawl.
      slow = setTimeout(() => process.stderr.write(`${role.name}: slow page ${path}\n`), 30000);
      try {
        const job = measurePage(); job.catch(() => {});   // a page given up on may still fail later: that is not a crash
        const got = await Promise.race([job, new Promise((_, no) => { stuck = setTimeout(() => no(new Error('measure timed out after 120 s')), 120000); })]);
        links = got.links; results.push(got.result);
        if (count % 50 === 0) process.stderr.write(`${role.name}: ${count} pages\n`);
      } catch (e) {
        results.push({ role: role.name, path, error: String(e).slice(0, 200) });
        s = await reopen(role, s, true);
        continue;
      } finally { clearTimeout(slow); clearTimeout(stuck); }
      async function measurePage() {
        const m = await settled(page);
        let cssDiff;
        if (oldCss) {
          // The swap is driven from here, not by the page's own callbacks: a page shown in a sandbox with scripts off (the
          // user editor's last page hit) runs no timer or load handler, though it still lays out and can be measured.
          const added = await page.evaluate(() => {
            const link = [...document.querySelectorAll('link[rel=stylesheet]')].find(l => /resurrection\.css/.test(l.href));
            if (!link) return false;
            const old = link.cloneNode(); old.href = link.href + (link.href.includes('?') ? '&' : '?') + 'rz-old=1'; old.dataset.rzOld = '1';
            link.dataset.rzNew = '1'; link.after(old);
            return true;
          });
          let swapped = false;
          for (let t = 0; added && t < 80 && !swapped; t++) {
            swapped = await page.evaluate(() => { const o = document.querySelector('link[data-rz-old]'); try { return !!(o && o.sheet && o.sheet.cssRules.length); } catch (e) { return !!(o && o.sheet); } });
            if (!swapped) await new Promise(r => setTimeout(r, 100));
          }
          if (swapped) await page.evaluate(() => document.querySelector('link[data-rz-new]').remove());
          const o = await settled(page);
          const key = (i) => [i.kind, i.sel, i.text, i.x, i.y, i.w, i.h].join('|');
          const now = new Set(m.items.map(key));
          cssDiff = { swapped, boxes: m.boxes.length === o.boxes.length ? m.boxes.filter((b, k) => b !== o.boxes[k]).length : -1,
            height: [o.scrollHeight, m.scrollHeight], targets: o.items.filter(i => !now.has(key(i))).map(i => ({ ...i })) };
        }
        delete m.boxes;
        const found = await page.$$eval('a[href]', as => as.map(a => a.getAttribute('href')));
        return { links: found, result: { role: role.name, path, final: page.url().replace(base, ''), status, ...m, ...(cssDiff ? { cssDiff } : {}) } };
      }
      // (A page that closes itself, navigates or crashes while it is measured is recorded above, and the crawl goes on.)
      for (let href of links) {
        if (!href || /^(https?:|mailto:|javascript:|#)/i.test(href)) continue;
        href = href.replace(/&amp;/g, '&').replace(/#.*$/, '');
        if (!/^[a-z0-9_]+\.php/i.test(href) || SKIP.test(href)) continue;
        if (!seen.has(pageKey(href))) queue.push(href);
      }
    }
    await s.context.close().catch(() => {});
    process.stderr.write(`${role.name}: ${count} pages\n`);
  }
  await browser.close();
  fs.writeFileSync(out, JSON.stringify(results));
  const small = results.flatMap(r => (r.items || []).filter(i => i.w < 44 || i.h < 44).map(i => ({ role: r.role, path: r.path, ...i })));
  console.log(`pages ${results.length}, targets ${results.reduce((n, r) => n + (r.items || []).length, 0)}, under 44: ${small.length}`);
})();
