<?php

    // Output normalizer for FTX exchange

    class normalizer_ftx extends normalizer_base {

        public $orderSizing = 'base';          // Base or quote

        // Get current balances
        public function fetch_balance($data) {
            $result = $data->result;
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
                    $balances[$currency] = new balanceObject($currency,$price,$balanceFree,$balanceUsed,$balanceTotal);
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
            $ohlcvurl = $this->ccxt->urls['api'].'/api/markets/'.$symbol.'/candles?resolution='.((int) $timeframe).'&limit='.$count.'&end_time='.$endtime;
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
                    $ohlcv[] = new ohlcvObject($symbol,$actualtf,$timestamp,$open,$high,$low,$close,$volume,$rawEntry);
                }
            }
            return $ohlcv;
        }        

        // Get list of markets from exchange
        public function fetch_markets($data) {
            $result = $data->result;
            $markets = [];
            $marketFilters = ['BEAR','BULL','MOON','DOOM','HEDGE','MOVE'];
            foreach($result as $market) {
                if ((in_array($market['type'],['spot','future'])) && ($market['quote'] == 'USD') && ($market['base'] != 'USDT') && ($market['active'] == true) && (!is_numeric(substr($market['symbol'],-4)))) {
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
                        $marketRaw = $market;
                        $markets[] = new marketObject($id,$symbol,$base,$quote,$expiration,$bid,$ask,$contractSize,$marketRaw);
                    }
                }
            }
            return $markets;
        }

        // Get list of positions from exchange
        public function fetch_positions() {
            $result = [];
            $positions = $this->ccxt->private_get_positions();
            foreach ($positions['result'] as $positionRaw) {
                $market = $this->marketsBySymbol[$positionRaw['future']];
                $direction = $positionRaw['size']  == 0 ? 'flat' : ($positionRaw['side'] == 'buy' ? 'long' : ($positionRaw['side'] == 'sell' ? 'short' : 'null'));
                $baseSize = round($positionRaw['size'],5);
                $entryPrice = $positionRaw['entryPrice'];
                $quoteSize = $baseSize * $entryPrice;
                if (abs($baseSize) > 0) {
                    $result[] = new positionObject($market,$direction,$baseSize,$quoteSize,$entryPrice,$positionRaw);
                }
            }
            return $result;
        }

        // Create a stop loss order
        public function create_stoploss($symbol, $direction, $size, $trigger, $price = null, $reduce = true) {
            $params = [
                'market' => $symbol,
                'side' => $direction,
                'size' => $size,
                'type' => 'stop',
                'triggerPrice' => $trigger,
                'reduceOnly' =>  $reduce,
            ];
            if (!is_null($price)) {
                $params['orderPrice'] = $price;
            }
            return $this->ccxt->private_post_conditional_orders($params);
        }

        // Create a take profit order
        public function create_takeprofit($symbol, $direction, $size, $trigger, $reduce = true) {
            $params = [
                'market' => $symbol,
                'side' => $direction,
                'size' => $size,
                'type' => 'takeProfit',
                'triggerPrice' => $trigger,
                'reduceOnly' =>  $reduce,
            ];
            return $this->ccxt->private_post_conditional_orders($params);
        }

        // Parse order result
        public function parse_order($market, $order) {
            if (isset($order['result'])) {
                $order = $order['result'];  // Fix some inconsistency in the API
            }
            $id = $order['id'];
            $timestamp = strtotime($order['createdAt']);
            $type = strtolower($order['type']);
            $direction = (strtolower($order['side']) == 'buy' ? 'long' : 'short');
            $price = (isset($order['orderPrice']) ? $order['orderPrice'] : (isset($order['price']) ? $order['price'] : (isset($order['avgFillPrice']) ? $order['avgFillPrice'] : null)));
            $trigger = (isset($order['triggerPrice']) ? $order['triggerPrice'] : null);
            $sizeBase = $order['size'];
            $sizeQuote = $order['size'] * $price;
            $filledBase = isset($order['filledSize']) ? $order['filledSize'] : 0;
            $filledQuote = $filledBase * $price;
            $status = ((strtolower($order['status']) == 'new') ? 'open' : strtolower($order['status']));
            if ($type == "stop") {
                if (is_null($price)) {
                    $type = "stopmarket";
                    $price = $trigger;
                } else {
                    $type = "stoplimit";
                }
            }
            $orderRaw = $order;
            return new orderObject($market,$id,$timestamp,$type,$direction,$price,$trigger,$sizeBase,$sizeQuote,$filledBase,$filledQuote,$status,$orderRaw);
        }
     
        // Get list of orders from exchange
        public function fetch_orders($markets, $onlyOpen = false) {
            if ($onlyOpen) {
                $ordersNormal = $this->ccxt->private_get_orders();
            } else {
                $ordersNormal = $this->ccxt->private_get_orders_history();
            }
            $ordersTrigger = $this->ccxt->private_get_conditional_orders();
            $orders = array_merge($ordersNormal['result'],$ordersTrigger['result']);
            $result = [];
            foreach ($orders as $order) {
                foreach ($markets as $market) {
                    if ($order['future'] === $market->symbol) {
                        $result[] = $this->parse_order($market, $order);
                    }
                }
            }
            return $result;
        }

        // Cancel order
        public function cancel_order($id) {
            $normalOrders = $this->ccxt->private_get_orders();
            $triggerOrders = $this->ccxt->private_get_conditional_orders();
            $orders = array_merge($normalOrders['result'],$triggerOrders['result']);
            foreach($orders as $order) {
                if ($order['id'] == $id) {
                    if (array_key_exists('triggerPrice',$order)) {
                        $result = $this->ccxt->private_delete_conditional_orders_order_id(['order_id'=>$id]);
                    } else {
                        $result = $this->ccxt->private_delete_orders_order_id(['order_id'=>$id]);
                    }
                    if ((isset($result['success'])) && ($result['success'] === true)) {
                        return true;
                    } else {
                        logger::error($result['result']);
                        return false;
                    }
                }
            }
            return false;
        }

        // Cancel all orders
        public function cancel_all_orders($symbol = null) { 
            if (!is_null($symbol)) {
                $result = $this->ccxt->private_delete_orders(['market'=>$symbol]);
            } else {
                $result = $this->ccxt->private_delete_orders();
            }
            if ((isset($result['success'])) && ($result['success'] === true)) {
                return true;
            } else {
                logger::error($result['result']);
                return false;
            }
        }


    }


?>