<?php
class Connect_database {
    //sets the variables for the PDO
    private $host = "localhost";
    private $database = "hecm2";
    private $user = "user";
    private $pass = "hecm123";
    //make the PDO connection
    function create_dbh() {
        try {
            $dbh = new PDO("mysql:host=$this->host;dbname=$this->database","$this->user", $this->pass);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $dbh;

        } catch (PDOException $e) {
            echo "error: ".$e->getMessage();
        }
    }
}
$database = new Connect_database();