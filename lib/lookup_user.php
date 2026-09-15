<?php
/** User-editor search: all search text is raw and bound, ordering is allow-listed. */
function lookup_user($query=false, $order=false, $fields=false, $where=false){
    $columns = ['acctid','login','name','level','laston','loggedin','gentimecount','gentime','lastip','uniqueid','emailaddress'];
    if ($fields !== false || $where !== false) { throw new InvalidArgumentException('Custom search SQL is not supported.'); }
    $query = $query === false ? '' : $query;
    if (!is_string($query) || strlen($query) > 255) { throw new InvalidArgumentException('Invalid account search.'); }
    $order = $order === false || $order === '' ? 'acctid' : $order;
    if (!is_string($order) || !preg_match('/\A([a-z]+)(?: (ASC|DESC))?\z/i', $order, $match) || !in_array($match[1], $columns, true)) {
        throw new InvalidArgumentException('Invalid account ordering.');
    }
    $sql = 'SELECT ' . implode(',', array_map('db_identifier', $columns)) . ' FROM ' . db_prefix('accounts');
    $sort = ' ORDER BY ' . db_identifier($match[1]) . ' ' . ($match[2] ?? 'ASC');
    $id = ctype_digit($query) ? (int)$query : 0;
    $predicate = ' WHERE login LIKE ? OR name LIKE ? OR acctid=? OR emailaddress LIKE ? OR lastip LIKE ? OR uniqueid LIKE ?';
    $rows = db_query($sql . $predicate . $sort . ' LIMIT 2', true, [$query,$query,$id,$query,$query,$query]);
    if (db_num_rows($rows) !== 1) {
        $pattern = '%' . $query . '%';
        $name = '%' . implode('%', str_split($query)) . '%';
        $rows = db_query($sql . $predicate . $sort . ' LIMIT 101', true, [$pattern,$name,$id,$pattern,$pattern,$pattern]);
    }
    $error = db_num_rows($rows) === 0 ? '`$No results found`0' : (db_num_rows($rows) > 100 ? '`$Too many results found, narrow your search please.`0' : '');
    return [$rows, $error];
}
