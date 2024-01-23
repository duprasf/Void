<?php
/**
* Basic template of a class
*
* Void Function (c) 2010
*/
namespace Void;

class StringFunction {
    static protected $selfClosingTag = array("area","base","br","col","command","embed","hr","img","input","keygen","link","meta","param","source","track","wbr");

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

    static public function canBeConvertedToUtf8($str)
    {
        $len = strlen($str);
        for($i = 0; $i < $len; $i++){
            $c = ord($str[$i]);
            if ($c > 128) {
                if (($c > 247)) return false;
                elseif ($c > 239) $bytes = 4;
                elseif ($c > 223) $bytes = 3;
                elseif ($c > 191) $bytes = 2;
                else return false;
                if (($i + $bytes) > $len) return false;
                while ($bytes > 1) {
                    $i++;
                    $b = ord($str[$i]);
                    if ($b < 128 || $b > 191) return false;
                    $bytes--;
                }
            }
        }
        return true;
    }

    static public function getAccentCharacterMap()
    {
        return array(
            'Š'=>'S',    'Œ'=>'OE',    'Ž'=>'Z',    'š'=>'s',    'œ'=>'oe',    'ž'=>'z',    'Ÿ'=>'Y',    '¥'=>'Y',    'µ'=>'u',    'À'=>'A',    'Á'=>'A',
            'Â'=>'A',    'Ã'=>'A',    'Ä'=>'A',    'Å'=>'A',    'Æ'=>'AE',    'Ç'=>'C',    'È'=>'E',    'É'=>'E',    'Ê'=>'E',    'Ë'=>'E',    'Ì'=>'I',    'Í'=>'I',
            'Î'=>'I',    'Ï'=>'I',    'Ð'=>'D',    'Ñ'=>'N',    'Ò'=>'O',    'Ó'=>'O',    'Ô'=>'O',    'Õ'=>'O',    'Ö'=>'O',    'Ø'=>'O',    'Ù'=>'U',    'Ú'=>'U',
            'Û'=>'U',    'Ü'=>'U',    'Ý'=>'Y',    'ß'=>'s',    'à'=>'a',    'á'=>'a',    'â'=>'a',    'ã'=>'a',    'ä'=>'a',    'å'=>'a',    'æ'=>'ae',    'ç'=>'c', 'ć'=>'c',
            'è'=>'e',    'é'=>'e',    'ê'=>'e',    'ë'=>'e',    'ì'=>'i',    'í'=>'i',    'î'=>'i',    'ï'=>'i',    'ð'=>'o',    'ñ'=>'n',    'ò'=>'o',    'ó'=>'o',
            'ô'=>'o',    'õ'=>'o',    'ö'=>'o',    'ø'=>'o',    'ù'=>'u',    'ú'=>'u',    'û'=>'u',    'ü'=>'u',    'ý'=>'y',    'ÿ'=>'y',
        );
    }

    static public function removeAccents($string)
    {
        $replacePairs=self::getAccentCharacterMap();
        return str_replace(array_keys($replacePairs), array_values($replacePairs), $string);
    }

    static public function get($string, $exception = array())
    {
        return self::clean($string, $exception);
    }

    static public function convertToCleanString($string, $exception = array())
    {
        return self::clean($string, $exception);
    }

    static public function clean($string, $exception = array())
    {
        if(strlen($string) === 0) {
            return $string;
        }
        if(!is_array($exception)) {
            $exception = array($exception);
        }

        $string = strip_tags($string);
        if(!self::isUTF8($string)) {
            $string = mb_convert_encoding($string, 'UTF-8');
        }
        // this switch to utf-8 only to decode it a line later is nessecary for
        // entities that are in utf-8 but no equivalent in ISO like &hellip;
        $string = html_entity_decode($string, 0, 'UTF-8');
        $string = mb_convert_encoding($string, 'ISO-8859-1');

        $replacePairs=self::getAccentCharacterMap();
        // this following characters are the &ndash; and the &mdash; converted to the normal -
        $replacePairs['–']='-';
        $replacePairs['—']='-';

        $replacePairs['_']=' ';

        foreach($replacePairs as $key=>$val) {
            $replacePairs[mb_convert_encoding($key, 'ISO-8859-1')] = $val;
        }

        $exception[]='\w';

        $clear=    strtolower(preg_replace('!-{2,}!', '-',
            strtr(
                trim(
                    preg_replace('([^'.implode('',$exception).'])', ' ',
                        str_replace(array_keys($replacePairs), array_values($replacePairs), $string)
                    )
                ), ' ', '-'
            )
        ));

        if(strlen($clear) > 0) {
            return $clear;
        }
        else {
            throw new Exception("convertToCleanString was not able to create a valid clean name for ({$string})");
        }
    }

    static public function implodeLast($glue, $glueLast, array $array)
    {
        $last = array_pop($array);
        return implode($glue, $array) . (count($array)?$glueLast:'') . $last;
    }

    /**
    * this will return the first part of an long html but will count only text (not tags) and will close tags.
    *
    * @param string $html
    * @param int $maxLength
    * @param bool $cutAtWordBoundry if true will cut at the next word boundry (will not cut in the middle of a word)
    * @param string $addCharacters charaters to be added to the end (ex: "..." or "Register to read more"), this will be added to the current tag of the last word
    * @param bool $isUtf8
    *
    * @return string
    */
    static public function truncateHtml($html, $maxLength = 0, $cutAtWordBoundry=true, $addCharacters='', $isUtf8=true)
    {
        if($maxLength == 0 ) return $html;

        $printedLength = 0;
        $position = 0;
        $tags = array();
        $return = '';

        // For UTF-8, we need to count multibyte sequences as one character.
        $re = $isUtf8
        ? '{</?([a-z]+)[^>]*>|&#?[a-zA-Z0-9]+;|[\x80-\xFF][\x80-\xBF]*}'
        : '{</?([a-z]+)[^>]*>|&#?[a-zA-Z0-9]+;}';

        while ($printedLength < $maxLength && preg_match($re, $html, $match, PREG_OFFSET_CAPTURE, $position))
        {
            list($tag, $tagPosition) = $match[0];

            // Print text leading up to the tag.
            $str = substr($html, $position, $tagPosition - $position);
            if ($printedLength + strlen($str) > $maxLength)
            {
                $return.= substr($str, 0, $maxLength - $printedLength);
                $printedLength = $maxLength;
                break;
            }

            $return.= $str;
            $printedLength += strlen($str);
            if ($printedLength >= $maxLength) break;

            if ($tag[0] == '&' || ord($tag) >= 0x80)
            {
                // Pass the entity or UTF-8 multibyte sequence through unchanged.
                $return.= $tag;
                $printedLength++;
            }
            else
            {
                // Handle the tag.
                $tagName = $match[1][0];
                if ($tag[1] == '/')
                {
                    // This is a closing tag.

                    $openingTag = array_pop($tags);
                    //assert($openingTag == $tagName); // check that tags are properly nested.

                    $return.= $tag;
                }
                else if ($tag[strlen($tag) - 2] == '/' || preg_match("(<(".implode('|',self::$selfClosingTag).") )", $tag))
                {
                    // Self-closing tag.
                    $return.= $tag;
                }
                else
                {
                    // Opening tag.
                    $return.= $tag;
                    $tags[] = $tagName;
                }
            }

            // Continue after the tag.
            $position = $tagPosition + strlen($tag);
        }

        // Print any remaining text.
        if ($printedLength < $maxLength && $position < strlen($html))
            $return.= substr($html, $position, $maxLength - $printedLength);

        $return.= $addCharacters;
        // Close any open tags.
        while (!empty($tags))
            $return.= sprintf('</%s>', array_pop($tags));

        return $return;
    }

    /**
    * truncateHtml can truncate a string up to a number of characters while preserving whole words and HTML tags
    *
    * @param string $text String to truncate.
    * @param integer $length Length of returned string, including ellipsis.
    * @param string $ending Ending to be appended to the trimmed string.
    * @param boolean $exact If false, $text will not be cut mid-word
    * @param boolean $considerHtml If true, HTML tags would be handled correctly
    *
    * @return string Trimmed string.
    */
    static function truncateHtml2($text, $length = 100, $ending = '...', $exact = false, $considerHtml = true)
    {
        if ($considerHtml) {
            // if the plain text is shorter than the maximum length, return the whole text
            if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
                return $text;
            }
            // splits all html-tags to scanable lines
            preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
            $total_length = strlen($ending);
            $open_tags = array();
            $truncate = '';
            foreach ($lines as $line_matchings) {
                // if there is any html-tag in this line, handle it and add it (uncounted) to the output
                if (!empty($line_matchings[1])) {
                    // if it's an "empty element" with or without xhtml-conform closing slash
                    if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
                        // do nothing
                        // if tag is a closing tag
                    } else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
                        // delete tag from $open_tags list
                        $pos = array_search($tag_matchings[1], $open_tags);
                        if ($pos !== false) {
                            unset($open_tags[$pos]);
                        }
                        // if tag is an opening tag
                    } else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
                        // add tag to the beginning of $open_tags list
                        array_unshift($open_tags, strtolower($tag_matchings[1]));
                    }
                    // add html-tag to $truncate'd text
                    $truncate .= $line_matchings[1];
                }
                // calculate the length of the plain text part of the line; handle entities as one character
                $content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
                if ($total_length+$content_length> $length) {
                    // the number of characters which are left
                    $left = $length - $total_length;
                    $entities_length = 0;
                    // search for html entities
                    if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
                        // calculate the real length of all entities in the legal range
                        foreach ($entities[0] as $entity) {
                            if ($entity[1]+1-$entities_length <= $left) {
                                $left--;
                                $entities_length += strlen($entity[0]);
                            } else {
                                // no more characters left
                                break;
                            }
                        }
                    }
                    $truncate .= substr($line_matchings[2], 0, $left+$entities_length);
                    // maximum lenght is reached, so get off the loop
                    break;
                } else {
                    $truncate .= $line_matchings[2];
                    $total_length += $content_length;
                }
                // if the maximum length is reached, get off the loop
                if($total_length>= $length) {
                    break;
                }
            }
        } else {
            if (strlen($text) <= $length) {
                return $text;
            } else {
                $truncate = substr($text, 0, $length - strlen($ending));
            }
        }
        // if the words shouldn't be cut in the middle...
        if (!$exact) {
            // ...search the last occurance of a space...
            $spacepos = strrpos($truncate, ' ');
            if (isset($spacepos)) {
                // ...and cut the text in this position
                $truncate = substr($truncate, 0, $spacepos);
            }
        }
        // add the defined ending to the text
        $truncate .= $ending;
        if($considerHtml) {
            // close all unclosed html-tags
            foreach ($open_tags as $tag) {
                $truncate .= '</' . $tag . '>';
            }
        }
        return $truncate;
    }

    static public function replaceStart($search, $replace, $string, $startPos = 0)
    {
        if(substr($string, $startPos, strlen($search)) === $search) {
            return substr_replace($string, $replace, strpos($string, $search, $startPos), strlen($search));
        }
        return $string;
    }

    static public function camel2dashed($className) {
        return strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $className));
    }

    static public function base64url_encode($data) {
      return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    static public function base64url_decode($data) {
      return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
