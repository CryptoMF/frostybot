<?php

    // Base class from which all normalizers should be extended

    abstract class normalizer_base {

        public $ccxt;
        public $options;
        public $markets;
        public $marketsById;
        public $marketsBySymbol;

        public function __construct($ccxt, $options = []) {
            $this->ccxt = $ccxt;
            $this->options = $options;
        }

        public function get_market_by_symbol($symbol) {
            if (array_key_exists($symbol, $this->marketsBySymbol)) {
                return $this->marketsBySymbol[$symbol];
            }
            return false;
        }

        public function get_market_by_id($id) {
            if (array_key_exists($id, $this->marketsById)) {
                return $this->marketsById[$id];
            }
            return false;
        }

        public function roundall($arr, $precision = 5) {
            $retarr = [];
            foreach ($arr as $key => $val) {
                $retarr[$key] = round($val, $precision);
            }
            return $retarr;
        }

        protected function update_order_status($order, $status) {
            if (array_key_exists('status', $order)) {
                $order['info']['status'] = $status;
                $order['status'] = $status;
            } else {
                foreach($order as $key => $val) {
                    $order[$key] = $this->update_order_status($val, $status);
                }
            }
            return $order;
        }

        protected function merge_order_result($orders1, $orders2) {
            $orders1 = (array_key_exists('status', $orders1) ? [$orders1] : $orders1);
            $orders2 = (array_key_exists('status', $orders2) ? [$orders2] : $orders2);
            $orders = array_merge($orders1, $orders2);
            return count($orders) == 1 ? $orders[0] : $orders;
        }


    }

?>