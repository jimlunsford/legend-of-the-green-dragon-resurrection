<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/** Existing core declarations shared by rendering and mutation validation. */
function resurrection_core_layout(): array {
 global $session;
    $days=filter_var(getsetting('daysperday',4),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>6]]);
    if ($days===false) throw new DomainException('Invalid game-day configuration.');
    $enum='range,0,'.(int)(86400/$days).',300';
	$setup = array(
		"Game Setup,title",
		"loginbanner"=>"Login Banner (under login prompt: 255 chars)",
		"maxonline"=>"Max # of players online (0 for unlimited), int",
		"allowcreation"=>"Allow creation of new characters,bool",
		"gameadminemail"=>"Admin Email",
		// 1.1.2 listed the server URL under LoGDnet Setup, which is removed with LoGDnet. Mail and links still use it.
		"serverurl"=>"Server URL",
		"emailpetitions"=>"Should submitted petitions be emailed to Admin Email address?,bool",
		"Enter languages here like this: `i(shortname 2 chars) comma (readable name of the language)`i and continue as long as you wish,note",
		"serverlanguages"=>"Languages available on this server",
		"defaultlanguage"=>"Default Language,enum,".getsetting("serverlanguages","en,English,fr,Français,dk,Danish,de,Deutsch,es,Español,it,Italian"),
		"edittitles"=>"Should DK titles be editable in user editor,bool",
		"motditems"=>"How many items should be shown on the motdlist,int",

		"Main Page Display,title",
		"homecurtime"=>"Should the current realm time be shown?,bool",
		"homenewdaytime"=>"Should the time till newday be shown?,bool",
		"homenewestplayer"=>"Should the newest player be shown?,bool",
		"impressum"=>"Tell the world something about the person running this server. (e.g. name and address),textarea",

		"Source Code,title",
		// The label is the setting's description in the settings editor. It must not contain a comma.
		"sourceurl"=>"Public source code URL for the View PHP Source link. Point this at your own complete modified source if you change the game; LICENSE.txt has the terms for a changed game's source. Only http and https addresses are accepted.|https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection",

		// Phase 6 task 3 (S1): the Content Security Policy (src/Http/SecurityHeaders.php). Notes and choices hold no comma.
		"Security Headers,title",
		"Every page sends a Content Security Policy that lets the browser run only the game's own script and style files. Every page the game ships passes it. Choose Report only or Off on purpose: only for a third-party module that still prints inline script or inline style (its pages break under Enforce). Report only sends the policy as Content-Security-Policy-Report-Only: the browser blocks nothing and reports what it would block in its console. Off sends no policy. The frame and content-type protection and the Referrer-Policy are sent whatever this says.,note",
		"csp_mode"=>"Content Security Policy,enum,enforce,Enforce (the default),report-only,Report only,off,Off",

		// Phase 6 task 18 (F4): sign-in throttling (lib/signin_throttle.php); bounded (lib/settings.php). No comma in a note.
		"Sign-in Limits,title",
		"signin_fail_pair"=>"Failed sign-ins allowed for one name from one address in 15 minutes,int,".implode(',', resurrection_bounded_settings()['signin_fail_pair']),
		"signin_fail_address"=>"Failed sign-ins allowed from one address in 15 minutes,int,".implode(',', resurrection_bounded_settings()['signin_fail_address']),
		"signin_fail_account"=>"Failed sign-ins allowed for one name from all addresses in one hour,int,".implode(',', resurrection_bounded_settings()['signin_fail_account']),

		"Beta Setup,title",
		// Phase 6 task 18 (N11): kept, because code reads them (common.php, village.php, moderate.php, lib/commentary.php),
		// but nothing they gate is in the game, and each label says so (Game Settings shows no notes). 1.1.2's labels
		// were "Enable beta features for all players?" and "Enable beta features per player?".
		"beta"=>"Enable beta features for all players? (Does nothing in this game: its beta page pavilion.php is not part of it.),bool",
		"betaperplayer"=>"Enable beta features per player? (Does nothing in this game: its beta page pavilion.php is not part of it.),bool",

		"Account Creation,title",
		"defaultsuperuser"=>
			"Flags automatically granted to new players,bitfield," .
			($session['user']['superuser'] | SU_ANYONE_CAN_SET)." ,".
			SU_INFINITE_DAYS.",Infinite Days,".
			SU_DEVELOPER.",Developer Super Powers (special inc list; god mode; auto defeat master; etc),".
			SU_DEBUG_OUTPUT. ",Debug Output",
		"newplayerstartgold"=>"Amount of gold to start a new character with,int",
		"maxrestartgold"=>"Maximum amount of gold a player will get after a dragonkill,int",
		"maxrestartgems"=>"Maximum number of gems a player will get after a dragonkill,int",
		"requireemail"=>"Require users to enter their email address,bool",
		// Phase 6 task 7: held off (lib/settings.php, resurrection_locked_settings()); shown locked, never saved. Since
		// task 13c only while account mail is not on; while it is on, a plain setting (below).
		"requirevalidemail"=>"Require users to validate their email address,locked",
		"blockdupeemail"=>"One account per email address,bool",

		// Phase 6 task 13a (S11): account mail's limits. The switch, the test mail and the status are under Account mail.
		"Account Mail,title",
		"Password recovery and email verification by mail are turned on under Account mail once a test mail has been confirmed. These settings apply while it is on.,note",
		"account_mail_hourly"=>"Most account mail sent in one hour,int,".implode(',', resurrection_bounded_settings()['account_mail_hourly']),
		"account_mail_staff"=>"Staff accounts may recover their password by mail,bool",
		"spaceinname"=>"Allow spaces in user names,bool",
		"allowoddadminrenames"=>"Allow admins to enter 'illegal' names in the user editor,bool",
		"selfdelete"=>"Allow player to delete their character,bool",

		"Commentary/Chat,title",
		"soap"=>"Clean user posts (filters bad language and splits words over 45 chars long),bool",
		"maxcolors"=>"Max # of color changes usable in one comment,range,5,40,1",
		"postinglimit"=>"Limit posts to let one user post only up to 50% of the last posts (else turn it off),bool",

		"Place names and People names,title",
		"villagename"=>"Name for the main village",
		"innname"=>"Name of the inn",
		"barkeep"=>"Name of the barkeep",
		"barmaid"=>"Name of the barmaid",
		"bard"=>"Name of the bard",
		"clanregistrar"=>"Name of the clan registrar",
		"deathoverlord"=>"Name of the death overlord",

		"Referral Settings,title",
		"refereraward"=>"How many points will be awarded for a referral?,int",
		"referminlevel"=>"What level does the referral need to reach to credit the referer?,int",

		"Random events,title",
		"forestchance"=>"Chance for Something Special in the Forest,range,0,100,1",
		"villagechance"=>"Chance for Something Special in any village,range,0,100,1",
		"innchance"=>"Chance for Something Special in the Inn,range,0,100,1",
		"gravechance"=>"Chance for Something Special in the Graveyard,range,0,100,1",
		"gardenchance"=>"Chance for Something Special in the Gardens,range,0,100,1",

		"General Combat,title",
		"autofight"=>"Allow fighting multiple rounds automatically,bool",
		"autofightfull"=>"Allow fighting until fight is over,enum,0,Never,1,Always,2,Only when not allowed to flee",

		"Training,title",
		"automaster"=>"Masters hunt down truant students,bool",
		"multimaster"=>"Can players gain multiple levels (challenge multiple masters) per game day?,bool",
		"displaymasternews"=>"Display news if somebody fought his master?,bool",

		"Clans,title",
		"allowclans"=>"Enable Clan System?,bool",
		"goldtostartclan"=>"Gold to start a clan,int",
		"gemstostartclan"=>"Gems to start a clan,int",
		"officermoderate"=>"Can clan officers who are also moderators moderate their own clan even if they cannot moderate all clans?,bool",

		"New Days,title",
		"daysperday"=>"Game days per calendar day,range,1,6,1",
		"specialtybonus"=>"Extra daily uses in specialty area,range,0,5,1",
		// Phase 6 task 18 (N11): newdaycron and its three notes removed; nothing reads it (daily maintenance runs only from
		// cron.php or webcron.php). A stored row is left alone.
		"resurrectionturns"=>"Modify (+ or -) the number of turns deducted after a resurrection as an absolute (number) or relative (number followed by %),amount",

		"Forest,title",
		"turns"=>"Forest Fights per day,range,5,30,1",
		"dropmingold"=>"Forest Creatures drop at least 1/4 of max gold,bool",
		"suicide"=>"Allow players to Seek Suicidally?,bool",
		"suicidedk"=>"Minimum DKs before players can Seek Suicidally?,int",
		"forestgemchance"=>"Player will find a gem one in X times,range,10,100,1",
		"disablebonuses"=>"Should monsters which get buffed with extra HP/Att/Def get a gold+exp bonus?,bool",
		"forestexploss"=>"What percentage of experience should be lost?,range,10,100,1",

		"Multiple Enemies,title",
		"multifightdk"=>"Multiple monsters will attack players above which amount of dragonkills?,range,8,50,1",
		"multichance"=>"The chance for an attack from multiple enemies is,range,0,100,1",
		"addexp"=>"Additional experience (%) per enemy during multifights?,range,0,15",
		"instantexp"=>"During multi-fights hand out experience instantly?,bool",
		"maxattacks"=>"How many enemies will attack per round (max. value),range,1,10",
		"allowpackofmonsters"=>"Allow multiple monsters of the same type to appear in a battle?,bool",
		"Random values for type of seeking is added to random base.,note",
		"multibasemin"=>"The base number of multiple enemies at minimum is,range,1,100,2",
		"multibasemax"=>"The base number of multiple enemies at maximum is,range,1,100,3",
		"multislummin"=>"The number of multiple enemies at minimum for slumming is,range,0,100,0",
		"multislummax"=>"The number of multiple enemies at maximum for slumming is,range,0,100,1",
		"multithrillmin"=>"The number of multiple enemies at minimum for thrill seeking is,range,0,100,1",
		"multithrillmax"=>"The number of multiple enemies at maximum for thrill seeking is,range,0,100,2",
		"multisuimin"=>"The number of multiple enemies at minimum for suicide is,range,0,100,2",
		"multisuimax"=>"The number of multiple enemies at maximum for suicide is,range,0,100,4",

		"Stables,title",
		"allowfeed"=>"Does Merick have feed onhand for creatures,bool",

		"Companions/Mercenaries,title",
		"enablecompanions"=>"Enable the usage of companions,bool",
		"companionsallowed"=>"How many companions are allowed per player,int",
		"Modules my alter this value on a per player basis!,note",
		"companionslevelup"=>"Are companions allowed to level up?,bool",

		"Bank Settings,title",
		"fightsforinterest"=>"Max forest fights remaining to earn interest?,range,0,10,1",
		"maxinterest"=>"Max Interest Rate (%),range,5,10,1",
		"mininterest"=>"Min Interest Rate (%),range,0,5,1",
		"maxgoldforinterest"=>"Over what amount of gold does the bank cease paying interest? (0 for unlimited),int",
		"borrowperlevel"=>"Max player can borrow per level (val * level for max),range,5,200,5",
		"allowgoldtransfer"=>"Allow players to transfer gold,bool",
		"transferperlevel"=>"Max player can receive from a transfer (val * level),range,5,100,5",
		"mintransferlev"=>"Min level a player (0 DK's) needs to transfer gold,range,1,5,1",
		"transferreceive"=>"Total transfers a player can receive in one day,range,0,5,1",
		"maxtransferout"=>"Amount player can transfer to others (val * level),range,5,100,5",
		"innfee"=>"Fee for express inn payment (x or x%),amount",

		"Mail Settings,title",
		"mailsizelimit"=>"Message size limit per message,int",
		"inboxlimit"=>"Limit # of messages in inbox,int",
		"oldmail"=>"Automatically delete old messages after (days),int",
		"superuseryommessage"=>"Warning to give when attempting to YoM an admin?",
		"onlyunreadmails"=>"Only unread mail count towards the inbox limit?,bool",

		"PvP,title",
		"pvp"=>"Enable Slay Other Players,bool",
		"pvpday"=>"Player Fights per day,range,1,10,1",
		"pvpimmunity"=>"Days that new players are safe from PvP,range,1,5,1",
		"pvpminexp"=>"Experience below which player is safe from PvP,int",
		"pvpattgain"=>"Percent of victim experience attacker gains on win,floatrange,.25,20,.25",
		"pvpattlose"=>"Percent of experience attacker loses on loss,floatrange,.25,20,.25",
		"pvpdefgain"=>"Percent of attacker experience defender gains on win,floatrange,.25,20,.25",
		"pvpdeflose"=>"Percent of experience defender loses on loss,floatrange,.25,20,.25",

		"Content Expiration,title",
		"expirecontent"=>"Days to keep comments and news?  (0 = infinite),int",
		"expiretrashacct"=>"Days to keep never logged-in accounts? (0 = infinite),int",
		"expirenewacct"=>"Days to keep 1 level (0 dragon) accounts? (0 =infinite),int",
		"expireoldacct"=>"Days to keep all other accounts? (0 = infinite),int",
		// Phase 6 task 8: bounded (lib/settings.php, resurrection_bounded_settings()); a stored value outside is clamped.
		"LOGINTIMEOUT"=>"Seconds of inactivity before auto-logoff,int,".implode(',', resurrection_bounded_settings()['LOGINTIMEOUT']),

		"High Load Optimization,title",
		"This has been moved to the dbconnect.php,note",
		/*
		"usedatacache"=>"Use Data Caching,bool",
		"datacachepath"=>"Path to store data cache information`n`iNote`i when using in an environment where Safe Mode is enabled; this needs to be a path that has the same UID as the web server runs.",
		//this has been put to the dbconnect.php
		*/

		"Game day Setup,title",
		"dayduration"=>"Day Duration,viewonly",
		"curgametime"=>"Current game time,viewonly",
		"curservertime"=>"Current Server Time,viewonly",
		"lastnewday"=>"Last new day,viewonly",
		"nextnewday"=>"Next new day,viewonly",
		"gameoffsetseconds"=>"Real time to offset new day,$enum",

		"Translation Setup,title",
		"enabletranslation"=>"Enable the use of the translation engine,bool",
		"It is strongly recommended to leave this feature turned on.,note",
		"cachetranslations"=>"Cache the translations (datacache must be turned on)?,bool",
		"permacollect"=>"Permanently collect untranslated texts (overrides the next settings!),bool",
		"collecttexts"=>"Are we currently collecting untranslated texts?,viewonly",
		"tl_maxallowed"=>"Collect untranslated texts if you have fewer player than this logged in. (0 never collects),int",
		"charset"=>"Which charset should be used for htmlentities?,enum,UTF-8,UTF-8",

		// Phase 6 task 18 (N11): the Error Notification section removed (show_notices, notify_on_warn, notify_on_error,
		// notify_address, notify_every and its note); nothing reads them since 1.1.2's lib/errorhandler.php is gone.
		// Stored rows are left alone.

		"Miscellaneous Settings,title",
		"allowspecialswitch"=>"The Barkeeper may help you to switch your specialty?,bool",
		"maxlistsize"=>"Maximum number of items to be shown in the warrior list,int",
	);
 // New accounts gain no flags from configuration. 1.1.2's PayPal and LoGDnet settings are no longer declared at all
 // (payments and LoGDnet are removed); rows an existing database stores for them are left alone and never read.
 unset($setup['defaultsuperuser']);
 // requirevalidemail is held off only while account mail is not on (Phase 6 task 13c, S11 decision 5); while it is on,
 // it saves as any other yes/no setting, in the same place.
 if (!array_key_exists('requirevalidemail', resurrection_locked_settings())) $setup['requirevalidemail']="Require users to validate their email address (sign-ups confirm their address by mail before they can sign in),bool";
 return $setup;
}
