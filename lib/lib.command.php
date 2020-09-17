<?php

    // Command Handler

    class command {

        private $params = [];
        private $exchange;
        private $commandStr;
        private $type;
        private $format;

        // Override constructor (for internal used only)
        public function __construct($command = null) {
            $this->commandStr = is_array($command) ? json_encode($command) : $command;
        }

        // Execute multiple commands if provided
        private function execMultiple($commandstr) {
            if (is_json($commandstr)) {
                $commands = json_decode($commandstr, false);
            } else {
                $commands = explode("|", str_replace("\n","|",str_replace("\r","",trim($commandstr))));
            }
            if ((is_array($commands)) && (count($commands) > 1)) {
                $overallResult = true;
                $allResults = [];
                foreach ($commands as $command) {
                    $commandObj = new command($command);
                    $result = $commandObj->execute();
                    if ($result !== false) {
                        $allResults[] = testResult(0,'SUCCESS',$result);
                    } else {
                        $overallResult = false;
                        $allResults[] = testResult(999,'ERROR',false);
                    }
                }
                if ($overallResult === true) {
                    outputResult(0,'SUCCESS',$allResults);
                } else {
                    outputResult(999,'ERROR',$allResults);
                }
                die;
            }
        }

        // Find out if we're using CLI or URL, and pass it to the correct parsing method
        private function parseParams() {
            if (!is_null($this->commandStr)) {
                $this->parseInternal();                     // This is an internal command (cron/unit test etc)
            } else {
                $execname = isset($_SERVER['argv'][0]) ? basename($_SERVER['argv'][0]) : '';
                if ($execname == 'frostybot') {
                    $this->parseCLI();                      // This is a CLI command
                } else {
                    $this->parseURL();                      // This is an HTTP POST/GET request
                }
            }
        }

        // Parse parameter array
        private function parseArray($arr) {
            $commandStr = (isset($arr['command']) ? $arr['command'] : '');
            if(strpos($commandStr,':') === false) {
                $stub = "__frostybot__";
                $command = $commandStr;
            } else {
                list($stub,$command) = explode(":", $commandStr);
            }
            $this->params['stub'] = trim(strtolower($stub));
            $this->params['command'] = trim(strtolower($command));
            unset($arr['command']);
            $debugstr = ($stub == "__frostybot__" ? $command : $stub.':'.$command);
            foreach($arr as $key => $value) {
                $this->params[$key] = trim(str_replace(['“','”'],'',$value));
                $debugstr .= ' '.$key.'='.$value;
            }
            logger::debug('Parsing complete: '. $this->type.' request received in '.$this->format.' format');
            logger::debug('Command: '.$debugstr);
        }

        // Parse inline parameters (Example: ftx:close symbol=BTC-PERP)
        private function parseInline($arg) {
            $this->format = 'inline';
            $params = explode(" ", $arg);
            $arr = ['command' => $params[0]];
            array_shift($params);
            foreach($params as $param) {
                if (strpos($param, '=') !== false) {
                    list($key, $value) = explode("=", $param);
                    $arr[$key] = $value;
                }
            }
            $this->parseArray($arr);
        }

        // Parse JSON parameters (Exmaple: { 'command': 'ftx:close', 'symbol': 'BTC-PERP' })
        private function parseJson($arg) {
            $this->format = 'JSON';
            $arr = (is_object($arg) ? (array) $arg : json_decode($arg, true));
            if ((count($arr) == 1) && (isset($arr[0]))) {
                $arr = $arr[0];
            }
            $this->parseArray($arr);
        }

        // Check if text is JSON or Inline format and parse accoringly
        private function parseJsonOrInline($arg) {
            $GLOBALS['cmd'] = $arg;
            if (is_json($arg)) {
                $this->parseJson($arg);                     // Arguments are in JSON format
            } else {
                $this->parseInline($arg);                   // Arguments are in inline format
            }
        }

        // Parse URL GET/POST paramters (from TradingView)
        private function parseURL() {
            whitelist::validate($_SERVER['REMOTE_ADDR']);
            if (isset($_GET['command'])) {                  // Parse HTTP GET Parameters
                $this->type = 'GET';
                $this->format = 'URL';
                $this->parseArray($_GET);
            }
            $postdata = file_get_contents('php://input');
            if ($postdata !== "") {                         // Parse HTTP POST Parameters
                $this->type = 'POST';
                $this->execMultiple($postdata);             // Check if this is a multi-command instruction or a single instruction
                $this->parseJsonOrInline($postdata);        // Check if post data is JSON or Inline format and parse accordingly
            }
        }

        // Parse CLI parameters
        private function parseCLI() {
            $this->type = "CLI";                            // This is a CLI request
            $args = $_SERVER['argv'];
            if (isset($args[1])) {
                array_shift($args);
                $commandstr = trim(implode(" ",$args));
                $this->execMultiple($commandstr);           // Check if this is a multi-command instruction or a single instruction
                $this->parseJsonOrInline($commandstr);      // Check if CLI arguments are JSON or Inline format and parse accordingly
            } else {
                die(PHP_EOL.'USAGE:   '.$args[0].' <stub>:<command> param=val param=val'.PHP_EOL.PHP_EOL.'EXAMPLE: '.$args[0].' deribit:POSITION symbol=BTC-PERPETUAL'.PHP_EOL.PHP_EOL);
            }
        }

        // Parse Internal parameters
        private function parseInternal() {
            $this->type = "Internal";                       // This is an internal request (executed by Frostybot)
            $this->parseJsonOrInline($this->commandStr);    // Check if internal arguments are JSON or Inline format and parse accordingly
        }

        // Execute the command, and ensure that the necessary parameters have been given
        public function execute($output = false) {
            $this->parseParams();                                                                   // Parse parameters
            $GLOBALS['stub'] = str_replace('__frostybot__:','',$this->params['stub']);
            if (requiredParams($this->params,['stub','command']) !== false) {
                $stub = strtolower($this->params['stub']);
                $command = strtolower($this->params['command']);
                if ($command == 'config') {                                                         // Don't load config if we are busy configuring it
                    $this->params['stub_update'] = $stub;
                    $stub = '__frostybot__';
                }
                $accounts = config::get();                                                          // Get account config for supplied stub
                if (($stub == '__frostybot__') || (in_array($command,['config','symbolmap'])) || (array_key_exists($stub, $accounts))) {    // This is an exchange command, load the exchange libraries
                    if (($stub !== '__frostybot__') && (!in_array($command,['config','symbolmap']))) {
                        $config = $accounts[$stub];                                                 // Load stub configuration
                        $symbolmap = (isset($config['symbolmap']) ? $config['symbolmap'] : []);     // If no symbol supplied load the default mapping, or a custom mapping if configured
                        $defaultsymbol = (isset($symbolmap['default']) ? $symbolmap['default'] : null);
                        if (isset($this->params['symbol'])) {
                            $symbol = $this->params['symbol'];
                            if (array_key_exists($symbol, $symbolmap)) {
                                $this->params['symbol'] = $symbolmap[$symbol];
                            }
                        } else {
                            $this->params['symbol'] = $defaultsymbol;
                        }
                        $GLOBALS['symbol'] = $this->params['symbol'];
                        $GLOBALS['command'] = $command;
                        $this->exchange = new exchange($config['exchange'],$config['parameters']);  // Initialize the exchange (CCXT, normalizers etc)
                    }
                    switch (strtoupper($command)) {
                        case 'CONFIG'       :   $result = config::manage($this->params);
                                                break;
                        case 'INIT'         :   $db = new db();
                                                $result = $db->initialize();
                                                break;
                        case 'CRON'         :   $result = cron::run($this->params);
                                                break;
                        case 'LOG'          :   $result = logger::get($this->params);
                                                break;
                        case 'FLUSHCACHE'   :   $result = cache::flush(0, (isset($this->params['permanent']) ? (bool) $this->params['permanent'] : false));
                                                break;
                        case 'WHITELIST'    :   $result = whitelist::manage($this->params);
                                                break;
                        case 'SYMBOLMAP'    :   $result = symbolmap::manage(requiredParams($this->params,['exchange']));
                                                break;
                        case 'NOTIFICATIONS':   $result = notifications::manage($this->params);
                                                break;
                        case 'UNITTESTS'    :   $result = unitTests::runTests(requiredParams($this->params,['group']));
                                                break;
                        case 'CCXTINFO'     :   $result = $this->exchange->ccxtinfo($this->params);
                                                break;
                        case 'BALANCE'      :   $result = $this->exchange->fetch_balance();
                                                break;
                        case 'BALANCEUSD'   :   $result = $this->exchange->total_balance_usd();
                                                break;
                        case 'MARKET'       :   $result = $this->exchange->market(requiredParams($this->params,['symbol']));
                                                break;
                        case 'MARKETS'      :   $result = $this->exchange->markets(true, false);
                                                break;
                        case 'OHLCV'        :   $result = $this->exchange->ohlCv(requiredParams($this->params,['symbol']));
                                                break;
                        case 'POSITION'     :   $result = $this->exchange->position(requiredParams($this->params,['symbol']));
                                                break;
                        case 'POSITIONS'    :   $result = $this->exchange->positions();
                                                break;
                        case 'ORDER'        :   $result = $this->exchange->order(requiredParams($this->params,['id','symbol']));
                                                break;
                        case 'ORDERS'       :   $result = $this->exchange->orders(requiredParams($this->params,['symbol']));
                                                break;
                        case 'CANCEL'       :   $result = $this->exchange->cancel(requiredParams($this->params,['id','symbol']));
                                                break;
                        case 'CANCELALL'    :   $result = $this->exchange->cancel(requiredParams(array_merge($this->params,['id'=>'all']),['id','symbol']));
                                                break;
                        case 'LONG'         :   $result = $this->exchange->long(requiredParams($this->params,['symbol']));
                                                break;
                        case 'SHORT'        :   $result = $this->exchange->short(requiredParams($this->params,['symbol']));
                                                break;
                        case 'BUY'          :   $result = $this->exchange->buy(requiredParams($this->params,['symbol', 'size']));
                                                break;
                        case 'SELL'         :   $result = $this->exchange->sell(requiredParams($this->params,['symbol', 'size']));
                                                break;
                        case 'CLOSE'        :   $result = $this->exchange->close(requiredParams($this->params,['symbol']));
                                                break;
                        case 'STOPLOSS'     :   $result = $this->exchange->stoploss(requiredParams($this->params,['symbol','stoptrigger']));
                                                break;
                        case 'TAKEPROFIT'   :   $result = $this->exchange->takeprofit(requiredParams($this->params,['symbol','profittrigger']));
                                                break;
                        case 'TRAILSTOP'    :   $result = $this->exchange->trailstop(requiredParams($this->params,['symbol','trailstop']));
                                                break;
                        default             :   logger::error('Unknown command: '.$command);
                                                $result = false;
                                                break;
                    }

                } else {
                    $result = false;
                    logger::error('Account not configured: '.$stub);
                }
                if ($output === true) {
                    global $__outputs__;
                    if ($result !== false) {
                        outputResult(0,"SUCCESS",$result);
                    } else {
                        outputResult(999,"ERROR",false);
                    }
                    //$__outputs__->outputResults();
                } else {
                    return $result;
                }
            }
        }

    }


    $command = new command();
    $command->execute(true);

?>
