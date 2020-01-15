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



    }

?>