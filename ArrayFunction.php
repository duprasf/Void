<?php
/**
* This is a basic Array class with little useful functions
*
* Created by Francois Dupras
* October 2010
*/

namespace Void;

class ArrayFunction
{
    public static function array_merge_recursive_distinct(array $array1, array $array2)
    {
        $merged = $array1;
        foreach ($array2 as $key => $value) {
            if(is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::array_merge_recursive_distinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }

    public static function randomByPriority($array, $number = 1, $fieldName = 'priority')
    {
        $selectedKey = $number > 1 ? [] : null;
        $totalWeight = 0;
        $first = reset($array);
        if(!isset($first[$fieldName]) && isset($first['popularity'])) {
            $fieldName = 'popularity';
        }
        foreach($array as $k => $v) {
            if(!isset($array[$k][$fieldName])) {
                $array[$k][$fieldName] = 1;
            }
            $totalWeight += $array[$k][$fieldName];
        }

        for($i = 0; $i < $number; $i++) {
            $rand = Dice::rand(1, $totalWeight);
            $currentWeight = 0;
            foreach($array as $k => $v) {
                $currentWeight += $v[$fieldName];
                if($rand <= $currentWeight) {
                    if($number > 1) {
                        $selectedKey[] = $k;
                    } else {
                        $selectedKey = $k;
                    }
                    break;
                }
            }
        }
        return $selectedKey;
    }

    public static function getValuesForKey($array, $key)
    {
        return self::array_column($array, $key);
    }

    public static function array_column($array, $key)
    {
        if(function_exists('array_column')) {
            return array_column($array, $key);
        }
        $return = array();
        if(is_array($key)) {
            foreach($array as $cr) {
                foreach($key as $k) {
                    $return[$k][] = $cr[$k];
                }
            }
        } else {
            foreach($array as $cr) {
                $return[] = $cr[$key];
            }
        }
        return $return;
    }

    public static function arrayToTable(array $data, array $options = array())
    {
        $table = '';
        if(isset($options['caption'])) {
            $table .= '<caption>'.$options['caption'].'</caption>'.PHP_EOL;
        }
        if(isset($options['thead']) && is_array($options['thead'])) {
            $table .= '<thead>'.PHP_EOL.self::arrayToTableRow($options['thead'], $options).'</thead>'.PHP_EOL;
        }

        if(isset($options['noTbodyTag'])) {
            $table .= self::arrayToTableRow($data, $options);
        } else {
            $table .= '<tbody>'.PHP_EOL.self::arrayToTableRow($data, $options).'</tbody>'.PHP_EOL;
        }

        return '<table'
            .(isset($options['tableClass']) ? ' class="'.$options['tableClass'].'"' : '')
            .(isset($options['tableId']) ? ' id="'.$options['tableId'].'"' : '')
            .'>'.PHP_EOL
            .$table
            .'</table>'
            .PHP_EOL
        ;
    }

    public static function arrayToTableRow(array $data, array $options = array())
    {
        if(isset($options['useKeyAsTh']) && $options['useKeyAsTh']) {
            $return = '';
            foreach($data as $key => $val) {
                $return .= "<tr><th>{$key}</th><td>".(
                    is_array($val)
                    ? implode('</td><td>', $val)
                    : $val
                ).'</td></tr>';
            };
        } elseif(is_array(reset($data))) {
            $return = '<tr>'.PHP_EOL.implode('<tr>'.PHP_EOL.'</tr>'.PHP_EOL, array_map(function ($x) {return '<td>'.implode('</td>'.PHP_EOL.'<td>', $x).'</td>'.PHP_EOL;}, $data)).'</tr>'.PHP_EOL;
        } else {
            $return = '<tr><td>'.implode('</td><td>', $data).'</td></tr>';
        }
        return $return;
    }
}
