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
    /**
     * Recursively merges two arrays with distinct values
     * 
     * Unlike array_merge_recursive(), this method replaces values instead of creating
     * nested arrays when the same key exists in both arrays.
     *
     * @param array $array1 The first array to merge
     * @param array $array2 The second array to merge (takes precedence)
     * @return array The merged array with distinct values
     */
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

    /**
     * Selects random element(s) from an array based on weighted priority
     * 
     * Each element should have a priority/weight field. Elements with higher values
     * have a greater chance of being selected. Falls back to 'popularity' field if
     * 'priority' is not found.
     *
     * @param array $array The array to select from (elements should have weight field)
     * @param int $number Number of elements to select (default: 1)
     * @param string $fieldName Name of the weight field (default: 'priority')
     * @return mixed|array Returns single key if $number=1, otherwise array of keys
     */
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

    /**
     * Extracts values for a specific key from an array of arrays
     * 
     * Alias for array_column() method.
     *
     * @param array $array The array to extract values from
     * @param string|array $key The key to extract values for
     * @return array Array of values for the specified key
     */
    public static function getValuesForKey($array, $key)
    {
        return self::array_column($array, $key);
    }

    /**
     * Returns the values from a single column in the input array
     * 
     * Polyfill for native array_column() function. Supports extracting multiple
     * columns when $key is an array.
     *
     * @param array $array The input array (array of arrays)
     * @param string|array $key Column key(s) to extract
     * @return array Array of values from the specified column(s)
     */
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

    /**
     * Converts an array to an HTML table
     * 
     * Supports various options for customizing the table output including caption,
     * thead, CSS classes, and table ID.
     *
     * @param array $data The data to convert to table rows
     * @param array $options Optional settings:
     *                       - 'caption': Table caption text
     *                       - 'thead': Array for table header
     *                       - 'tableClass': CSS class for table element
     *                       - 'tableId': ID attribute for table element
     *                       - 'noTbodyTag': Skip tbody wrapper if true
     * @return string HTML table markup
     */
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

    /**
     * Converts an array to HTML table row(s)
     * 
     * Handles various array structures: flat arrays, nested arrays, and key-value pairs.
     * Used internally by arrayToTable().
     *
     * @param array $data The data to convert to row(s)
     * @param array $options Optional settings:
     *                       - 'useKeyAsTh': Use array keys as th elements
     * @return string HTML tr/td/th markup
     */
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

    /**
     * Converts flat array with bracket notation keys to multidimensional array
     * 
     * Transforms keys like 'field[subfield][index]' into nested array structure.
     * Example: ['user[name]' => 'John'] becomes ['user' => ['name' => 'John']]
     *
     * @param array $input Flat array with bracket notation keys
     * @return array Multidimensional array structure
     */
    public static function explodeToMultidimensionArray(array $input): array
    {
        $output = [];
        foreach($input as $key=>$val) {
            if(strpos($key, '[') === false) {
                $output[$key] = $val;
                continue;
            }
            preg_match_all('(^(\w[\w\d_-]+?)\[(.+)\]$)s', $key, $matches, PREG_SET_ORDER);
            $matches=$matches[0];

            // Get the keys we want to assign
            $keys = explode('][', $matches[2]);
            try {
                self::setInArray($output, $matches[1], $keys, $val);
            } catch(\Throwable $e) {
                var_dump($e->getMessage());
                exit();
            } catch(\Exception $e) {
                var_dump($e->getMessage());
                exit();
            }
        }

        return $output;
    }

    /**
     * Recursively sets a value in a nested array structure
     * 
     * Used internally by explodeToMultidimensionArray() to build nested arrays.
     * Creates intermediate arrays as needed.
     *
     * @param array $arr Reference to the array to modify
     * @param string $firstKey The first level key
     * @param array $keys Remaining keys for nested levels
     * @param mixed $val The value to set
     * @return array The modified array
     */
    static public function setInArray(&$arr, $firstKey, array $keys, $val)
    {
        $key = array_shift($keys);
        if(count($keys) == 0) {
            $arr[$firstKey][$key] = $val;
            return $arr;
        }
        if(!isset($arr[$firstKey][$key])) {
            $arr[$firstKey][$key]=[];
        }
        return self::setInArray($arr[$firstKey], $key, $keys, $val);
    }

    /**
     * Computes the recursive difference between two arrays
     * 
     * Returns elements from $array1 that are not present or differ in $array2.
     * Uses hash optimization for fast comparison of large arrays and nested structures.
     *
     * @param array $array1 The array to compare from
     * @param array $array2 The array to compare against
     * @return array Elements from $array1 that differ from $array2
     */
    static public function arrayRecursiveDiff($array1, $array2)
    {
        // Quick hash comparison - if arrays are identical, return empty immediately
        // For large arrays, this avoids deep traversal
        $hash1 = self::fastHash($array1);
        $hash2 = self::fastHash($array2);

        if ($hash1 === $hash2) {
            return [];
        }

        $return = [];

        foreach ($array1 as $key => $value) {
            // Guard clause: Key doesn't exist in array2 - include it and continue
            if (!isset($array2[$key]) && !array_key_exists($key, $array2)) {
                $return[$key] = $value;
                continue;
            }

            $value2 = $array2[$key];

            // Handle nested arrays with hash optimization
            if (is_array($value) && is_array($value2)) {
                $subHash1 = self::fastHash($value);
                $subHash2 = self::fastHash($value2);

                // Early continue: Hashes match - arrays are identical
                if ($subHash1 === $subHash2) {
                    continue;
                }

                // Different hashes - recurse to find differences
                $aRecursiveDiff = self::arrayRecursiveDiff($value, $value2);
                if ($aRecursiveDiff) {
                    $return[$key] = $aRecursiveDiff;
                }
                continue;
            }

            // Scalar or mixed types - use strict comparison
            if ($value !== $value2) {
                $return[$key] = $value;
            }
        }

        return $return;
    }

    /**
     * Fast hash generation for arrays/values
     * Uses xxHash (via hash()) if available, falls back to faster alternatives
     *
     * @param mixed $data
     * @return string
     */
    private static function fastHash($data)
    {
        // For simple scalar values, return as-is (no hashing needed)
        if (is_scalar($data)) {
            return (string)$data;
        }

        // For arrays, use xxh3 (fastest), xxh64, or md5 as fallback
        // xxh3 is ~10x faster than md5 for large data
        static $hashAlgo = null;
        if ($hashAlgo === null) {
            if (in_array('xxh3', hash_algos())) {
                $hashAlgo = 'xxh3';
            } elseif (in_array('xxh64', hash_algos())) {
                $hashAlgo = 'xxh64';
            } else {
                $hashAlgo = 'md5';
            }
        }

        // Serialize and hash
        // serialize() is faster than json_encode() for PHP arrays
        return hash($hashAlgo, serialize($data));
    }

    /**
     * Recursively filters an array, removing empty values
     * 
     * Traverses nested arrays and removes elements that evaluate to false.
     * Note: Currently the $callback parameter is not used.
     *
     * @param array $array The array to filter
     * @param callable|null $callback Optional callback function (currently unused)
     * @return array The filtered array with empty values removed
     */
    static public function array_filter_recursive(array $array, $callback=null){
        foreach($array as $k=>$v) {
            if(is_array($v)) {
                $array[$k] = static::array_filter_recursive($v, $callback);
            }
            if(!$v){
                unset($array[$k]);
            }
        }
        return $array;
    }
}
