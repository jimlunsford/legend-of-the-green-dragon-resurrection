<?php
declare(strict_types=1);

namespace Resurrection\Ui;
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

/**
 * The safe frame (Phase 5 close-out, docs/PHASE5-CLOSEOUT-20261105.md): a built-in template that prepare_template()
 * uses when templates/resurrection.htm is missing, unreadable, or fails its checks (the required tags, {copyright},
 * and the Resurrection attribution line in the footer and the popup footer). 1.1.2 fell back to another skin; with one
 * template left there is none, so the frame lives here, in code, where editing the template file cannot remove it.
 *
 * It has every part the code fills, with the same keys, so no page prints a missing-part warning; its four frame parts
 * are the template's, less the phone's pieces (the compact header, the tab bar and the More section, which only the
 * stylesheet keeps hidden on a desktop). It links the stylesheet; when that file is missing the page is plain markup
 * that still reads, the credit line, the license block and the attribution line included, and its own small
 * stylesheet, assets/ui/safeframe.css, linked before the stylesheet so the stylesheet wins, keeps the art from growing
 * past the page. (Until Phase 6 task 3 that rule was a <style> element here, which the Content Security Policy refuses.)
 */
final class SafeFrame
{
    /** The Resurrection attribution line, both links exact. footer and popupfoot must each carry it. */
    public const string ATTRIBUTION = 'A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33 LLC</a> Project by <a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a>';

    private const string TEMPLATE = <<<'HTML'
<!--!header--><!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
<link rel="stylesheet" href="assets/ui/safeframe.css">
<link rel="stylesheet" href="assets/ui/resurrection.css">
{headscript}{script}
</head>
<body class="rz-page rz-safe-frame">
<a class="rz-skip" href="#rz-content">Skip to content</a>
<header class="rz-header">
 <div class="rz-header-inner">
  <div class="rz-wordmark">
   <span class="rz-wordmark-title">Legend of the Green Dragon</span>
   <span class="rz-wordmark-sub">Resurrection</span>
  </div>
  <div class="rz-header-portrait">{portrait}</div>
  <div class="rz-header-actions">{mail}{motd}{petition}</div>
  <div class="rz-header-staff">{petitiondisplay}</div>
  <div class="rz-header-ad">{headerad}</div>
 </div>
</header>
<div class="rz-frame">
 <div class="rz-columns">
  {navstart}{navad}{nav}{navend}
  <main class="rz-main" id="rz-content">
   <h1 class="rz-title">{title}</h1>
   <div class="rz-card rz-content">{scene}{verticalad}{bodyad}
<!--!footer-->
   </div>
  </main>
  <aside class="rz-character" id="rz-character" aria-label="Character">
   <div class="rz-character-inner">{stats}</div>
  </aside>
 </div>
</div>
<footer class="rz-footer">
 <div class="rz-footer-inner">
  <div class="rz-footer-credits">{copyright}{credits}{source}</div>
  <div class="rz-footer-meta">{version}{paypal}<br><span class="rz-newday">{newday}</span><br><span class="rz-pagegen">{pagegen}</span></div>
  <p class="rz-attribution">A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33 LLC</a> Project by <a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a></p>
 </div>
</footer>
</body>
</html>
<!--!popuphead--><!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
<link rel="stylesheet" href="assets/ui/safeframe.css">
<link rel="stylesheet" href="assets/ui/resurrection.css">
{headscript}{script}
</head>
<body class="rz-page rz-popup rz-safe-frame">
<header class="rz-header">
 <div class="rz-header-inner">
  <div class="rz-wordmark">
   <span class="rz-wordmark-title">Legend of the Green Dragon</span>
   <span class="rz-wordmark-sub">Resurrection</span>
  </div>
 </div>
</header>
<div class="rz-frame">
 <main class="rz-main" id="rz-content">
  <h1 class="rz-title">{title}</h1>
  <div class="rz-card rz-content">
<!--!popupfoot-->
  </div>
 </main>
</div>
<footer class="rz-footer">
 <div class="rz-footer-inner">
  <div class="rz-footer-credits">{copyright}</div>
  <p class="rz-attribution">A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33 LLC</a> Project by <a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a></p>
 </div>
</footer>
</body>
</html>
<!--!statstart--><div class="rz-stats"><section class="rz-stats-lead">
<!--!stathead--></section><section class="rz-card rz-statsection"><h2 class="rz-stathead">{title}</h2>
<!--!statrow--><div class="rz-statrow"><span class="rz-statlabel">{title}</span><span class="rz-statvalue">{value}</span></div>
<!--!statbuff--></section><section class="rz-card rz-statsection"><h2 class="rz-stathead">{title}</h2><div class="rz-statbuff">{value}</div>
<!--!statend--></section></div>
<!--!statheader--></section><section class="rz-card rz-statcard"><div class="rz-statid">{portrait}<div class="rz-statid-text"><h2 class="rz-statname">{name}</h2><p class="rz-statsub">{subtitle}</p></div></div>
<!--!statportrait--><img class="rz-statportrait" src="{src}" alt="{alt}" width="{width}" height="{height}">
<!--!statbar--><div class="rz-statbar rz-statbar-{kind}"><div class="rz-statbar-text"><span class="rz-statbar-label">{icon}{label}</span><span class="rz-statbar-value">{current} / {max}{note}</span></div><div class="rz-statbar-track" aria-hidden="true"><div class="rz-statbar-fill rz-fill-{percent}"></div></div></div>
<!--!statbarcap--><div class="rz-statbar rz-statbar-{kind}"><div class="rz-statbar-text"><span class="rz-statbar-label">{icon}{label}</span><span class="rz-statbar-value">{current}{note}</span></div><div class="rz-statbar-track" aria-hidden="true"><div class="rz-statbar-fill rz-fill-{percent}"></div></div><div class="rz-statbar-caption">{caption}</div></div>
<!--!stattiles--><div class="rz-stattiles">{tiles}</div>
<!--!stattile--><div class="rz-stattile"><span class="rz-stattile-label">{icon}{label}</span><span class="rz-stattile-value">{value}{note}</span></div>
<!--!equiprows--><div class="rz-equiprows">{rows}</div>
<!--!equiprow--><div class="rz-equiprow"><span class="rz-equiprow-label">{icon}{label}</span><span class="rz-equiprow-value">{value}</span></div>
<!--!labelicon--><img class="rz-labelicon" src="{src}" alt="" aria-hidden="true" width="{width}" height="{height}">
<!--!navhead--><h2 class="rz-navhead">{title}</h2>
<!--!navhelp--><div class="rz-navhelp">{text}</div>
<!--!navitem--><a href="{link}" {accesskey} class="rz-navitem nav" {popup}><span class="rz-key" aria-hidden="true">{key}</span><span class="rz-navlabel">{text}</span></a>
<!--!navstart--><nav class="rz-nav" id="rz-nav" aria-label="Navigation"><div class="rz-card rz-nav-panel">
<!--!navend--></div></nav>
<!--!petitioncount--><div class="rz-petitioncount">{petitioncount}</div>
<!--!scene--><div class="rz-scene"><img class="rz-scene-img" src="{src}" alt="{alt}" width="{width}" height="{height}"></div>
<!--!portrait--><div class="rz-card rz-portrait"><img class="rz-portrait-img" src="{src}" alt="{alt}" width="{width}" height="{height}"><div class="rz-portrait-text"><span class="rz-portrait-name">{name}</span><span class="rz-portrait-race">{race}</span></div></div>
<!--!adwrapper--><div class="rz-card rz-adwrapper">{content}</div>
<!--!login--><div class="rz-login">
 <div class="rz-field"><label for="name">{username}</label><input name="name" id="name" accesskey="u" autocomplete="username"></div>
 <div class="rz-field"><label for="password">{password}</label><input name="password" id="password" accesskey="p" type="password" autocomplete="current-password"></div>
 <input type="submit" value="{button}" class="button rz-button-primary">
</div>
<!--!loginfull--><div class="rz-loginfull">Server Full!</div>
<!--!phonehead--><div class="rz-phonehead"><div class="rz-phonehead-top">{portrait}<div class="rz-phonehead-id"><p class="rz-phonehead-name">{name}</p><p class="rz-phonehead-sub">{subtitle}</p></div><div class="rz-phonehead-mail">{mail}</div></div><div class="rz-phonehead-bars">{bars}</div></div>
<!--!phoneportrait--><img class="rz-phonehead-img" src="{src}" alt="{alt}" width="{width}" height="{height}">
<!--!phonebar--><div class="rz-phonebar rz-phonebar-{kind}"><div class="rz-phonebar-text"><span class="rz-phonebar-label">{icon}{label}</span><span class="rz-phonebar-value">{value}</span></div><div class="rz-phonebar-track" aria-hidden="true"><div class="rz-phonebar-fill rz-fill-{percent}"></div></div></div>
<!--!tabbar--><nav class="rz-tabbar" aria-label="Sections">{tabs}</nav>
<!--!tab--><a class="rz-tab" href="#{target}">{label}</a>
<!--!battle--><div class="rz-battle">{screen}</div>
<!--!fighters--><div class="rz-fighters">{cards}</div>
<!--!fighter--><section class="rz-fighter rz-fighter-{kind}{state}"><div class="rz-fighter-id">{portrait}<div class="rz-fighter-text"><h2 class="rz-fighter-name">{name}</h2><p class="rz-fighter-sub">{sub}</p></div></div>{hp}</section>
<!--!fighterportrait--><img class="rz-fighter-portrait" src="{src}" alt="{alt}" width="{width}" height="{height}">
<!--!fighterbar--><div class="rz-fighterbar"><div class="rz-fighterbar-text"><span class="rz-fighterbar-label">{label}</span><span class="rz-fighterbar-value">{value}</span></div><div class="rz-fighterbar-track" aria-hidden="true"><div class="rz-fighterbar-fill rz-fill-{percent}"></div></div></div>
<!--!fighterhp--><div class="rz-fighterbar rz-fighterbar-nobar"><div class="rz-fighterbar-text"><span class="rz-fighterbar-label">{label}</span><span class="rz-fighterbar-value">{value}</span></div></div>
<!--!battleactions--><div class="rz-battle-actions">{main}{groups}</div>
<!--!battlemain--><div class="rz-battle-buttons rz-battle-main">{buttons}</div>
<!--!battlegroup--><section class="rz-battle-group" aria-labelledby="rz-battle-{id}"><h3 class="rz-battle-grouphead" id="rz-battle-{id}">{title}</h3>{note}<div class="rz-battle-buttons">{buttons}</div></section>
<!--!battlenote--><p class="rz-battle-note">{text}</p>
<!--!battlelog--><section class="rz-battle-log" aria-label="{label}">{intro}{rounds}</section>
<!--!battleintro--><div class="rz-battle-intro">{lines}</div>
<!--!battlerounds--><ol class="rz-battle-rounds">{rounds}</ol>
<!--!battleround--><li class="rz-battle-round">{lines}</li>
<!--!battleline--><div class="rz-battle-line">{text}</div>
<!--!battleresult--><section class="rz-battle-result rz-battle-result-{kind}" aria-label="{title}"><h3 class="rz-battle-resulthead">{title}</h3>{lines}</section>
<!--!battleafter--><div class="rz-battle-after">{lines}</div>

HTML;

    /**
     * The frame's parts, split as loadtemplate() splits a template file.
     *
     * @return array<string, string>
     */
    public static function parts(): array
    {
        return self::split(self::TEMPLATE);
    }

    /**
     * A template's text split into its parts at each "<!--!name-->" marker, as loadtemplate() does.
     *
     * @return array<string, string>
     */
    public static function split(string $text): array
    {
        $template = [];
        foreach (explode('<!--!', $text) as $piece) {
            $end = strpos($piece, '-->');
            if ($end !== false && $end > 0) { $template[substr($piece, 0, $end)] = substr($piece, $end + 3); }
        }
        return $template;
    }

    /**
     * Whether a template's footer and popup footer each carry the attribution line with both exact links.
     *
     * @param array<array-key, mixed> $template
     */
    public static function hasAttribution(array $template): bool
    {
        foreach (['footer', 'popupfoot'] as $part) {
            if (!is_string($template[$part] ?? null) || !str_contains($template[$part], self::ATTRIBUTION)) { return false; }
        }
        return true;
    }
}
