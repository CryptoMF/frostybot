<?php

    // Output normalizer for Binance exchange

    class normalizer_binance extends normalizer_base {

        public $orderSizing = 'base';          // Base or quote
        public $ccxtParams = [
        ];

        // Get balance on Spot Exchange
        private function fetch_balance_spot() {
            $tickersRaw = $this->ccxt->v3_get_ticker_bookticker();
            $tickers = [];
            foreach ($tickersRaw as $tickerRaw) {
                $tickers[$tickerRaw['symbol']] = $tickerRaw;
            }
            $result = @$this->ccxt->fetch_balance();  // Had to kill the output because CCXT throws some errors
            unset($result['info']);
            unset($result['free']);
            unset($result['used']);
            unset($result['total']);
            $balances = [];
            foreach ($result as $currency => $balance) {
                if (in_array($currency, ['USDT','BUSD'])) {
                    $price = 1;
                } else {
                    if (isset($tickers[$currency.'USDT'])) {
                        $ticker = $tickers[$currency.'USDT'];
                        $price = $ticker['askPrice'];        
                    } else {
                        $ticker = $this->ccxt->fetch_ticker($currency.'/USDT');
                        $price = !is_null($ticker['ask']) ? $ticker['ask'] : $ticker['last'];
                    }
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

        // Get balance on Future Exchange
        private function fetch_balance_futures() {
            $result = @$this->ccxt->fetch_balance();  // Had to kill the output because CCXT throws some errors
            foreach ($result['info']['assets'] as $asset) {
                $currency = $asset['asset'];
                if ($currency != 'USDT') {
                    $ticker = $this->ccxt->fetch_ticker($currency.'/USDT');
                    $price = !is_null($ticker['ask']) ? $ticker['ask'] : $ticker['last'];
                } else {
                    $price = 1;
                }
                $balanceFree = (float) $asset['availableBalance'];
                $balanceTotal = (float) $asset['marginBalance'];
                $balanceUsed = $balanceTotal - $balanceFree;
                $balances[$currency] = new \frostybot\balanceObject($currency,$price,$balanceFree,$balanceUsed,$balanceTotal);
            }
            return $balances;
        }


        // Get current balances
        public function fetch_balance() {
            $exchange = $this->ccxt->options['defaultType'];
            switch ($exchange) {
                case 'spot'     :   return $this->fetch_balance_spot();
                case 'future'   :   return $this->fetch_balance_futures();
            }
        }

        // Get list of markets from Spot Exchange
        private function fetch_markets_spot() {
            $tickersRaw = $this->ccxt->v3_get_ticker_bookticker();
            $tickers = [];
            foreach ($tickersRaw as $tickerRaw) {
                $tickers[$tickerRaw['symbol']] = $tickerRaw;
            }
            $result = $this->ccxt->fetch_markets();
            $markets = [];
            foreach($result as $market) {
                if ((in_array($market['quote'], ['USDT', 'BUSD', 'USD'])) && ($market['active'] == true)) {
                    $id = $market['id'];
                    $symbol = $market['symbol'];
                    $quote = $market['quote'];
                    $base = $market['base'];
                    $expiration = null;
                    $bid = null;
                    $ask = null;
                    if (isset($tickers[$id])) {
                        $bid = (isset($tickers[$id]) ? (float) $tickers[$id]['bidPrice'] : null);
                        $ask = (isset($tickers[$id]) ? (float) $tickers[$id]['askPrice'] : null);    
                    } else {
                        $ticker = $this->ccxt->fetch_ticker($base.'/USDT');
                        $bid = (isset($ticker['bid']) ? $ticker['bid'] : null);
                        $ask = (isset($ticker['ask']) ? $ticker['ask'] : null);
                    }
                    //$expiration = (isset($market['info']['expiration']) ? $market['info']['expiration'] : null);
                    $contractSize = 1;
                    $precision = [
                        'amount' => $market['limits']['amount']['min'],
                        'price' => $market['limits']['price']['min']
                    ];
                    $marketRaw = $market;
                    $markets[] = new \frostybot\marketObject($id,$symbol,$base,$quote,$expiration,$bid,$ask,$contractSize,$precision,$marketRaw);
                }
            }
            return $markets;
        }        

        // Get list of markets from Futures Exchange
        private function fetch_markets_futures() {
            $tickersRaw = $this->ccxt->fapiPublic_get_ticker_bookticker();
            $tickers = [];
            foreach ($tickersRaw as $tickerRaw) {
                $tickers[$tickerRaw['symbol']] = $tickerRaw;
            }
            $result = $this->ccxt->fetch_markets();
            $markets = [];
            foreach($result as $market) {
                if ((in_array($market['quote'], ['USDT', 'USD'])) && ($market['active'] == true)) {
                    $id = $market['id'];
                    $symbol = $market['symbol'];
                    $quote = $market['quote'];
                    $base = $market['base'];
                    $expiration = null;
                    //$expiration = (isset($market['info']['expiration']) ? $market['info']['expiration'] : null);
                    $bid = (isset($tickers[$id]) ? (float) $tickers[$id]['bidPrice'] : null);
                    $ask = (isset($tickers[$id]) ? (float) $tickers[$id]['askPrice'] : null);
                    $contractSize = 1;
                    $precision = [
                        'amount' => $market['limits']['amount']['min'],
                        'price' => $market['limits']['price']['min']
                    ];
                    $marketRaw = $market;
                    $markets[] = new \frostybot\marketObject($id,$symbol,$base,$quote,$expiration,$bid,$ask,$contractSize,$precision,$marketRaw);
                }
            }
            return $markets;
        }

        // Get list of markets
        public function fetch_markets() {
            $exchange = $this->ccxt->options['defaultType'];
            switch ($exchange) {
                case 'spot'     :   return $this->fetch_markets_spot();
                case 'future'   :   return $this->fetch_markets_futures();
            }
        }

        // Cancel all orders of the Spot Exchange
        private function cancel_all_orders_spot($symbol, $params) {
            return @$this->ccxt->cancel_all_orders($symbol);
        }
        
        // Cancel all orders on the Futures Exchange
        private function cancel_all_orders_futures($symbol, $params) {
            $orders = $this->ccxt->fetch_open_orders($symbol);
            $result = @$this->ccxt->cancel_all_orders($symbol, $params);
            if ($result['code'] == 200) {
                return $orders;
            }
            return false;
        }

        // Cancel all open orders
        public function cancel_all_orders($symbol, $params = []) {
            $exchange = $this->ccxt->options['defaultType'];
            switch ($exchange) {
                case 'spot'     :   return $this->cancel_all_orders_spot($symbol, $params);
                case 'future'   :   return $this->cancel_all_orders_futures($symbol, $params);
            }
        }

        // Get list of positions from Spot Exchange
        // Note: Since a spot exchange does not have the concept of "positions", positions are emulated from current balances of assets against USDT
        private function fetch_positions_spot() {
            $balances = $this->fetch_balance_spot();
            $result = [];
            foreach ($balances as $currency => $balance) {
                if (!in_array($currency, ['BUSD','USDT'])) {
                    $market = $this->marketsById[$currency.'USDT'];
                    $direction = 'long';
                    $baseSize = $balance->balance_cur_total;
                    $quoteSize = $balance->balance_usd_total;
                    $entryPrice = $balance->price;
                    if (abs($baseSize) > 0) {
                        $result[] = new \frostybot\positionObject($market,$direction,$baseSize,$quoteSize,$entryPrice,$balance);
                    }
                }
            }
            return $result;
        }

        // Get list of positions from Futures Exchange
        private function fetch_positions_futures() {
            $positionsRaw = @$this->ccxt->fapiPrivate_get_positionrisk();
            $result = [];
            foreach ($positionsRaw as $positionRaw) {
                $market = $this->marketsById[$positionRaw['symbol']];
                $direction = $positionRaw['positionAmt']  == 0 ? 'flat' : ($positionRaw['positionAmt'] > 0 ? 'long' : ($positionRaw['positionAmt'] < 0 ? 'short' : 'null'));
                $baseSize = $positionRaw['positionAmt'];
                $quoteSize = $positionRaw['positionAmt'] * $positionRaw['entryPrice'];
                $entryPrice = $positionRaw['entryPrice'];
                if (abs($baseSize) > 0) {
                    $result[] = new \frostybot\positionObject($market,$direction,$baseSize,$quoteSize,$entryPrice,$positionRaw);
                }
            }
            return $result;
        }

        // Get list of positions
        public function fetch_positions() {
            $exchange = $this->ccxt->options['defaultType'];
            switch ($exchange) {
                case 'spot'     :   return $this->fetch_positions_spot();
                case 'future'   :   return $this->fetch_positions_futures();
            }
        }

        // Create parameters for orders on the Spot Exchange
        private function order_params_spot($params) {
            $typeMap = [
                'limit'     =>  'LIMIT',
                'market'    =>  'MARKET',
                'sllimit'   =>  'STOP_LOSS_LIMIT',
                'slmarket'  =>  'STOP_LOSS_LIMIT',    // Market stops are not supported by the Binance Spot API, even through their documentation says it is
                'tplimit'   =>  'TAKE_PROFIT_LIMIT', 
                'tpmarket'  =>  'TAKE_PROFIT',
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
                $result['params'] = [];
                if (substr($params['type'],0,2) == 'sl') {
                    $result['price'] = isset($params['stopprice']) ? $params['stopprice'] : $params['stoptrigger'];
                    $result['params']['stopPrice'] = $params['stoptrigger'];
                }
                if (substr($params['type'],0,2) == 'tp') {
                    $result['price'] = isset($params['profitprice']) ? $params['profitprice'] : null;
                    $result['params']['stopPrice'] = $params['profittrigger'];
                }
                if (isset($params['reduce']) && (strtolower($params['reduce']) == "true")) {
                    $result['params']['reduceOnly'] = (string) "true";
                }
            }
            return $result;
        }

        // Create parameters for orders on the Futures Exchange
        private function order_params_futures($params) {
            $typeMap = [
                'limit'     =>  'LIMIT',
                'market'    =>  'MARKET',
                'sllimit'   =>  'STOP',
                'slmarket'  =>  'STOP_MARKET',
                'tplimit'   =>  'TAKE_PROFIT', 
                'tpmarket'  =>  'TAKE_PROFIT_MARKET',
                'trailstop' =>  'TRAILING_STOP_MARKET'  
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
                $result['params'] = [];
                if (substr($params['type'],0,2) == 'sl') {
                    $result['price'] = isset($params['stopprice']) ? $params['stopprice'] : null;
                    $result['params']['stopPrice'] = $params['stoptrigger'];
                }
                if ($params['type'] == 'trailstop') {
                    $result['params']['trailValue'] = isset($params['trailby']) ? $params['trailby'] : null;
                }
                if (substr($params['type'],0,2) == 'tp') {
                    $result['price'] = isset($params['profitprice']) ? $params['profitprice'] : null;
                    $result['params']['stopPrice'] = $params['profittrigger'];
                }
                if (isset($params['reduce']) && (strtolower($params['reduce']) == "true")) {
                    $result['params']['reduceOnly'] = (string) "true";
                }
            }
            return $result;
        }

        // Create parameters for order
        public function order_params($params) {
            $exchange = $this->ccxt->options['defaultType'];
            switch ($exchange) {
                case 'spot'     :   return $this->order_params_spot($params);
                case 'future'   :   return $this->order_params_futures($params);
            }
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
            $trigger = ((isset($order['info']['stopPrice']) && ($order['info']['stopPrice'] != 0)) ? $order['info']['stopPrice'] : null);
            $price = (isset($order['price']) ? $order['price'] : (!is_null($trigger) ? $trigger : null));
            if ((strtolower($type) == 'market') || ($price == null)) {
                $price = $direction == 'buy' ? $market->ask : $market->bid;
            }
            $sizeBase = $order['amount'];
            $sizeQuote = $order['amount'] * $price;
            $filledBase = $order['filled'];
            $filledQuote = $order['filled'] * $price;
            $status = str_replace('canceled', 'cancelled', $order['status']);
            $orderRaw = $order;
            return new \frostybot\orderObject($market,$id,$timestamp,$type,$direction,$price,$trigger,$sizeBase,$sizeQuote,$filledBase,$filledQuote,$status,$orderRaw);
        }     

        // Get supported OHLCV timeframes
        public function fetch_timeframes() {
            return [
                "1"     => "1m",
                "3"     => "3m",
                "5"     => "5m",
                "15"    => "15m",
                "30"    => "30m",
                "60"    => "1h",
                "120"   => "2h",
                "240"   => "4h",
                "360"   => "6h",
                "480"   => "8h",
                "720"   => "12h",
                "1440"  => "1d",
                "4320"  => "3d",
                "10080" => "1w"
            ];
        }

        // Get OHLCV data
        public function fetch_ohlcv($symbol, $timeframe, $count=100) {
            $tfs = $this->fetch_timeframes();
            $actualtf = $tfs[$timeframe];
            $rawOHLCV = $this->ccxt->fetch_ohlcv($symbol, $actualtf, null, $count);
            $ohlcv = [];
            foreach ($rawOHLCV as $rawEntry) {
                list($ts, $open, $high, $low, $close, $volume) = $rawEntry;
                $timestamp = (float) $ts / 1000;
                $ohlcv[] = new \frostybot\ohlcvObject($symbol,$timeframe,$timestamp,$open,$high,$low,$close,$volume,$rawEntry);
            }
            return $ohlcv;
        }        


    }
    

?>