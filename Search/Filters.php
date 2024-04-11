<?php

namespace Void\Search;

class Filters implements \Countable
{
    protected $filterList = array();
    protected $availableFilters = array();

    public const NON_BINARY = "Non-binary files";
    public const HTML_SHTM_PHP = "html, shtm and PHP files";
    public const NON_UTF8 = "Only non-UTF8";

    public function __construct()
    {
        if(count(func_get_args()) > 0) {
            call_user_func_array(array($this, "addFilters"), func_get_args());
        }
    }

    /**
    * Apply filter to filename and return true or false
    *
    * @param $filename string the fully qualified name of a file
    *
    * @return bool true if the file pass the filters, false otherwise
    */
    public function __invoke($filename)
    {
        $bool = true;
        foreach($this->filterList as $filter) {
            if($filter instanceof \Closure) {
                $bool = $bool && $filter($filename);
            } elseif(is_callable($filter)) {
                $bool = $bool && call_user_func($filter, $filename);
            }
            if($bool === false) {
                break;
            }
        }
        return $bool;
    }

    /**
    * Add one or many filters. Alias of addFilters
    *
    * @param $filterNameOrCallable mixed, can be a string name of existing filter, closure or a callable
    */
    public function addFilter()
    {
        call_user_func_array(array($this, "addFilters"), func_get_args());
    }
    /**
    * Add one or many filters.
    *
    * @param $filterNameOrCallable mixed, can be a string name of existing filter, closure or a callable
    */
    public function addFilters($filterNameOrCallable)
    {
        $this->availableFilters = self::getAvailableFilters();
        foreach(func_get_args() as $arg) {
            if(is_string($arg) && isset($this->availableFilters[$arg])) {
                $this->filterList[] = $this->availableFilters[$arg];
            } elseif($arg instanceof \Closure || is_callable($arg)) {
                $this->filterList[] = $arg;
            }
        }
        return $this;
    }

    public function clear()
    {
        $this->filterList = array();
    }

    /**
    * Find if the string exists as a internal filter
    *
    * @param $filterName string the filter name
    *
    * @return bool true if it exists internally, false otherwise
    */
    public function filterExists($filterName)
    {
        return array_key_exists($filterName, $this->filterList);
    }

    /**
    * return the number of filters already setup
    */
    public function count()
    {
        return count($this->filterList);
    }


    /**
    * List of filters that can be applied to files
    * This can be mime type, extension, folders or anything else.
    * All you have to do is create a function that returns true or false,
    * true it keeps the file, false it does not.
    * @return array of available filters
    */
    public static function getAvailableFilters()
    {
        return array(
            self::NON_BINARY => function ($filename) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                return strpos($finfo->file($filename), "text") === 0;
            },
            self::HTML_SHTM_PHP => function ($filename) {
                return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), array("php", "html", "shtm"));
            },
            self::NON_UTF8 => function ($filename, &$encoding = null) {
                if(in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), array("php", "html", "shtm"))) {
                    $finfo = new \finfo(FILEINFO_MIME_ENCODING);
                    $encoding = $finfo->file($filename);
                    return $encoding != "utf-8";
                }
                return false;
            },
        );
    }
}
