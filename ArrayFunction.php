<?php
/**
* Basic template of a class
* 
* Void Function (c) 2010
*/
namespace Void;

class ArrayFunction {
	static public function array_merge_recursive_distinct (array $array1, array $array2) {
		$merged = $array1;
		foreach ($array2 as $key=>$value) {
			if(is_array($value)&&isset($merged[$key])&&is_array($merged[$key])) {
				$merged[$key] = self::array_merge_recursive_distinct ($merged[$key], $value);
			}
			else{
				$merged[$key] = $value;
			}
		}
		return $merged;
	}
	
	static public function randomByPriority($array, $number = 1, $fieldName = 'priority')
	{
		$selectedKey = $number>1 ? [] : null;
		$totalWeight = 0;
		$first = reset($array);
		if(!isset($first[$fieldName]) && isset($first['popularity'])) $fieldName = 'popularity';
		foreach($array as $k=>$v) {
			if(!isset($array[$k][$fieldName])){
				$array[$k][$fieldName] = 1;
			}
			$totalWeight+=$array[$k][$fieldName];
		}
		
		for($i=0; $i < $number; $i++) {
			$rand = Dice::rand(1, $totalWeight);
			$currentWeight = 0;
			foreach($array as $k=>$v) {
				$currentWeight+=$v[$fieldName];
				if($rand <= $currentWeight) {
					if($number > 1) $selectedKey[] = $k;
					else $selectedKey = $k;
					break;
				}
			}
		}
		return $selectedKey;
	}
}
