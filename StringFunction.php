<?php
/**
* Basic template of a class
* 
* Void Function (c) 2010
*/
namespace Void;

class StringFunction {
	static public function htmlentities_utf8($string, $quoteStyle=null, $charset='UTF-8', $double_encode=null)
	{
		return htmlentities($string, $quoteStyle, $charset, $double_encode);
	}
	static public function utf8_htmlentities($string, $quoteStyle=null, $charset='UTF-8', $double_encode=null)
	{
		return self::htmlentities_utf8($string, $quoteStyle, $charset, $double_encode);
	}
	
	// should be renamed dateToString and moved to \Infc\Date
	// also, add coments!
	static public function toTime($date, $format="", $lang="") {
		if(is_string($date)) $date = strtotime($date);
		if(!is_numeric($date)) return '';
		if($lang == "") $lang = $GLOBALS["lang"];
		if(strpos(setlocale(LC_TIME, 0), 'fr') !== false) {
			$return = strftime("%e %B %Y", $date);
		}
		else {
			$return = strftime("%B %e, %Y", $date);
		}
		return self::isUTF8($return) ? $return : utf8_encode($return);
	}

	static public function isUTF8($string)
	{
		return preg_match('%(?:
		[\xC2-\xDF][\x80-\xBF]        # non-overlong 2-byte
		|\xE0[\xA0-\xBF][\x80-\xBF]               # excluding overlongs
		|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}      # straight 3-byte
		|\xED[\x80-\x9F][\x80-\xBF]               # excluding surrogates
		|\xF0[\x90-\xBF][\x80-\xBF]{2}    # planes 1-3
		|[\xF1-\xF3][\x80-\xBF]{3}                  # planes 4-15
		|\xF4[\x80-\x8F][\x80-\xBF]{2}    # plane 16
		)+%xs', $string);
	}
	
	static public function get($string) { return self::convertToCleanString($string); }
	static public function clean($string) { return self::convertToCleanString($string); }
	static public function convertToCleanString($string)
	{
		$replacePairs = array(
		'_'=>' ',	'Š'=>'S',	'Œ'=>'OE',	'Ž'=>'Z',	'š'=>'s',	'œ'=>'oe',	'ž'=>'z',	'Ÿ'=>'Y',	'¥'=>'Y',	'µ'=>'u',	'À'=>'A',	'Á'=>'A',
		'Â'=>'A',	'Ã'=>'A',	'Ä'=>'A',	'Å'=>'A',	'Æ'=>'AE',	'Ç'=>'C',	'È'=>'E',	'É'=>'E',	'Ê'=>'E',	'Ë'=>'E',	'Ì'=>'I',	'Í'=>'I',
		'Î'=>'I',	'Ï'=>'I',	'Ð'=>'D',	'Ñ'=>'N',	'Ò'=>'O',	'Ó'=>'O',	'Ô'=>'O',	'Õ'=>'O',	'Ö'=>'O',	'Ø'=>'O',	'Ù'=>'U',	'Ú'=>'U',
		'Û'=>'U',	'Ü'=>'U',	'Ý'=>'Y',	'ß'=>'s',	'à'=>'a',	'á'=>'a',	'â'=>'a',	'ã'=>'a',	'ä'=>'a',	'å'=>'a',	'æ'=>'ae',	'ç'=>'c',
		'è'=>'e',	'é'=>'e',	'ê'=>'e',	'ë'=>'e',	'ì'=>'i',	'í'=>'i',	'î'=>'i',	'ï'=>'i',	'ð'=>'o',	'ñ'=>'n',	'ò'=>'o',	'ó'=>'o',
		'ô'=>'o',	'õ'=>'o',	'ö'=>'o',	'ø'=>'o',	'ù'=>'u',	'ú'=>'u',	'û'=>'u',	'ü'=>'u',	'ý'=>'y',	'ÿ'=>'y',
		);
		$clear=	strtolower(preg_replace('!-{2,}!', '-', 
					strtr(
						trim(
							preg_replace('!\W!', ' ', 
								str_replace(array_keys($replacePairs), array_values($replacePairs), $string)
							)
						), ' ', '-'
					)
				));
		if(strlen($clear) > 0)
			return $clear;
		else
			throw new Exception("convertToCleanString was not able to create a valid clean name for ({$string})");
	}
	
	static public function implodeLast($glue, $glueLast, array $array)
	{
		$last = array_pop($array);
		return implode($glue, $array) . (count($array)?$glueLast:'') . $last;
	}
}
