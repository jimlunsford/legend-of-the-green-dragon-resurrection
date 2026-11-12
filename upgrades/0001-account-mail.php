<?php
// Account mail (Phase 6 task 13a, S11; docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md, section 7). Three
// changes, each checked before it is made, so a run stopped after any of them finishes on the next run and changes
// nothing twice. Its definitions are written out here, never read from shared code. No rows are changed.
return [
    'description' => 'Add account mail: the account_tokens and account_mail_log tables and the accounts.emailverified column',
    'apply' => static function (): void {
        $table = static fn (string $name): bool => db_query('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', true, [db_prefix($name)]) !== [];
        $tail = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        if (!$table('account_tokens')) {
            db_query('CREATE TABLE ' . db_identifier(db_prefix('account_tokens')) . " (\n"
                . "`tokenid` int(11) unsigned NOT NULL auto_increment,\n"
                . "`acctid` int(11) unsigned NOT NULL DEFAULT '0',\n"
                . "`purpose` varchar(16) NOT NULL DEFAULT '',\n"
                . "`tokenhash` char(64) NOT NULL DEFAULT '',\n"
                . "`address` varchar(128) NOT NULL DEFAULT '',\n"
                . "`authversion` int unsigned NOT NULL DEFAULT '0',\n"
                . "`created` datetime NOT NULL,\n"
                . "`expires` datetime NOT NULL,\n"
                . "`opened` tinyint(4) unsigned NOT NULL DEFAULT '0',\n"
                . "`used` datetime,\n"
                . "PRIMARY KEY (`tokenid`),\n"
                . "UNIQUE KEY `tokenhash` (`tokenhash`),\n"
                . "KEY `account` (`acctid`,`purpose`),\n"
                . "KEY `expires` (`expires`)" . $tail);
        }
        if (!$table('account_mail_log')) {
            db_query('CREATE TABLE ' . db_identifier(db_prefix('account_mail_log')) . " (\n"
                . "`eventid` int(11) unsigned NOT NULL auto_increment,\n"
                . "`date` datetime NOT NULL,\n"
                . "`purpose` varchar(16) NOT NULL DEFAULT '',\n"
                . "`acctid` int(11) unsigned NOT NULL DEFAULT '0',\n"
                . "`addresskey` char(64) NOT NULL DEFAULT '',\n"
                . "`requesterkey` varchar(40) NOT NULL DEFAULT '',\n"
                . "`result` varchar(16) NOT NULL DEFAULT '',\n"
                . "PRIMARY KEY (`eventid`),\n"
                . "KEY `date` (`date`),\n"
                . "KEY `account` (`purpose`,`acctid`,`date`),\n"
                . "KEY `address` (`addresskey`,`date`),\n"
                . "KEY `requester` (`requesterkey`,`date`)" . $tail);
        }
        $columns = array_column(db_query('SHOW COLUMNS FROM ' . db_identifier(db_prefix('accounts'))), 'Field');
        if (!in_array('emailverified', $columns, true)) {
            db_query('ALTER TABLE ' . db_identifier(db_prefix('accounts')) . " ADD COLUMN `emailverified` varchar(128) NOT NULL DEFAULT ''");
        }
    },
];
