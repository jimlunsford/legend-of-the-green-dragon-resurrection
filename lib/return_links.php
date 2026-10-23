<?php
/** Return links taken from a request: only known on-site pages, never a value the request controls. */

/**
 * bio.php's return link. 1.1.2 cut `ret` after its last "/" and offered whatever was left as an allowed navigation, so a
 * crafted value (or a page with a crafted query, such as an always-reachable MOTD or mail page) could make any page,
 * op or a javascript: URL the next allowed move. Only the file name of a shipped page that links here with a return
 * is accepted; its query is kept only for the user editor's and the petition viewer's record pages. Anything else
 * returns to the warrior list, as no `ret` always did.
 */
function resurrection_bio_return(mixed $ret): string {
	$pages = ['village.php','inn.php','gardens.php','rock.php','shades.php','gypsy.php','lodge.php','superuser.php','moderate.php',
		'motd.php','viewpetition.php','clan.php','prefs.php','user.php','pvp.php','list.php'];
	$queries = ['user.php'=>'/\Aop=edit&userid=[0-9]{1,10}\z/', 'viewpetition.php'=>'/\Aop=view&id=[0-9]{1,10}\z/'];
	if (!is_string($ret) || $ret === "") return "list.php";
	require_once __DIR__ . "/sanitize.php";
	$ret = cmd_sanitize($ret);
	$parts = explode("?", $ret, 2);
	if (!preg_match('#\A(?:(?:/[A-Za-z0-9_.-]+)*/)?([a-z_]+\.php)\z#', $parts[0], $match) || !in_array($match[1], $pages, true)) return "list.php";
	$page = $match[1];
	if (isset($parts[1], $queries[$page]) && preg_match($queries[$page], $parts[1])) return $page . "?" . $parts[1];
	return $page;
}

/**
 * The page the news delete returns to. 1.1.2 cut the request's `return` after its last "/" and redirected there; now
 * only the news page itself, with its integer day offset and page (and the nav link's comment counter, dropped), is
 * accepted. Anything else returns to news.php.
 */
function resurrection_news_return(mixed $return): string {
    if (!is_string($return) || !preg_match('#\A(?:(?:/[A-Za-z0-9_.-]+)*/)?news\.php(?:\?([A-Za-z0-9=&-]*))?\z#', $return, $match)) return 'news.php';
    $query = [];
    foreach (explode('&', $match[1] ?? '') as $part) {
        // The comment counter a nav link adds (c=N-HHMMSS) is dropped, as cmd_sanitize() drops it.
        if ($part === '' || preg_match('/\Ac=[0-9]+-[0-9]+\z/', $part)) continue;
        if (!preg_match('/\A(offset|page)=(-?[0-9]{1,9})\z/', $part, $pair)) return 'news.php';
        $query[$pair[1]] = (int)$pair[2];
    }
    return $query === [] ? 'news.php' : 'news.php?' . http_build_query($query);
}
