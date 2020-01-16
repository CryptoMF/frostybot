<?php

    // Configure symbol mappings per exchange

    class symbolmap {

        // Add, update or delete symbol mappings
        public static function manage($params) {
            $exchange = strtolower($params['exchange']);
            $symbol = isset($params['symbol']) ? strtoupper($params['symbol']) : null;
            $mapping = isset($params['mapping']) ? strtoupper($params['mapping']) : null;
            if (isset($params['delete'])) {
                if ($params['delete'] == "true") {
                    if (is_null($symbol)) {
                        logger::error('You need to provide the symbol for the mapping you want to delete');
                    } else {
                        return self::delete($exchange, $symbol);
                    }
                }
            } else {
                if (!is_null($mapping)) {
                    if (is_null($symbol)) {
                        logger::error('You need to provide the symbol for the mapping you want to update');
                    } else {
                        return self::insertOrUpdate($exchange, $symbol, $mapping);
                    }
                } else {
                    return self::get($exchange, $symbol);
                }
            }
        }

        // Get symbols mappings from database
        public static function get($exchange, $symbol = null) {
            $db = new db();
            $query = is_null($symbol) ? ['exchange'=>$exchange] : ['exchange'=>$exchange, 'symbol'=>$symbol]; 
            $result = $db->select('symbolmap',$query);
            $mappings = [];
            foreach($result as $row) {
                $mappings[] = (object) [
                    'exchange' => $row->exchange,
                    'symbol' => $row->symbol,
                    'mapping' => $row->mapping
                ];
            }
            return $mappings;
        }

        // Insert or update a symbol mapping
        private static function insertOrUpdate($exchange, $symbol, $mapping) {
            $data = self::get($exchange, $symbol);
            if (count($data) > 0) {
                return self::update($exchange, $symbol, $mapping);
            } else {
                return self::insert($exchange, $symbol, $mapping);
            }
        }

        // Insert a new symbol mapping
        private static function insert($exchange, $symbol, $mapping) {
            logger::debug("Creating symbol mapping for : ".$exchange." ".$symbol."=>".$mapping);
            $db = new db();
            $db->insert('symbolmap',['exchange'=>$exchange, 'symbol'=>$symbol, 'mapping'=>$mapping]);
            return self::get($exchange);
        }
    
        // Update a symbol mapping
        private static function update($exchange, $symbol, $mapping) {
            logger::debug("Updating symbol mapping for : ".$exchange." ".$symbol."=>".$mapping);
            $db = new db();
            $db->update('symbolmap',['mapping'=>$mapping], ['exchange'=>$exchange, 'symbol'=>$symbol]);
            return self::get($exchange);
        }

        // Delete a symbol mapping
        private static function delete($exchange, $symbol) {
            logger::debug("Deleting symbol mapping for : ".$exchange." ".$symbol);
            $db = new db();
            $db->delete('symbolmap',['exchange'=>$exchange, 'symbol'=>$symbol]);
            return self::get($exchange);
        }

    }


?>