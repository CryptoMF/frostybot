<?php

    class db {

        private $database;
        private $conn;

        public function __construct($db = null) {
            $this->database = is_null($db) ? database : $db;
            $this->conn = new SQLite3($this->database);
            $this->conn->busyTimeout(5000);
            $this->conn->exec('PRAGMA journal_mode = wal;');
            $this->inittables();
        }

        public function initialize() {
            foreach(glob('db/sql/*.sql') as $sqlFile) {
                $filename = basename($sqlFile);
                $table = str_replace('.sql','',strtolower($filename));
                $this->exec('DROP TABLE '.$table.';');
                $sql = file_get_contents($sqlFile);
                logger::info('Initializing database: '.$sqlFile);
                $this->exec($sql);
            }     
            //config::import(accounts);
            return true;   
        }

        // Initialize missing tables
        private function inittables() {
            foreach(glob('db/sql/*.sql') as $sqlFile) {
                $table = str_replace(['db/sql/','.sql'], '', $sqlFile);
                $checktable = $this->select('sqlite_master', ['type' => 'table', 'name' => $table]);
                if (count($checktable) == 0) {
                    $sql = file_get_contents($sqlFile);
                    logger::debug('Initializing missing table: '.$table);
                    $this->exec($sql);
                //} else {
                    //logger::debug('Table '.$table.' exists. Removing initializing file.');
                    //kill($sqlFile);
                }               
            }     
            return true;   
        }

        public function exec($sql) {
            if ($result = $this->conn->exec($sql)) {
                return $result;
            }
            logger::error('SQLite Error: '.$this->conn->lastErrorMsg());
            return false;
        }

        public function query($sql) {
            $results = $this->conn->query($sql);
            if ($results !== false) { 
                $data = [];
                while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                    $data[] = (object) $row;
                }
                return $data;
            }
            return false;
        }

        public function select($table, $where = []) {
            $where = (is_object($where) ? (array) $where : $where);
            $wherelist = [];
            foreach ($where as $key => $value) {
                $wherelist[] = "`".$key."`='".$value."'";
            }
            $sql = "SELECT * FROM `".$table."`".(count($wherelist) > 0 ? " WHERE ".implode(' AND ', $wherelist) : "").";";
            //logger::debug($sql);
            return $this->query($sql);
        }

        public function insert($table, $data) {
            $data = (is_object($data) ? (array) $data : $data);
            foreach ($data as $key => $value) {
                $collist[] = $key;
                $vallist[] = $value;
            }
            $sql = "INSERT INTO `".$table."` (`".implode("`,`", $collist)."`) VALUES ('".implode("','", $vallist)."');";
            $sql = str_replace("'CURRENT_TIMESTAMP'","CURRENT_TIMESTAMP", $sql);
            //logger::debug($sql);
            return $this->exec($sql);
        }

        public function update($table, $data, $where) {
            $data = (is_object($data) ? (array) $data : $data);
            $where = (is_object($where) ? (array) $where : $where);
            $datalist = [];
            foreach ($data as $key => $value) {
                $datalist[] = "`".$key."`='".$value."'";
            }
            $wherelist = [];
            foreach ($where as $key => $value) {
                $wherelist[] = "`".$key."`='".$value."'";
            }
            $sql = "UPDATE `".$table."` SET ".implode(',', $datalist).(count($wherelist) > 0 ? " WHERE ".implode(' AND ', $wherelist) : "").";";
            $sql = str_replace("'CURRENT_TIMESTAMP'","CURRENT_TIMESTAMP", $sql);
            //logger::debug($sql);
            return $this->exec($sql);
        }

        public function insertOrUpdate($table, $data, $where) {
            $result = $this->select($table, $where);
            if (count($result) == 1) {
                return $this->update($table, $data, $where);
            } else {
                return $this->insert($table, $data);
            }
        }

        public function delete($table, $data = []) {
            $data = (is_object($data) ? (array) $data : $data);
            $wherelist = [];
            foreach ($data as $key => $value) {
                $wherelist[] = "`".$key."`='".$value."'";
            }
            $sql = "DELETE FROM `".$table."`".(count($wherelist) > 0 ? " WHERE ".implode(' AND ', $wherelist) : "").";";
            //logger::debug($sql);
            return $this->exec($sql);
        }

    }

?>