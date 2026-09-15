<?php
require_once __DIR__ . '/../../src/Configuration/LegacyConfig.php';
$success = false;
$failure = false;
if (file_exists("dbconnect.php")){
	$success=true;
	$initial=false;
}else{
	$initial = true;
	output("`@`c`bWriting your dbconnect.php file`b`c");
	output("`2I'm attempting to write a file named 'dbconnect.php' to your site root.");
	output("This file tells LoGD how to connect to the database, and is necessary to continue installation.`n");
	$dbconnect = \Resurrection\Configuration\LegacyConfig::render($session['dbinfo']);
	$fp = @fopen("dbconnect.php","w+");
	if ($fp){
		if (fwrite($fp, $dbconnect)!==false){
			output("`n`@Success!`2  I was able to write your dbconnect.php file, you can continue on to the next step.");
		}else{
			$failure=true;
		}
		fclose($fp);
	}else{
		$failure=true;
	}
	if ($failure){
		output("`n`\$Unfortunately, I was not able to write your dbconnect.php file.");
		output("`2You will have to create this file yourself, and upload it to your web server.");
		output("Configuration contains secrets and cannot be displayed. Correct the directory permissions for this private installation and retry.");
		output("You can refresh this page to see if you were successful.");
	}else{
		$success=true;
	}
}
if ($success && !$initial){
	$version = getsetting("installer_version","-1");
	$sub = substr($version, 0, 5);
	$sub = (int)str_replace(".", "", $sub);
	if ($sub < 110) {
		$sql = "SELECT setting, value FROM ".db_prefix("settings")." WHERE setting IN ('usedatacache', 'datacachepath')";
		$result = db_query($sql);
		// Reload trusted local configuration: common.php unsets connection credentials.
		// Do not evaluate individual lines or reconstruct PHP from raw strings.
		$configPath = getcwd() . '/dbconnect.php';
		require $configPath;
		while ($row = db_fetch_assoc($result)) {
			if ($row['setting'] == 'datacachepath') {
				$DB_DATACACHEPATH = $row['value'];
			}
			if ($row['setting'] == 'usedatacache') {
				$DB_USEDATACACHE = $row['value'];
			}
		}
		$dbconnect = \Resurrection\Configuration\LegacyConfig::render([
			'DB_HOST' => $DB_HOST, 'DB_USER' => $DB_USER, 'DB_PASS' => $DB_PASS,
			'DB_NAME' => $DB_NAME, 'DB_PREFIX' => $DB_PREFIX,
			'DB_USEDATACACHE' => $DB_USEDATACACHE, 'DB_DATACACHEPATH' => $DB_DATACACHEPATH,
		]);
		// Check if the file is writeable for us. If yes, we will change the file and notice the admin
		// if not, they have to change the file themselves...
		$fp = @fopen("dbconnect.php","w+");
		if ($fp){
			if (fwrite($fp, $dbconnect)!==false){
				output("`n`@Success!`2  I was able to write your dbconnect.php file.");
			}else{
				$failure=true;
			}
			fclose($fp);
		}else{
			$failure=true;
		}
		if ($failure) {
			output("`2With this new version the settings for datacaching had to be moved to `idbconnect.php`i.");
			output("Due to your system settings and privleges for this file, I was not able to perform the changes by myself.");
			output("Configuration contains secrets and cannot be displayed. Correct local configuration file permissions and retry the upgrade.");
			output("`2This will let you use your existing datacaching settings.`n`n");
			output("If you have done this, you are ready for the next step.");
		} else {
			output("`n`^You are ready for the next step.");
		}
	} else {
		output("`n`^You are ready for the next step.");
	}
}else if(!$success) {
	$session['stagecompleted']=5;
}
?>