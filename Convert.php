<?php

namespace Void;

class Convert
{
    public static function cm2inches($cm)
    {
        return intval($cm) * 0.393701;
    }

    public static function inches2cm($inches)
    {
        return intval($inches) * 2.54;
    }

    public static function m2ft($m)
    {
        return intval($m) * 3.28084;
    }

    public static function kg2lbs($kg)
    {
        return intval($kg) * 2.20462;
    }

    public static function lbs2kg($lbs)
    {
        return intval($lbs) * 0.453592;
    }
}
