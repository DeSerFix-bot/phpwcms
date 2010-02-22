<?php
/*************************************************************************************
   Copyright notice
   
   (c) 2002-2010 Oliver Georgi (oliver@phpwcms.de) // All rights reserved.
 
   This script is part of PHPWCMS. The PHPWCMS web content management system is
   free software; you can redistribute it and/or modify it under the terms of
   the GNU General Public License as published by the Free Software Foundation;
   either version 2 of the License, or (at your option) any later version.
  
   The GNU General Public License can be found at http://www.gnu.org/copyleft/gpl.html
   A copy is found in the textfile GPL.txt and important notices to the license 
   from the author is found in LICENSE.txt distributed with these scripts.
  
   This script is distributed in the hope that it will be useful, but WITHOUT ANY 
   WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
   PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 
   This copyright notice MUST APPEAR in all copies of the script!
*************************************************************************************/

// ----------------------------------------------------------------
// obligate check for phpwcms constants
if (!defined('PHPWCMS_ROOT')) {
   die("You Cannot Access This Script Directly, Have a Nice Day.");
}
// ----------------------------------------------------------------



//multimedia

// read template
if(empty($crow["acontent_template"]) && is_file(PHPWCMS_TEMPLATE.'inc_default/multimedia.tmpl')) {

	$crow["acontent_template"]	= @file_get_contents(PHPWCMS_TEMPLATE.'inc_default/multimedia.tmpl');
	
} elseif(is_file(PHPWCMS_TEMPLATE.'inc_cntpart/multimedia/'.$crow["acontent_template"])) {

	$crow["acontent_template"]	= @file_get_contents(PHPWCMS_TEMPLATE.'inc_cntpart/multimedia/'.$crow["acontent_template"]);

} else {

	$crow["acontent_template"]	= '[MULTIMEDIA]<div class="multimedia">{MULTIMEDIA}</div>[/MULTIMEDIA]';

}




$media				= @unserialize($crow['acontent_form']);
$media["source"]	= '';
$media["code"]		= '';
$media["alt"]		= '';

$media["media_control"]	= $media["media_control"] ? 'true' : 'false';
$media["media_auto"]	= $media["media_auto"] ? 'true' : 'false';

if($media["media_src"]) {

	$media["source"] = $media['media_extern'];

} elseif($media["media_id"]) {

	$media["sql"]  = "SELECT * FROM ".DB_PREPEND."phpwcms_file WHERE f_public=1 AND f_aktiv=1 AND f_id=".intval($media["media_id"])." AND ";
	if( !FEUSER_LOGIN_STATUS ) {
		$media["sql"] .= 'f_granted=0 AND ';
	}
	$media["sql"] .= "f_name='".aporeplace($media["media_name"])."' LIMIT 1";

	$media["result"] = _dbQuery($media["sql"]);
	
	if(isset($media["result"][0])) {

		$media["mime"]	 = $media["result"][0]["f_type"];
		$media["source"] = PHPWCMS_FILES.$media["result"][0]["f_hash"];
				
		if($media["result"][0]["f_ext"]) {
			$media["source"] .= '.'.$media["result"][0]["f_ext"];
		}
	}
}

if(!empty($media["image_id"])) {

	$media["alt"] .= '<div class="alt-image">';
	$media["alt"] .= '<img src="img/cmsimage.php/' . $media["media_width"] . 'x' . $media["media_height"] . 'x1/' . $media["image_id"] . '" ';
	$media["alt"] .= 'alt="'.html_specialchars($media["image_name"]).'" border="0" />';
	$media["alt"] .= '</div>';

}
if(!empty($media["image_caption"])) {

	$media["alt"] .= plaintext_htmlencode($media["image_caption"]);

}

if($media["alt"]) {
	$media["alt"] = '	' . $media["alt"] . LF;
}

//Aufbauen der Plugin-Codeteile
if($media["source"]) {
	
	$randomID = 'mediaID'.$crow['acontent_id'];

	switch($media["media_player"]) {
		
		case 0:	//Quicktime Player/Plugin
				$block['custom_htmlhead']['AC_QuickTime.js'] = '  <script src="'.TEMPLATE_PATH.'inc_js/AC_QuickTime.js" type="text/javascript"></script>';
				
				$media["media_height"] = $media["media_height"] + ( $media["media_control"] == "true" ? 16 : 0 );
				$media["width"]  = $media["media_width"]  ? 'width="'.$media["media_width"].'" '   : '';
				$media["height"] = $media["media_height"] ? 'height="'.$media["media_height"].'" ' : '';
				
				$media["code"]  = LF.'<script type="text/javascript">'.LF.SCRIPT_CDATA_START.LF;
				$media['code'] .= empty($phpwcms['mode_XHTML']) ? '	QT_WriteOBJECT' : '	QT_WriteOBJECT_XHTML';
				$media['code'] .= "('".$media["source"]."', '".$media["media_width"]."', '".$media["media_height"]."', '', ";
				$media['code'] .= "'autoplay', '".$media["media_auto"]."', ";
				$media['code'] .= "'bgcolor', 'black', 'align', 'middle', 'cache', 'true', ";
				$media['code'] .= "'controller', '".$media["media_control"]."', 'type', 'video/quicktime')";
				$media["code"] .= LF.SCRIPT_CDATA_END.LF.'</script>';

				$media["code"] .= '<noscript><object '.$media["width"].$media["height"].'border="0" id="'.$randomID.'" ';
				if(BROWSER_NAME == 'IE' && BROWSER_OS == 'Win') {
					$media["code"] .= 'classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B"';
				} else {
					$media["code"] .= 'data="'.$media["source"].'" type="video/quicktime"';
				}
				$media['code'] .= '>'.LF;
				$media["code"] .= '	<param name="src" value="'.$media["source"].'"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="type" value="video/quicktime"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="align" value="middle"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="autoplay" value="'.$media["media_auto"].'"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="controller" value="'.$media["media_control"].'"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="bgcolor" value="black"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="cache" value="true"'.HTML_TAG_CLOSE.LF;

				$media["code"] .= $media["alt"];

				$media["code"] .= '</object></noscript>'.LF;
				break;

		case 1:	//Real Player/Plugin
				$console = 'real'.$randomID;
				
				$block['custom_htmlhead']['AC_WriteActiveX.js'] = '  <script src="'.TEMPLATE_PATH.'inc_js/AC_WriteActiveX.js" type="text/javascript"></script>';
				
				$media["width"]			= $media["media_width"]  ? 'width="'.$media["media_width"].  '" ' : '';
				$media["height"]		= $media["media_height"] ? 'height="'.$media["media_height"].'" ' : '';
				$media['console']		= 'real'.$randomID;
				
				$media["code"]  = LF.'<object id="'.$randomID.'" name="'.$randomID.'" '.$media["width"].$media["height"];
				$media["code"] .= 'classid="clsid:CFCDAA03-8BE4-11cf-B84B-0020AFBBCCFA">'.LF;
				$media["code"] .= '	<param name="src" value="'.$media["source"].'"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="autostart" value="'.$media["media_auto"].'"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="controls" value="ImageWindow"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="console" value="'.$media['console'].'"'.HTML_TAG_CLOSE.LF;
				if(BROWSER_NAME == 'Mozilla') {
					$media["code"] .= '	<embed src="'.$media["source"].'" border="0" autostart="'.$media["media_auto"].'" ';
					$media["code"] .= 'id="e'.$randomID.'" name="e'.$randomID.'" '.$media["width"].$media["height"];
					$media["code"] .= 'controls="ImageWindow" console="'.$media['console'].'" type="audio/x-pn-realaudio-plugin">';
					$media["code"] .= '</embed>';
				}
				
				$media["code"] .= $media["alt"];
				
				$media["code"] .= '</object>'.LF;
				if($media["media_control"] == "true") {
					$media["code"] .= '<br />'.LF.'<object id="'.$randomID.'_C" name="'.$randomID.'_C" height="32" '.$media["width"];
					$media["code"] .= 'classid="clsid:CFCDAA03-8BE4-11cf-B84B-0020AFBBCCFA">'.LF;
					$media["code"] .= '	<param name="src" value="'.$media["source"].'"'.HTML_TAG_CLOSE.LF;
					$media["code"] .= '	<param name="autostart" value="'.$media["media_auto"].'"'.HTML_TAG_CLOSE.LF;
					$media["code"] .= '	<param name="controls" value="ControlPanel"'.HTML_TAG_CLOSE.LF;
					$media["code"] .= '	<param name="console" value="'.$media['console'].'"'.HTML_TAG_CLOSE.LF;
					if(BROWSER_NAME == 'Mozilla') {
						$media["code"] .= '	<embed src="'.$media["source"].'" border="0" autostart="'.$media["media_auto"].'" ';
						$media["code"] .= 'id="e'.$randomID.'_C" name="e'.$randomID.'_C" height="32" '.$media["width"];
						$media["code"] .= 'controls="ControlPanel" console="'.$media['console'].'" type="audio/x-pn-realaudio-plugin">';
						$media["code"] .= '</embed>';
					}
					$media["code"] .= '</object>'.LF;
				}
				
				if(BROWSER_NAME == 'IE' && BROWSER_OS == 'Win') {
					$media["code"]    = trim($media["code"]);
					$media["iecode"]  = LF.'<script type="text/javascript">'.LF.SCRIPT_CDATA_START.LF;
					$media["iecode"] .= "	_writeActiveXObject('".str_replace(LF, '', $media["code"])."');";
					$media["iecode"] .= LF.SCRIPT_CDATA_END.LF.'</script>'.LF;
					$media["iecode"] .= '<noscript>'.$media["code"].'</noscript>'.LF;
					$media["code"]    = $media["iecode"];
				}

				break;
				
				
		case 2:	//Windows Media Player/Plugin
				$block['custom_htmlhead']['AC_WriteActiveX.js'] = '  <script src="'.TEMPLATE_PATH.'inc_js/AC_WriteActiveX.js" type="text/javascript"></script>';
		
				$media["width"]			= $media["media_width"]  ? 'width="'.$media["media_width"].'" ' : '';
				$media["media_height"]	= $media["media_height"] + ($media["media_control"] == "true" ? 45 : 0);
				$media["height"]		= $media["media_height"] ? 'height="'.$media["media_height"].'" ' : '';
				
				$media["code"]  = LF.'<object id="'.$randomID.'" name="'.$randomID.'" '.$media["width"].$media["height"];
				if(BROWSER_NAME == 'IE' && BROWSER_OS == 'Win') {
					$media["code"] .= 'classid="clsid:22D6f312-B0F6-11D0-94AB-0080C74C7E95" ';
					$media["code"] .= 'type="application/x-oleobject"';
				} else {
					$media["code"] .= 'data="'.$media["source"].'" type="'.((!$media["media_src"] && $media["media_type"]) ? $media["media_type"] : 'video/x-ms-wmv').'"';
				}
				$media["code"] .= '>'.LF;
				$media["code"] .= '	<param name="filename" value="'.$media["source"].'"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="autostart" value="'.($media["media_auto"]=='true'?1:0).'"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="autosize" value="0"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="showstatusbar" value="0"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="showcontrols" value="'.($media["media_control"]=='true'?1:0).'"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="showdisplay" value="0"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="displaysize" value="0"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="showtracker" value="1"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="enabletracker" value="1"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="autorewind" value="0"'.HTML_TAG_CLOSE.LF;
				$media["code"] .= '	<param name="animationatstart" value="1"'.HTML_TAG_CLOSE.LF;
				if($media["width"] && $media["width"] <=240) {
					$media["code"] .= '	<param name="showpositioncontrols" value="0"'.HTML_TAG_CLOSE.LF;
				}
				
				$media["code"] .= $media["alt"];
				
				$media["code"] .= '</object>'.LF;
				
				if(BROWSER_NAME == 'IE' && BROWSER_OS == 'Win') {
					$media["code"]    = trim($media["code"]);
					$media["iecode"]  = LF.'<script type="text/javascript">'.LF.SCRIPT_CDATA_START.LF;
					$media["iecode"] .= "	_writeActiveXObject('".str_replace(LF, '', $media["code"])."');";
					$media["iecode"] .= LF.SCRIPT_CDATA_END.LF.'</script>'.LF;
					$media["iecode"] .= '<noscript>'.$media["code"].'</noscript>'.LF;
					$media["code"]    = $media["iecode"];
				}
				
				break;
				
				
		case 3:	//Flash Player/Plugin
				if(!$media["media_src"] && (!$media["media_width"] || !$media["media_height"])) {
					$media['local'] = @getimagesize(PHPWCMS_ROOT.'/'.$media["source"]);
					if(is_array($media['local'])) {
						$media["media_width"]  = $media['local'][0];
						$media["media_height"] = $media['local'][1];
					}
				}
				
				$media["width"]  = $media["media_width"]  ? ' width="'.$media["media_width"].'"' : '';
				$media["height"] = $media["media_height"] ? ' height="'.$media["media_height"].'"' : '';
				
				$media["param"]  = '	<param name="movie" value="'.$media["source"].'" />'.LF;
				$media["param"] .= '	<param name="quality" value="autohigh" />'.LF;
				$media["param"] .= '	<param name="scale" value="noborder" />'.LF;
				$media["param"] .= '	<param name="loop" value="false" />'.LF;
				$media["param"] .= '	<param name="play" value="'.$media["media_auto"].'" />'.LF;
				
				if($media["media_transparent"]) {
					$media["param"] .= '	<param name="wmode" value="transparent" />'.LF;
				} else {
					$media["param"] .= '	<param name="wmode" value="opaque" />'.LF;
				}
				
				
				$media["code"]  = LF . '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" id="'.$randomID.'"'.$media["width"].$media["height"].'>' . LF;
				$media["code"] .= $media["param"];
				$media["code"] .= '	<!--[if !IE]>--><object type="application/x-shockwave-flash" data="'.$media["source"].'"'.$media["width"].$media["height"].'><!--<![endif]-->' . LF;
				$media["code"] .= $media["param"];
				
				$media["code"] .= $media["alt"];
				
				$media["code"] .= '	<!--[if !IE]>--></object><!--<![endif]-->' . LF;
				$media["code"] .= '</object>' . LF;
				
				initSwfObject();
				
				$block['custom_htmlhead'][$randomID]  = '  <script type="text/javascript">'.LF.SCRIPT_CDATA_START.LF;
				$block['custom_htmlhead'][$randomID] .= '	swfobject.registerObject("'.$randomID.'", "9.0.0", "'.PHPWCMS_URL.TEMPLATE_PATH.'inc_js/swfobject/2.1/expressInstall.swf");';
				$block['custom_htmlhead'][$randomID] .= LF.SCRIPT_CDATA_END.LF.'  </script>';

				break;
	}
}

if($media["code"]) {
	
	$media["result"] = '';	

	switch($media["media_pos"]) {
	
		case 0:	$media["result"] .= headline($crow["acontent_title"], $crow["acontent_subtitle"], $template_default["article"]);
				$media["result"] .= $media["code"];
				break;
				
		case 1:	$media["result"] .= headline($crow["acontent_title"], $crow["acontent_subtitle"], $template_default["article"]);
				$media["result"] .= "<div align=\"center\">".$media["code"]."</div>";
				break;
				
		case 2:	$media["result"] .= headline($crow["acontent_title"], $crow["acontent_subtitle"], $template_default["article"]);
				$media["result"] .= "<div align=\"right\">".$media["code"]."</div>";
				break;
				
		case 3: $media["result"] .= "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\">\n";
				$media["result"] .= "<tr><td colspan=\"2\">".spacer(1,3)."</td></tr>\n";
				$media["result"] .= ($crow["acontent_title"]) ?	"<tr><td class=\"tableHead\">".html_specialchars($crow["acontent_title"])."</td><td>".
														spacer(5,1)."</td></tr>\n<tr><td colspan=\"2\">".spacer(1,3)."</td></tr>\n" : "";
				$media["result"] .= ($crow["acontent_subtitle"]) ?	"<tr><td class=\"tableSubHead\">".html_specialchars($crow["acontent_subtitle"])."</td><td>".
															spacer(5,1)."</td></tr>\n<tr><td colspan=\"2\">".spacer(1,3)."</td></tr>\n" : "";
				$media["result"] .= "<tr><td>".$media["code"]."</td><td>".spacer(5,1)."</td></tr>\n";
				$media["result"] .= "<tr><td colspan=\"2\">".spacer(1,3)."</td></tr>\n";
				$media["result"] .= "</table>\n";
				break;
				
		case 4: $media["result"] .= "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"right\">\n";
				$media["result"] .= "<tr><td colspan=\"2\">".spacer(1,3)."</td></tr>\n";
				$media["result"] .= ($crow["acontent_title"]) ?	"<tr><td>".spacer(5,1)."</td><td class=\"tableHead\">".html_specialchars($crow["acontent_title"])."</td></tr>\n<tr><td colspan=\"2\">".spacer(1,3)."</td></tr>\n" : "";
				$media["result"] .= ($crow["acontent_subtitle"]) ?	"<tr><td>".spacer(5,1)."</td><td class=\"tableSubHead\">".html_specialchars($crow["acontent_subtitle"])."</td></tr>\n<tr><td colspan=\"2\">".spacer(1,3)."</td></tr>\n" : "";
				$media["result"] .= "<tr><td>".spacer(5,1)."</td><td>".$media["code"]."</td></tr>\n";
				$media["result"] .= "<tr><td colspan=\"2\">".spacer(1,3)."</td></tr>\n";
				$media["result"] .= "</table>\n";
				break;
	}

} else {

	$media["result"] = headline($crow["acontent_title"], $crow["acontent_subtitle"], $template_default["article"]);

}

$CNT_TMP .= LF . trim( render_cnt_template($crow["acontent_template"], 'MULTIMEDIA', trim($media["result"]) ) ) . LF;

unset($media);
									
?>