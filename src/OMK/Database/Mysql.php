<?php class OMK_Database_Mysql extends OMK_Database_Adapter {
    /** PDO */
    protected $dbConnection;
    public function __construct($options = null) {
        
        if (array_key_exists("host", $options) && null != $options["host"]) {
            $host = $options["host"];
        }else{
            throw new OMK_Exception(_("Missing host."));
        } 
        if (array_key_exists("database", $options) && null != $options["database"]) {
            $database = $options["database"];
        }else{
            throw new OMK_Exception(_("Missing host."));
        }
        if (array_key_exists("user", $options) && null != $options["user"]) {
            $user = $options["user"];
        }else{
            throw new OMK_Exception(_("Missing user."));
        }
        if (array_key_exists("password", $options) && null != $options["password"]) {
            $password = $options["password"];
        } else {
            throw new OMK_Exception(_("Missing password."));
        }
        if (array_key_exists("prefix", $options) && null != $options["prefix"]) {
            $prefix = $options["prefix"];
        } else {
            $prefix = "";
        }
        try {
            $options = array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            );
            $this->dbConnection = new PDO("mysql:host=$host;dbname=$database", $user, $password, $options);  
            $this->dbConnection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );  

        } catch (PDOException $e) {
            
            $msg = sprintf(_("Connection to mysql server failed: %s"), $e->getMessage());
            $this->getClient()->getLoggerAdapter()->log(array(
                "level"     => OMK_Logger_Adapter::WARN,
                "message"   => $msg,
                "exception" => $e
            ));
            throw new OMK_Exception($msg);
        }
    }
    
    function query( $statement ){
        try { 
            $results = $this->dbConnection->query($statement);
            if( false !== $results){
                return $results;
            }
            $error_code     = $this->dbConnection->errorCode();
            $error_info     = implode(" - ", $this->dbConnection->errorInfo() );
            throw new OMK_Exception(sprintf(_("An error occured with mysql exec.\n Code: %s\n Info: %s"),  $error_code, $error_info));
       }  
        catch(PDOException $e) {  
            $msg    = sprintf(_("Mysql query failed: %s.",$e->getMessage()));
            $this->getClient()->getLoggerAdapter()->log(array(
                "level"     => OMK_Logger_Adapter::WARN,
                "message"   => $msg,
                "exception" => $e
            ));
            throw new OMK_Exception($msg);
        }  
    }
    
    function exec( $query, $bind ){
        try { 
            $statement      = $this->dbConnection->prepare($query);
            $affected_rows  = $statement->execute($bind);
            if( false !== $affected_rows){
                return $affected_rows;
            }
            $error_code     = $this->dbConnection->errorCode();
            $error_info     = implode(" - ", $this->dbConnection->errorInfo() );
            throw new OMK_Exception(sprintf(_("An error occured with mysql exec.\n Code: %s\n Info: %s"),  $error_code, $error_info));
        }  
        catch(PDOException $e) {  
            $msg    = sprintf(_("Mysql exec failed: %s."),$e->getMessage());
            $this->getClient()->getLoggerAdapter()->log(array(
                "level"     => OMK_Logger_Adapter::WARN,
                "message"   => $msg,
                "exception" => $e
            ));
            throw new OMK_Exception($msg);
        }  
    }

    function insert($options = null) {
        
        if (array_key_exists("table", $options) && null != $options["table"]) {
            $table = $options["table"];
        } else {
            throw new OMK_Exception(_("Missing table."));
        }
        if (array_key_exists("data", $options) && null != $options["data"]) {
            $data = $options["data"];
        } else {
            throw new OMK_Exception(_("Missing data."));
        }
        $cols = array();
        $vals = array();
        $i = 0;
        foreach ($data as $col => $val) {
            $cols[] = $this->quoteIdentifier($col);
            unset($data[$col]);
            $data[':col'.$i] = $val;
            $vals[] = ':col'.$i;
            $i++;
        }

        // build the statement
        $sql = "INSERT INTO "
             . $this->quoteIdentifier($table)
             . ' (' . implode(', ', $cols) . ') '
             . 'VALUES (' . implode(', ', $vals) . ')';
        try{
            $affected_rows = $this->exec($sql, $data);
        } catch (OMK_Exception $e){
            $msg          = sprintf(_("Failed to insert new row in %s"), $table);
            $this->getClient()->getLoggerAdapter()->log(array(
               "level"      => OMK_Logger_Adapter::WARN,
                "message"   => $msg,
                "exception" => $e
            ));
            return array(
                "code"      => self::ERR_INSERT,
                "message"   => $msg
            );
        }
        return array(
            "code"      => 0,
            "message"   => sprintf(_("Successfully inserted new row in %s"),$table),
            "id"        => $this->dbConnection->lastInsertId()
        );
    }
    
    function select($options = null) {
        parent::select($options);
    }
    
    function update($options = null) {
        
        if (array_key_exists("table", $options) && null != $options["table"]) {
            $table = $options["table"];
        } else {
            throw new OMK_Exception(_("Missing table."));
        }
        if (array_key_exists("data", $options) && null != $options["data"]) {
            $data = $options["data"];
        } else {
            throw new OMK_Exception(_("Missing data."));
        }
        if (array_key_exists("id", $options) && null != $options["id"]) {
            $id = $options["id"];
        } else {
            throw new OMK_Exception(_("Missing id."));
        }
        $cols = array();
        $vals = array();
        $i = 0;
        foreach ($data as $col => $val) {
            unset($data[$col]);
            $quoted_col     = $this->quoteIdentifier($col);
            if( $col == "dt_updated"){
                $vals[] = $quoted_col.' = NOW()';
            }else{
                $data[':col'.$i] = $val;
                $vals[] = $quoted_col.' = :col'.$i;
            }
            $i++;
        }
        $data[":id"] = $id;

        // build the statement
        $sql = "UPDATE "
             . $this->quoteIdentifier($table)
             . ' SET '
             . implode(",", $vals)
             . ' WHERE `id` = :id';
        try{
            
            $affected_rows = $this->exec($sql, $data);
            
        } catch (OMK_Exception $e){
            $msg          = sprintf(_("Failed to update row id %s in %s"), $id, $table);
            $this->getClient()->getLoggerAdapter()->log(array(
               "level"      => OMK_Logger_Adapter::WARN,
                "message"   => $msg,
                "exception" => $e
            ));
            return array(
                "code"      => self::ERR_UPDATE,
                "message"   => $msg
            );
        }
        return array(
            "code"          => 0,
            "message"       => sprintf(_("Successfully updated row in %s"),$table),
            "id"            => $this->dbConnection->lastInsertId()
        );
        
        
    }
    
    function delete($options = null) {
        parent::delete($options);
    }
    
    function quoteIdentifier( $identifier ){
        $identifier = (string) $identifier;
        return "`". addslashes($identifier)."`";
    }
} 
