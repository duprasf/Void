<?php
namespace Void;

class FileToText {
    protected $filename;
    protected $removeFileOnDestruct = array();
    protected $originalFilename;
    protected $decodedtext = '';

    protected $multibyte = 4; // Use setUnicode(TRUE|FALSE)
    protected $convertquotes = ENT_QUOTES; // ENT_COMPAT (double-quotes), ENT_QUOTES (Both), ENT_NOQUOTES (None)
    protected $showprogress = false; // TRUE if you have problems with time-out

    public function __destruct()
    {
        foreach($this->removeFileOnDestruct as $file) {
            if(file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
    * Convert a PDF, DOC, DOT, DOCX or DOTX to plain text
    *
    * @param string $filePath local or remote path of a file
    * @return string clean text found in document
    */
    public function __invoke($filePath)
    {
        $this->filename = '';
        return $this->convertToText($filePath);
    }

    /**
    * Get the clean text output from the previous conversion
    *
    * @return string
    */
    public function getOutput()
    {
        if(!\Void\StringFunction::isUTF8($this->decodedtext)) {
            $this->decodedtext = utf8_encode($this->decodedtext);
        }
        return $this->decodedtext;
    }

    /**
    * Convert a PDF, DOC, DOT, DOCX or DOTX to plain text
    *
    * @param string $filePath local or remote path of a file
    * @return string clean text found in document
    */
    public function convertToText($filePath = null)
    {
        $this->originalFilename = '';
        $this->decodedtext = '';

        if($filePath) {
            $this->filename = $filePath;
        }
        if($this->filename == null) {
            return $this->decodedtext = "File was not set";
        }

        $ext = pathinfo($this->filename, PATHINFO_EXTENSION);

        $host = parse_url($this->filename,PHP_URL_HOST);
        if($host !== null) {
            $headers = get_headers($this->filename, true);
            if(strpos($headers[0], ' 404 Not Found') !== false || (strpos($headers[0], ' 302 Found') !== false && strpos($headers['Location'], ' 404 Not Found') !== false)) {
                throw new \Exception("Could not reach the file");
            }
            $this->originalFilename = $this->filename;
            $this->filename = tempnam(sys_get_temp_dir(), 'php-converter-');
            $this->removeFileOnDestruct[] = $this->filename;
            $stream = stream_context_create(array("http" => array("user_agent", "INFRAnet crawler 1.0")));
            if(!copy($this->originalFilename,$this->filename, $stream)) {
                throw new \Exception('File could not be copied locally');
            }
        }


        switch(strtolower($ext)) {
            case "doc":
            case "dot":
                $this->decodedtext = $this->readDoc();
                break;
            case "docx":
            case "dotx":
                $this->decodedtext = $this->readDocx();
                break;
            case 'xlsx':
            case 'xltx':
                $this->decodedtext = $this->readXlsx();
                break;
            case 'pptx':
            case 'potx':
                $this->decodedtext = $this->readPptx();
                break;
            case "pdf":
                $this->decodedtext = $this->readPdf();
                break;
            default:
                $this->decodedtext = "Invalid File Type";
                break;
        }
        return $this->getOutput();
    }

    /**
    * This approach uses detection of NUL (chr(00)) and end line (chr(13)) to decide where the text is:
    * - divide the file contents up by chr(13)
    * - reject any slices containing a NUL
    * - stitch the rest together again
    * - clean up with a regular expression
    *
    * @return string
    */
    protected function readDoc()
    {
        $fileHandle = fopen($this->filename, "r");
        $line = fread($fileHandle, filesize($this->filename));
        $lines = explode(chr(0x0D),$line);
        $outtext = "";
        foreach($lines as $thisline)
        {
            $pos = strpos($thisline, chr(0x00));
            $thisline = substr($thisline, $pos, strlen($thisline));
            if ($pos === FALSE && strlen($thisline)) {
                $outtext .= $thisline." ";
            }
        }
        $outtext = preg_replace("/[^a-zA-Z0-9\s\,\.\-\n\r\t@\/\_\(\)]/","",$outtext);
        return $outtext;
    }

    protected function readDocx()
    {

        $striped_content = '';
        $content = '';

        $zip = zip_open($this->filename);

        if (!$zip || is_numeric($zip)) return false;

        while ($zip_entry = zip_read($zip)) {

            if (zip_entry_open($zip, $zip_entry) == FALSE) continue;

            if (zip_entry_name($zip_entry) != "word/document.xml") continue;

            $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));

            zip_entry_close($zip_entry);
        }

        zip_close($zip);

        $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
        $content = str_replace('</w:r></w:p>', "\r\n", $content);
        $striped_content = strip_tags($content);

        return $striped_content;
    }

    protected function readXlsx()
    {
        $xml_filename = "xl/sharedStrings.xml"; //content file name
        $zip = new \ZipArchive();
        $output = "";
        if(true === $zip->open($this->filename)){
            $dom = new \DOMDocument();
            if(($index = $zip->locateName($xml_filename)) !== false){
                $xml = $zip->getFromIndex($index);
                $dom->loadXML($xml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $output = strip_tags($dom->saveXML());
            }
            $zip->close();
        }
        return $output;
    }

    protected function readPptx()
    {
        $zip = new \ZipArchive();
        $output = "";
        if(true === $zip->open($this->filename)){
            $slide_number = 1; //loop through slide files
            $dom = new \DOMDocument();
            while(($index = $zip->locateName("ppt/slides/slide".$slide_number.".xml")) !== false){
                $xml = $zip->getFromIndex($index);
                $dom->loadXML($xml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $output .= strip_tags($dom->saveXML());
                $slide_number++;
            }
            $zip->close();
        }
        return $output;
    }

    protected function readPdf()
    {
        include_once(ROOT_APP.'/vendor/Smalot/tcpdf/tcpdf_parser.php');
        // this Smalot parser works better than the "built-in" one
        // Parse pdf file and build necessary objects.
        $parser = new \Smalot\PdfParser\Parser();
        try {
            $pdf = @$parser->parseFile($this->filename);
            $output = $pdf->getText();
            if($output == '') {
                $output = $this->decodePDF();
            }
        }
        catch(\Exception $e) {
            $output = '';
        }

        return $output;
    }

    protected function setUnicode($input)
    {
        if($input == true) $this->multibyte = 4;
        else $this->multibyte = 2;
    }

    protected function decodePDF()
    {
        $infile = file_get_contents($this->filename, FILE_BINARY);
        if (empty($infile))
            return "";

        $transformations = array();
        $texts = array();

        $objects = array();
        preg_match_all("#obj[\n|\r](.*)endobj[\n|\r]#ismU", $infile . "endobj\r", $out);
        if(isset($out[1]) && is_array($out[1])) {
            $objects = $out[1];
        }

        for ($i = 0; $i < count($objects); $i++) {
            $currentObject = $objects[$i];

            if($this->showprogress) {
                flush(); ob_flush();
            }

            if (preg_match("#stream[\n|\r](.*)endstream[\n|\r]#ismU", $currentObject . "endstream\r", $stream )) {
                $stream = ltrim($stream[1]);
                $options = $this->getObjectOptions($currentObject);

                if (!(empty($options["Length1"]) && empty($options["Type"]) && empty($options["Subtype"])) )
                    // if ( $options["Image"] && $options["Subtype"] )
                    // if (!(empty($options["Length1"]) &&  empty($options["Subtype"])) )
                    continue;

                unset($options["Length"]);

                $data = $this->getDecodedStream($stream, $options);

                if (strlen($data)) {
                    if (preg_match_all("#BT[\n|\r](.*)ET[\n|\r]#ismU", $data . "ET\r", $textContainers)) {
                        if(isset($textContainers[1])) {
                            $this->getDirtyTexts($texts, $textContainers[1]);
                        }
                    } else
                        $this->getCharTransformations($transformations, $data);
                }
            }
        }

        return $this->getTextUsingTransformations($texts, $transformations);
    }

    protected function decodeAsciiHex($input)
    {
        $output = "";

        $isOdd = true;
        $isComment = false;

        for($i = 0, $codeHigh = -1; $i < strlen($input) && $input[$i] != '>'; $i++) {
            $c = $input[$i];

            if($isComment) {
                if ($c == '\r' || $c == '\n')
                $isComment = false;
                continue;
            }

            switch($c) {
                case '\0': case '\t': case '\r': case '\f': case '\n': case ' ': break;
                case '%':
                    $isComment = true;
                    break;

                default:
                    $code = hexdec($c);
                    if($code === 0 && $c != '0')
                        return "";

                    if($isOdd)
                        $codeHigh = $code;
                    else
                        $output .= chr($codeHigh * 16 + $code);

                    $isOdd = !$isOdd;
                    break;
            }
        }

        if($input[$i] != '>')
            return "";

        if($isOdd)
            $output .= chr($codeHigh * 16);

        return $output;
    }

    protected function decodeAscii85($input)
    {
        $output = "";

        $isComment = false;
        $ords = array();

        for($i = 0, $state = 0; $i < strlen($input) && $input[$i] != '~'; $i++) {
            $c = $input[$i];

            if($isComment) {
                if ($c == '\r' || $c == '\n')
                    $isComment = false;
                continue;
            }

            if ($c == '\0' || $c == '\t' || $c == '\r' || $c == '\f' || $c == '\n' || $c == ' ')
                continue;
            if ($c == '%') {
                $isComment = true;
                continue;
            }
            if ($c == 'z' && $state === 0) {
                $output .= str_repeat(chr(0), 4);
                continue;
            }
            if ($c < '!' || $c > 'u')
                return "";

            $code = ord($input[$i]) & 0xff;
            $ords[$state++] = $code - ord('!');

            if ($state == 5) {
                $state = 0;
                for ($sum = 0, $j = 0; $j < 5; $j++)
                    $sum = $sum * 85 + $ords[$j];
                for ($j = 3; $j >= 0; $j--)
                    $output .= chr($sum >> ($j * 8));
            }
        }
        if ($state === 1)
            return "";
        elseif ($state > 1) {
            for ($i = 0, $sum = 0; $i < $state; $i++)
                $sum += ($ords[$i] + ($i == $state - 1)) * pow(85, 4 - $i);
            for ($i = 0; $i < $state - 1; $i++) {
                try {
                    if(false == ($o = chr($sum >> ((3 - $i) * 8)))) {
                        throw new Exception('Error');
                    }
                    $output .= $o;
                } catch (Exception $e) { /*Dont do anything*/ }
            }
        }

        return $output;
    }

    protected function decodeFlate($data)
    {
        if(!function_exists('gzuncompress')) {
            throw new \Exception('Missing the gzuncompress function');
        }
        return @gzuncompress($data);
    }

    protected function getObjectOptions($object)
    {
        $options = array();

        if (preg_match("#<<(.*)>>#ismU", $object, $out)) {
            if(isset($out[1])) {
                $options = explode("/", $out[1]);
            }
            array_shift($options);

            $o = array();
            for ($j = 0; $j < count($options); $j++) {
                $options[$j] = preg_replace("#\s+#", " ", trim($options[$j]));
                if (strpos($options[$j], " ") !== false) {
                    $parts = explode(" ", $options[$j]);
                    $o[$parts[0]] = $parts[1];
                } else
                    $o[$options[$j]] = true;
            }
            $options = $o;
            unset($o);
        }

        return $options;
    }

    protected function getDecodedStream($stream, $options)
    {
        $data = "";
        if (empty($options["Filter"]))
            $data = $stream;
        else {
            $length = !empty($options["Length"]) ? $options["Length"] : strlen($stream);
            $_stream = substr($stream, 0, $length);

            foreach ($options as $key => $value) {
                if ($key == "ASCIIHexDecode")
                    $_stream = $this->decodeAsciiHex($_stream);
                elseif ($key == "ASCII85Decode")
                    $_stream = $this->decodeAscii85($_stream);
                elseif ($key == "FlateDecode")
                    $_stream = $this->decodeFlate($_stream);
                elseif ($key == "Crypt") { // TO DO
                }
            }
            $data = $_stream;
        }
        return $data;
    }

    protected function getDirtyTexts(&$texts, $textContainers)
    {
        for ($j = 0; $j < count($textContainers); $j++) {
            if (preg_match_all("#\[(.*)\]\s*TJ[\n|\r]#ismU", $textContainers[$j], $parts)) {
                $parts = isset($parts[1]) ? $parts[1] : array();
            }
            elseif (preg_match_all("#T[d|w|m|f]\s*(\(.*\))\s*Tj[\n|\r]#ismU", $textContainers[$j], $parts)) {
                $parts = isset($parts[1]) ? $parts[1] : array();
            }
            elseif (preg_match_all("#T[d|w|m|f]\s*(\[.*\])\s*Tj[\n|\r]#ismU", $textContainers[$j], $parts)) {
                $parts = isset($parts[1]) ? $parts[1] : array();
            }
            if(is_array($parts)) {
                $texts[] = ' '.$this->getArrayAsString($parts);
            }
            else if(is_string($parts)) {
                $texts[] = ' '.$parts;
            }
        }
    }

    public function getArrayAsString($a, $glue = '')
    {
        $that = $this;
        return implode($glue, array_map(function($v) use($that){ return is_array($v) ? $that->getArrayAsString($v) : $v; }, $a));
    }

    protected function getCharTransformations(&$transformations, $stream)
    {
        preg_match_all("#([0-9]+)\s+beginbfchar(.*)endbfchar#ismU", $stream, $chars, PREG_SET_ORDER);
        preg_match_all("#([0-9]+)\s+beginbfrange(.*)endbfrange#ismU", $stream, $ranges, PREG_SET_ORDER);

        for ($j = 0; $j < count($chars); $j++) {
            $count = $chars[$j][1];
            $current = explode("\n", trim($chars[$j][2]));
            for ($k = 0; $k < $count && $k < count($current); $k++) {
                if (preg_match("#<([0-9a-f]{2,4})>\s+<([0-9a-f]{4,512})>#is", trim($current[$k]), $map))
                    $transformations[str_pad($map[1], 4, "0")] = $map[2];
            }
        }
        for ($j = 0; $j < count($ranges); $j++) {
            $count = $ranges[$j][1];
            $current = explode("\n", trim($ranges[$j][2]));
            for ($k = 0; $k < $count && $k < count($current); $k++) {
                if (preg_match("#<([0-9a-f]{4})>\s+<([0-9a-f]{4})>\s+<([0-9a-f]{4})>#is", trim($current[$k]), $map)) {
                    $from = hexdec($map[1]);
                    $to = hexdec($map[2]);
                    $_from = hexdec($map[3]);

                    for ($m = $from, $n = 0; $m <= $to; $m++, $n++)
                        $transformations[sprintf("%04X", $m)] = sprintf("%04X", $_from + $n);
                } elseif (preg_match("#<([0-9a-f]{4})>\s+<([0-9a-f]{4})>\s+\[(.*)\]#ismU", trim($current[$k]), $map)) {
                    $from = hexdec($map[1]);
                    $to = hexdec($map[2]);
                    $parts = preg_split("#\s+#", trim($map[3]));

                    for ($m = $from, $n = 0; $m <= $to && $n < count($parts); $m++, $n++)
                        $transformations[sprintf("%04X", $m)] = sprintf("%04X", hexdec($parts[$n]));
                }
            }
        }
    }
    protected function getTextUsingTransformations($texts, $transformations)
    {
        $document = "";
        for ($i = 0; $i < count($texts); $i++) {
            $isHex = false;
            $isPlain = false;

            $hex = "";
            $plain = "";
            for ($j = 0; $j < strlen($texts[$i]); $j++) {
                $c = $texts[$i][$j];
                switch($c) {
                    case "<":
                        $hex = "";
                        $isHex = true;
                        $isPlain = false;
                        break;
                    case ">":
                        $hexs = str_split($hex, $this->multibyte); // 2 or 4 (UTF8 or ISO)
                        for ($k = 0; $k < count($hexs); $k++) {

                            $chex = str_pad($hexs[$k], 4, "0"); // Add tailing zero
                            if (isset($transformations[$chex]))
                                $chex = $transformations[$chex];
                            $document .= html_entity_decode("&#x".$chex.";");
                        }
                        $isHex = false;
                        break;
                    case "(":
                        $plain = "";
                        $isPlain = true;
                        $isHex = false;
                        break;
                    case ")":
                        $document .= $plain;
                        $isPlain = false;
                        break;
                    case "\\":
                        $c2 = $texts[$i][$j + 1];
                        if (in_array($c2, array("\\", "(", ")"))) $plain .= $c2;
                        elseif ($c2 == "n") $plain .= '\n';
                        elseif ($c2 == "r") $plain .= '\r';
                        elseif ($c2 == "t") $plain .= '\t';
                        elseif ($c2 == "b") $plain .= '\b';
                        elseif ($c2 == "f") $plain .= '\f';
                        elseif ($c2 >= '0' && $c2 <= '9') {
                            $oct = preg_replace("#[^0-9]#", "", substr($texts[$i], $j + 1, 3));
                            $j += strlen($oct) - 1;
                            $plain .= html_entity_decode("&#".octdec($oct).";", $this->convertquotes);
                        }
                        $j++;
                        break;

                    default:
                        if ($isHex)
                            $hex .= $c;
                        elseif ($isPlain)
                            $plain .= $c;
                        break;
                }
            }
            $document .= "\n";
        }
        return $document;
    }
}
