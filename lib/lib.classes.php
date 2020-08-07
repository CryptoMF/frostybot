<?php

    // Base Frostybot Object

    abstract class frostyObject {

        private $properties = [];

        public function __get($var) {
            return (isset($this->properties[$var]) ? $this->properties[$var] : null);
        }

        public function __set($var, $val) {
            $this->properties[$var] = $val;
        }

        public function __isset($var) {
            return isset($this->properties[$var]);
        }

        public function __unset($var) {
            unset($this->properties[$var]);
        }

        public function __toString() {
            return $this->toJSON();
        }

        public function toJSON() {
            return json_encode($this->toObject(), JSON_PRETTY_PRINT);
        }

        public function toObject() {
            return (object) $this->properties;
        }

    }

    // OHLCV Object

    class ohlcvObject extends frostyObject {

        public $symbol;
        public $timeframe;
        public $timestamp;
        public $datetime;
        public $open;
        public $high;
        public $low;
        public $close;
        public $volume;
        public $complete;
        //public $raw;        

        public function __construct($symbol,$timeframe,$timestamp,$open,$high,$low,$close,$volume,$raw = null) {
            $this->symbol = $symbol;
            $this->timeframe = (int) $timeframe;
            $this->timestamp = (int) $timestamp;
            $this->datetime = date('Y-m-d H:i:00', $timestamp);
            $this->open = $open;
            $this->high = $high;
            $this->low = $low;
            $this->close = $close;
            $this->volume = $volume;
            $this->complete = ($timestamp < time('UCT'));
            //if (debug === true) {
            //    $this->raw = $raw;      
            //}
        }

    }

    // Balance Object

    class balanceObject extends frostyObject {

        public $currency;
        public $price;
        public $balance_cur_used;
        public $balance_cur_free;
        public $balance_cur_total;
        public $balance_usd_used;
        public $balance_usd_free;
        public $balance_usd_total;

        public function __construct($currency,$price,$balanceFree,$balanceUsed,$balanceTotal) {
            $this->currency = $currency;
            $this->price = $price;
            $this->balance_cur_used = $balanceUsed;
            $this->balance_cur_free = $balanceFree;
            $this->balance_cur_total = $balanceTotal;
            $this->balance_usd_used = round($this->balance_cur_used * $price,2,PHP_ROUND_HALF_DOWN);
            $this->balance_usd_free = round($this->balance_cur_free * $price,2,PHP_ROUND_HALF_DOWN);
            $this->balance_usd_total = round($this->balance_cur_total * $price,2,PHP_ROUND_HALF_DOWN);
        }

    }

    // Market Object

    class marketObject extends frostyObject {

        public $id;
        public $symbol;
        public $base;
        public $quote;
        public $expiration;
        public $bid;
        public $ask;
        public $contract_size;
        public $precision;
        //public $raw;        

        public function __construct($id,$symbol,$base,$quote,$expiration,$bid,$ask,$contractSize,$precision,$raw = null) {
            $this->id = $id;
            $this->symbol = $symbol;
            $this->base = $base;
            $this->quote = $quote;
            $this->expiration = is_null($expiration) ? null : date('c', strtotime($expiration));
            $this->bid = $bid;
            $this->ask = $ask;
            $this->contract_size = $contractSize;
            $this->precision = (object) $precision;
            //if (debug === true) {
            //    $this->raw = $raw;   
            //}
        }

    }

    // Position Object

    class positionObject extends frostyObject {

        public $market;     // marketObject
        public $direction;
        public $size_base;
        public $size_quote;
        public $price_entry;
        public $price_current;
        public $value_entry;
        public $value_current;
        public $pnl;
        //public $raw;        

        public function __construct($market,$direction,$baseSize,$quoteSize,$entryPrice,$raw = null) {
            $this->market = $market;
            $this->direction = $direction;
            $currentPrice = ($direction == "long" ? $market->ask : $market->bid);
            $this->size_base = abs($baseSize);
            $this->size_quote = abs($quoteSize);
            $this->price_entry = round($entryPrice,4);
            $this->price_current = round($currentPrice,4);
            $this->value_entry = round(abs($this->price_entry * $baseSize),4);
            $this->value_current = round(abs($this->price_current * $baseSize),4);
            $this->pnl = $this->value_current - $this->value_entry;
            //if (debug === true) {
            //    $this->raw = $raw;     
            //}
        }

    }

    class orderObject extends frostyObject {

        public $market;
        public $id;
        public $timestamp;
        public $type;               // limit/stoploss
        public $direction;          // long/short
        public $price;
        public $trigger = null;     // For stoploss order
        public $size_base;
        public $size_quote;
        public $filled_base = 0;
        public $filled_quote = 0;
        public $filled = false;
        public $status;
        //public $raw;              

        public function __construct($market,$id,$timestamp,$type,$direction,$price,$trigger,$sizeBase,$sizeQuote,$filledBase,$filledQuote,$status,$raw = null) {
            $this->market = $market;
            $this->id = $id;
            $this->timestamp = $timestamp;
            $this->type = $type;
            $this->direction = $direction;
            $this->price = $price;
            $this->trigger = $trigger;
            $this->size_base = $sizeBase;
            $this->size_quote = $sizeQuote;
            $this->filled_base = $filledBase;
            $this->filled_quote = $filledQuote;
            $this->filled = $filledBase >= $sizeBase ? true : false;
            $this->status = $status;
            //if (debug === true) {
            //    $this->raw = $raw;    
            //}
        }

    }

    class linkedOrderObject extends frostyObject {

        public $id;
        public $stub;
        public $symbol;
        public $status = 0;
        public $orders = [];

        public function __construct($id = null) {
            $args = func_get_args();
            if ( (count($args) == 1) && ( strlen($args[0]) == 32) ) {   // Just the id which is used to load the object from database     
                $this->id = $args[0];
                $this->retrieve();
            }
            if (count($args) == 2) {                                    // Stub and Symbol which is used to create new linked objects
                list($stub, $symbol) = $args;
                $this->id = md5(uniqid('',true));
                $this->stub = $stub;
                $this->symbol = $symbol;
            }

        }

        public function add($order) {
            $this->orders[$order->id] = $order;
            $this->save();
        }

        private function save() {
            $db = new DB();
            $data = [
                'modified'  => 'CURRENT_TIMESTAMP',
                'id'        => $this->id,
                'status'    => $this->status,
                'stub'      => $this->stub,
                'symbol'    => $this->symbol,
                'orders'    => json_encode($this->orders, JSON_PRETTY_PRINT),
            ];
            if ($db->insertOrUpdate('linkedorders', $data, ['id'=>$this->id])) {
                logger::debug('Succesfully Saved linked order '.$this->id.' to the database');
                return true;
            } else {
                logger::debug('Error saving linked order '.$this->id.' to the database');
                return true;
            }
        }

        private function retrieve() {
            $db = new DB();
            $result = $db->select('linkedorders', ['id'=>$this->id]);
            if (count($result) == 1) {
                $row = $result[0];
                $this->stub = $row->stub;
                $this->symbol = $row->symbol;
                $this->status = $row->status;
                $this->orders = json_decode($row->orders);
            }
        }

    }

?>
