<?php

    // Output normalizer for Deribit exchange

    class normalizer_deribit extends normalizer_base {

        public $orderSizing = 'quote';                  // Base or quote
        //public $ccxtParams = [
        //    'fetch_orders' => ['type'=>'any']
        //];
        public $cancelAllOrdersParams = [];

        // Get current balances
        public function fetch_balance($data) {
            $result = $data->result;
            $currency = 'BTC';
            $ticker = $this->ccxt->fetch_ticker('BTC-PERPETUAL');
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
            return [
                '15' => '15'   // Currently the FrostyAPI server only keeps 15m OHLCV data
            ];
        }

        // Get OHLCV data from FrostyAPI (The Deribit API does not provide this data)
        public function fetch_ohlcv($symbol, $timeframe, $count=100) {
            logger::debug('OHLCV data is not available for Deribit due to API limitations. Fetching OHLCV from FrostyAPI...');
            $symbolmap = [
                'BTC-PERPETUAL' =>  'DERIBIT_PERP_BTC_USD',
                'ETH-PERPETUAL' =>  'DERIBIT_PERP_ETH_USD',
            ];
            $frostyapi = new FrostyAPI();
            $search = [
                'objectClass'       =>  'OHLCV',
                'exchange'          =>  'deribit',
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

        /*
        // Parse raw trade data to build OHLCV data, very time consuming so replaced with FrostyAPI
        private function parse_trades($symbol, $timeframe, $trades) {
            $ohlcv = [];
            foreach($trades as $trade) {
                $trade = (array) $trade;
                $time = floor($trade['timeStamp'] / 1000);
                $timestamp = ((floor(($time / 60) / $timeframe) * $timeframe) * 60) - ($timeframe * 60);
                $price = $trade['price'];
                $open = $price;
                $high = $price;
                $low = $price;
                $close = $price;
                $volume = $trade['amount'];
                $rawEntry = $trade;
                $ohlcv[] = new ohlcvObject($symbol,$timeframe,$timestamp,$open,$high,$low,$close,$volume,$rawEntry);
            }
            $ohlcv = array_reverse(bucketize($ohlcv, $timeframe));
            return $ohlcv;

        }

        // Get OHLCV data by manually compiling it from trade history (very time consuming)
        public function fetch_ohlcv($symbol, $timeframe, $count=100) {
            logger::debug('OHLCV data is not available for Deribit due to API limitations. Generating OHLCV from trade history...');
            $apiurl = str_replace('https://','',strtolower($this->ccxt->urls['api']));
            $tradecache = cache::get('deribit:trade:history:'.$apiurl.':'.$symbol);
            if ($tradecache === false) {
                $tradecache = [];
                $trades = [];
                $mincache = null;
                $maxcache = null;
            } else {
                $tradecache = (array) $tradecache;
                $trades = $tradecache;
                $mincache = min(array_keys($trades));
                $maxcache = max(array_keys($trades));
            }
            $result = $this->ccxt->public_get_getlasttrades(['instrument' => $symbol,'count' => 1000]);
            $minseq = null;
            $time_start = time();
            do {
                if (is_null($minseq)) {
                    $result = $this->ccxt->public_get_getlasttrades(['instrument' => $symbol,'count' => 1000]);
                } else {
                    $result = $this->ccxt->public_get_getlasttrades(['instrument' => $symbol,'count' => 1000,'endSeq'=>($minseq-1)]);
                    if (count($result['result']) == 0) {
                        //logger::debug('Empty trade resultset, exiting loop...');
                        break;
                    }
                }
                foreach ($result['result'] as $trade) {
                    $seq = $trade['tradeSeq'];
                    if (array_key_exists($seq, $trades)) {
                        $minseq = min(array_keys($trades));
                        //logger::debug('Hit cache range, exiting loop...');
                        break;
                    } else {
                        $minseq = (is_null($minseq) ? $seq : ($seq < $minseq ? $seq : $minseq));
                        $trades[$seq] = $trade;
                    }
                }
                //logger::debug('New minseq: '.$minseq);
                $ohlcv = $this->parse_trades($symbol, $timeframe, $trades);
                $time_end = time();
                $duration = $time_end - $time_start;
            } while (!(($duration < 60) && (count($ohlcv) > $count)));
            cache::set('deribit:trade:history:'.$apiurl.':'.$symbol,$trades,true);
            logger::debug('Raw trades obtained: '.count($trades).' ('.count($tradecache).' from cache)');
            $ohlcv = $this->parse_trades($symbol, $timeframe, $trades);
            return $ohlcv;
        }
        */

        // Get list of markets from exchange
        public function fetch_markets($data) {
            $result = $data->result;
            $markets = [];
            foreach($result as $market) {
                if (($market['type'] != 'option') && ($market['quote'] == 'USD') && ($market['active'] == true)) {
                        $id = $market['symbol'];
                    $symbol = $market['symbol'];
                    $quote = $market['quote'];
                    $base = $market['base'];
                    $expiration_date = date('Y-m-d H:i:s', $market['info']['expiration_timestamp'] / 1000);
                    $expiration = (substr($expiration_date,0,4) == '3000' ? null : $expiration_date);
                    $bid = (isset($market['info']['bid']) ? $market['info']['bid'] : null);
                    $ask = (isset($market['info']['ask']) ? $market['info']['ask'] : null);
                    $contractSize = (isset($market['info']['contractSize']) ? $market['info']['contractSize'] : 1);
                    $marketRaw = $market;
                    $markets[] = new marketObject($id,$symbol,$base,$quote,$expiration,$bid,$ask,$contractSize,$marketRaw);
                }
            }
            return $markets;
        }

        // Get list of positions from exchange
        public function fetch_positions() {
            $result = [];
            $currencies = array_keys($this->ccxt->fetch_currencies());
            foreach ($currencies as $currency) {
                if ($currency != 'USD') {
                    $positions = $this->ccxt->private_get_get_positions(['currency' => $currency]);
                    foreach ($positions['result'] as $positionRaw) {
                        $market = $this->marketsById[$positionRaw['instrument_name']];
                        $base = $market->base;
                        $quote = $market->quote;
                        $direction = ($positionRaw['size']  == 0 ? 'flat' : ($positionRaw['size'] > 0 ? 'long' : ($positionRaw['size'] < 1 ? 'short' : 'null')));
                        $baseSize = abs($positionRaw['size_currency']);
                        $quoteSize = abs($positionRaw['size']);   
                        $entryPrice = $positionRaw['average_price'];
                        if (abs($baseSize) > 0) {
                            $result[] = new positionObject($market,$direction,$baseSize,$quoteSize,$entryPrice,$positionRaw);
                        }
                    }
                }
            }
            return $result;
        }

        // Create parameters for order
        public function order_params($params) {
            $typeMap = [
                'limit'     =>  'limit',
                'market'    =>  'market',
                'sllimit'   =>  'stop_limit',
                'slmarket'  =>  'stop_market',
                'tplimit'   =>  'limit',    // Deribit does not support take profit orders
                'tpmarket'  =>  'limit'     // Deribit does not support take profit orders
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
                'mark'      =>  'mark_price',
                'last'      =>  'last_price',
                'index'     =>  'index_price'
            ];
            if (!in_array($params['type'],['limit','market'])) {
                $result['type']   = strpos($params['type'],'limit') !== false ? 'limit' : 'market';
                $result['params'] = [
                    'type'   =>  $typeMap[$params['type']]
                ];
                if (substr($params['type'],0,2) == 'sl') {
                    $result['params']['price'] = isset($params['stopprice']) ? $params['stopprice'] : null;
                    $result['params']['stop_price']   = $params['stoptrigger'];
                    $result['params']['reduce_only'] = (isset($params['reduce']) && (strtolower($params['reduce']) == "true")) ? true : false;
                    $result['params']['trigger'] = (isset($params['triggertype'])) ? $triggerTypeMap[$params['triggertype']] : 'mark_price';
                }
                if (substr($params['type'],0,2) == 'tp') {
                    $result['params']['price'] = isset($params['profitprice']) ? $params['profitprice'] : $params['profittrigger'];
                    $result['params']['reduce_only'] = (isset($params['reduce']) && (strtolower($params['reduce']) == "true")) ? true : false;
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
            $timestamp = round($order['timestamp'] / 1000);
            $type = strtolower($order['type']);
            $direction = (strtolower($order['side']) == 'buy' ? 'long' : 'short');
            $trigger = (isset($order['info']['stop_price']) ? $order['info']['stop_price'] : null);
            $price = (isset($order['price']) ? $order['price'] : (!is_null($trigger) ? $trigger : null));
            $sizeBase = ($order['amount'] * $market->contract_size) / $price;
            $sizeQuote = ($order['amount'] * $market->contract_size);
            $filledBase = ($order['filled'] * $market->contract_size) / $price;
            $filledQuote = ($order['filled'] * $market->contract_size);
            $status = $order['status'] == 'canceled' ? 'cancelled' : ($order['status'] == 'untriggered' ? 'open' : $order['status']);
            $orderRaw = $order;
            return new orderObject($market,$id,$timestamp,$type,$direction,$price,$trigger,$sizeBase,$sizeQuote,$filledBase,$filledQuote,$status,$orderRaw);
        }

    }


?>
