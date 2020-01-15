<?php

    // Base exchange class wrapper (combines the CCXT lib and the normalizer)

    class exchange {

        private $ccxt;                          // CCXT class
        private $normalizer;                    // Normalizer class
        private $settings = [                   // Default settings
                    //'mode' => 'test',
                ]; 
        private $exchange;
        private $markets;
        private $marketsById;
        private $marketsBySymbol;
        private $params;

        // Construct backend CCXT exchange object, and instanciate the appropriate output normalizer if it exists
        public function __construct($exchange, $options) {
            $this->settings = array_merge($this->settings, $options);
            $this->exchange = strtolower($exchange);
            $class = "\\ccxt\\" . strtolower($exchange);
            $normalizer = 'normalizer_'.strtolower($exchange);
            if (class_exists($class)) {
                $this->ccxt = new $class($options);
            }
            if (class_exists($normalizer)) {
                $this->normalizer = new $normalizer($this->ccxt, $options);
            }
            $markets = $this->markets(60);
            list($byid, $bysymbol) = array_values((array) $this->index_markets($markets));
            $this->markets = $markets;
            $this->marketsById = $byid;
            $this->marketsBySymbol = $bysymbol;
            if(is_object($this->normalizer)) {
                $this->normalizer->markets = $markets;
                $this->normalizer->marketsById = $byid;
                $this->normalizer->marketsBySymbol = $bysymbol;
            }
        }

        // Execute a particular CCXT method, 
        // If a normalizer method by the same name exists, execute that too, passing all the CCXT data to the normalizer
        public function __call($name, $params) {
            $result = false;
            if (method_exists($this->ccxt, $name)) {
                $result = call_user_func([$this->ccxt, $name], $params);
            }
            if (method_exists($this->normalizer, $name)) {
                $result = call_user_func([$this->normalizer, $name], (object) ['params' => $params, 'result'=> $result, 'settings' => $this->settings]);
            }
            return $result;
        }
        
        // Get CCXT info
        public function ccxtinfo($params) {
            $function = isset($params['function']) ? $params['function'] : null;
            if (!is_null($function)) {
                if (isset($this->ccxt->defined_rest_api[$function])) {
                    return $this->ccxt->defined_rest_api[$function];
                } else {
                    logger::error('Function "'.$function.'" not implemented in the exchange API');
                    return false;
                }
            } else {
                $info = [];
                $keys = ['id','has','api','status','timeframes','urls','options'];
                foreach($this->ccxt as $key => $val) {
                    if (in_array($key,$keys)) {
                        $info[$key] = $val;
                    }
                }
                return $info;
            }
        }

        // Get market data for specific symbol
        public function market($params, $cachetime = 5) {
            $symbol = (is_array($params) ? $params['symbol'] : $params);
            $key = $this->exchange.':'.$symbol.':markets';
            if ($cacheResult = cache::get($key,$cachetime)) {
                return $cacheResult;
            } else {
                //$markets = $this->markets(false,120);
                $markets = $this->markets;
                if (array_key_exists($symbol, $this->marketsBySymbol)) {
                    $market = $this->marketsBySymbol[$symbol];
                    if (is_null($market->bid) || is_null($market->ask)){
                        $ticker = $this->ccxt->fetch_ticker($symbol);
                        $market->bid = $ticker['bid'];
                        $market->ask = $ticker['ask'];
                    }
                    cache::set($key,$market);
                    return $market;
                } else {
                    logger::error('Invalid market symbol');
                    return false;
                }
            }
        }

        // Get market data for all markets
        public function markets($tickers = true, $cachetime = 5) {
            $key = $this->exchange.':markets';
            if ($cacheResult = cache::get($key,$cachetime)) {
                return $cacheResult;
            } else {
                $markets = $this->fetch_markets();
                $ret = [];
                foreach($markets as $market) {
                    $symbol = $market->symbol;
                    if (($tickers !== false) && (is_null($market->bid) || is_null($market->ask))){
                        $ticker = $this->ccxt->fetch_ticker($symbol);
                        $market->bid = $ticker['bid'];
                        $market->ask = $ticker['ask'];
                    }
                    $ret[] = $market;
                }
                cache::set($key,$ret);
                return $ret;
            }
        }

        // Index markets by ID and by Symbol
        private function index_markets($markets) {
            $byid = [];
            $bysymbol = [];
            foreach ($markets as $market) {
                $byid[$market->id] = $market;
                $bysymbol[$market->symbol] = $market;
            }
            return (object) ['byid' => $byid, 'bysymbol' => $bysymbol];
        }

        // Get position for specific symbol
        public function position($params) {
            $symbol = (is_array($params) ? $params['symbol'] : $params);
            $suppress = (isset($params['suppress']) ? $params['suppress'] : false);
            $positions = $this->positions();
            foreach($positions as $position) {
                if ($symbol == $position->market->symbol) {
                    return $position;
                }
            }           
            if ($suppress !== true) { 
                logger::notice('You do not currently have a position on '.$symbol);
            }
            return false;
        }

        // Get current positions
        public function positions() {
            return $this->normalizer->fetch_positions();
        }

        // Get order data for a specific order ID
        public function order($params) {
            $id = is_array($params) ? $params['id'] : $params;
            $orders = $this->orders();
            foreach ($orders as $order) {
                if ($order->id == $id) {
                    return $order;
                }
            }
            logger::error('Invalid order or order not found');
            return false;
        }

        // Get all orders
        public function orders($settings = []) {
            $markets = $this->markets;
            $filters = [];
            if (isset($settings['id'])) { $filters['id'] = $settings['id']; }
            if (isset($settings['symbol'])) { $filters['symbol'] = $settings['symbol']; }
            if (isset($settings['type'])) { $filters['type'] = $settings['type']; }
            if (isset($settings['direction'])) { $filters['direction'] = $settings['direction']; }
            if (isset($settings['status'])) { 
                $filters['status'] = $settings['status']; 
                $onlyOpen = ($settings['status'] == "open" ? true : false); 
            } else {
                $onlyOpen = false;
            }
            $result = [];
            $orders = $this->normalizer->fetch_orders($markets,$onlyOpen);
            foreach ($orders as $order) {
                if (count($filters) > 0) {
                    $filter = false;
                    foreach($filters as $key => $value) {
                        if ($key == 'symbol') {
                            if ($order->market->symbol !== $value) {
                                $filter = true;
                            }    
                        } else {
                            if ($order->$key !== $value) {
                                $filter = true;
                            }
                        }
                    }
                    if (!$filter) {
                        $result[] = $order;                                
                    }
                } else {
                    $result[] = $order;
                }
            }
            return $result;
        }

        // Cancel order(s)
        public function cancel($params) {
            $id =  (isset($params['id']) ? $params['id'] : null);
            $symbol = (isset($params['symbol']) ? $params['symbol'] : null);
            if ($id == 'all') {
                if (method_exists($this->normalizer,'cancel_all_orders')) {
                    return $this->normalizer->cancel_all_orders($symbol);
                } else {
                    $orders = ((!is_null($symbol)) ? $this->orders(['status'=>'open','symbol'=>$symbol]) : $this->orders(['status'=>'open']));
                    foreach($orders as $order) {
                        $result = $this->cancel(['id'=>$order->id]);
                        if (!in_array($result->status,['canceled','cancelled'])) {       // Deribit don't know how to spell cancelled
                            logger::error('Not all open orders were able to be cancelled');
                            return false;
                        }

                    }
                    return true;
                }
            } else {
                if (method_exists($this->normalizer,'cancel_order')) {
                    $result = $this->normalizer->cancel_order($id);
                    return (object) $result;
                } else {
                    $order = $this->order($id);
                    if (!is_null($order)) {
                        if ($order->status == "open") {
                            $result = $this->ccxt->cancel_order($id);
                            return (object) $result;
                        } 
                    }
                }
            }
            logger::error('Failed to cancel order: '.$id);
            return false;
        }

        // Get free balance in USD value using current market data
        public function available_balance_usd() {
            $balances = $this->fetch_balance();
            $usd_free = 0;
            foreach ($balances as $balance) {
                $usd_free += $balance->balance_usd_free;
            }
            return $usd_free;
        }

        // Get total balance in USD value using current market data
        public function total_balance_usd() {
            $balances = $this->fetch_balance();
            $usd_total = 0;
            foreach ($balances as $balance) {
                $usd_total += $balance->balance_usd_total;
            }
            return $usd_total;
        }

        // Get current position size in number of contracts (as opposed to USD)
        private function position_size($symbol) {
            $position = $this->position(['symbol' => $symbol, 'suppress' => true]);
            $market = $this->market(['symbol' => $symbol]);
            if (is_object($position)) {           // If already in a position
                $quoteSize = round($position->size_quote / $market->contract_size,0);
                $baseSize = $position->size_base;
                return (strtolower($this->normalizer->orderSizing) == 'quote' ? $quoteSize : $baseSize);
            }
            return 0;
        }

        // Get current position direction (long or short)
        private function position_direction($symbol) {
            $position = $this->position(['symbol' => $symbol, 'suppress' => true]);
            if (is_object($position)) {           // If already in a position
                return $position->direction;
            }
            return false;
        }

        // Convert USD value to number of contracts, depending of if exchange uses base or quote price and what the acual contract size is per contract
        private function convert_size($usdSize, $params) {
            $symbol = $params['symbol'];
            $market = $this->market(['symbol' => $symbol]);
            $contractSize = $market->contract_size;                                                      // Exchange contract size in USD
            $price = isset($params['price']) ? $params['price'] : (($market->bid + $market->ask) / 2);   // Use price if given, else justuse a rough market estimate
            if ($this->normalizer->orderSizing == 'quote') {                                             // Exchange uses quote price
                $orderSize = round($usdSize / $contractSize,0);
            } else {                                                                                     // Exchange uses base price
                $orderSize = $usdSize / $price;
            }
            return $orderSize;
        }

        // Perform Trade
        private function trade($direction, $params) {
            $stub = $params['stub'];
            $symbol = $params['symbol'];
            $market = $this->market(['symbol' => $symbol]);
            $size = $params['size'];
            $price = isset($params['price']) ? $params['price'] : null;
            $type = is_null($price) ? 'market' : 'limit';
            if (strtolower(substr($size,-1)) == 'x') {             // Position size given in x
                $multiplier = str_replace('x','',strtolower($size));
                $size = $this->total_balance_usd() * $multiplier;
            }
            if (strtolower(substr($size,-1)) == '%') {             // Position size given in %
                $multiplier = str_replace('%','',strtolower($size)) / 100;
                $size = $this->total_balance_usd() * $multiplier;
            }
            $requestedSize = $this->convert_size($size, $params);
            $position = $this->position(['symbol' => $symbol, 'suppress' => true]);
            $positionSize = $this->position_size($symbol);                                               // Position size in contracts
            $currentDir = $this->position_direction($symbol);                                            // Current position direction (long or short)
            if ($positionSize != 0) {                                                                   // If already in a position
                if ($direction != $currentDir) {
                    $requestedSize += $positionSize;                                                    // Flip position if required
                } 
                if ($direction == $currentDir) {
                    if ($requestedSize > $positionSize) {
                        $requestedSize -= $positionSize;                                                // Extend position if required
                    } else {      
                        $requestedSize = 0;
                        logger::warning('Already '.$direction.' more contracts than requested');        // Prevent PineScript from making you poor
                    }
                }
            }
  
            if ($requestedSize > 0) {
                $balance = $this->total_balance_usd();
                $comment = isset($params['comment']) ? $params['comment'] : 'None';
                $rawResult = $this->ccxt->create_order($symbol, $type, ($direction == "long" ? "buy" : "sell"), abs($requestedSize), $price);
                $orderResult = $this->normalizer->parse_order($market, $rawResult['info']);
                logger::info('TRADE:'.strtoupper($direction).' | Symbol: '.$symbol.' | Type: '.$type.' | Size: '.($requestedSize * $market->contract_size).' | Price: '.($price == "" ? 'Market' : $price).' | Balance: '.$balance.' | Comment: '.$comment);
                if ((isset($params['stoptrigger'])) || (isset($params['profittrigger']))) {
                    $linkedOrder = new linkedOrderObject($stub, $symbol);
                    $linkedOrder->add($orderResult);
                    // Stop loss orders
                    if (isset($params['stoptrigger'])) {
                        $slparams = [
                            'symbol' => $symbol,
                            'stoptrigger' => $params['stoptrigger'],
                            'size' => (isset($params['stopsize']) ? $params['stopsize'] : $requestedSize),
                            'stopprice' => (isset($params['stopprice']) ? $params['stopprice'] : null),
                            'reduce' => (isset($params['reduce']) ? $params['reduce'] : false)
                        ];
                        $slresult = $this->stoploss($slparams);
                        $linkedOrder->add($slresult);
                    }
                    // Take profit orders
                    if (isset($params['profittrigger'])) {
                        $slparams = [
                            'symbol' => $symbol,
                            'profittrigger' => $params['profittrigger'],
                            'size' => (isset($params['stopsize']) ? $params['stopsize'] : $requestedSize),
                            'reduce' => (isset($params['reduce']) ? $params['reduce'] : false)
                        ];
                        $tpresult = $this->takeprofit($slparams);
                        $linkedOrder->add($tpresult);
                    }
                    return $linkedOrder;
                } else {
                    return $orderResult;
                }
            }
            return false;
        }

        // Long Trade (Limit or Market, depending on if you supply the price parameter)
        public function long($params) {
            return $this->trade('long', $params);
        }

        // Short Trade (Limit or Market, depending on if you supply the price parameter)
        public function short($params) {
            return $this->trade('short', $params);
        }

        // Stop Loss Orders 
        // Limit or Market, depending on if you supply the 'price' parameter or not
        // Buy or Sell is automatically determined by comparing the 'stoptrigger' price and current market price. This is a required parameter.
        public function stoploss($params) {
            $symbol = $params['symbol'];
            $trigger = $params['stoptrigger'];
            $market = $this->market(['symbol' => $symbol]);
            $size = isset($params['size']) ? $params['size'] : $this->positionSize($symbol);    // Use current position size is no size is provided
            $price = isset($params['stopprice']) ? $params['stopprice'] : null;
            $type = is_null($price) ? 'market' : 'limit';
            $reduce = isset($params['reduce']) ? $params['reduce'] : false;
            $direction = ($trigger > $market->ask) ? 'buy' : ($trigger < $market->bid ? 'sell' : null);
            if (is_null($direction)) {                                                          // Trigger price in the middle of the spread, so can't determine direction
                logger::error('Could not determine direction of stop loss order because the trigger price is inside the spread. Adjust the trigger price and try again.');
            }
            if ($size > 0) {
                $result = $this->normalizer->create_stoploss($market->id, $direction, $size, $trigger, $price, $reduce);
                return $this->normalizer->parse_order($market, $result);
            } else {
                logger::error("Could not automatically determine the size of the stop loss order (perhaps you don't currently have any open positions). Please try again and provide the 'size' parameter.");
            }
        }

        // Take Profit Orders 
        // Buy or Sell is automatically determined by comparing the 'profittrigger' price and current market price. This is a required parameter.
        // Take profit orders are always limit orders by design
        public function takeprofit($params) {
            $symbol = $params['symbol'];
            $trigger = $params['profittrigger'];
            $market = $this->market(['symbol' => $symbol]);
            $size = isset($params['size']) ? $params['size'] : $this->positionSize($symbol);    // Use current position size is no size is provided
            $type = 'limit';
            $reduce = isset($params['reduce']) ? $params['reduce'] : false;
            $direction = ($trigger > $market->ask) ? 'sell' : ($trigger < $market->bid ? 'buy' : null);
            if (is_null($direction)) {                                                          // Trigger price in the middle of the spread, so can't determine direction
                logger::error('Could not determine direction of take profit order because the trigger price is inside the spread. Adjust the trigger price and try again.');
            }
            if ($size > 0) {
                $result = $this->normalizer->create_takeprofit($market->id, $direction, $size, $trigger, $reduce);
                return $this->normalizer->parse_order($market, $result);
            } else {
                logger::error("Could not automatically determine the size of the take profit order (perhaps you don't currently have any open positions). Please try again and provide the 'size' parameter.");
            }
        }

        // Close Position
        public function close($params) {
            $symbol = $params['symbol'];
            $size = str_replace('%', '', isset($params['size']) ? $params['size'] : '100%');
            $orderSizing = (isset($this->normalizer->orderSizing) ? $this->normalizer->orderSizing : 'quote');
            $position = $this->position(['symbol' => $symbol, 'suppress' => true]);
            if (is_object($position)) {
                $dir = $position->direction == 'long' ? 'sell' : ($position->direction == 'short' ? 'buy' : null);
                $market = $position->market;
                $contract_size = ($orderSizing == 'quote' ? (round($position->size_quote * ($size / 100),0) / $market->contract_size) : ($position->size_base * ($size / 100)));
                $type = 'market';
                //$price = ($dir == "buy" ? $market->ask : $market->bid);
                $price = null;
                if ($contract_size > 0) {
                    $balance = $this->total_balance_usd();
                    $comment = isset($params['comment']) ? $params['comment'] : 'None';
                    $rawResult = $this->ccxt->create_order($symbol, $type, $dir, abs($contract_size), $price);
                    $orderResult = $this->normalizer->parse_order($market, $rawResult['info']);
                    logger::info('TRADE:CLOSE | Symbol: '.$symbol.' | Direction: '.$dir.' | Type: '.$type.' | Size: '.($contract_size * $market->contract_size).' | Price: '.(is_null($price) ? 'Market' : $price).' | Balance: '.$balance.' | Comment: '.$comment);
                    return $orderResult;
                }
            } else {
                logger::error("You do not currently have a position on ".$symbol);
            }
        }

        // Get OHLCV data
        public function ohlcv($params) {
            $symbol = $params['symbol'];
            $timeframe = timeframeToMinutes(isset($params['timeframe']) ? $params['timeframe'] : '1h');
            $cacheTime = $timeframe / 2;
            $count = isset($params['count']) ? $params['count'] : 100;
            $ohlcvTimeframes = [];
            $tfs = $this->normalizer->fetch_timeframes();
            foreach ($tfs as $tfkey => $tf) {
                $tfmin = timeframeToMinutes($tf);
                if (!is_null($tfmin)) {
                    $ohlcvTimeframes[timeframeToMinutes($tf)] = $tfkey;
                }
            }
            if (!array_key_exists($timeframe, $ohlcvTimeframes)) {
                $maxtf = 1;
                foreach (array_keys($ohlcvTimeframes) as $tf) {
                    if (($tf < $timeframe) && ($tf > $maxtf) && ($timeframe % $tf == 0)) {
                        $maxtf = $tf;
                    }
                }
                $gettf = (isset($ohlcvTimeframes[$maxtf]) ? $ohlcvTimeframes[$maxtf] : $timeframe);
                $bucketize = true;
                $multiplier = floor($timeframe / $maxtf);
            } else {
                $gettf = $ohlcvTimeframes[$timeframe];
                $bucketize = false;
                $multiplier = 1;
            }
            $qty = $count * $multiplier;
            if ($qty > 1000) { $qty = 1000; }
            $period = ((floor((time() / 60) / $timeframe) * $timeframe) * 60) + ($timeframe * 60);
            $cacheTime = (count($ohlcvTimeframes) == 0 ? 60 : $period - time());  // Reduce cache time if we are using trade data to generatw OHLCV (no OHLCV support on exchange API)
            $key = $this->exchange.':ohlcv:'.$symbol.':'.$timeframe.':'.$period.':'.$qty;
            if ($cacheResult = cache::get($key,$cacheTime)) {
                $ohlcv = $cacheResult;
            } else {
                $ohlcv = $this->normalizer->fetch_ohlcv($symbol,$gettf,$qty);
                cache::set($key,$ohlcv);
            }
            if ($bucketize !== false) {
                $ohlcv = bucketize($ohlcv, $timeframe);
            }
            $ohlcv = array_slice($ohlcv,(0-$count));
            $result = [
                'ohlcv'     => $ohlcv,
                'count'     => count($ohlcv),
                'symbol'    => $symbol,
                'timeframe' => $timeframe,
            ];
            return $result;
        }

        // Settings Getter (did not use __get because I want more control over what can be get)
        public function get($param) {
            if (isset($this->settings[$param])) {
                return $this->settings[$param];
            } 
            return null;
        }

        // Settings Setter (did not use __set because I want more control over what can be set)
        public function set($param, $value) {
            $this->settings[$param] = $value;
            return $this->settings[$param];
        }

    }


?>
