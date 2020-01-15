<?php

    // Output normalizer for Deribit exchange

    class normalizer_deribit extends normalizer_base {

        public $orderSizing = 'quote';          // Base or quote

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
                    $expiration = (substr($market['info']['expiration'],0,4) == '3000' ? null : $market['info']['expiration']);
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
            $positions = $this->ccxt->private_get_positions();
            foreach ($positions['result'] as $positionRaw) {
                $market = $this->marketsById[$positionRaw['instrument']];
                $base = $market->base;
                $quote = $market->quote;
                $direction = ($positionRaw['size']  == 0 ? 'flat' : $positionRaw['size'] > 0 ? 'long' : ($positionRaw['size'] < 1 ? 'short' : 'null'));
                $baseSize = abs($positionRaw['delta']);
                $quoteSize = abs($positionRaw['amount']);
                $entryPrice = $positionRaw['averagePrice'];
                if (abs($baseSize) > 0) {
                    $result[] = new positionObject($market,$direction,$baseSize,$quoteSize,$entryPrice,$positionRaw);
                }
            }
            return $result;
        }

        // Create a stop loss order
        public function create_stoploss($symbol, $direction, $size, $trigger, $price = null, $reduce = true) {
            $params = [
                'instrument' => $symbol,
                'amount' => $size * 10,
                'type' => (is_null($price) ? 'stop_market' : 'stop_limit'),
                'stopPx' => $trigger,
                'reduceOnly' =>  $reduce,
                'execInst' => 'index_price'
            ];
            if (!is_null($price)) {
                $params['price'] = $price;
            }
            if ($direction == "sell") {
                return $this->ccxt->private_post_sell($params);
            } else {
                return $this->ccxt->private_post_buy($params);
            }
        }

        // Create a take profit order (on Deribit there is no specific take profit order type, so a standard limit order is used)
        public function create_takeprofit($symbol, $direction, $size, $trigger, $reduce = true) {
            $params = [
                'instrument' => $symbol,
                'amount' => $size * 10,
                'price' => $trigger,
                'type' => 'limit',
                'reduce_only' =>  $reduce
            ];
            if ($direction == "sell") {
                return $this->ccxt->private_post_sell($params);
            } else {
                return $this->ccxt->private_post_buy($params);
            }
        }

        // Parse order result
        public function parse_order($order) {
            if ((is_object($order)) && (get_class($order) == 'orderObject')) {
                return $order;
            }
            if (isset($order['result'])) {
                if (isset($order['result']['order'])) {
                    $order = $order['result']['order'];  // Fix some inconsistency in the API
                } else {
                    $order = $order['result'];  // Fix some inconsistency in the API
                }
            }
            $market = $this->get_market_by_symbol($order['instrument']);
            $id = $order['orderId'];
            $timestamp = strtotime($order['created']);
            $type = str_replace('_','',strtolower($order['type']));
            $direction = (strtolower($order['direction']) == 'buy' ? 'long' : 'short');
            $price = (isset($order['price']) ? $order['price'] : 1);
            $trigger = (isset($order['stopPx']) ? $order['stopPx'] : null);
            if ($type == "stopmarket") { $price = $trigger; }
            $sizeBase = $order['amount'] / $price;
            $sizeQuote = $order['amount'];
            $filledBase = (isset($order['filledAmount']) ? $order['filledAmount'] / $price : 0);
            $filledQuote = (isset($order['filledAmount']) ? $order['filledAmount'] : 0);
            $status = (strtolower($order['state']) == 'untriggered' ? 'open' : strtolower($order['state']));
            $orderRaw = $order;
            return new orderObject($market,$id,$timestamp,$type,$direction,$price,$trigger,$sizeBase,$sizeQuote,$filledBase,$filledQuote,$status,$orderRaw);
        }
        
        // Get list of orders from exchange
        public function fetch_orders($onlyOpen = false) {
            $markets = $this->markets;
            $ordersHistory = ($onlyOpen === true ? ['result' => []] : $this->ccxt->private_get_orderhistory());
            $ordersCurrent = [];
            $orders = $ordersHistory['result'];
            foreach ($this->markets as $market) {     // What an absolutely shyte API...
                $symbol = $market->symbol;
                $marketOrders = $this->ccxt->private_get_getopenorders(['type'=>'any','instrument'=>$symbol]);
                $orders = array_merge($orders, $marketOrders['result']);
            }
            $result = [];
            foreach ($orders as $order) {
                $result[] = $this->parse_order($order);
            }
            return $result;
        }

        // Cancel all orders
        // The deribit API implementation is buggy AF. It's safer to use the default of cycling through each open order and cancelling it.
        // Will have to test some more and see what the issue is, but right now it will not cancel order for all symbols if symbol is not provided.
        /*
        public function cancel_all_orders($symbol = null) { 
            if (!is_null($symbol)) {
                $result = $this->ccxt->private_post_cancelall(['instrument'=>$symbol]);
            } else {
                $result = $this->ccxt->private_post_cancelall(['type'=>'futures']);
            }
            if ((isset($result['success'])) && ($result['success'] === true)) {
                return true;
            } else {
                logger::error($result['result']);
                return false;
            }
        }
        */


    }


?>