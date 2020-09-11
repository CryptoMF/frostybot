<?php

    // Base exchange class wrapper (combines the CCXT lib and the normalizer)

    class exchange {

        private $ccxt;                          // CCXT class
        private $normalizer;                    // Normalizer class
        private $exchange;
        private $settings = [];                 // Default settings
        public $markets;
        public $marketsById;
        public $marketsBySymbol;
        private $params;

        // Construct backend CCXT exchange object, and instanciate the appropriate output normalizer if it exists
        public function __construct($exchange, $options) {
            $this->settings = array_merge($this->settings, $options);
            $this->exchange = strtolower($exchange);
            $class = "\\ccxt\\" .$this->exchange.(class_exists("\\ccxt\\" .$this->exchange."_frostybot") ? "_frostybot" : "");
            $normalizer = 'normalizer_'.strtolower(str_replace("_frostybot","",$exchange));
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
            if (method_exists($this->normalizer, $name)) {
                $result = call_user_func_array([$this->normalizer, $name], $params);
            } else {
                if (method_exists($this->ccxt, $name)) {
                    $result = call_user_func_array([$this->ccxt, $name], $params);
                }
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
            if ($cacheResult = cache::get($key, $cachetime)) {
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
        public function position($params, $cachetime = 10) {
            $symbol = (is_array($params) ? $params['symbol'] : $params);
            $suppress = (isset($params['suppress']) ? $params['suppress'] : false);
            $positions = $this->positions($cachetime);
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
        public function positions($cachetime = 10) {
            $key = requestuid().':positions';
            if ($cacheResult = cache::get($key, $cachetime)) {
                return $cacheResult;
            } else {
                $ret = $this->fetch_positions();
                cache::set($key,$ret);
                return $ret;
            }
        }

        // Get order data for a specific order ID
        public function order($params) {
            if ($this->ccxt->has['fetchOrder']) {
                $rawOrder = $this->fetch_order($params['id'],$params['symbol']);
                if ($rawOrder !== false) {
                    return $this->normalizer->parse_order($rawOrder);
                }
            } else {
                $orders = $this->orders(['symbol' => $params['symbol']]);
                foreach ($orders as $order) {
                    if ($order->id == $params['id']) {
                        return $order;
                    }
                }
            }
            logger::error('Invalid order or order not found');
            return false;
        }

        // Filter list of orders
        public function filter_orders($orders, $settings) {
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
            if (count($filters) == 0) {
                return $orders;
            }
            $result = [];
            foreach ($orders as $order) {
                $filter = false;
                foreach($filters as $key => $value) {
                    $filter = ($key == 'symbol') ? ($order->market->symbol !== $value) : ($order->$key !== $value);
                }
                if (!$filter) {
                    $result[] = $order;
                }
            }
            return $result;
        }

        // Get all orders
        public function orders($settings = []) {
            $symbol = $settings['symbol'];
            $filters = [];
            $fetchParams = isset($this->normalizer->ccxtParams['fetch_orders']) ? $this->normalizer->ccxtParams['fetch_orders'] : [];
            if (isset($settings['id'])) { $filters['id'] = $settings['id']; }
            if (isset($settings['symbol'])) { $filters['symbol'] = $settings['symbol']; }
            if (isset($settings['type'])) { $filters['type'] = $settings['type']; }
            if (isset($settings['direction'])) { $filters['direction'] = $settings['direction']; }
            if (isset($settings['status'])) {
                $onlyOpen = ($settings['status'] == "open" ? true : false);
            } else {
                $onlyOpen = false;
            }
            if (($onlyOpen) && ($this->ccxt->has['fetchOpenOrders'])) {
                $rawOrders = $this->fetch_open_orders($symbol, null, null, $fetchParams);
            } else {
                if ($this->ccxt->has['fetchOrders']) {
                    $rawOrders = $this->fetch_orders($symbol, null, null, $fetchParams);
                } else {
                    $rawOrders = [];
                    if ($this->ccxt->has['fetchOpenOrders']) {
                        $rawOrders = array_merge($rawOrders,$this->ccxt->fetch_open_orders($symbol, null, null, $fetchParams));
                    }
                    if ($this->ccxt->has['fetchClosedOrders']) {
                        $rawOrders = array_merge($rawOrders,$this->ccxt->fetch_closed_orders($symbol, null, null, $fetchParams));
                    }
                }
            }
            $orders = [];
            foreach ($rawOrders as $rawOrder) {
                $orders[] = $this->normalizer->parse_order($rawOrder);
            }
            return $this->filter_orders($orders,$settings);
        }

        // Cancel order(s)
        public function cancel($params) {
            $id =  (isset($params['id']) ? $params['id'] : null);
            $symbol = (isset($params['symbol']) ? $params['symbol'] : null);
            if ($id == 'all') {
                //$cancelParams = isset($this->normalizer->ccxtParams['cancel_orders']) ? $this->normalizer->ccxtParams['cancel_orders'] : [];
                $results = [];
                if ($this->ccxt->has['cancelAllOrders']) {
                    $orders = $this->cancel_all_orders($symbol);
                    if (is_array($orders)) {
                        foreach ($orders as $order) {
                            $results[] = $this->normalizer->parse_order($order);
                        }
                    } 
                } else {
                    $orders = $this->orders(array_merge($params,['status'=>'open']));
                    foreach ($orders as $order) {
                        $results[] = $this->normalizer->parse_order($this->ccxt->cancel_order($order->id, $order->market->symbol));
                    }
                }
                if (count($results) > 0) {
                    $GLOBALS['balance'] = $this->total_balance_usd();
                }
                return $results;
            } else {
                $result = $this->normalizer->parse_order($this->cancel_order($id, $symbol));
                $GLOBALS['balance'] = $this->total_balance_usd();
                return $result;
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
        public function total_balance_usd($params = null) {
            $balances = $this->fetch_balance();
            $usd_total = 0;
            foreach ($balances as $balance) {
                $usd_total += $balance->balance_usd_total;
            }
            return $usd_total;
        }

        // Get current position size in number of contracts (as opposed to USD)
        private function position_size_contracts($symbol) {
            $position = $this->position(['symbol' => $symbol, 'suppress' => true]);
            $market = $this->market(['symbol' => $symbol]);
            if (is_object($position)) {           // If already in a position
                $quoteSize = round($position->size_quote / $market->contract_size,0);
                $baseSize = $position->size_base;
                return (strtolower($this->normalizer->orderSizing) == 'quote' ? $quoteSize : $baseSize);
            }
            return 0;
        }

        // Get current position size in USD (as opposed to contracts)
        private function position_size_usd($symbol) {
            $position = $this->position(['symbol' => $symbol, 'suppress' => true]);
            $market = $this->market(['symbol' => $symbol]);
            if (is_object($position)) {           // If already in a position
                $quoteSize = $position->size_quote;
                $baseSize = $position->size_base;
                return (strtolower($this->normalizer->orderSizing) == 'quote' ? $quoteSize : ($baseSize * $market->bid));
            }
            return 0;
        }

        // Get current directional position size in USD (negative if currently short, positive if currently long)
        private function position_size_directional($symbol) {
            $currentDir = $this->position_direction($symbol);
            $positionSizeUsd = $this->position_size_usd($symbol);
            return $currentDir !== false ? ($currentDir == 'long' ? $positionSizeUsd : 0 - $positionSizeUsd) : 0;
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
        private function convert_size($usdSize, $symbol, $price = null) {
            $market = $this->market(['symbol' => $symbol]);
            $contractSize = $market->contract_size;                                                      // Exchange contract size in USD
            $price = (!is_null($price)) ? $price : (($market->bid + $market->ask) / 2);                  // Use price if given, else just use a rough market estimate
            if ($this->normalizer->orderSizing == 'quote') {                                             // Exchange uses quote price
                $orderSize = round($usdSize / $contractSize,0);
            } else {                                                                                     // Exchange uses base price
                $orderSize = $usdSize / $price;
            }
            return $orderSize;
        }

        // Convert price (convert layered orders and relative prices to actual prices)
        private function convert_price($symbol, $price = null) {
            if (is_null($price)) {                                              // Price not given
                return null;
            }
            $market = $this->market(['symbol' => $symbol]);
            if (strpos($price, ',') !== false) {                                // Layered order
                $price = $price.(substr_count($price, ',') < 2 ? ',5' : '');    // Add default qty
                list($range1, $range2, $qty) = explode(',', $price);
                $operator = in_array(((string) $price)[0], ['+','-']) ? ((string) $price)[0] : '';  // Price express relative to current market price
                if ($operator != '') {
                    $range1 = $this->get_absolute_price($symbol, $operator.$range1);
                    $range2 = $this->get_absolute_price($symbol, $operator.$range2);
                }
                $rangebottom = min($range1, $range2);
                $rangetop = max($range1, $range2);

                $ret = [];
                if ($qty > 1) {
                    $inc = ($rangetop - $rangebottom) / ($qty - 1);
                    for ($i = 0; $i < $qty; $i++) {
                        $ret[] = $rangebottom + ($i * $inc);
                    }
                } else {
                    $ret[] = $rangebottom + ($rangetop - $rangebottom) / 2;
                }
                return $ret;
            } else {                                                            // Non-layered order
                if (((string) $price)[0] == "+") {                              // Price expressed in relation to market price (above price)
                    $price = $market->bid + abs($price);
                }
                if (((string) $price)[0] == "-") {                              // Price expressed in relation to market price (below price)
                    $price = $market->ask - abs($price);
                }
                return $price;
            }
        }

        // Get average price
        private function average_price($symbol, $price) {
            $price = $this->convert_price($symbol, $price);
            if (is_array($price)) {
                return array_sum($price) / count($price);
            } else {
                return is_null($price) ? null : $price;
            }
        }

        // Calculate the absolute price in case it is a percentage or multiplier of total balance
        private function get_absolute_size($size) {
            if (strtolower(substr($size,-1)) == 'x') {             // Position size given in x
                $multiplier = str_replace('x','',strtolower($size));
                return $this->total_balance_usd() * $multiplier;
            }
            if (strtolower(substr($size,-1)) == '%') {             // Position size given in %
                $multiplier = str_replace('%','',strtolower($size)) / 100;
                return $this->total_balance_usd() * $multiplier;
            }
            return $size;
        }

        // Calculate size based on the risk and price difference
        private function calculate_size_from_risk($symbol, $risk, $stopprice, $entryprice) {
            if (strtolower(substr($risk,-1)) == '%') {             // risk given in %
                $multiplier = str_replace('%','',strtolower($risk)) / 100;
                $risk_usd = $this->total_balance_usd() * $multiplier;
            }
            else {
                $risk_usd = $risk;
            }

            if (is_null($entryprice)) {
                $market = $this->market(['symbol' => $symbol]);
                if ($stopprice  > $market->ask) {
                    $entryprice = $market->ask;
                }
                else if ($stopprice < $market->bid) {
                    $entryprice = $market->bid;
                }
                else {
                    logger::error('Unable to calculate the entry price, stop price is within spraed.');
                    return null;
                }
            }

            $size_usd = $risk_usd / abs($entryprice - $stopprice);
            $size = $size_usd * $entryprice;

            return $size;
        }

        // Perform Trade
        private function trade($direction, $params) {
            $stub = $params['stub'];
            $symbol = $params['symbol'];
            $market = $this->market(['symbol' => $symbol]);
            $price = isset($params['price']) ? $this->average_price($symbol, $params['price']) : null;
            $maxSize = isset($params['maxsize']) ? $params['maxsize'] : null;
            $type = is_null($price) ? 'market' : 'limit';

            $positionSizeCon = $this->position_size_contracts($symbol);             // Position size in contracts
            $positionSizeUsd = $this->position_size_usd($symbol);                   // Position size in USD
            $currentDir = $this->position_direction($symbol);                       // Current position direction (long or short)

            $size = $params['size'];                                                // The size should be an absolute or relative value
            $sizeType = in_array($size[0], ['-','+']) ? 'relative' : 'absolute';    // Check if size is relative or absolute
            if (($direction != $currentDir) && ($sizeType == 'relative')) {         // If the size type is relative, but switching direction, make the size type absolute
                if ($currentDir !== false) {
                    $size = $this->get_absolute_size(substr($size,1));
                    $sizeType = 'absolute';
                }
            }

            // ----------------------------------------------------------
            // Size relative to current position provided (size=+xxx or size=-xxx)
            // ----------------------------------------------------------
            if ($sizeType == "relative") {
                $operator = $size[0];
                $size = $this->get_absolute_size(substr($size,1));                  // Make trade size absolute
                // Increase position
                if ($operator == '+') {
                    // Nothing to adjust, will just increase position size
                }
                // Decrease position
                if ($operator == '-') {
                    $direction = ($direction == "long" ? "short" : "long");         // Switch direction if decreasing size
                    if ($positionSizeUsd - $size < 0) {
                        if ($positionSizeUsd > 0) {
                            $size = $positionSizeUsd;                               // Close position if size is negative
                            logger::warning('Size greater than current position, closing position');
                        } else {
                            $size = 0;                                              // Don't execute negative order if not in a position
                            logger::error('Cannot reduce position when not in a position');
                        }
                    }
                }
                if ((!is_null($maxSize)) && ($size > 0))  {
                    $resultSize = $positionSizeUsd + ($operator == '-' ? 0 - $size : $size);
                    if (abs($positionSizeUsd) >= abs($maxSize)) {
                        logger::error('Maximum position size reached, order not permitted.');
                    } else {
                        if ($resultSize > $maxSize) {
                            $size = ($operator == '-' ? $size + ($resultSize  - $maxSize) : $maxSize - $positionSizeUsd);
                            logger::debug('Position size limit: '.$maxSize.", Resultant position size: ".$resultSize.", Adjusted size: ".$size);
                            if ($size < 0) {
                                $size = abs($size);
                                $operator = '-';
                                //$size = 0;
                            }
                            if ($size > 0) {
                                logger::warning('Order size would exceed maximum position size, adjusting order size to '.$size.'.');
                            }
                            if (round($size) == 0) {
                                logger::error('Maximum position size reached, order not permitted.');
                            }
                        }
                    }
                }
                if (isset($params['stoptrigger'])) {
                    logger::warning('Stoptrigger not supported when using relative size orders, ignoring...');
                    unset($params['stoptrigger']);
                }
                if (isset($params['profittrigger'])) {
                    logger::warning('Profittrigger not supported when using relative size orders, ignoring...');
                    unset($params['profittrigger']);
                }
                //logger::debug('Relative size parameter calculated as '.$size);
                $requestedSize = $this->convert_size($size, $symbol, $price);       // Requested size in contracts
            }
            // ----------------------------------------------------------

            // ----------------------------------------------------------
            // Absolute trade size provided (size=xxx or size=xxx)
            // ----------------------------------------------------------
            if ($sizeType == "absolute") {
                $size = $this->get_absolute_size($size);                                                // Make trade size absolute
                $requestedSize = $this->convert_size($size, $symbol, $price);                           // Requested size in contracts
                $position = $this->position(['symbol' => $symbol, 'suppress' => true]);
                if ($positionSizeCon != 0) {                                                            // If already in a position
                    if ($direction != $currentDir) {
                        $requestedSize += $positionSizeCon;                                             // Flip position if required
                    }
                    if ($direction == $currentDir) {
                        if ($requestedSize > $positionSizeCon) {
                            $requestedSize -= $positionSizeCon;                                         // Extend position if required
                        } else {
                            $requestedSize = 0;
                            logger::warning('Already '.$direction.' more contracts than requested');    // Prevent PineScript from making you poor
                        }
                    }
                }
                //logger::debug('Absolute size parameter calculated as '.$size);
            }
            // ----------------------------------------------------------

            $side = ($direction == "long" ? "buy" : "sell");
            if ($requestedSize > 0) {
                $orderParams = [
                    'stub'   => $stub,
                    'symbol' => $symbol,
                    'type'   => $type,
                    'amount' => $requestedSize,
                    'side'   => $side,
                    'price'  => (isset($params['price']) ? $params['price'] : null)
                ];
                $orderResult = $this->submit_order($orderParams);
                $balance = $this->total_balance_usd();
                $GLOBALS['balance'] = $balance;
                $comment = isset($params['comment']) ? $params['comment'] : 'None';
                logger::info('TRADE | Direction: '.strtoupper($side).' | Symbol: '.$symbol.' | Type: '.$type.' | Size: '.$size.' | Price: '.($price == "" ? 'Market' : $price).' | Balance: '.$balance.' | Comment: '.$comment);
                if ((isset($params['stoptrigger'])) || (isset($params['profittrigger']))) {
                    $marketprice = $side == "buy" ? $market->bid : $market->ask;
                    if (is_a($orderResult,'frostybot\linkedOrderObject')) {
                        $linkedOrder = $orderResult;
                    } else {
                        $linkedOrder = new \frostybot\linkedOrderObject($stub, $symbol);
                        $linkedOrder->add($orderResult);
                    }
                    // Stop loss orders
                    if (isset($params['stoptrigger'])) {
                        $slParams = [
                            'symbol' => $symbol,
                            'stoptrigger' => $params['stoptrigger'],
                            'stopprice' => (isset($params['stopprice']) ? $params['stopprice'] : null),
                            'size' => (isset($params['stopsize']) ? $params['stopsize'] : $size),
                            'entryprice' => (!is_null($price) ? $price : $marketprice),
                            'reduce' => (isset($params['reduce']) ? $params['reduce'] : false),
                            'triggertype' => (isset($params['triggertype']) ? $params['triggertype'] : null),
                        ];
                        $slResult = $this->stoploss($slParams);
                        $linkedOrder->add($slResult);
                    }
                    // Take profit orders
                    if (isset($params['profittrigger'])) {
                        $tpParams = [
                            'symbol' => $symbol,
                            'profittrigger' => $params['profittrigger'],
                            'size' => (isset($params['profitsize']) ? $params['profitsize'] : $size),
                            'entryprice' => (!is_null($price) ? $price : $marketprice),
                            'reduce' => (isset($params['reduce']) ? $params['reduce'] : false)
                        ];
                        $tpResult = $this->takeprofit($tpParams);
                        $linkedOrder->add($tpResult);
                    }
                    // Trailing stop orders
                    /*
                    if (isset($params['trailstop'])) {
                        $tsParams = [
                            'symbol' => $symbol,
                            'trailstop' => $params['trailstop'],
                            'size' => $size,
                            'reduce' => (isset($params['reduce']) ? $params['reduce'] : false)
                        ];
                        $tsResult = $this->trailingstop($tsParams);
                        $linkedOrder->add($tsResult);
                    }
                    */
                    return $linkedOrder;
                } else {
                    return $orderResult;
                }
            }
            return false;
        }

        // Calculates the size for relative price or based on the risk level. Returns absolute price.
        private function calculate_trade_size($params) {
            if (isset($params['risk']) and isset($params['size'])) {
                logger::error("The 'risk' and 'size' parameters are mutually exclusive.");
            }
            if (!(isset($params['risk']) or isset($params['size']))) {
                logger::error("Either 'risk' or 'size' parameter is mandatory for executing a trade.");
            }

            if (isset($params['risk'])) {
                if (!isset($params['stoptrigger'])) {
                    logger::error("Risk calculation requires 'stoptrigger' parameter.");
                }
            }

            $size = null;
            if (isset($params['size'])) {
                $size = (string) $params['size'];
            }
            else if (isset($params['risk']) and isset($params['stoptrigger'])) {
                $symbol = $params['symbol'];
                $stoptrigger = $this->get_absolute_price($symbol, $params['stoptrigger']);
                $price = isset($params['price']) ? $this->average_price($symbol, $params['price']) : null;
                $size = $this->calculate_size_from_risk($symbol, $params['risk'], $stoptrigger, $price);
            }
            else {
                logger::error("Incorrect trade parameters supplied.");
            }

            if (is_null($size)) {
                logger::error('Unable to determine the size of the trade.');
            }

            return $size;
        }

        // Long Trade (Limit or Market, depending on if you supply the price parameter)
        public function long($params) {
            $params['size'] = $this->calculate_trade_size($params);
            return $this->trade('long', $params);
        }

        // Short Trade (Limit or Market, depending on if you supply the price parameter)
        public function short($params) {
            $params['size'] = $this->calculate_trade_size($params);
            return $this->trade('short', $params);
        }


        // Simple buy or sell trade (Only paramters allowed:  <size=xxx> [price=xxx] [maxsize=xxx] )
        public function simple_trade($side, $params) {
            $stub = $params['stub'];
            $symbol = $params['symbol'];
            $size = $params['size'];
            $maxSize = isset($params['maxsize']) ? $params['maxsize'] : null;
            $market = $this->market(['symbol' => $symbol]);
            $price = isset($params['price']) ? $this->average_price($symbol, $params['price']) : null;
            if ((!is_numeric($size)) || ($size < 0)) {
                logger::error('Size parameter must be a positive value in USD.');
                return false;
            }
            if ((!is_numeric($maxSize)) || ($maxSize < 0)) {
                logger::error('Maxsize parameter must be a positive value in USD.');
                return false;
            }
            if ((!is_null($price)) && ($params['price'] < 0)) {
                logger::error('Price parameter must be a positive value in USD.');
                return false;
            }
            if (isset($params['stoptrigger'])) {
                logger::warning('Stoptrigger not supported when using simple buy/sell orders, ignoring...');
                unset($params['stoptrigger']);
            }
            if (isset($params['profittrigger'])) {
                logger::warning('Profittrigger not supported when using simple buy/sell orders, ignoring...');
                unset($params['profittrigger']);
            }
            $type = is_null($price) ? 'market' : 'limit';
            $directionSize = $this->position_size_directional($symbol);
            $resultSize = $directionSize + ($side == 'buy' ? $size : 0 - $size);
            $directionMaxSize = $resultSize < 0 ? 0 - $maxSize : $maxSize;
            if ((!is_null($maxSize)) && (abs($resultSize) > $maxSize)) {
                $size = abs($directionMaxSize) - abs($directionSize);
                if ($size < 0) {
                    $size = 0;
                }
                logger::debug('Position size limit: '.$directionMaxSize.", Resultant position size: ".$resultSize.", Adjusted size: ".$size);
                if ($size == 0) {
                    logger::error('Maximum position size reached, order not permitted.');
                }
                if ($size > 0) {
                    logger::warning('Order size would exceed maximum position size, adjusting order size to '.$size.'.');
                }
            }
            $orderParams = [
                'stub'   => $stub,
                'symbol' => $symbol,
                'type'   => is_null($price) ? 'market' : 'limit',
                'amount' => $this->convert_size($size, $symbol, $price),
                'side'   => $side,
                'price'  => (isset($price) ? $price : null)
            ];
            $orderResult = $this->submit_order($orderParams);
            $balance = $this->total_balance_usd();
            $GLOBALS['balance'] = $balance;
            $comment = isset($params['comment']) ? $params['comment'] : 'None';
            logger::info('TRADE | Direction: '.strtoupper($side).' | Symbol: '.$symbol.' | Type: '.$type.' | Size: '.$size.' | Price: '.($price == "" ? 'Market' : $price).' | Balance: '.$balance.' | Comment: '.$comment);
            return $orderResult;
        }

        // Simple Buy Order  (Only size, price and maxsize parameters allowed. Limit or Market, depending on if you supply the price parameter)
        public function buy($params) {
            return $this->simple_trade('buy', $params);
        }

        // Simple Sell Order  (Only size, price and maxsize parameters allowed. Limit or Market, depending on if you supply the price parameter)
        public function sell($params) {
            return $this->simple_trade('sell', $params);
        }

        // Submit an order the exchange
        private function submit_order($params) {
            if ((isset($params['price'])) && (!is_null($params['price'])) && (strpos($params['price'], ',') !== false)) {     // This is a layered order
                $result = $this->layered_order($params);
            } else {                                                                             // This is a non-layered order
                $result = $this->regular_order($params);
            }
            cache::flush(0);
            return $result;
        }

        // Layered order
        private function layered_order($params) {
            $prices = $this->convert_price($params['symbol'], $params['price']);
            $linkedOrder = new \frostybot\linkedOrderObject($params['stub'], $params['symbol']);
            $amount = $params['amount'] / count($prices);
            foreach ($prices as $price) {
                $params['amount'] = $amount;
                $params['price'] = $this->get_absolute_price($params['symbol'], $price);
                $orderResult = $this->regular_order($params);
                $linkedOrder->add($orderResult);
            }
            return $linkedOrder;
        }

        // Regular non-layered order
        private function regular_order($params) {
            $params['price'] = $this->convert_price($params['symbol'], (isset($params['price']) ? $params['price'] : null));
            $orderParams = $this->normalizer->order_params($params);
            list ($symbol, $type, $side, $amount, $price, $params) = array_values((array) $orderParams);
            $rawOrderResult = $this->ccxt->create_order($symbol, $type, $side, $amount, $price, $params);
            $parsedOrderResult = $this->parse_order($rawOrderResult);
            return $parsedOrderResult;
        }

        // Calculate the relative price in case the input contains +/- relative price or a percentage
        private function get_relative_price($symbol, $price) {
            $market = $this->market(['symbol' => $symbol]);
            $operator = in_array(((string) $price)[0], ['+','-']) ? ((string) $price)[0] : '';
            if ($operator != '') {
                if (substr($price, -1) == '%') {                                   // Price expressed as a percentage of market price
                    $variance = abs(str_replace('%','',$price));
                    $price = $market->bid - ($market->bid * ((100 + $variance) / 100));
                }                                   
                switch ($operator) {
                    case '+'        :   $price = abs($price);       // Price expressed in relation to market price (above price)
                                        break;
                    case '-'        :   $price = 0 - abs($price);   // Price expressed in relation to market price (below price)
                                        break;
                }
                return round($price / $market->precision->price) * $market->precision->price;
            } else {
                logger::error('Relative price requires a + or - prefix');
            }
            return false;
        }

        // Calculate the absolute price in case the input contains +/- relative price or a percentage
        private function get_absolute_price($symbol, $price) {
            $market = $this->market(['symbol' => $symbol]);
            $operator = in_array(((string) $price)[0], ['+','-']) ? ((string) $price)[0] : '';
            if ($operator != '') {
                if (substr($price, -1) == '%') {                                   // Price expressed as a percentage of market price
                    $variance = abs(str_replace('%','',$price));
                    $price = $market->bid - ($market->bid * ((100 + $variance) / 100));
                }                                   
                switch ($operator) {
                    case '+'        :   $price = $market->bid + abs($price); // Price expressed in relation to market price (above price)
                                        break;
                    case '-'        :   $price = $market->ask - abs($price); // Price expressed in relation to market price (below price)
                                        break;
                }
                $price = round($price / $market->precision->price) * $market->precision->price;
            }
            return $price;
        }

        // Stop Loss Orders
        // Limit or Market, depending on if you supply the 'price' parameter or not
        // Buy or Sell is automatically determined by comparing the 'stoptrigger' price and current market price. This is a required parameter.
        public function stoploss($params) {
            $symbol = $params['symbol'];
            $trigger = $this->get_absolute_price($symbol, $params['stoptrigger']);
            $price = isset($params['stopprice']) ? $this->get_absolute_price($symbol, $params['stopprice']) : $trigger;
            $market = $this->normalizer->get_market_by_symbol($symbol);
            if (isset($params['size'])) {
              $params['size'] = $this->get_absolute_size($params['size']);
            }
            if (isset($params['entryprice'])) {
                if ($this->normalizer->orderSizing == 'quote') {
                    $params['size'] = ($params['size'] / $params['entryprice']) * $price;
                } else {
                    $price = $params['entryprice'];
                }
            }
            if (isset($params['stopprice'])) {
                $params['stopprice'] = $this->get_absolute_price($symbol, $params['stopprice']);
            }
            $params['type'] = isset($params['stopprice']) ? 'sllimit' : 'slmarket';
            $params['side'] = $trigger  > $market->ask ? 'buy' : ($trigger < $market->bid ? 'sell' : null);
            $params['amount'] = isset($params['size']) ? $this->convert_size($params['size'], $symbol, $price) : $this->position_size_contracts($params['symbol']);    // Use current position size is no size is provided
            $params['stoptrigger'] = $trigger;
            if (is_null($params['side'])) {                   // Trigger price in the middle of the spread, so can't determine direction
                logger::error('Could not determine direction of the stop loss order because the trigger price is inside the spread. Adjust the trigger price and try again.');
            }
            if (!($params['amount'] > 0)) {
                logger::error("Could not automatically determine the size of the stop loss order (perhaps you don't currently have any open positions). Please try again and provide the 'size' parameter.");
            }
            $result = $this->submit_order($params);
            $GLOBALS['balance'] = $this->total_balance_usd();
            return $result;
        }

        // Take Profit Orders
        // Buy or Sell is automatically determined by comparing the 'profittrigger' price and current market price. This is a required parameter.
        // Take profit orders are always limit orders by design
        public function takeprofit($params) {
            $symbol = $params['symbol'];
            $trigger = $this->get_absolute_price($symbol, $params['profittrigger']);
            $price = isset($params['profitprice']) ? $this->get_absolute_price($symbol, $params['profitprice']) : $trigger;
            $market = $this->normalizer->get_market_by_symbol($symbol);
            if (isset($params['entryprice'])) {
                if ($this->normalizer->orderSizing == 'quote') {
                    $params['size'] = ($params['size'] / $params['entryprice']) * $price;
                } else {
                    $price = $params['entryprice'];
                }
            }
            if (isset($params['profitprice'])) {
                $params['profitprice'] = $this->get_absolute_price($symbol, $params['profitprice']);
            }
            $params['type'] = isset($params['profitprice']) ? 'tplimit' : 'tpmarket';
            $params['side'] = $trigger  > $market->ask ? 'sell' : ($trigger < $market->bid ? 'buy' : null);
            if (isset($params['size'])) {
              $params['size'] = $this->get_absolute_size($params['size']);
            }
            $params['amount'] = isset($params['size']) ? $this->convert_size($params['size'], $symbol, $price) : $this->position_size_contracts($params['symbol']);    // Use current position size is no size is provided
            $params['profittrigger'] = $trigger;
            if (is_null($params['side'])) {                  // Trigger price in the middle of the spread, so can't determine direction
                logger::error('Could not determine direction of the take profit order because the trigger price is inside the spread. Adjust the trigger price and try again.');
            }
            if (!($params['amount'] > 0)) {
                logger::error("Could not automatically determine the size of the take profit order (perhaps you don't currently have any open positions). Please try again and provide the 'size' parameter.");
            }
            $result = $this->submit_order($params);
            $GLOBALS['balance'] = $this->total_balance_usd();
            return $result;
        }


        // Trailing stop (not supported on all exchanges)
        // Buy or Sell is automatically determined by trailstop being positive or negative (Position = buy, Negative = sell)
        public function trailstop($params) {
            if ($this->ccxt->id != 'ftx') {
                logger::error('Trailing stop is currently only supported on FTX');
            }
            $symbol = $params['symbol'];
            $market = $this->normalizer->get_market_by_symbol($symbol);
            $trailby = $this->get_relative_price($symbol, $params['trailstop']);
            if (isset($params['size'])) {
              $params['size'] = $this->get_absolute_size($params['size']);
            }
            if (isset($params['entryprice'])) {
                if ($this->normalizer->orderSizing == 'quote') {
                    $params['size'] = ($params['size'] / $params['entryprice']) * $price;
                } else {
                    $price = $params['entryprice'];
                }
            }
            $params['type'] = 'trailstop';
            $params['side'] = $trailby  > 0 ? 'buy' : ($trailby < 0 ? 'sell' : null);
            $params['amount'] = isset($params['size']) ? $this->convert_size($params['size'], $symbol, $price) : $this->position_size_contracts($params['symbol']);    // Use current position size is no size is provided
            $params['trailby'] = $trailby;
            if (is_null($params['side'])) {                 
                logger::error('Could not determine direction of the trailing stop order because the trail distance is zero.');
            }
            if (!($params['amount'] > 0)) {
                logger::error("Could not automatically determine the size of the trailing stop order (perhaps you don't currently have any open positions). Please try again and provide the 'size' parameter.");
            }
            $result = $this->submit_order($params);
            $GLOBALS['balance'] = $this->total_balance_usd();
            return $result;
        }

        // Close Position
        public function close($params) {
            $symbol = $params['symbol'];
            $size = str_replace('%', '', isset($params['size']) ? $params['size'] : '100%');
            $orderSizing = (isset($this->normalizer->orderSizing) ? $this->normalizer->orderSizing : 'quote');
            $position = $this->position(['symbol' => $symbol, 'suppress' => true]);
            if (is_object($position)) {
                $side = $position->direction == 'long' ? 'sell' : ($position->direction == 'short' ? 'buy' : null);
                $market = $position->market;
                $requestedSize = ($orderSizing == 'quote' ? (round($position->size_quote * ($size / 100),0) / $market->contract_size) : ($position->size_base * ($size / 100)));
                $price = isset($params['price']) ? $this->average_price($symbol, $params['price']) : null;
                $type = (is_null($price) ? 'market' : 'limit');
                if ($requestedSize > 0) {
                    $orderParams = [
                        'symbol' => $symbol,
                        'type'   => $type,
                        'amount' => $requestedSize,
                        'side'   => $side,
                        'price'  => (isset($params['price']) ? $params['price'] : null),
                        'reduce' => true
                    ];
                    $orderResult = $this->submit_order($orderParams);
                    $balance = $this->total_balance_usd();
                    $GLOBALS['balance'] = $balance;
                    $comment = isset($params['comment']) ? $params['comment'] : 'None';
                    logger::info('TRADE:CLOSE | Symbol: '.$symbol.' | Direction: '.$side.' | Type: '.$type.' | Size: '.($requestedSize * $market->contract_size).' | Price: '.(is_null($price) ? 'Market' : $price).' | Balance: '.$balance.' | Comment: '.$comment);
                    return $orderResult;
                }
            } else {
                logger::warning("You do not currently have a position on ".$symbol);
                return false;
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
