<?php

    // Helper function to check if required parameters are present

    function requiredParams($params, $required=[]) {
        foreach($required as $check) {
            if (!array_key_exists($check, $params)) {
                logger::error('Required parameter not found: '.$check);
            }
        }
        return $params;
    }

    // Bucketize OHLCV data into a different timeframe
    function bucketize($ohlcv, $timeframe) {
        $output = [];
        foreach ($ohlcv as $record) {
            $timestamp = ((floor($record->timestamp / 60 / $timeframe) * $timeframe) * 60) + ($timeframe * 60);
            $bucket = date('Y-m-d H:i:s', $timestamp);
            if (!isset($output[$bucket])) {
                $output[$bucket] = new ohlcvObject($record->symbol,$timeframe,$timestamp,$record->open,$record->high,$record->low,$record->close,$record->volume);
            } else {
                $output[$bucket]->high = $record->high > $output[$bucket]->high ? $record->high : $output[$bucket]->high;
                $output[$bucket]->low = $record->low < $output[$bucket]->low ? $record->low : $output[$bucket]->low;
                $output[$bucket]->close = $record->close;
                $output[$bucket]->volume += $record->volume;
            }
        }
        return array_values($output);
    }

    // Convert timeframe string into minutes
    function timeframeToMinutes($timeframe) {
        if (is_numeric($timeframe)) {
            return $timeframe;
        } else {
            $units = strtolower($timeframe[-1]);
            if ($units == 's') {
                return null;
            }
            $qty = (int) str_replace($units,'',strtolower($timeframe));
            switch ($units) {
                case 'm' : return $qty;
                case 'h' : return ($qty * 60);
                case 'd' : return ($qty * 1440);
                case 'w' : return ($qty * 10080);
                default : return $qty;
            }
        }
    }

    // Round all the elements of an array

    function roundall($arr, $precision = 5) {
        $retarr = [];
        foreach ($arr as $key => $val) {
            $retarr[$key] = round($val, $precision);
        }
        return $retarr;
    }

    // Extract a value from all array elements into a new array
    function array_extract($arr, $field) {
        $result = [];
        foreach($arr as $key => $item) {
            $result[$key] = is_array($item) ? $item[$field] : $item->$field;
        }
        return $result;
    }
    
    // Calculate Exponential moving average in a dataset

    function ema($data, $period = 9, $field = 'close', $emafield = 'ema') {
        $smoothing_constant = 2 / ($period + 1);
        $previous_EMA = null;
        foreach($data as $key => $row) {
            if ($key >= $period) {
                if (!isset($previous_EMA)) {
                    $sum = 0;
                    for ($i = $key - ($period-1); $i <= $key; $i ++)
                        $sum += $data[$i]->$field;
                    $sma = $sum / $period;
                    $data[$key]->$emafield = $sma;
                    $previous_EMA = $sma;
                } else {
                    $ema = ($data[$key]->$field - $previous_EMA) * $smoothing_constant + $previous_EMA;
                    $data[$key]->$emafield = $ema;
                    $previous_EMA = $ema;
                }
            }
        }
        return $data;
    }

    // Return the current global request UID (UID generate once per request)

    function requestuid() {
        if (!isset($GLOBALS['requestuid'])) {
            $GLOBALS['requestuid'] = md5(uniqid('requestuid', true));
        }
        return $GLOBALS['requestuid'];
    }

    // Check if supplied string is JSON

    function is_json($str) {
        json_decode($str);
        return (json_last_error() == JSON_ERROR_NONE);
    }

?>