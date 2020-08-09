<?php

    // Output normalizer for FTX exchange

    class normalizer_ftx extends normalizer_base {

        public $orderSizing = 'base';          // Base or quote
        public $ccxtParams = [
//            'cancel_orders' => [
//                'conditionalOrdersOnly' => false,
//                'limitOrdersOnly'       => false,
//            ]
        ];

        // Get current balances
        public function fetch_balance() {
            $result = $this->ccxt->fetch_balance();
            unset($result['info']);
            unset($result['free']);
            unset($result['used']);
            unset($result['total']);
            $balances = [];
            foreach ($result as $currency => $balance) {
                if ($currency == 'USD') {
                    $price = 1;
                } else {
                    $ticker = $this->ccxt->fetch_ticker($currency.'/USD');
                    $price = $ticker['ask'];    
                }
                $balanceFree = $balance['free'];
                $balanceUsed = $balance['used'];
                $balanceTotal = $balance['total'];
                if ($balanceTotal > 0) {
                    $balances[$currency] = new \frostybot\balanceObject($currency,$price,$balanceFree,$balanceUsed,$balanceTotal);
                }
            }
            return $balances;
        }

        // Get supported OHLCV timeframes
        public function fetch_timeframes() {
            return [
                '60'    =>  '1',
                '300'   =>  '5',
                '900'   =>  '15',
                '3600'  =>  '60',
                '14400' =>  '240',
                '86400' =>  '1440'
            ];
        }

        // Get OHLCV data
        public function fetch_ohlcv($symbol, $timeframe, $count=100) {
            $tfs = $this->fetch_timeframes();
            $actualtf = $tfs[$timeframe];
            $endtime = ((floor((time() / 60) / $actualtf) * $actualtf) * 60) + ($actualtf * 60);
            $ohlcvurl = 'https://www.ftx.com/api/markets/'.$symbol.'/candles?resolution='.((int) $timeframe).'&limit='.$count.'&end_time='.$endtime;
            //echo $ohlcvurl.PHP_EOL.'end: '.date('Y-m-d H:i:00',$endtime).PHP_EOL.'periods: '.$count.PHP_EOL;
            //die;
            $ohlcv = [];
            if ($rawOHLCV = json_decode(file_get_contents($ohlcvurl))) {
                foreach ($rawOHLCV->result as $rawEntry) {
                    $time = $rawEntry->time / 1000;
                    $timestamp = ((floor(($time / 60) / $actualtf) * $actualtf) * 60); // + ($actualtf * 60);
                    $open = $rawEntry->open;
                    $high = $rawEntry->high;
                    $low = $rawEntry->low;
                    $close = $rawEntry->close;
                    $volume = $rawEntry->volume;
                    $ohlcv[] = new \frostybot\ohlcvObject($symbol,$actualtf,$timestamp,$open,$high,$low,$close,$volume,$rawEntry);
                }
            }
            return $ohlcv;
        }        

        // Get list of markets from exchange
        public function fetch_markets() {
            $result = $this->ccxt->fetch_markets();
            $markets = [];
            //$marketFilters = ['BEAR','BULL','MOON','DOOM','HEDGE','MOVE'];
            $marketFilters = [];
            foreach($result as $market) {
                if ((in_array($market['type'],['spot','future'])) && ($market['quote'] == 'USD') && ($market['base'] != 'USDT') && ($market['active'] == true)) {
                    $filter = false;
                    foreach($marketFilters as $marketFilter) {
                        if (strpos($market['symbol'],$marketFilter) !== false) {
                            $filter = true;
                        }
                    }
                    if (!$filter) {
                        $id = $market['symbol'];
                        $symbol = $market['symbol'];
                        $quote = $market['quote'];
                        $base = $market['base'];
                        $expiration = (isset($market['info']['expiration']) ? $market['info']['expiration'] : null);
                        $bid = (isset($market['info']['bid']) ? $market['info']['bid'] : null);
                        $ask = (isset($market['info']['ask']) ? $market['info']['ask'] : null);
                        $contractSize = (isset($market['info']['contractSize']) ? $market['info']['contractSize'] : 1);
                        $precision = $market['precision'];
                        $marketRaw = $market;
                        $markets[] = new \frostybot\marketObject($id,$symbol,$base,$quote,$expiration,$bid,$ask,$contractSize,$precision,$marketRaw);
                    }
                }
            }
            return $markets;
        }

        // Get list of positions from exchange
        public function fetch_positions() {
            $result = [];
            $positions = $this->ccxt->private_get_positions(['showAvgPrice' => true]);
            foreach ($positions['result'] as $positionRaw) {
                $market = $this->marketsBySymbol[$positionRaw['future']];
                $direction = $positionRaw['size']  == 0 ? 'flat' : ($positionRaw['side'] == 'buy' ? 'long' : ($positionRaw['side'] == 'sell' ? 'short' : 'null'));
                $baseSize = round($positionRaw['size'],5);
                $entryPrice = $positionRaw['recentAverageOpenPrice'];
                $quoteSize = $baseSize * $entryPrice;
                if (abs($baseSize) > 0) {
                    $result[] = new \frostybot\positionObject($market,$direction,$baseSize,$quoteSize,$entryPrice,$positionRaw);
                }
            }
            return $result;
        }

        // Cancel specific order
        public function cancel_order($id, $symbol) {          
            $order = $this->fetch_order($id, $symbol);
            if (in_array($order['type'],['limit','market'])) {  // If it's not a standard order, then it must be a conditional order
                $response = $this->ccxt->privateDeleteOrdersOrderId(['order_id'=>$id]);
            } else {
                $response = $this->ccxt->privateDeleteConditionalOrdersOrderId (['order_id'=>$id]);
            }
    
            if ($response['success'] == true) {
                return $this->update_order_status($order, "canceled");
            }
    
            $result = $this->safe_value($response, 'result', array());
            return $result;
        }

        // Cancel all open orders
        public function cancel_all_orders ($symbol) {
            $orders = $this->fetch_open_orders($symbol);
            $results = [];
            if (is_array($orders    )) {
                foreach($orders as $order) {
                    $id = $order['id'];
                    if (in_array($order['type'],['limit','market'])) {  // If it's not a standard order, then it must be a conditional order
                        $response = $this->ccxt->privateDeleteOrdersOrderId (['order_id'=>$id]);
                    } else {
                        $response = $this->ccxt->privateDeleteConditionalOrdersOrderId (['order_id'=>$id]);
                    }
                    $results[] = ($response['success'] == true ? $this->update_order_status($order, "cancelled") : $order);
                }
            }
            return $results;
        }


        // Fetch conditional orders (take profit and stop loss)
        private function fetch_conditional_orders($symbol, $historical = true) {
            $response = $this->ccxt->privateGetConditionalOrdersHistory (['market'=>$symbol]);
            $result = $this->ccxt->safe_value($response, 'result', array());
            return $result;
        }
    
        // Fetch a conditional order (take profit and stop loss)
        private function fetch_conditional_order($id, $symbol) {
            $orders = $this->fetch_conditional_orders( $symbol );
            foreach ($orders as $order) {
                if (($order['id'] == $id) && ($order['market'] == $symbol)) {
                    return $order;
                }
            }
            return false;
        }
    
        // Fetch a specific order
        public function fetch_order($id, $symbol) {
            try {
                $response = $this->ccxt->privateGetOrdersOrderId(['order_id' => $id]);
            } catch (Exception $e) {
                $result = $this->fetch_conditional_order($id, $symbol);
                if ($result !== false) {
                    return $this->ccxt->parse_order($result);
                }
                return false;
            }
            
            $result = $this->ccxt->safe_value($response, 'result', array());
            return $this->ccxt->parse_order($result);
        }
    

        // Fetch all open orders for symbol
        public function fetch_open_orders($symbol, $since = null, $limit = null) {
            $request = [];
            $this->ccxt->load_markets();
            $market = null;
            if ($symbol !== null) {
                $market = $this->ccxt->market ($symbol);
                $request['market'] = $market['id'];
            }
            $response = $this->ccxt->privateGetOrders(['market' => $symbol]);    
            if ($response['success'] == true) {
                $conditional = $this->fetch_conditional_orders($symbol, false);
                $response['result'] = array_merge($response['result'], $conditional);
            }
            $results = $this->ccxt->safe_value($response, 'result', array());
            $filtered = [];
            foreach($results as $result) {
                if ($result['status'] == 'open') {
                    $filtered[] = $result;
                }
            }
            return $this->ccxt->parse_orders($filtered, $market, $since, $limit);
        }
    
        public function fetch_orders($symbol, $since = null, $limit = null, $params = []) {
            $request = [];
            $this->ccxt->load_markets();
            $market = null;
            if ($symbol !== null) {
                $market = $this->ccxt->market ($symbol);
                $request['market'] = $market['id'];
            }
            $response = $this->ccxt->privateGetOrdersHistory($request);
            $normalOrders = $this->ccxt->safe_value($response, 'result', array());
            $conditionalOrders = $this->fetch_conditional_orders($symbol, true);
            $result = $this->merge_order_result($normalOrders, $conditionalOrders);
            return $this->ccxt->parse_orders($result, null, $since, $limit);
        }

        // Create parameters for order
        public function order_params($params) {
            $typeMap = [
                'limit'     =>  'limit',
                'market'    =>  'market',
                'sllimit'   =>  'stop',
                'slmarket'  =>  'stop',
                'tplimit'   =>  'takeProfit', 
                'tpmarket'  =>  'takeProfit',
                'trailstop' =>  'trailingStop'  
            ];
            $result = [
                'symbol'    => $params['symbol'],
                'type'      => $typeMap[$params['type']],
                'side'      => $params['side'],
                'amount'    => $params['amount'],
                'price'     => isset($params['price']) ? $params['price'] : null,
                'params'    => []
            ];
            if (!in_array($params['type'],['limit','market'])) {
                $result['type']   =  $typeMap[$params['type']];
                $result['params'] = [
                    'type'   =>  $typeMap[$params['type']]
                ];
                if (substr($params['type'],0,2) == 'sl') {
                    $result['params']['orderPrice'] = isset($params['stopprice']) ? $params['stopprice'] : null;
                    $result['params']['triggerPrice'] = $params['stoptrigger'];
                    $result['params']['reduceOnly'] = (isset($params['reduce']) && (strtolower($params['reduce']) == "true")) ? true : false;
                }
                if ($params['type'] == 'trailstop') {
                    $result['params']['trailValue'] = isset($params['trailby']) ? $params['trailby'] : null;
                    $result['params']['reduceOnly'] = (isset($params['reduce']) && (strtolower($params['reduce']) == "true")) ? true : false;
                }
                if (substr($params['type'],0,2) == 'tp') {
                    $result['params']['orderPrice'] = isset($params['triggerprice']) ? $params['triggerprice'] : null;
                    $result['params']['triggerPrice'] = $params['profittrigger'];
                    $result['params']['reduceOnly'] = (isset($params['reduce']) && (strtolower($params['reduce']) == "true")) ? true : false;
                }
            }
            return $result;
        }

        // Parse order result
        public function parse_order($order) {
            if ((is_object($order)) && (get_class($order) == 'orderObject')) {
                return $order;
            }
            $market = $this->get_market_by_symbol($order['symbol']);
            $id = $order['id'];
            $timestamp = strtotime($order['timestamp'] / 1000);
            $type = strtolower($order['type']);
            $direction = (strtolower($order['side']) == 'buy' ? 'long' : 'short');
            $trigger = (isset($order['info']['triggerPrice']) ? $order['info']['triggerPrice'] : null);
            $price = (isset($order['price']) ? $order['price'] : (!is_null($trigger) ? $trigger : null));
            $sizeBase = $order['amount'];
            $sizeQuote = $order['amount'] * $price;
            $filledBase = $order['filled'];
            $filledQuote = $order['filled'] * $price;
            $status = str_replace('canceled', 'cancelled', $order['status']);
            $orderRaw = $order;
            return new \frostybot\orderObject($market,$id,$timestamp,$type,$direction,$price,$trigger,$sizeBase,$sizeQuote,$filledBase,$filledQuote,$status,$orderRaw);
        }

    }


?>