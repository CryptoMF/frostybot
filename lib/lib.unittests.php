<?php

    // Unit test definitions

    const unitTests = [

        'exchangedata'  =>  [
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        'deribit',
                        'bitmex',
                        'ftx',
                    ],
                    'command'   =>  '<account>:balance',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'array',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<arraysize:min:1>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        'deribit',
                        'bitmex',
                        'ftx',
                    ],
                    'command'   =>  '<account>:balanceusd',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'double',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<numbersize:min:0>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        'deribit',
                        'bitmex',
                        'ftx',
                    ],
                    'command'   =>  '<account>:markets',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'array',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<arraysize:min:1>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        'deribit',
                        'bitmex',
                        'ftx',
                    ],
                    'command'   =>  '<account>:market symbol=BTCUSD',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'object',
                        'message'   =>  'SUCCESS',
                        'data'      =>  null,
                    ],                                    
                ],
            ],
        ],
        'ohlcv' =>      [
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        //'deribit',
                        'ftx',
                        'bitmex',
                    ],
                    'command'   =>  '<account>:ohlcv symbol=BTCUSD timeframe=1h',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'array',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<arraysize:min:1>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        //'deribit',
                        'ftx',
                        'bitmex',
                    ],
                    'command'   =>  '<account>:ohlcv symbol=BTCUSD timeframe=3h',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'array',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<arraysize:min:1>',
                    ],                                    
                ],
            ],
        ],
        'trading' =>      [
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        'deribit',
                        'bitmex',
                        //'ftx',
                    ],
                    'command'   =>  '<account>:long size=2x symbol=BTCUSD',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'OBJECT',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<propertyval:status:filled>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        'deribit',
                        'bitmex',
                        //'ftx',
                    ],
                    'command'   =>  '<account>:close size=50% symbol=BTCUSD',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'OBJECT',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<propertyval:status:filled>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        'deribit',
                        'bitmex',
                        //'ftx',
                    ],
                    'command'   =>  '<account>:short size=100 symbol=BTCUSD',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'OBJECT',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<propertyval:status:filled>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        'deribit',
                        'bitmex',
                        //'ftx',
                    ],
                    'command'   =>  '<account>:close size=50% symbol=BTCUSD',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'OBJECT',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<propertyval:status:filled>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        'deribit',
                        'bitmex',
                        //'ftx',
                    ],
                    'command'   =>  '<account>:close symbol=BTCUSD',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'OBJECT',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<propertyval:status:filled>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        //'ftx',
                        //'deribit',
                        'bitmex',
                    ],
                    'command'   =>  '<account>:long size=1x symbol=ETHUSD',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'OBJECT',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<propertyval:status:filled>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        //'ftx',
                        //'deribit',
                        'bitmex',
                    ],
                    'command'   =>  '<account>:close size=50% symbol=ETHUSD',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'OBJECT',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<propertyval:status:filled>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        //'ftx',
                        //'deribit',
                        'bitmex',
                    ],
                    'command'   =>  '<account>:short size=10 symbol=ETHUSD',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'OBJECT',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<propertyval:status:filled>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        //'ftx',
                        //'deribit',
                        'bitmex',
                    ],
                    'command'   =>  '<account>:close size=50% symbol=ETHUSD',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'OBJECT',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<propertyval:status:filled>',
                    ],                                    
                ],
            ],
            [
                'type'      =>  'command',
                'params'    =>  [
                    'accounts'  =>  [
                        //'ftx',
                        //'deribit',
                        'bitmex',
                    ],
                    'command'   =>  '<account>:close symbol=ETHUSD',
                    'expected'  =>  [
                        'code'      =>  0,
                        'type'      =>  'OBJECT',
                        'message'   =>  'SUCCESS',
                        'data'      =>  '<propertyval:status:filled>',
                    ],                                    
                ],
            ],
        ],

    ];

    class unitTests {

        // Unit test function

        public static function runTests($params) {
            cache::flush(0, $permanent=false);
            $results = [];
            $group = $params['group'];
            if ($group == 'all') {
                foreach (array_keys(unitTests) as $group) {
                    $results[$group] = self::unitTestsGroup($group);  
                }
            } else {
                $results[$group] = self::unitTestsGroup($group);  
            }
            foreach($results as $groupResults) {
                $overallResult = true;
                if ($groupResults->overall !== true) {
                    $overallResult = false;
                    break;
                }
            }
            if ($overallResult == true) {
                logger::notice('All unit tests successfully passed');
            }
            return (object) [
                'results' => $results,
                'overall' => $overallResult,
            ];
        }

        // Run unit test for group

        private static function unitTestsGroup($group) {
            $groupTests = unitTests[$group];
            $groupResults = [];
            foreach ($groupTests as $groupTest) {
                $type = $groupTest['type'];
                $params = (object)  $groupTest['params'];
                switch (strtolower($type)) {
                    case    'command'       :   $testResults = self::unitTestCommand($params);
                                                $groupResults[] = $testResults;
                                                break;
                }
            }
            foreach($groupResults as $groupResult) {
                $overallResult = true;
                if ($groupResult->overall !== true) {
                    $overallResult = false;
                    break;
                }
            }
            if ($overallResult === true) {
                logger::notice('Unit tests passed for group: '.$group);
            } else {
                logger::warning('Unit tests failed for group: '.$group);
            }
            return (object) [
                    'results'   =>  $groupResults,
                    'overall'   =>  $overallResult,
            ];
        }

        // Run balance unit test

        private static function unitTestCommand($params) {
            $accounts = $params->accounts;
            $command = $params->command;
            $expected = $params->expected;
            $testResults = [];
            $overallResult = true;
            foreach ($accounts as $account) {
                $parsedCommand = str_replace('<account>',$account,$command);
                $commandObj = new command($parsedCommand);
                $result = $commandObj->execute();
                if ($result !== false) {
                    $testResult = testResult(0,'SUCCESS',$result);
                } else {
                    $testResult = testResult(999,'ERROR',false);
                }
                $unitTestResult = self::unitTestResult($expected, $testResult);
                //$briefCommand = trim(substr($parsedCommand.' ',0,strpos($parsedCommand,' ')-1));
                list($briefCommand,$commandParams) = explode(' ',$parsedCommand.' ',2);
                if ($unitTestResult === true) {
                    logger::notice('Unit test passed for command: '.$briefCommand.(trim($commandParams) == "" ? '' : ' ('.trim($commandParams).')'));
                } else {
                    logger::warning('Unit test failed for command: '.$briefCommand.(trim($commandParams) == "" ? '' : ' ('.trim($commandParams).')'));
                }
                if ($unitTestResult !== true) {
                    $overallResult = false;
                }
                $testResults[] = (object) [
                                    'command'   =>  $parsedCommand,
                                    'result'    =>  $unitTestResult,
                ];
            }
            return (object) [
                'results'   =>  $testResults,
                'overall'   =>  $overallResult,
            ];
        }

        // Parse test results

        private static function unitTestResult($expected, $actual) {
            $expected = (object) $expected;
            $checkCode = ($expected->code == $actual->code);
            $checkType = (strtolower($expected->type) ==  strtolower($actual->type));
            $checkMessage = (strtolower($expected->message) ==  strtolower($actual->message));
            if (!is_null($expected->data)) {
                $expectedData = $expected->data;
                $expectedDataParams = explode(":",str_replace(['<','>'],'',$expectedData));
                if (is_array($expectedDataParams)) {
                    $checkData = false;
                    if ($expectedDataParams[0] == 'arraysize') {
                        $minmax = $expectedDataParams[1];
                        $value = $expectedDataParams[2];
                        if (is_array($actual->data)) {
                            $actualCount = count($actual->data);
                            $checkData = ($minmax == 'min' ? $actualCount >= $value : ($minmax == 'max' ? $actualCount <= $value : false));
                        } else {
                            $checkData = false;
                        }
                    }
                    if ($expectedDataParams[0] == 'numbersize') {
                        $minmax = $expectedDataParams[1];
                        $value = $expectedDataParams[2];
                        $actualValue = $actual->data;
                        $checkData = ($minmax == 'min' ? $actualValue >= $value : ($minmax == 'max' ? $actualValue <= $value : false));
                    }
                    if ($expectedDataParams[0] == 'propertyval') {
                        $property = $expectedDataParams[1];
                        $value = $expectedDataParams[2];
                        $data = (array) $actual->data;
                        $propertyValue = (isset($data[$property]) ? $data[$property] : null);
                        $checkData = (strtolower($propertyValue) == strtolower($value));
                    }
                }
            } else {
                $checkData = true;
            }
            $checkResult = ($checkCode && $checkType && $checkMessage && $checkData);
            if (!$checkResult) {
                print_r(['expected' => $expected, 'actual' => $actual]);
            }
            return (bool) $checkResult;
        }

    }

?>