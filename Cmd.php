<?php
namespace Void;

class Cmd {
    static public function run_in_background($command, $logFile=null, $priority = 0)
    {
        $priority = $priority > 0 ? sprinf("nice -n %d ",$priority) : '';
        if(!is_null($logFile)) {
            $logFile = realpath($logFile);
            if(strpos($logFile, "/www/") !== 0 && strpos($logFile, "/home/") !== 0 && strpos($logFile, "/htdocs/") !== 0 && strpos($logFile, "/sites/") !== 0) {
                $logFile = null;
            }
        }
        $pid = shell_exec(escapeshellcmd("nohup ".$priority.$command." ".(!is_null($logFile)? ">".$logFile:'')." 2>&1 & echo $!"));
        //http://911-need-code-help.blogspot.ca/2010/04/running-background-process-in-php.html
        return $pid;
    }

    static function is_process_running($pid)
    {
        try {
            exec(escapeshellcmd(sprintf("ps %d", $pid)), $processState);
            return(count($processState) >= 2);
        }
        catch(\Exception $e) {
            return false;
        }
    }

}
