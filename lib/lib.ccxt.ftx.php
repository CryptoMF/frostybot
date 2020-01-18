<?php


// Frostybot CCXT Override class for FTX

namespace ccxt;

use Exception; // a common import

class ftx_frostybot extends ftx {

    public function describe () {
        $describe = parent::describe ();
        $describe['api']['private']['get'][] = 'conditional_orders/history';   // Missing endpoint added for FrostyBot
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

    private function merge_order_result($orders1, $orders2) {
        $orders1 = (array_key_exists('status', $orders1) ? [$orders1] : $orders1);
        $orders2 = (array_key_exists('status', $orders2) ? [$orders2] : $orders2);
        $orders = array_merge($orders1, $orders2);
        return count($orders) == 1 ? $orders[0] : $orders;
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $request = array (
            'order_id' => intval ($id),
        );

        $order = $this->fetch_order($id, $symbol);

        if (in_array($order['type'],['limit','market'])) {  // If it's not a standard order, then it must be a conditional order
            $response = $this->privateDeleteOrdersOrderId (array_merge ($request, $params));
        } else {
            $response = $this->privateDeleteConditionalOrdersOrderId (['order_id'=>$id]);
        }

        if ($response['success'] == true) {
            return $this->update_order_status($order, "cancelled");
        }

        $result = $this->safe_value($response, 'result', array());
        return $result;
    }

    public function cancel_all_orders ($symbol = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);

        $request = array (
            'market' => $market['id'], // optional
            'conditionalOrdersOnly' => false, // cancel conditional orders only
            'limitOrdersOnly' => false, // cancel existing limit orders (non-conditional orders) only
        );
                
        $response = @$this->privateDeleteOrders (array_merge ($request, $params));

        /*
        if ($response['success'] == true) {
            return $this->update_order_status($orders, "cancelled");
        }
        */

        $result = $this->safe_value($response, 'result', array());
        return $result;
    }


    private function fetch_conditional_orders($symbol, $historical = true) {
        $response = $this->privateGetConditionalOrders (['market'=>$symbol]);
        $orders = $this->safe_value($response, 'result', array());
        //$orders = $this->parse_orders($result);
        if ($historical) {
            $response = $this->privateGetConditionalOrdersHistory (['market'=>$symbol]);
            $result = $this->safe_value($response, 'result', array());
            //$history = $this->parse_orders($result);
            $orders = $this->merge_order_result($orders, $result);
        }
        return $orders;
    }

    private function fetch_conditional_order($id, $symbol) {
        $orders = $this->fetch_conditional_orders( $symbol );
        foreach ($orders as $order) {
            if (($order['id'] == $id) && ($order['symbol'] == $symbol)) {
                return $order;
            }
        }
        return false;
    }

    public function fetch_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $request = array (
            'order_id' => $id,
        );

        try {
            $response = $this->privateGetOrdersOrderId (array_merge ($request, $params));
        } catch (Exception $e) {
            $result = $this->fetch_conditional_order($id, $symbol);
            if ($result !== false) {
                return $result;
            }
            $response = [];
        }
        
        $result = $this->safe_value($response, 'result', array());
        return $this->parse_order($result);
    }

    public function fetch_open_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $request = array();
        $market = null;
        if ($symbol !== null) {
            $market = $this->market ($symbol);
            $request['market'] = $market['id'];
        }
        $response = $this->privateGetOrders (array_merge ($request, $params));

        if ($response['success'] == true) {
            $conditional = $this->fetch_conditional_orders($symbol, false);
            $response['result'] = array_merge($response['result'], $conditional);
        }

        $result = $this->safe_value($response, 'result', array());
        return $this->parse_orders($result, $market, $since, $limit);
    }

    public function fetch_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $request = array();
        $market = null;
        if ($symbol !== null) {
            $market = $this->market ($symbol);
            $request['market'] = $market['id'];
        }
        if ($limit !== null) {
            $request['limit'] = $limit; // default 100, max 100
        }
        if ($since !== null) {
            $request['start_time'] = intval ($since / 1000);
        }
        $response = $this->privateGetOrdersHistory (array_merge ($request, $params));

        if ($response['success'] == true) {
            $conditional = $this->fetch_conditional_orders($symbol);
            $response['result'] = array_merge($response['result'], $conditional);
        }

        $result = $this->safe_value($response, 'result', array());
        return $this->parse_orders($result, $market, $since, $limit);
    }

}
