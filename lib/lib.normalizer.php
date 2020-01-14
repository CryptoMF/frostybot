<?php

    // Base class from which all normalizers should be extended

    abstract class normalizer_base {

        public $ccxt;
        public $options;

        public function __construct($ccxt, $options = []) {
            $this->ccxt = $ccxt;
            $this->options = $options;
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