<?php


// Frostybot CCXT Override class for FTX

namespace ccxt;

use Exception; // a common import

class ftx_frostybot extends ftx {

    public function describe () {
        $describe = parent::describe ();
        $describe['api']['private']['get'][] = 'conditional_orders/history';   // Missing endpoint added for FrostyBot
        return $describe;
    }


    public function cancel_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $request = array (
            'order_id' => intval ($id),
        );

        $order = $this->fetch_order($id, $symbol);
        if (in_array($order['type'],['limit','market'])) {
            $response = $this->privateDeleteOrdersOrderId (array_merge ($request, $params));
        } else {
            $response = $this->privateDeleteConditionalOrdersOrderId (['order_id'=>$id]);
        }
        if ($response['success'] == true) {
            $order['info']['status'] = "cancelled";
            $order['status'] = "cancelled";
            return $order;
        }
        // ------------------------------------------------------------------
        //
        //     {
        //         "success" => true,
        //         "$result" => "Order queued for cancelation"
        //     }
        //
        $result = $this->safe_value($response, 'result', array());
        return $result;
    }

    public function cancel_all_orders ($symbol = null, $params = array ()) {
        $this->load_markets();
        $request = array (
            // 'market' => $market['id'], // optional
            'conditionalOrdersOnly' => false, // cancel conditional orders only
            'limitOrdersOnly' => false, // cancel existing limit orders (non-conditional orders) only
        );
        
        $market = null;
        if ($symbol !== null) {
            $market = $this->market ($symbol);
            $request['market'] = $market['id'];
        }
        
        $orders = $this->fetch_open_orders($symbol);
        $response = $this->privateDeleteOrders (array_merge ($request, $params));
        $result = $this->safe_value($response, 'result', array());
        //
        //     {
        //         "success" => true,
        //         "$result" => "Orders queued for cancelation"
        //     }
        //
        if ((is_array($result)) && ($result['success'] == true)) {
            return $orders;
        }
        return $result;
    }

    public function fetch_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $request = array (
            'order_id' => $id,
        );

        try {
            $response = $this->privateGetOrdersOrderId (array_merge ($request, $params));
        } catch (Exception $e) {
            $rawResults = $this->privateGetConditionalOrders (['market'=>$symbol]);
            if ($rawResults['success']) {
                foreach ($rawResults['result'] as $rawResult) {
                    if (($rawResult['id'] == $id) && ($rawResult['market'] == $symbol)) {
                        $response = [
                            'success'   =>  true,
                            'result'    =>  $rawResult
                        ];
                        break;
                    }
                }
            }
            if (!isset($response)) {
                $rawResults = $this->privateGetConditionalOrdersHistory (['market'=>$symbol]);
                if ($rawResults['success']) {
                    foreach ($rawResults['result'] as $rawResult) {
                        if (($rawResult['id'] == $id) && ($rawResult['market'] == $symbol)) {
                            $response = [
                                'success'   =>  true,
                                'result'    =>  $rawResult
                            ];
                            break;
                        }
                    }
                }    
            }
        }
        // ------------------------------------------------------------------
        //
        //     {
        //         "success" => true,
        //         "$result" => {
        //             "createdAt" => "2019-03-05T09:56:55.728933+00:00",
        //             "filledSize" => 10,
        //             "future" => "XRP-PERP",
        //             "$id" => 9596912,
        //             "market" => "XRP-PERP",
        //             "price" => 0.306525,
        //             "avgFillPrice" => 0.306526,
        //             "remainingSize" => 31421,
        //             "side" => "sell",
        //             "size" => 31431,
        //             "status" => "open",
        //             "type" => "limit",
        //             "reduceOnly" => false,
        //             "ioc" => false,
        //             "postOnly" => false,
        //             "clientId" => null
        //         }
        //     }
        //
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
            $conditional = $this->privateGetConditionalOrders (array_merge ($request, $params));
            if ($conditional['success'] == true) {
                foreach ($conditional['result'] as $order) {
                    $response['result'][] = $order;
                }
            }
        }
        // ------------------------------------------------------------------
        //
        //     {
        //         "success" => true,
        //         "$result" => array (
        //             {
        //                 "createdAt" => "2019-03-05T09:56:55.728933+00:00",
        //                 "filledSize" => 10,
        //                 "future" => "XRP-PERP",
        //                 "id" => 9596912,
        //                 "$market" => "XRP-PERP",
        //                 "price" => 0.306525,
        //                 "avgFillPrice" => 0.306526,
        //                 "remainingSize" => 31421,
        //                 "side" => "sell",
        //                 "size" => 31431,
        //                 "status" => "open",
        //                 "type" => "$limit",
        //                 "reduceOnly" => false,
        //                 "ioc" => false,
        //                 "postOnly" => false,
        //                 "clientId" => null
        //             }
        //         )
        //     }
        //
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
            $conditional = $this->privateGetConditionalOrders (array_merge ($request, $params));
            if ($conditional['success'] == true) {
                foreach ($conditional['result'] as $order) {
                    $response['result'][] = $order;
                }
            }
        }
        // ------------------------------------------------------------------
        //
        //     {
        //         "success" => true,
        //         "$result" => array (
        //             {
        //                 "createdAt" => "2019-03-05T09:56:55.728933+00:00",
        //                 "filledSize" => 10,
        //                 "future" => "XRP-PERP",
        //                 "id" => 9596912,
        //                 "$market" => "XRP-PERP",
        //                 "price" => 0.306525,
        //                 "avgFillPrice" => 0.306526,
        //                 "remainingSize" => 31421,
        //                 "side" => "sell",
        //                 "size" => 31431,
        //                 "status" => "open",
        //                 "type" => "$limit",
        //                 "reduceOnly" => false,
        //                 "ioc" => false,
        //                 "postOnly" => false,
        //                 "clientId" => null
        //             }
        //         )
        //     }
        //
        $result = $this->safe_value($response, 'result', array());
        return $this->parse_orders($result, $market, $since, $limit);
    }

}
