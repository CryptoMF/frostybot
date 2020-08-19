<?php

    // Output normalizer for Binance exchange

    class normalizer_binance extends normalizer_base {

        public $orderSizing = 'base';          // Base or quote
        public $ccxtParams = [
        ];

        // Get current balances
        public function fetch_balance() {
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

        // Get list of markets from exchange
        public function fetch_markets() {
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

        // Get list of positions from exchange
        public function fetch_positions() {
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

        // Create parameters for order
        public function order_params($params) {
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
            $trigger = ($order['info']['stopPrice'] != 0 ? $order['info']['stopPrice'] : null);
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