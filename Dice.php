<?php

namespace Void;

class Dice
{
    public static $lastResult;
    public static $lastRoll;
    public static $lastRollExpression;
    public static $lastReplacement = [];
    public static $reroll1onLastRoll = false;

    public static function resetLastRoll()
    {
        self::$lastRollExpression = '';
        self::$lastRoll = array();
        self::$reroll1onLastRoll = false;
        self::$lastResult = null;
    }

    public static function getLastRollString($addDiceToDescription = false)
    {
        $returnString = str_replace(array("(", ")"), array('\(','\)'), self::$lastRollExpression);
        // replace dices in math expression ($param) with results of roll

        $placement = 0;
        $returnString = preg_replace_callback('((\d*)d(\d+))i', function ($matches) use (&$placement, $addDiceToDescription) {
            $string = '';
            $cr = self::$lastRoll[$placement++];
            $string = '['.($addDiceToDescription ? $matches[0].' | ' : '');
            $eachDice = [];
            foreach($cr['desc'] as $die) {
                $s = str_repeat('¹', $die['reroll1']);
                if(isset($die['removed'])) {
                    $s .= "<del>{$die['value']}</del>";
                } else {
                    $s .= $die['value'];
                }
                $eachDice[] = $s;
            }
            $string .= implode(', ', $eachDice).']';

            return $string;
        }, $returnString);
        $returnString = str_replace(array('\(', '\)'), array('(',')'), $returnString);
        foreach(self::$lastReplacement as $rep) {
            $returnString = preg_replace('(\(%([a-zA-Z0-9-]+)\))', '[\1|'.$rep.']', $returnString);
        }
        return $returnString;
    }

    public static function getLastRoll($addDiceToDescription = false)
    {
        return array(
            "expression" => preg_replace('(\(%([a-zA-Z0-9-]+)\))', '\1', self::$lastRollExpression),
            "roll" => self::$lastRoll,
            "rollString" => self::getLastRollString($addDiceToDescription),
            "rerolled1" => self::$reroll1onLastRoll,
            "result" => self::$lastResult,
        );
    }

    /**
    * Get a random number between two number or dice roll. If $max is omited, the roll is between 1 and $min
    *
    * @param string|int|array $min This can be a number or a dice roll expression (if array, you must define a min and max key or it will take the first and last elements as min and max)
    * @param string|int $max
    */
    public static function rand($min, $max = 0)
    {
        if(is_array($min)) {
            $max = isset($min['max']) ? $min['max'] : end($min);
            $min = isset($min['min']) ? $min['min'] : reset($min);
        }
        if(is_string($min) && $min != intval($min)) {
            $min = self::roll($min);
        }
        if(is_string($max) && $max != intval($max)) {
            $max = self::roll($max);
        }
        if(is_numeric($min) && is_numeric($max)) {
            if($max < $min) {
                $max = $min;
                $min = 1;
            }
            return random_int(intval($min), intval($max));
        }
        return false;
    }

    /**
    * Roll a random dice roll.
    *
    * @param string $param this should be an expression like 3D6, 2D8+5, D20 or any arithmetic and D (for dice) format.
    * @param bool $reroll1 if set to true, all rolled 1 will be rerolled.
    * @param bool $removeLowest if set to true one extra dice will be rolled per diceset and the lowest dice will be removed before returning the result.
    */
    public static function roll($param, $reroll1 = false, $removeLowest = false, ...$replacement)
    {
        self::resetLastRoll();
        self::$lastRollExpression = $param;
        self::$lastReplacement = $replacement;

        $param = str_replace(array("(", ")"), array('\(','\)'), $param);
        // replace dices in math expression ($param) with results of roll
        $param = preg_replace_callback('((\d*)d(\d+))i', function ($matches) use ($reroll1, $removeLowest) {
            $total = 0;
            if(!is_numeric($matches[1])) {
                $matches[1] = 1;
            }
            if($removeLowest) {
                $matches[1] += 1;
            }
            $rolls = array();
            $rollDesc = array();
            for($i = 1; $i <= $matches[1]; $i++) {
                $failsafe = 0;
                do {
                    $val = random_int(1, $matches[2]);
                } while($val == 1 && $reroll1 && $failsafe++ < 10000);
                $rollDesc[] = array('value' => $val, 'reroll1' => $failsafe);
                $rolls[] = $val;
            }
            if($removeLowest) {
                $removedPos = array_search(min($rolls), $rolls);
                $rollDesc[$removedPos]['removed'] = true;
                unset($rolls[$removedPos]);
            }
            self::$reroll1onLastRoll = false;
            foreach($rollDesc as $r) {
                if(isset($r['reroll1']) && $r['reroll1'] && !(isset($r['removed']) && $r['removed'])) {
                    self::$reroll1onLastRoll = true;
                    break;
                }
            }

            $total += array_sum($rolls);
            self::$lastRoll[] = array('value' => $total, 'desc' => $rollDesc, 'rerolled1' => self::$reroll1onLastRoll);
            return $total;
        }, $param);

        $param = str_replace(array('\(', '\)'), array('(',')'), $param);
        $param = preg_replace(['(\(%[a-zA-Z0-9-]+\))'], $replacement, $param);

        // send param to be evaluated as math expression
        self::$lastResult = $param != '' ? EvalMath::e($param) : 0;
        return self::$lastResult;
    }

    /**
    * Similar to roll, except that this function always returns the  maximum on each dice roll (usefull to show the range of an expression in conjonction with the min function)
    *
    * @param string $param this should be an expression like 3D6 or 2D20+15 or any arithmetic and D (for dice) format.
    * @param bool $reroll1 if set to true, all rolled 1 will be rerolled.
    * @param bool $removeLowest if set to true one extra dice will be rolled per diceset and the lowest dice will be removed before returning the result.
    *    ******** $removeLowest is not used in ->min() and ->max() since all dices have the same values. It is there only for compatibility with ->roll()
    */
    public static function max($param, $reroll1 = false, $removeLowest = false)
    {
        $param = str_replace(array("(", ")"), array('\(','\)'), $param);
        $param = preg_replace_callback('((\d*)d(\d+))i', function ($matches) use ($reroll1, $removeLowest) {
            if(!is_numeric($matches[1])) {
                $matches[1] = 1;
            }
            return $matches[1] * $matches[2];
        }, $param);
        $param = str_replace(array('\(', '\)'), array('(',')'), $param);
        // send param to be evaluated as math expression
        return $param != '' ? EvalMath::e($param) : 0;
    }

    /**
    * Similar to roll, except that this function always returns the  minimum on each dice roll (usefull to show the range of an expression in conjonction with the max function)
    *
    * @param string $param this should be an expression like 3D6 or 2D20+15 or any arithmetic and D (for dice) format.
    * @param bool $reroll1 if set to true, all rolled 1 will be rerolled.
    * @param bool $removeLowest if set to true one extra dice will be rolled per diceset and the lowest dice will be removed before returning the result.
    *    ******** $removeLowest is not used in ->min() and ->max() since all dices have the same values. It is there only for compatibility with ->roll()
    */
    public static function min($param, $reroll1 = false, $removeLowest = false)
    {
        $param = str_replace(array("(", ")"), array('\(','\)'), $param);
        $param = preg_replace_callback('((\d*)d(\d+))i', function ($matches) use ($reroll1, $removeLowest) {
            if(!is_numeric($matches[1])) {
                $matches[1] = 1;
            }
            // if you reroll one, than each dice is 2, multiply by 2
            return $matches[1] * ($reroll1 ? 2 : 1);
        }, $param);
        $param = str_replace(array('\(', '\)'), array('(',')'), $param);
        // send param to be evaluated as math expression
        return $param != '' ? EvalMath::e($param) : 0;
    }
}
