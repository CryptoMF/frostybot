<?php


// Frostybot CCXT Override class for Deribit

namespace ccxt;

use Exception; // a common import

class deribit_frostybot extends deribit {

    public function describe () {
        $describe = parent::describe ();
        //$describe['has']['cancelAllOrders'] = false;
        return $describe;
    }

    private function update_order_status($order, $status) {
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

    public function cancel_all_orders($symbol = null, $params = array ()) {
        $orders = $this->fetch_open_orders($symbol = null);
        $result = parent::cancel_all_orders($symbol, $params);
        if ((is_array($result)) && ($result['result'] == count($orders))) {
            return $this->update_order_status($orders, 'canceled');
        }
        return $orders;
    }

}
