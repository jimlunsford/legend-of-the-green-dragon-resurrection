<?php
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
// translator ready
// addnews ready
// mail ready
function output_array($array,$prefix=""){
	$out="";
	while (list($key,$val)=resurrection_array_next($array)){
		$out.=$prefix."[$key] = ";
		if (is_array($val)){
			$out.="array{\n".output_array($val,$prefix."[$key]")."\n}\n";
		}else{
			$out.=$val."\n";
		}
	}
	return $out;
}

function code_array($array){
	reset($array);
	$output="array(";
	$i=0;
	while (list($key,$val)=resurrection_array_next($array)){
		if ($i>0) $output.=", ";
		if (is_array($val)){
			$output.="'".addslashes($key)."'=>".code_array($val);
		}else{
			$output.="'".addslashes($key)."'=>'".addslashes($val)."'";
		}
		$i++;
	}
	$output.=")\n";
	return $output;
}
?>
