<?php

    // Garbage collection settings

    const cachegcage = 5;                   // Delete cache files older than 5 days during garbage collection
    const cachegcpct = 20;                  // Garbage collection probability percentage


    // Cache stats

    $GLOBALS['cachestats'] = ['totals' => ['set' => 0, 'hit' => 0, 'miss' => 0]];

    // Cache handler

    class cache {


        public static function statinc($key, $stat) {
            if (!isset($GLOBALS['cachestats'][$key])) {
                $GLOBALS['cachestats'][$key] = ['set' => 0, 'hit' => 0, 'miss' => 0];
            }
            $GLOBALS['cachestats']['totals'][$stat]++;
            $GLOBALS['cachestats'][$key][$stat]++;
        }

        public static function get($key,$timeout=false) {
            $keymd5 = md5($key);
            if ($timeout !== false) {
                $db = new db();
                $query = ['key' => $keymd5];
                $result = $db->select('cache', $query);
                if (count($result) == 1) {
                    $row = $result[0];
                    $ts = $row->timestamp;
                    $perm = (bool) $row->permanent;
                    $data = json_decode($row->data);
                    $now = time();
                    $age = $now - $ts;
                    if (($age <= $timeout) || ($perm === true)) {
                        //logger::debug('Cache hit: '.$key. ' ('.$keymd5.')');
                        self::statinc($key,'hit');
                        return $data;
                    } else {
                        $db->delete('cache',['key'=>$keymd5]);
                    }                
                }
            }
            //logger::debug('Cache miss: '.$key.' ('.$keymd5.')');
            self::statinc($key,'miss');
            return false;
        }
    
        public static function set($key,$data,$permanent=false) {
            $db = new db();
            $keymd5 = md5($key);
            $data = [
                'key' => $keymd5,
                'permanent' => ($permanent === true ? '1' : '0'),
                'timestamp' => time(),
                'data' => json_encode($data,JSON_PRETTY_PRINT),
            ];
            if ($db->insertorupdate('cache',$data, ['key'=> $keymd5])) {
                //logger::debug('Cache set: '.$key.' ('.$keymd5.')');
                self::statinc($key,'set');
                return true;
            }
            //logger::debug('Cache fail: '.$key.' ('.$keymd5.')');
            self::statinc($key,'fail');
            return false;
        }
    
        public static function flush($days, $permanent=false) {
            $db = new db();
            $total = 0;
            $result = $db->select('cache');
            foreach ($result as $row) {
                $key = $row->key;
                $ts = $row->timestamp;
                $perm = (bool) $row->permanent;
                $data = json_decode($row->data);
                $now = time();
                $age = $now - $ts;
                $dayage = $age / 86400;
                if (($dayage > $days) && ($permanent === $perm)) {
                    if ($db->delete('cache',['key' => $key])) {
                        $total++;
                    }
                }                
            }
            logger::debug('Cache flush completed: '.$total.' entries deleted');
            return $total;
         }
    

    }

    // Run random garbage collection for cache files older than 5 days
    $randomgc = rand(0,100);
    if ($randomgc >= (100 - cachegcpct)) {
        logger::debug('Garbage collection triggered, flushing cache older than '.cachegcage.' days...');
        cache::flush(cachegcage);
    }

?>