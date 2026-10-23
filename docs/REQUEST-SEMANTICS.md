# Raw HTTP request contract

## Audited previous behavior

At e4c9421e8d37c63c451f380d470321e1015e2350, common.php loaded the database wrapper, which loaded lib/errorhandling.php. That file called the removed get_magic_quotes_gpc API, recursively applied addslashes to GET, POST, SESSION, COOKIE and the old HTTP_* arrays, and tried to turn magic_quotes_gpc on. It also excluded notices and deprecations from error reporting. Bootstrap stopped at the removed API on supported PHP.

lib/http.php returned those globally escaped values, with HTTP_GET_VARS/HTTP_POST_VARS fallbacks. postparse interpolated scalar values assuming they were already escaped and independently escaped serialized arrays. Login, account creation, bans, reference tracking, commentary, configuration and user editing constructed SQL from those values. Many output/form paths called stripslashes to undo the transport convention. lib/php_generic_environment.php invoked the generic register_global bridge; lib/register_global.php exported arbitrary array keys as globals.

lib/safeescape.php preserves pre-existing backslashes while adding quote escapes. Its remaining callers are in system mail and clan membership. lib/stripslashes_deep.php still has petition callers. Those destination-specific legacy callers are outside the installation/authentication boundary and are not certified by this migration. The generic register_global helper is retained as historical support code but has no active callers.

## Replacement

Incoming GET, POST and cookies remain raw application data. There is no magic-quotes call, configuration toggle or fake function. No global SQL or HTML escaping is performed. Error reporting includes warnings, notices and deprecations. Diagnostics never serialize a request or emit argument-bearing backtraces.

Resurrection\Http\Input provides string, integer, boolean and allow-list reads with explicit defaults. Scalars reject arrays; integer syntax and range are checked instead of casting expressions. Missing/null handling is explicit. Legacy httpget/httppost keep their false-on-missing convention but no longer consult removed HTTP_* globals. The remaining server-global compatibility bridge exports only a fixed list of server metadata; request keys never become application globals.

SQL values use db_query's bound-parameter argument. Table/column identifiers use db_identifier or explicit allow-lists. The old postparse fragment API escapes values only at its SQL destination with the connection's quoting rules, validates identifiers and is not a request normalization layer. It remains a migration aid; new code should bind values instead.

The phase migrates installer/account/authentication/ban checks, reference tracking, account persistence, commentary moderation and posting, core configuration updates, administrative account updates and searches. Module dispatch validates module names and ignores request force flags. Explicit authorized lifecycle operations own module changes; ordinary runtime injection cannot install or update code.

Passwords are read as raw strings, including quotes and backslashes, and verified with password_verify. Submitted stored hashes are rejected. Commentary stores raw text; its established sanitization and HTML rendering are applied for display. Custom talk lines no longer add SQL-era quote escapes. Forms emit CSRF tokens and escape attribute values at the output boundary.

## Evidence and limits

InputTest covers missing/default/null/scalar/array/type/range/allow-list behavior and literal quote/backslash/HTML payload preservation. Password and CSRF tests cover malformed credentials, replay and session-bound tokens. The real fresh-install tests preserve raw Unicode, quotes and backslashes in database values. HTTP smoke tests submit a raw password containing an apostrophe and backslash, then authenticate it, inspect fixed diagnostic records and follow the historical navigation flow.

This is not a claim that every historical route is now SQL-safe. Remaining petition, mail, clan, editor and gameplay routes still contain string-built SQL or stripslashes assumptions. They require route-specific migration and output review before public hosting. C09 remains a scoped compatibility effort, with untested gameplay routes recorded separately in the checkpoint.
