<?php

    // Configure notification settings

    class notifications {
        
        const notificationTypes = [
            
            // Telegram Notifications (https://telegram.org)
            
            'telegram' => [
                'required' => [
                    'chat_id',
                    'token',    
                ],
                'defaults' => [
                    'parse_mode' => 'HTML',
                ],
                'url' => 'https://api.telegram.org/bot{{token}}/sendMessage',
                'method' => 'POST',
                'textfield' => 'text',
                'templates' => [
                    'order' => '<title><b>{{symbol}} Order{{plural}} Created</b> <i>{{stub}}</i></title><orders>'.PHP_EOL.'Direction: {{direction}} | Type: {{type}} | Size: {{size_quote}} | Price: {{price}} | Trigger: {{trigger}} | Order ID: {{id}}</orders><debug>'.PHP_EOL.'<i>{{debug}}</i></debug>',
                    'cancel' => '<title><b>{{symbol}} Order{{plural}} Cancelled</b> <i>{{stub}}</i></title><orders>'.PHP_EOL.'Direction: {{direction}} | Type: {{type}} | Size: {{size_quote}} | Price: {{price}} | Trigger: {{trigger}} | Order ID: {{id}}</orders><debug>'.PHP_EOL.'<i>{{debug}}</i></debug>',
                    'custom' => '<title><b>{{type}} Message</b> <i>{{stub}}</i></title><message>'.PHP_EOL.'{{message}}</message><debug>'.PHP_EOL.'<i>{{debug}}</i></debug>',
                ],
                'result' => [
                    'field' => 'ok',
                    'expected' => true,
                    'errormsg' => 'description'
                ],
            ],

            // Discord Notifications (https://discord.com)
            
            'discord' => [
                'required' => [
                    'id',
                    'token',    
                ],
                'defaults' => [
                ],
                'url' => 'https://discordapp.com/api/webhooks/{{id}}/{{token}}?wait=1',
                'method' => 'POST',
                'textfield' => 'content',
                'templates' => [
                    'order' => '<title>**{{symbol}} Order{{plural}} Created** *{{stub}}*</title><orders>'.PHP_EOL.'Direction: {{direction}} | Type: {{type}} | Size: {{size_quote}} | Price: {{price}} | Trigger: {{trigger}} | Order ID: {{id}}</orders><debug>'.PHP_EOL.'*{{debug}}*</debug>',
                    'cancel' => '<title>**{{symbol}} Order{{plural}} Cancelled** *{{stub}}*</title><orders>'.PHP_EOL.'Direction: {{direction}} | Type: {{type}} | Size: {{size_quote}} | Price: {{price}} | Trigger: {{trigger}} | Order ID: {{id}}</orders><debug>'.PHP_EOL.'*{{debug}}*</debug>',
                    'custom' => '<title>**{{type}} Message** *{{stub}}*</title><message>'.PHP_EOL.'{{message}}</message><debug>'.PHP_EOL.'*{{debug}}*</debug>',
                ],
                'result' => [
                    'field' => 'type',
                    'expected' => 0,
                    'errormsg' => 'message'
                ],
            ],

            // Pushover Notifications (https://pushover.net)

            'pushover' => [
                'required' => [
                    'token',
                    'user',
                ],
                'defaults' => [
                    'html' => 1,
                ],
                'url' => 'https://api.pushover.net/1/messages.json',
                'method' => 'POST',
                'textfield' => 'message',
                'templates' => [
                    'order' => '<title><b>{{symbol}} Order{{plural}} Created</b> <i>{{stub}}</i></title><orders>'.PHP_EOL.'Direction: {{direction}} | Type: {{type}} | Size: {{size_quote}} | Price: {{price}} | Trigger: {{trigger}} | Order ID: {{id}}</orders><debug>'.PHP_EOL.'<i>{{debug}}</i></debug>',
                    'cancel' => '<title><b>{{symbol}} Order{{plural}} Cancelled</b> <i>{{stub}}</i></title><orders>'.PHP_EOL.'Direction: {{direction}} | Type: {{type}} | Size: {{size_quote}} | Price: {{price}} | Trigger: {{trigger}} | Order ID: {{id}}</orders><debug>'.PHP_EOL.'<i>{{debug}}</i></debug>',
                    'custom' => '<title><b>{{type}} Message</b> <i>{{stub}}</i></title><message>'.PHP_EOL.'{{message}}</message><debug>'.PHP_EOL.'<i>{{debug}}</i></debug>',
                ],
                'result' => [
                    'field' => 'status',
                    'expected' => 1,
                    'errormsg' => 'errors'
                ],
            ],

        ];

        // Add, update or delete notifications
        public static function manage($params) {
            if ((isset($params['test'])) && ($params['test'] == "true")) {
                return self::send('custom', ['type'=>'Test', 'message'=>'This is a test message'], (isset($params['platform']) ? $params['platform'] : null));
            }
            if (isset($params['platform'])) {
                $platform = strtolower($params['platform']);
                if ((isset($params['delete'])) && ($params['delete'] == "true")) {
                    return self::delete($platform);
                }
                if ((!isset($params['test'])) && (!isset($params['delete']))) {
                    if (!array_key_exists($platform, self::notificationTypes)) {
                        logger::error('Unsupported notification platform: '.$platform);
                    } else {
                        $required = self::notificationTypes[$platform]['required'];
                        foreach ($required as $param) {
                            if (!array_key_exists($param, $params)) {
                                logger::error('Required parameter not found for '.$platform.': '.$param);
                            }
                        }
                        return self::insertOrUpdate($platform, $params);
                    }
                }
            } else {
                return self::get();
            }
        }
        
        // Post Request
        private static function postrequest($url, $data) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($ch);
            curl_close ($ch);
            return $output;
        }

        // Get Request
        private static function getrequest($url, $data) {
            $ch = curl_init();
            $url = $url.'?'.http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($ch);
            curl_close ($ch);
            return $output;
        }

        // Get section templates
        private static function getsections($text) {
            $allowedTags = ['title','orders','type','message','debug'];
            $sections = [];
            foreach ($allowedTags as $tag) {
                preg_match("'<".$tag.">(.*?)</".$tag.">'si", $text, $match);
                if ($match) {
                    $sections[$tag] = [
                        'original' => $match[0],
                        'text' => $match[1],
                    ];
                }
            }
            return $sections;
        }

        // Check if no orders provided
        private static function noorders($params = null) {
            if (is_null($params)) { return true; }
            if ((is_array($params)) && (count($params) == 0)) { return true; }
            return false;
        }

        // Check if orders passed are complex (linked order, layed order, array of orders)
        private static function orderplural($params = null) {
            if (self::noorders($params)) { return 's'; }
            return (((is_object($params)) && (get_class($params) == 'linkedOrderObject')) || ((is_array($params)) && (get_class($params[0]) == 'orderObject'))) ? 's' : '';
        }    
        
        // Get order symbol
        private static function ordersymbol($params = null) {
            if (self::noorders($params)) { return $GLOBALS['symbol']; }
            if ((is_object($params)) && (get_class($params) == 'orderObject')) {
                $symbol = $params->market->symbol;
            }
            if ((is_object($params)) && (get_class($params) == 'linkedOrderObject')) {   // Linked or Layered order
                $symbol = $params->symbol;
            }
            if ((is_array($params)) && (get_class($params[0]) == 'orderObject')) {       // Array of orders
                $symbol = $params[0]->market->symbol;
            }
            return $symbol;
        }

        // Parse order
        private static function parseorders($text, $params = null) {
            if (self::noorders($params)) { return ''; }
            $content = '';
            if ((is_object($params)) && (get_class($params) == 'orderObject')) {        // Single order
                $data = [];
                $order = $params;
                $market = (array) $order->market;
                $order = (array) $order;
                $order['direction'] = ($order['direction'] == 'long' ? 'Buy' : 'Sell');
                $order['type'] = ucwords(strtolower(str_replace('_', ' ', $order['type'])));
                unset($order['market']);
                $data = array_merge($market, $order);
                $content = self::parsetext($text, $data);
            }
            if ((is_object($params)) && (get_class($params) == 'linkedOrderObject')) {   // Linked or Layered order
                foreach($params->orders as $order) {
                    $content .= self::parseorders($text, $order);
                }
            }
            if ((is_array($params)) && (get_class($params[0]) == 'orderObject')) {       // Array of orders
                foreach($params as $order) {
                    $content .= self::parseorders($text, $order);
                }
            }
            return $content;
        }

        // Parse text
        private static function parsetext($text, $params) {
            $params = (array) $params;
            if (substr($text,0,4) == 'http') {   # Are we parsing a url?
                preg_match_all('~{{([^{]*)}}~i', $text, $match);
                $fields = $match[1];
                foreach ($fields as $field) {
                    $text = str_replace('{{'.$field.'}}', urlencode($params[$field]), $text);
                }
            } else {
                foreach ((array) $params as $key => $value) {
                    if (!is_array($value)) {
                        $text = str_replace('{{'.$key.'}}', $value, $text);
                    }
                }
            }
            return $text;
        }

        // Send notifications
        public static function send($type, $params, $platform = null) {
            if (is_null($platform)) {       // If no platform is specified, send on all platforms
                foreach (array_keys(self::notificationTypes) as $platform) {
                    self::send($type, $params, $platform);
                }
            } else {
                $settings = self::get($platform);
                if (count($settings) > 0) {
                    $settings = $settings[0];
                    $defaults = isset(self::notificationTypes[$platform]['defaults']) ? self::notificationTypes[$platform]['defaults'] : [];
                    $data = [];
                    foreach ($defaults as $field => $value) {                                      // Load default settings
                        $data[$field] = $value;
                    }
                    foreach((array) $settings->params as $field => $value) {                       // Load user-defined settings
                        $data[$field] = $value;
                    }
                    $debug = ((isset($settings->params->debug)) && ($settings->params->debug == true) ? true : false);
                    $cmd = isset($GLOBALS['cmd']) ? '(Command: '.$GLOBALS['cmd'].')' : '';
                    $params['debug'] = ($debug ? '(Command: '.(isset($GLOBALS['cmd']) ? $GLOBALS['cmd'] : 'None').')' : '');
                    $params['stub'] = (((isset($GLOBALS['stub'])) && (str_replace('_','',trim($GLOBALS['stub'])) != "")) ? '('.str_replace('_','',trim($GLOBALS['stub'])).')' : '');
                    if (isset($params['orders'])) {
                        $params['plural'] = self::orderplural($params['orders']);
                        $params['symbol'] = self::ordersymbol($params['orders']);
                    }
                    $content = self::notificationTypes[$platform]['templates'][$type];
                    $sections = self::getsections($content);
                    foreach ($sections as $tag => $section) {                                      // Parse content inside sections
                        $text = $section['text'];
                        $original = $section['original'];
                        switch (strtolower($tag)) {
                            case 'orders'   :   $content = str_replace($original, self::parseorders($text, $params['orders']), $content);
                                                break;
                            case 'debug'    :   $content = str_replace($original, ($debug ? self::parsetext($text, $params) : ''), $content);
                                                break;
                            default         :   $content = str_replace($original, self::parsetext($text, $params), $content);
                        }
                    }
                    $content = str_replace($original, self::parsetext($content, $params), $content);  // Parse content not inside a section
                    $textfield = self::notificationTypes[$platform]['textfield'];
                    $url = self::parsetext(self::notificationTypes[$platform]['url'], $settings->params);
                    $data[$textfield] = $content;
                    switch (strtolower(self::notificationTypes[$platform]['method'])) {
                        case 'post' : $output = json_decode(self::postrequest($url, $data), true);
                                      break;
                        case 'get'  : $output = json_decode(self::getrequest($url, $data), true);
                                      break;
                    }
                    $result = self::notificationTypes[$platform]['result'];
                    if ((isset($output[$result['field']])) && ($output[$result['field']] == $result['expected'])) {
                        logger::debug('Notification sent successfully: '.ucwords($platform));
                    } else {
                        $error = $output[$result['errormsg']];
                        $error = (is_array($error) ? implode('. ', $error) : $error);
                        logger::debug('Failed to send notification: '.ucwords($platform).' ('.$error.')');
                    }
                } 
            }
        }

        // Get notification settings from database
        public static function get($platform = null) {
            $db = new db();
            $result = $db->select('notifications', (!is_null($platform) ? ['platform' => $platform] : []));
            $notifications = [];
            foreach($result as $row) {
                $notifications[] = (object) [
                    'platform' => $row->platform,
                    'params' => json_decode($row->params),
                ];
            }
            return $notifications;
        }

        // Insert or update a notification setting
        private static function insertOrUpdate($platform, $params) {
            unset($params['stub']);
            unset($params['command']);
            unset($params['platform']);
            $data = self::get($platform);
            if (count($data) > 0) {
                return self::update($platform, $params);
            } else {
                return self::insert($platform, $params);
            }
        }

        // Insert a new notification setting
        private static function insert($platform, $params) {
            logger::debug("Adding notification settings for ".$platform);
            $db = new db();
            $db->insert('notifications', ['platform'=>$platform, 'params'=>json_encode($params)]);
            return self::get();
        }
    
        // Update a notification setting
        private static function update($platform, $params) {
            logger::debug("Updating notification settings for ".$platform);
            $db = new db();
            $db->update('notifications', ['params'=>json_encode($params)], ['platform'=>$platform]);
            return self::get();
        }

        // Delete a notification setting
        private static function delete($platform) {
            logger::debug("Deleting notification settings for ".$platform);
            $db = new db();
            $db->delete('notifications', ['platform'=>$platform]);
            return self::get();
        }

    }


?>