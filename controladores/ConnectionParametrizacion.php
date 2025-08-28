<?php
    class ConnectionParametrizacion {
        private static $instance = null;
        private $connection;

        private function __construct() {
            $host = "localhost";
            $username = "root";
            $password = "";
            $database = "parametrosapi";

            $this->connection = new mysqli($host, $username, $password, $database);

            if ($this->connection->connect_error) {
                die("Error de conexión: " . $this->connection->connect_error);
            }

            // Configurar la codificación de caracteres a UTF-8
            $this->connection->set_charset("utf8");
        }

        public static function getInstance() {
            if (self::$instance == null) {
                self::$instance = new ConnectionParametrizacion();
            }
            return self::$instance;
        }

        public function getConnection() {
            return $this->connection;
        }
    }

?>