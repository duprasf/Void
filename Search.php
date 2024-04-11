<?php

namespace Void;

class Search
{
    public const DEFAULT_FILTER = Search\Filters::NON_BINARY;
    public const OPERATION_SEARCH = 1;
    public const OPERATION_REPLACE = 2;
    public const OPERATION_SEARCH_UTF8 = 3;
    public $filters = null;
    protected $_iterator = null;
    protected $_options = array();
    protected $_path = null;
    protected $_searchTerm = '';
    protected $_directoryException = array();
    protected $_isBasicSearch = false;

    private static $_validPattern = true;
    private static $_errorMessage = "";

    public function __construct($path = "")
    {
        $this->filters = new Search\Filters(self::DEFAULT_FILTER);
        if(is_string($path) && $path != "") {
            $this->setPath($path);
        }
    }

    /**
    * overwrite the default filters class
    * @param $filters a search filters or extented class
    */
    public function setFilters(Search\Filters $filters)
    {
        $this->filters = $filters;
        return $this;
    }

    public function __get($name)
    {
        $return = null;
        switch($name) {
            case "options": $return = $this->_options;
                break;
            case "iterator": $return = $this->_iterator;
                break;
            case "path": $return = $this->_path;
                break;
            case "searchTerm": $return = $this->_searchTerm;
                break;
            case "directoryException": $return = $this->_directoryException;
                break;
            case 'isBasicSearch': $return = $this->_isBasicSearch;
                break;
            default:
                if(isset($this->options[$name])) {
                    $return = $this->options[$name];
                }
                break;
        }
        return $return;
    }

    public function __set($name, $value)
    {
        switch($name) {
            case "filterName":
                $this->filters->addFilters($value);
                break;
            case "path": $this->setPath($value);
                break;
            case "searchTerm":
                if(!is_null($value) && !is_string($value)) {
                    throw new Exception("Search term needs to be string");
                }
                $this->_searchTerm = $value;
                break;
            case "directoryException":
                if(is_string($value)) {
                    $this->_directoryException[] = $value;
                } elseif(is_array($value)) {
                    $this->_directoryException = array_merge($this->_directoryException, $value);
                }
                break;
            case 'isBasicSearch':
                $this->_isBasicSearch = $value ? true : false;
                break;
            default:
                break;
        }
    }

    public function __call($name, $args)
    {
        if(strpos($name, "set") === 0) {
            $name = substr($name, 3);
            $name[0] = strtolower($name[0]);
            $this->$name = $args[0];
        }
        return $this;
    }

    public function setOptions($options)
    {
        foreach($options as $key => $option) {
            if(isset($this->options[$key])) {
                $this->options[$key] = $option;
            }
        }
        return $this;
    }

    public function setPath($path)
    {
        $this->_path = realpath($path);
        $this->_iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->_path), \RecursiveIteratorIterator::SELF_FIRST);
        return $this;
    }

    public function __invoke($searchTerm = null, $options = array())
    {
        return $this->search($searchTerm, $options);
    }

    public function basicSearch($searchTerm = null, $options = array())
    {
        $this->isBasicSearch = true;
        return $this->search($searchTerm, $options);
    }

    public function search($searchTerm = null, $options = array())
    {
        if(!$this->filters instanceof Search\Filters) {
            throw new \Exception("What have you done with the filters");
        }
        if(is_array($options)) {
            $this->setOptions($options);
        }
        if(is_string($options) && $options != "") {
            if($this->filters->filterExists($options)) {
                $this->filters->addFilter($options);
            } else {
                $this->setPath($options);
            }
        }
        if($this->iterator == null) {
            throw new Exception("Please set a path before starting a search!");
        }


        $this->searchTerm = $searchTerm;
        if(!is_null($searchTerm) && !$this->isBasicSearch && !$this->isValidPattern($this->_searchTerm)) {
            return self::$_errorMessage;
        }

        // 5 minutes time limit
        set_time_limit(600);

        // I had to put it in a variable because it tried to call __call instead of the __invoke of the filters
        $filters = $this->filters;
        $results = new Search\Results($this, self::OPERATION_SEARCH);
        $finfo = new \finfo(FILEINFO_MIME_ENCODING);
        foreach ($this->iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if($this->isInException($file->getPathName())) {
                continue;
            }
            if($filters($file->getPathName())) {
                $content = file_get_contents($file->getPathName());
                if(is_null($searchTerm)) {
                    $results->addResult(array("filename" => $file->getPathName(), "encoding" => $finfo->file($file->getPathName()), "finds" => array()));
                } elseif($this->isBasicSearch) {
                    $start = strpos($content, $this->_searchTerm);
                    if($start !== false) {
                        $context = substr($content, max(0, $start - 40), strlen($this->_searchTerm) + 80);
                        if(strlen(htmlentities($context, null, "UTF-8")) == 0) {
                            $context = substr($content, max(0, $start - 40), strlen($this->_searchTerm) + 81);
                        }
                        if(strlen(htmlentities($context, null, "UTF-8")) == 0) {
                            $context = substr($content, max(0, $start - 39), strlen($this->_searchTerm) + 81);
                        }
                        $finds[] = array("word" => $this->_searchTerm, "context" => $context);
                        $results->addResult(array("filename" => $file->getPathName(), "encoding" => $finfo->file($file->getPathName()), "finds" => $finds));
                    }
                } elseif(preg_match_all($this->_searchTerm, $content, $out, PREG_OFFSET_CAPTURE)) {
                    $out = $out[0];
                    $finds = array();
                    foreach($out as $cr) {
                        $context = substr($content, max(0, $cr[1] - 40), strlen($cr[0]) + 80);
                        if(strlen(htmlentities($context, null, "UTF-8")) == 0) {
                            $context = substr($content, max(0, $cr[1] - 40), strlen($cr[0]) + 81);
                        }
                        if(strlen(htmlentities($context, null, "UTF-8")) == 0) {
                            $context = substr($content, max(0, $cr[1] - 39), strlen($cr[0]) + 81);
                        }
                        $finds[] = array("word" => $cr[0], "context" => $context);
                    }
                    $results->addResult(array("filename" => $file->getPathName(), "encoding" => $finfo->file($file->getPathName()), "finds" => $finds));
                }
            }
        }
        return $results;
    }

    public function replace($pattern, $replace, $options = false)
    {
        $results = $this->search($pattern, $options);
        $results->operation = self::OPERATION_REPLACE;
        $results->replaceTerm = $replace;

        foreach($results->results as $key => $found) {
            foreach($found["finds"] as $kFind => $find) {
                if($newContent = preg_replace($this->_searchTerm, $replace, $find["context"])) {
                    $results->results[$key]["finds"][$kFind]["originalContent"] = $find["context"];
                    $results->results[$key]["finds"][$kFind]["newContent"] = $newContent;
                }
            }
            if($options === true) {
                $content = file_get_contents($found["filename"]);
                $newContent = preg_replace($this->_searchTerm, $replace, $content);
                file_put_contents($found["filename"], $newContent);
            }
        }
        return $results;
    }

    public function searchUtf8()
    {
        $this->filters->addFilter("Only non-UTF8");
        return $this->search();
    }

    public function isInException($file)
    {
        foreach($this->directoryException as $exception) {
            if(strpos(dirname($file), $exception) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
    * check if the current search pattern is valid or not
    *
    * @return bool
    */
    public function isValidPattern()
    {
        set_error_handler(array($this, "_patternErrorHandler"));
        self::$_validPattern = true;
        preg_match($this->_searchTerm, "test");
        restore_error_handler();
        return self::$_validPattern;
    }
    protected function _patternErrorHandler($code, $message)
    {
        self::$_validPattern = false;
        self::$_errorMessage = $message;
        return true;
    }
}
