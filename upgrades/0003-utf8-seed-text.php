<?php
// Phase 6 task 20 (R9): three seed values that 1.1.2's source already carried broken. Its Latin-1 letters had been turned
// into U+FFFD, the Unicode replacement character, before the historical tag was cut (historical-source-1.1.2 holds the
// same bytes), so every rc.1 database shows master 5 as "Un?lith", the languages list as "Fran?ais" and "Espa?ol", and
// the swear filter's list with "kyrp?*". The fresh install now writes the words 1.1.2 meant: "Unélith", "Français",
// "Español" and "kyrpä*". This step changes each row only where it still holds exactly the broken value an rc.1
// install wrote, compared byte for byte; a value an administrator has edited is left alone, and a rerun changes
// nothing. Its values are written out here, never read from shared code. No other row is changed.
return [
    'description' => 'Write the UTF-8 text 1.1.2 meant into three seed values: master 5 (Unélith), serverlanguages (Français, Español) and the nasty word list (kyrpä*)',
    'apply' => static function (): void {
        $fixes = [
            ['UPDATE ' . db_identifier(db_prefix('masters')) . ' SET creaturename=? WHERE creatureid=5 AND CAST(creaturename AS BINARY)=CAST(? AS BINARY)',
                'Unélith', 'Un' . "\u{FFFD}" . 'lith'],
            ['UPDATE ' . db_identifier(db_prefix('settings')) . " SET value=? WHERE setting='serverlanguages' AND CAST(value AS BINARY)=CAST(? AS BINARY)",
                'en,English,fr,Français,dk,Danish,de,Deutsch,es,Español,it,Italian',
                'en,English,fr,Fran' . "\u{FFFD}" . 'ais,dk,Danish,de,Deutsch,es,Espa' . "\u{FFFD}" . 'ol,it,Italian'],
        ];
        $broken = '*damn* *dyke *fuck* *nigger* *phuck* *shit* amcik andskota arschloch arse* ass asshole atouche ayir '
            . 'bastard bitch* boiolas bollock* buceta butt* butt-pirate cabron cawk cazzo chink chraa chuj cipa '
            . 'clit cock* cum cunt* dago daygo dego dick* dike dildo dirsa dupa dziwka ejaculate ejackulate ekrem* '
            . 'ekto enculer faen fag* fanculo fanny fatass fcuk feces feg felcher ficken fitta fitte flikker '
            . 'foreskin phuck fuk* fut futkretzn fuxor gay gook guiena hell helvete hoer* honkey honky hor hore '
            . 'huevon hui injun jism jizz kanker* kawk kike klootzak knulle kraut kuk kuksuger kurac kurwa kusi* '
            . 'kyrp' . "\u{FFFD}" . '* leitch lesbian lesbo mamhoon masturbat* merd merde mibun monkleigh mouliewop muie mulkku '
            . 'muschi nazis nepesaurio nigga* nigger nutsack orospu paska* pendejo penis perse phuck picka pierdol* '
            . 'pillu* pimmel pimpis piss* pizda poontsee poop porn preteen preud prick pron pula pule pusse pussy '
            . 'puta puto qahbeh queef* queer* qweef rautenberg schaffer scheiss* scheisse schlampe schmuck screw '
            . 'scrotum sharmuta sharmute shemale shipal shiz skribz skurwysyn slut smut sphencter shpincter spic '
            . 'spierdalaj splooge suka teets teez testicle tits titties titty twat twaty vittu votze wank* wetback* '
            . 'whoar whore wichser woose wop yed zabourah';
        $fixes[] = ['UPDATE ' . db_identifier(db_prefix('nastywords')) . " SET words=? WHERE type='nasty' AND CAST(words AS BINARY)=CAST(? AS BINARY)",
            str_replace('kyrp' . "\u{FFFD}" . '*', 'kyrpä*', $broken), $broken];
        foreach ($fixes as [$sql, $fixed, $was]) {
            if (!str_contains($was, "\u{FFFD}") || str_contains($fixed, "\u{FFFD}")) throw new \LogicException('Seed text step: bad literal.');
            db_query($sql, true, [$fixed, $was]);
        }
    },
];
