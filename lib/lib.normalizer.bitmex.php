<?php

    // Output normalizer for Bitmex exchange

    class normalizer_bitmex extends normalizer_base {

        public $orderSizing = 'quote';          // Base or quote
        public $ccxtParams = [
        ];

        // Get current balances
        public function fetch_balance($data) {
            $result = $data->result;
            $currency = 'BTC';
            $ticker = $this->ccxt->fetch_ticker('BTC/USD');
            $price = $ticker['ask'];
            $balanceFree = $result['BTC']['free'];
            $balanceUsed = $result['BTC']['used'];
            $balanceTotal = $result['BTC']['total'];
            $balances = [];
            $balances['BTC'] = new balanceObject($currency,$price,$balanceFree,$balanceUsed,$balanceTotal);
            return $balances;
        }

        // Get supported OHLCV timeframes
        public function fetch_timeframes() {
            return [                // Use this if using the FrostyAPI for OHLCV data
                '15'    =>  '15',
            ];
            /*return [              // Use this if using the Bitmex API for OHLCV data
                '1'     => '1m',
                '5'     => '5m',
                '60'    => '1h',
                '1440'  => '1d',
            ];*/
        }

        // Get OHLCV data from FrostyAPI 
        public function fetch_ohlcv($symbol, $timeframe, $count=100) {
            logger::debug('Fetching OHLCV from FrostyAPI...');
            $symbolmap = [
                'BTC/USD'  =>  'BITMEX_PERP_BTC_USD',
                'ETH/USD'  =>  'BITMEX_PERP_ETH_USD',
            ];
            $frostyapi = new FrostyAPI();
            $search = [
                'objectClass'       =>  'OHLCV',
                'exchange'          =>  'bitmex',
                'symbol'            =>  $symbolmap[strtoupper($symbol)],
                'timeframe'         =>  $timeframe,
                'limit'             =>  $count,
                'sort'              =>  'timestamp_start:desc',
            ];
            $ohlcv = [];
            $result = $frostyapi->data->search($search);
            if (is_array($result->data)) {
                foreach ($result->data as $rawEntry) {
                    $timestamp = $rawEntry->timestamp_end;
                    $open = $rawEntry->open;
                    $high = $rawEntry->high;
                    $low = $rawEntry->low;
                    $close = $rawEntry->close;
                    $volume = $rawEntry->volume;
                    $ohlcv[] = new ohlcvObject($symbol,$timeframe,$timestamp,$open,$high,$low,$close,$volume,$rawEntry);
                }
            }
            return $ohlcv;
        }

        // Get OHLCV data from Bitmex API (replaced by the method above)
        /*
        public function fetch_ohlcv($symbol, $timeframe, $count=100) {
            $markets = $this->ccxt->fetch_markets();
            foreach($markets as $market) {
                if ($market['symbol'] == $symbol) {
                    $id=$market['id'];
                    break;
                }
            }
            $tfs = $this->fetch_timeframes();
            $binSize = $tfs[$timeframe];
            $ohlcvurl = $this->ccxt->urls['api'].'/api/v1/trade/bucketed?binSize='.$binSize.'&partial=true&symbol='.$id.'&count='.$count.'&reverse=true';
            $ohlcv = [];
            if ($rawOHLCV = json_decode(file_get_contents($ohlcvurl))) {
                $rawOHLCV = array_reverse($rawOHLCV);
                foreach ($rawOHLCV as $rawEntry) {
                    $timestamp = strtotime($rawEntry->timestamp);
                    $open = $rawEntry->open;
                    $high = $rawEntry->high;
                    $low = $rawEntry->low;
                    $close = $rawEntry->close;
                    $volume = $rawEntry->volume;
                    $ohlcv[] = new ohlcvObject($symbol,$timeframe,$timestamp,$open,$high,$low,$close,$volume,$rawEntry);
                }
            }
            return $ohlcv;
        }
        */

        // Get list of markets from exchange
        public function fetch_markets($data) {
            $result = $data->result;
            $markets = [];
            foreach($result as $market) {
                if (($market['quote'] == 'USD') && ($market['active'] == true) && ($market['info']['typ'] == 'FFWCSX')) {
                    $id = $market['id'];
                    $symbol = $market['symbol'];
                    $quote = $market['quote'];
                    $base = $market['base'];
                    $expiration = (isset($market['info']['expiration']) ? $market['info']['expiration'] : null);
                    $bid = (isset($market['info']['bid']) ? $market['info']['bid'] : null);
                    $ask = (isset($market['info']['ask']) ? $market['info']['ask'] : null);
                    $contractSize = (isset($market['info']['contractSize']) ? $market['info']['contractSize'] : 1);
                    $precision = $market['precision'];
                    $marketRaw = $market;
                    $markets[] = new marketObject($id,$symbol,$base,$quote,$expiration,$bid,$ask,$contractSize,$precision,$marketRaw);
                }
            }
            return $markets;
        }

        // Get list of positions from exchange
        public function fetch_positions() {
            $result = [];
            $positions = $this->ccxt->private_get_position();
            foreach ($positions as $positionRaw) {
                $market = $this->marketsById[$positionRaw['symbol']];
                $direction = $positionRaw['homeNotional']  == 0 ? 'flat' : ($positionRaw['homeNotional'] > 0 ? 'long' : ($positionRaw['homeNotional'] < 0 ? 'short' : 'null'));
                $baseSize = $positionRaw['homeNotional'];
                $quoteSize = $positionRaw['currentQty'];
                $entryPrice = $positionRaw['avgEntryPrice'];
                if (abs($baseSize) > 0) {
                    $result[] = new positionObject($market,$direction,$baseSize,$quoteSize,$entryPrice,$positionRaw);
                }
            }
            return $result;
        }

        // Create parameters for order
        public function order_params($params) {
            $typeMap = [
                'limit'     =>  'Limit',
                'market'    =>  'Market',
                'sllimit'   =>  'StopLimit',
                'slmarket'  =>  'Stop',
                'tplimit'   =>  'Limit',    // Bitmex does not support take profit orders
                'tpmarket'  =>  'Limit'     // Bitmex does not support take profit orders
            ];
            $result = [
                'symbol'    => $params['symbol'],
                'type'      => $typeMap[$params['type']],
                'side'      => $params['side'],
                'amount'    => $params['amount'],
                'price'     => isset($params['price']) ? $params['price'] : null,
                'params'    => []
            ];
            $triggerTypeMap = [
                'mark'      =>  'MarkPrice',
                'last'      =>  'LastPrice',
                'index'     =>  'IndexPrice'
            ];
            if (!in_array($params['type'],['limit','market'])) {
                $result['type']   = strpos($params['type'],'limit') !== false ? 'limit' : 'market';
                $result['params'] = [
                    'ordType'   =>  $typeMap[$params['type']]
                ];
                if (substr($params['type'],0,2) == 'sl') {
                    $result['price'] = isset($params['stopprice']) ? $params['stopprice'] : null;
                    $result['params']['stopPx']   = $params['stoptrigger'];
                    $result['params']['execInst'] = (isset($params['reduce']) && (strtolower($params['reduce']) == "true")) ? 'ReduceOnly' : 'Close';
                    $result['params']['execInst'] .= (isset($params['triggertype'])) ? ','.$triggerTypeMap[$params['triggertype']] : '';
                }
                if (substr($params['type'],0,2) == 'tp') {
                    $result['price'] = isset($params['profitprice']) ? $params['profitprice'] : $params['profittrigger'];
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
            $trigger = (isset($order['info']['stopPx']) ? $order['info']['stopPx'] : null);
            $price = (isset($order['price']) ? $order['price'] : (!is_null($trigger) ? $trigger : null));
            $sizeBase = $order['amount'] / $price;
            $sizeQuote = $order['amount'];
            $filledBase = $order['filled'] / $price;
            $filledQuote = $order['filled'];
            $status = $order['status'];
            $orderRaw = $order;
            return new orderObject($market,$id,$timestamp,$type,$direction,$price,$trigger,$sizeBase,$sizeQuote,$filledBase,$filledQuote,$status,$orderRaw);
        }     

    }


?>