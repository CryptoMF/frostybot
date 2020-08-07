<?php

    set_time_limit(0);

    $time_start = microtime(true);

    class outputsObject {

        private $results = [];
        private $messages = [];

        public function addResult($code, $message, $data) {
            $this->results[] = new resultObject($code,$message,$data);
        }

        public function addMessage($code, $type, $message, $data) {
            $this->messages[] = new messageObject($code,$type,$message,$data);
        }

        public function getResults() {
            if (count($this->results) == 1) {
                return $this->results[0]->get();
            } else {
                $output = [];
                foreach($this->results as $result) {
                    $output[] = $result->get();
                }
                return $output;
            }
        }

        public function outputResults() {
            global $time_start;
            $time_end = microtime(true);
            $duration = round($time_end - $time_start,5);
            logger::debug('Script execution time: '.$duration.' seconds');
            $output = (object) [
                'results' => $this->getResults(),
                'messages' => $this->getMessages(),
            ];
            echo json_encode($output, JSON_PRETTY_PRINT).PHP_EOL;
            die;
        }

        public function getMessages() {
            $output = [];
            foreach($this->messages as $message) {
                $output[] = $message->get();
            }
            return $output;
        }

    }

    class resultObject {

        public $code;
        public $type;
        public $message;
        public $data;

        public function __construct($code, $message, $data = []) {
            $this->code = $code;
            if (in_array($data,['error'])) {
                $this->type = $data;
            } else {
                $this->type = gettype($data);
                if (is_null($this->type)) {
                    $this->type='none';
                }
                $this->data = $data;
            }
            $this->message = $message;
        }

        public function get() {
            $result = [];
            $result['code'] = $this->code;
            $result['message'] = $this->message;
            if (((is_null($this->type)) || ($this->type == "NULL"))  && (strtolower($this->message) == 'success')) {
                $result['type'] = "SUCCESS";
            } else {
                $result['type'] = strtoupper($this->type);
            }
            if (!is_null($this->data)) {
                if(is_object($this->data)) {
                    $result['class'] = get_class($this->data);
                }
                $result['data'] = $this->data;
            }
            return (object) $result;
        }

    }

    class messageObject {

        public $code;
        public $type;
        public $message;
        public $data;

        public function __construct($code, $type, $message, $data = []) {
            $this->code = $code;
            $this->type = strtoupper($type);
            $this->message = $message;
            $this->data = $data;
        }

        public function get() {
            $result = [];
            $result['code'] = $this->code;
            $result['type'] = $this->type;
            $result['message'] = $this->message;
            if ($this->data != []) {
                $result['data'] = $this->data;
            }
            return (object) $result;
        }

    }

    $__outputs__ = new outputsObject();

    function outputResult($code,$message,$data = []) {
        global $__outputs__;
        $__outputs__->addResult($code,$message,$data);
        $__outputs__->outputResults();
    }

    function testResult($code,$message,$data) {
        $output = new outputsObject();
        $output->addResult($code,$message,$data);
        return $output->getResults();
    }

    function message($code,$type,$message,$data = []) {
        global $__outputs__;
        //$logtype = strtolower($type);
        //logger::$logtype($message);
        $__outputs__->addMessage($code,$type,$message,$data);
    }

    function errorHandler($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        switch ($errno) {
            case E_USER_ERROR:
                $type = 'ERROR';
                break;    
            case E_USER_WARNING:
                $type = 'WARNING';
                break;
            case E_USER_NOTICE:
                $type = 'NOTICE';
                break;
            case E_ERROR:
                $type = 'ERROR';
                break;    
            case E_WARNING:
                $type = 'WARNING';
                break;
            case E_NOTICE:
                $type = 'NOTICE';
                break;
            default:
                $type = 'NOTICE';
                break;
        }
        $logtype = strtolower($type);
        logger::$logtype($errstr.' ('.basename($errfile).':'.$errline.')', $errstr);
        return true;        
    }

    function exceptionHandler($e) {
        $type = strtolower(get_class($e));
        if (!in_array($type,['error','warning','notice','info','debug'])) {
            $message = strtoupper($type).': '.$e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
            $type='error';    
        } else {
            $message = $e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
        }
        logger::$type($message, $e->getMessage());
        return true;
    }

    set_error_handler("errorHandler");
    set_exception_handler( "exceptionHandler" );
    ini_set( "display_errors", "off" );
    error_reporting(E_ALL);

    class logger {

        static private function message($type, $message) {
            $message = date('Y-m-d H:i:s').' | '.str_pad(strtoupper($type),7," ",STR_PAD_RIGHT).' | '.$message.PHP_EOL;
            file_put_contents(logfile, $message, FILE_APPEND);
        }

        static public function notice($message) {
            message(0,'NOTICE',$message);
            self::message('notice', $message);
        }

        static public function info($message) {
            message(0,'INFO',$message);
            self::message('info', $message);
        }

        static public function debug($message) {
            if (debug === true) {
                message(1000,'DEBUG',$message);
                self::message('debug', $message);
            }
        }

        static public function warning($message, $notifymsg = null) {
            message(800,'WARNING',$message);
            notifications::send('custom', ['type'=>'Warning', 'message'=>(is_null($notifymsg) ? $message : $notifymsg)]);
            self::message('warning', $message, $notifymsg);
        }

        static public function error($message, $notifymsg = null) {
            //message(900,'ERROR',$message);
            notifications::send('custom', ['type'=>'Error', 'message'=>(is_null($notifymsg) ? $message : $notifymsg)]);
            self::message('error', $message, $notifymsg);
            outputResult(900,$message,'error');
        }

        static public function get($params) {
            $clear = (isset($params['clear']) ? $params['clear'] : false);
            if ($clear == true) {
                file_put_contents(logfile,'');
                logger::notice('Log file cleared');
            }
            $lines = (isset($params['lines']) ? $params['lines'] : 10);
            $filter = (isset($params['filter']) ? $params['filter'] : null);
            $log = explode("\n",file_get_contents(logfile));
            $output = [];
            foreach($log as $key => $entry) {
                if (trim($entry) == "") {
                    unset($log[$key]);
                } else {
                    $logentry = explode(" | ", $entry, 3);
                    if ((is_array($logentry)) && (count($logentry) == 3)) {
                        list($date,$type,$message) = $logentry;
                        if (!is_null($filter)) {
                            if ((strpos(strtolower($message),strtolower($filter)) !== false) || (strpos(strtolower($type),strtolower($filter)) !== false)) {
                                $output[] = (object) [
                                    'datetime' => $date,
                                    'type' => trim($type),
                                    'message' => trim(str_replace("\r","",$message)),
                                ];        
                            }
                        } else {
                            $output[] = (object) [
                                'datetime' => $date,
                                'type' => trim($type),
                                'message' => trim(str_replace("\r","",$message)),
                            ];    
                        }
                    }
                }
            }
            if (!is_null($lines)) {
                $output = array_slice($output,(0-$lines));
            } 
            return $output;
        }


    }



?>