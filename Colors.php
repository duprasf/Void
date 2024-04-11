<?php

namespace Void;

use Void\ColorsDefinition;

class Colors
{
    public const CENTERED = 'centered';
    public const FULL = 'full';

    public static function getColorList($useExtraColors = false)
    {
        return array_merge(ColorsDefinition::$webColors, $useExtraColors ? ColorsDefinition::$htmlColors : array());
    }

    public static function getColorName($inputColor, &$details = null, &$group = null)
    {
        foreach(self::getColorList() as $group => $colors) {
            foreach($colors as $name => $details) {
                if(isset($details['hex']) && $inputColor == $details['hex']) {
                    return $name;
                }
            }
        }
        $group = null;
        $details = null;
        return false;
    }

    public static function getClosestWebColor($inputColor)
    {
        if(is_string($inputColor)) {
            $inputColor = self::hex2rgb($inputColor);
        }
        if(!isset($inputColor['r']) || !isset($inputColor['g']) || !isset($inputColor['b'])) {
            return false;
        }
        return sprintf('%02X%02X%02X', (round(round(($rgb['r'] / 0x33)) * 0x33)), round(round(($rgb['g'] / 0x33)) * 0x33), round(round(($rgb['b'] / 0x33)) * 0x33));
    }

    public static function getCloseColorsHsl($inputColor, $precision = null)
    {
        if(is_array($inputColor) && isset($inputColor['h']) && isset($inputColor['s']) && isset($inputColor['l'])) {
            $color = $inputColor;
        } elseif(is_array($inputColor) && isset($inputColor['r']) && isset($inputColor['g']) && isset($inputColor['b'])) {
            $color = self::rgb2hsl($inputColor);
        } else {
            $rgb = self::html2rgb($inputColor);
            if(!$rgb) {
                $rgb = self::hex2rgb($inputColor);
            }
            if(!$rgb) {
                return false;
            }
            $color = self::rgb2hsl($rgb);
        }

        $conditions = array();
        // white
        if($color['l'] >= 249) {
            $conditions['h'] = array('min' => 0, 'max' => 255);
            $conditions['s'] = array('min' => 0, 'max' => 255);
            $conditions['l'] = array('min' => 245, 'max' => 255);
        }
        //black
        elseif($color['l'] <= 10) {
            $conditions['h'] = array('min' => 0, 'max' => 255);
            $conditions['s'] = array('min' => 0, 'max' => 255);
            $conditions['l'] = array('min' => 0, 'max' => 12);
        }
        //grey
        elseif($color['s'] <= 10) {
            $conditions['h'] = array('min' => 0, 'max' => 255);
            $conditions['s'] = array('min' => 0, 'max' => 255);
            $conditions['l'] = array('min' => $color['l'] - 25 < 0 ? 0 : $color['l'] - 25, 'max' => $color['l'] + 25 > 255 ? 255 : $color['l'] + 25);
        }
        //colors
        else {
            $conditions['h'] = array('min' => $color['h'] - 4 < 0 ? 255 + $color['h'] - 4 : $color['h'] - 4, 'max' => $color['h'] + 4 > 255 ? 255 : $color['h'] + 4);
            if($color['s'] <= 58) {
                $conditions['s'] = array('min' => $color['s'] - 40 < 10 ? 10 : $color['s'] - 40, 'max' => $color['s'] + 40);
            } else {
                $conditions['s'] = array('min' => $color['s'] - 100 < 58 ? 58 : $color['s'] - 100, 'max' => $color['s'] + 120 > 255 ? 255 : $color['s'] + 120);
            }
            $conditions['l'] = array('min' => $color['l'] - 13 < 0 ? 0 : $color['l'] - 13, 'max' => $color['l'] + 13 > 255 ? 255 : $color['l'] + 13);
        }
        return $conditions;
    }

    public static function getCloseColors($inputColor, $precision = null, $useExtraColors = false)
    {
        $colorsgroup = self::getColorList($useExtraColors);
        $colorFound = $rgb = self::html2rgb($inputColor);
        if(!$rgb) {
            $rgb = self::hex2rgb($inputColor);
        }
        if(!$rgb) {
            return false;
        }

        if($precision == null) {
            $precision = $colorFound && $inputColor != $colorFound ? 30 : 5;
        }

        $r1 = $rgb['r'];
        $g1 = $rgb['g'];
        $b1 = $rgb['b'];

        $colors = array();
        $precisionMod = 80000;
        $diffFromBlack = sqrt(pow(30 * ($r1), 2) + pow(59 * ($g1), 2) + pow(11 * ($b1), 2));
        if($diffFromBlack >= 12500) {
            $precisionMod = 60000;
        }

        foreach($colorsgroup as $color) {
            $diff = static::compareColors($rgb, $color['hex']);
            if($diff < $precision * $precisionMod) {
                $colors[] = $color;
            }
        }

        return $colors;
    }

    public static function compareColors($colorA, $colorB)
    {
        if(!is_array($colorA)) {
            $colorA = self::hex2rgb($colorA);
        }
        if(!is_array($colorB)) {
            $colorB = self::hex2rgb($colorB);
        }
        if(!isset($colorA['r']) || !isset($colorA['g']) || !isset($colorA['b'])) {
            return false;
        }
        if(!isset($colorB['r']) || !isset($colorB['g']) || !isset($colorB['b'])) {
            return false;
        }

        $r1 = $colorA['r'];
        $r2 = $colorB['r'];
        $g1 = $colorA['g'];
        $g2 = $colorB['g'];
        $b1 = $colorA['b'];
        $b2 = $colorB['b'];

        return (pow(30 * ($r1 - $r2), 2) + pow(59 * ($g1 - $g2), 2) + pow(11 * ($b1 - $b2), 2));
    }

    /**
    * Return a list of colors inside the image
    *
    * @param string $imageFilename the file name of the image
    * @param int $numberOfColors number of collors to return (0=most used)
    * @param int $precision check every x pixel (0=automatic, from file size)
    * @param int|array $centerPercent percentage from left, rigth, top and bottom to ignore (center on the image)
    * @param bool $removeWhiteGreyBlack if true, will try to remove most white, grey, black color since the human eyes will not see it as part of the image "color"
    *
    * @return array
    */
    public function getPalette($imageFilename, $numberOfColors = 0, $precision = 0, $centerPercent = 25, $removeWhiteGreyBlack = false)
    {
        $palette = array();
        $paletteCenter = array();
        $size = getimagesize($imageFilename);
        if(!$size) {
            return false;
        }
        $filesize = filesize($imageFilename);
        $precision = intval($precision);
        //$precision = ($precision>0 ? $precision : floor($filesize/($centerPercent?100000:500000))) | 1;
        $precision = ($precision > 0 ? $precision : floor($filesize / 100000)) | 1;

        switch($size['mime']) {
            case 'image/jpeg':
                $img = imagecreatefromjpeg($imageFilename);
                break;
            case 'image/png':
                $img = imagecreatefrompng($imageFilename);
                break;
            case 'image/gif':
                $img = imagecreatefromgif($imageFilename);
                break;
            default:
                return false;
        }
        if(!$img) {
            return false;
        }
        if(is_numeric($centerPercent)) {
            $centerPercent = array('top' => $centerPercent, 'right' => $centerPercent, 'bottom' => $centerPercent, 'left' => $centerPercent);
        }
        if(!isset($centerPercent['top'])) {
            $centerPercent['top'] = 0;
        }
        if(!isset($centerPercent['right'])) {
            $centerPercent['right'] = 0;
        }
        if(!isset($centerPercent['bottom'])) {
            $centerPercent['bottom'] = 0;
        }
        if(!isset($centerPercent['left'])) {
            $centerPercent['left'] = 0;
        }

        $centerLeft = floor($size[0] * $centerPercent['left'] / 100);
        $centerTop = floor($size[1] * $centerPercent['top'] / 100);
        $centerRight = ceil($size[0] * (100 - $centerPercent['right']) / 100);
        $centerBottom = ceil($size[1] * (100 - $centerPercent['bottom']) / 100);

        for($i = 0; $i < $size[0]; $i += $precision) {
            for($j = 0; $j < $size[1]; $j += $precision) {
                $thisColor = imagecolorat($img, $i, $j);
                $rgb = imagecolorsforindex($img, $thisColor);

                $fullHex = sprintf('%02X%02X%02X', $rgb['red'], $rgb['green'], $rgb['blue']);
                $hsl = $this->hex2hsl($fullHex);
                //$color = sprintf('%02X%02X%02X', $rgb['red'],$rgb['green'],$rgb['blue']);
                //$color = sprintf('%02X%02X%02X', (round(round(($rgb['red'] / 0x11)) * 0x11)), round(round(($rgb['green'] / 0x11)) * 0x11), round(round(($rgb['blue'] / 0x11)) * 0x11));
                //$color = ''.sprintf('%02X%02X%02X', (round(round(($rgb['red'] / 0x22)) * 0x22)), round(round(($rgb['green'] / 0x22)) * 0x22), round(round(($rgb['blue'] / 0x22)) * 0x22));
                $color = ''.sprintf('%02X%02X%02X', (round(round(($rgb['red'] / 0x33)) * 0x33)), round(round(($rgb['green'] / 0x33)) * 0x33), round(round(($rgb['blue'] / 0x33)) * 0x33));

                if(!isset($palette[$color])) {
                    $palette[$color] = array('hsl' => array(), "count" => 0);
                }
                $hslString = json_encode($hsl);
                $rgb = array('r' => $rgb['red'], 'g' => $rgb['green'], 'b' => $rgb['blue'], 'a' => $rgb['alpha']);
                $rgbString = json_encode($rgb);
                $palette[$color]['hsl'][$hslString] = isset($palette[$color]['hsl'][$hslString]) ? $palette[$color]['hsl'][$hslString] + 1 : 1;
                $palette[$color]['rgb'][$rgbString] = isset($palette[$color]['rgb'][$rgbString]) ? $palette[$color]['rgb'][$rgbString] + 1 : 1;
                $palette[$color]['count']++;

                if(
                    $i >= $centerLeft
                    && $i <= $centerRight
                    && $j >= $centerTop
                    && $j <= $centerBottom
                ) {
                    if(!isset($paletteCenter[$color])) {
                        $paletteCenter[$color] = array('hsl' => array(), "count" => 0);
                    }
                    $hslString = json_encode($hsl);
                    $paletteCenter[$color]['hsl'][$hslString] = isset($paletteCenter[$color]['hsl'][$hslString]) ? $paletteCenter[$color]['hsl'][$hslString] + 1 : 1;
                    $paletteCenter[$color]['count']++;
                }
            }
        }
        imagedestroy($img);

        if($removeWhiteGreyBlack) {
            //$palette = $this->removeWhiteGreyBlack($palette);
            //$paletteCenter = $this->removeWhiteGreyBlack($paletteCenter);
        }

        uasort($palette, function ($a, $b) {
            if ($a['count'] == $b['count']) {
                return 0;
            }
            // reverse sort
            return ($a['count'] > $b['count']) ? -1 : 1;
        });
        uasort($paletteCenter, function ($a, $b) {
            if ($a['count'] == $b['count']) {
                return 0;
            }
            // reverse sort
            return ($a['count'] > $b['count']) ? -1 : 1;
        });

        $return = array();
        if($numberOfColors == 0) {
            $return[self::FULL] = $this->getMostUsedColors($palette, $numberOfColors);
            $return[self::CENTERED] = $this->getMostUsedColors($paletteCenter, $numberOfColors);
        } else {
            $return[self::FULL] = array_slice($palette, 0, $numberOfColors, true);
            $return[self::CENTERED] = array_slice($paletteCenter, 0, $numberOfColors, true);
        }
        return $return;
    }

    protected function removeWhiteGreyBlack($palette)
    {
        $max = reset($palette);
        $max = $max['count'];
        $new = array();
        foreach($palette as $color => $info) {
            $pixels = $info['count'];
            if($pixels >= $max * 0.85 || !preg_match('(([9A-F])\1{5})i', $color)) {
                $new[$color] = $info;
            }
        }
        return $new;
    }

    protected function getMostUsedColors($palette, $numberOfColors = 0)
    {
        $return = array();
        // select must used colors as 70% of the third most used image
        // when the number of pixel is below 70% of that color then stop
        next($palette);
        next($palette);
        // current($palette) returns the number of time a specific color was found.
        $current = current($palette);
        $max = $current['count'] * 0.7;

        foreach($palette as $color => $details) {
            if($numberOfColors++ >= 10 && $details['count'] < $max) {
                break;
            }
            $return[$color] = $details;
        }

        return $return;
    }

    public static function html2rgb($name)
    {
        $name = strtolower($name);
        if(key_exists($name, ColorsDefinition::$htmlColors)) {
            return ColorsDefinition::$htmlColors[$name];
        }
        return false;
    }

    public static function hex2rgb($hex)
    {
        if(!is_string($hex) && !is_int($hex)) {
            return false;
        }
        if(strlen($hex) > 7 || !preg_match('(^#?[\da-f]{3,6}$)i', $hex)) {
            return false;
        }
        $hex = str_replace("#", "", $hex);

        if(strlen($hex) == 3) {
            $hexR = substr($hex, 0, 1);
            $hexG = substr($hex, 1, 1);
            $hexB = substr($hex, 2, 1);
            $r = hexdec($hexR.$hexR);
            $g = hexdec($hexG.$hexG);
            $b = hexdec($hexB.$hexB);
        } elseif(strlen($hex) == 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            return false;
        }

        return array('r' => $r, 'g' => $g, 'b' => $b);
    }

    public static function rgb2hex($rgb, $includePoundSign = false)
    {
        $hex = $includePoundSign ? "#" : '';
        $r = isset($rgb['r']) ? $rgb['r'] : (isset($rgb['red']) ? $rgb['red'] : 0);
        $g = isset($rgb['g']) ? $rgb['g'] : (isset($rgb['green']) ? $rgb['green'] : 1);
        $b = isset($rgb['b']) ? $rgb['b'] : (isset($rgb['blue']) ? $rgb['blue'] : 2);
        $hex .= str_pad(dechex($r), 2, "0", STR_PAD_LEFT);
        $hex .= str_pad(dechex($g), 2, "0", STR_PAD_LEFT);
        $hex .= str_pad(dechex($b), 2, "0", STR_PAD_LEFT);

        return strtoupper($hex);
    }

    public static function rgb2hsl($r, $g = null, $b = null)
    {
        if(is_array($r)) {
            if(isset($r['r']) && isset($r['g']) && isset($r['b'])) {
                $b = $r['b'];
                $g = $r['g'];
                $r = $r['r'];
            } elseif(isset($r['red']) && isset($r['green']) && isset($r['blue'])) {
                $b = $r['blue'];
                $g = $r['green'];
                $r = $r['red'];
            } elseif(isset($r[0]) && isset($r[1]) && isset($r[2])) {
                $b = $r[0];
                $g = $r[1];
                $r = $r[2];
            }
        }
        if($r >= 0 && $r <= 255 && $g >= 0 && $g <= 255 && $b >= 0 && $b <= 255) {
            $Red         = round($r / 255, 6);
            $Green       = round($g / 255, 6);
            $Blue        = round($b / 255, 6);
            $HSLColor    = array( 'h' => 0, 's' => 0, 'l' => 0 );

            $Minimum     = min($Red, $Green, $Blue);
            $Maximum     = max($Red, $Green, $Blue);
            $Chroma      = $Maximum - $Minimum;
            $HSLColor['l'] = ($Minimum + $Maximum) / 2;

            if($Chroma == 0) {
                $HSLColor['l'] = round($HSLColor['l'] * 255, 0);
                return $HSLColor;
            }
            $Range = $Chroma * 6;
            $HSLColor['s'] = $HSLColor['l'] <= 0.5 ? $Chroma / ($HSLColor['l'] * 2) : $Chroma / (2 - ($HSLColor['l'] * 2));

            if($Red <= 0.004 || $Green <= 0.004 || $Blue <= 0.004) {
                $HSLColor['s'] = 1;
            }


            if($Maximum == $Red) {
                $HSLColor['h'] = round(($Blue > $Green ? 1 - (abs($Green - $Blue) / $Range) : ($Green - $Blue) / $Range) * 255, 0);
            } elseif($Maximum == $Green) {
                $HSLColor['h'] = round(($Red > $Blue ? abs(1 - (4 / 3) + (abs($Blue - $Red) / $Range)) : (1 / 3) + ($Blue - $Red) / $Range) * 255, 0);
            } else {
                $HSLColor['h'] = round(($Green < $Red ? 1 - 2 / 3 + abs($Red - $Green) / $Range : 2 / 3 + ($Red - $Green) / $Range) * 255, 0);
            }

            $HSLColor['s'] = round($HSLColor['s'] * 255, 0);
            $HSLColor['l']  = round($HSLColor['l'] * 255, 0);
            return $HSLColor;
        } else {
            return false;
        }
    }

    public static function hex2hsl($hex)
    {
        $hex = str_replace('#', '', $hex);

        if(strlen($hex) == 3) {
            $hexR = substr($hex, 0, 1);
            $hexG = substr($hex, 1, 1);
            $hexB = substr($hex, 2, 1);
            $r = hexdec($hexR.$hexR);
            $g = hexdec($hexG.$hexG);
            $b = hexdec($hexB.$hexB);
        } elseif(strlen($hex) == 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            return false;
        }

        return self::rgb2hsl($r, $g, $b);
    }

    public static function hsl2hex(array $hsl)
    {
        if(!isset($hsl['h']) || !isset($hsl['s']) || !isset($hsl['l'])) {
            return false;
        }
        $HSLColor    = array( 'Hue' => $hsl['h'], 'Saturation' => $hsl['s'], 'Luminance' => $hsl['l'] );
        $RGBColor    = array( 'Red' => 0, 'Green' => 0, 'Blue' => 0 );

        $Hue = null;
        $Saturation = null;
        $Luminance = null;

        foreach($HSLColor as $Name => $Value) {
            if(is_string($Value) && strpos($Value, '%') !== false) {
                $Value = round(round((int)str_replace('%', '', $Value) / 100, 2) * 255, 0);
            } elseif(is_float($Value)) {
                $Value = round($Value * 255, 0);
            }

            $Value    = (int)$Value * 1;
            $Value    = $Value > 255 ? 255 : ($Value < 0 ? 0 : $Value);
            $ValuePct = round($Value / 255, 6);

            $$Name = $ValuePct;

        }

        $RGBColor['Red']   = $Luminance;
        $RGBColor['Green'] = $Luminance;
        $RGBColor['Blue']  = $Luminance;

        $Radial  = $Luminance <= 0.5 ? $Luminance * (1.0 + $Saturation) : $Luminance + $Saturation - ($Luminance * $Saturation);

        if($Radial > 0) {
            $Ma   = $Luminance + ($Luminance - $Radial);
            $Sv   = round(($Radial - $Ma) / $Radial, 6);
            $Th   = $Hue * 6;
            $Wg   = floor($Th);
            $Fr   = $Th - $Wg;
            $Vs   = $Radial * $Sv * $Fr;
            $Mb   = $Ma + $Vs;
            $Mc   = $Radial - $Vs;

            // Color is between yellow and green
            if ($Wg == 1) {
                $RGBColor['Red']   = $Mc;
                $RGBColor['Green'] = $Radial;
                $RGBColor['Blue']  = $Ma;
            }
            // Color is between green and cyan
            elseif($Wg == 2) {
                $RGBColor['Red']   = $Ma;
                $RGBColor['Green'] = $Radial;
                $RGBColor['Blue']  = $Mb;
            }

            // Color is between cyan and blue
            elseif($Wg == 3) {
                $RGBColor['Red']   = $Ma;
                $RGBColor['Green'] = $Mc;
                $RGBColor['Blue']  = $Radial;
            }

            // Color is between blue and magenta
            elseif($Wg == 4) {
                $RGBColor['Red']   = $Mb;
                $RGBColor['Green'] = $Ma;
                $RGBColor['Blue']  = $Radial;
            }

            // Color is between magenta and red
            elseif($Wg == 5) {
                $RGBColor['Red']   = $Radial;
                $RGBColor['Green'] = $Ma;
                $RGBColor['Blue']  = $Mc;
            }

            // Color is between red and yellow or is black
            else {
                $RGBColor['Red']   = $Radial;
                $RGBColor['Green'] = $Mb;
                $RGBColor['Blue']  = $Ma;
            }
        }

        $RGBColor['Red']   = ($C = round($RGBColor['Red'] * 255, 0)) < 15 ? '0'.dechex($C) : dechex($C);
        $RGBColor['Green'] = ($C = round($RGBColor['Green'] * 255, 0)) < 15 ? '0'.dechex($C) : dechex($C);
        $RGBColor['Blue']  = ($C = round($RGBColor['Blue'] * 255, 0)) < 15 ? '0'.dechex($C) : dechex($C);
        return '#' . $RGBColor['Red'].$RGBColor['Green'].$RGBColor['Blue'];
    }

    /**
    * This function returns a number between 0 (black) and 255 (white)
    * >= 130 is usually a good place to switch foreground text color from white to black
    *
    * @param array $rgb
    * @return int between 0-255 (130 is a good switch place)
    */
    public static function rgb2Brightness(array $rgb)
    {
        $r = isset($rgb['r']) ? $rgb['r'] : (isset($rgb['red']) ? $rgb['red'] : 0);
        $g = isset($rgb['g']) ? $rgb['g'] : (isset($rgb['green']) ? $rgb['green'] : 1);
        $b = isset($rgb['b']) ? $rgb['b'] : (isset($rgb['blue']) ? $rgb['blue'] : 2);
        return sqrt(
            $r * $r * .241 +
            $g * $g * .691 +
            $b * $b * .068
        );
    }
}
