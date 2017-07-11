<?php
namespace Void;

class Convert {
    static public function cm2inches($cm)
    {
        return intval($cm)*0.393701;
    }

    static public function inches2cm($inches)
    {
        return intval($inches)*2.54;
    }

    static public function m2ft($m)
    {
        return intval($m)*3.28084;
    }

    static public function kg2lbs($kg)
    {
        return intval($kg)*2.20462;
    }

    static public function lbs2kg($lbs)
    {
        return intval($lbs)*0.453592;
    }
}
