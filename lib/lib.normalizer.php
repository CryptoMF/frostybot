<?php

    // Base class from which all normalizers should be extended

    abstract class normalizer_base {

        public $ccxt;
        public $options;
        public $markets;
        private $marketsById;
        private $marketsBySymbol;

        public function __construct($ccxt, $options = []) {
            $this->ccxt = $ccxt;
            $this->options = $options;
        }

        private function get_market_by_symbol($symbol) {
            if (array_key_exists($symbol, $this->marketsBySymbol)) {
                return $this->marketsBySymbol[$symbol];
            }
            return false;
        }

        private function get_market_by_id($id) {
            if (array_key_exists($symbol, $this->marketsBySymbol)) {
                return $this->marketsBySymbol[$symbol];
            }
            return false;
        }

        private function roundall($arr, $precision = 5) {
            $retarr = [];
            foreach ($arr as $key => $val) {
                $retarr[$key] = round($val, $precision);
            }
            return $retarr;
        }



    }

?>