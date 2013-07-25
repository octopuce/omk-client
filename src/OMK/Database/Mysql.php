<?php class OMK_Database_Mysql extends OMK_Database_Adapter {
    /** PDO */
    protected $dbConnection;
    protected $colsName = array("files","logs","queue","settings","variables");
    protected $prefixedColsNames = NULL;
    protected $prefix = "";
    public function __construct($options = null) {
        
        if (array_key_exists("host", $options) && NULL != $options["host"]) {
            $host = $options["host"];
        }else{
            throw new OMK_Exception(_("Missing host."));
        } 
        if (array_key_exists("database", $options) && NULL != $options["database"]) {
            $database = $options["database"];
        }else{
            throw new OMK_Exception(_("Missing host."));
        }
        if (array_key_exists("user", $options) && NULL != $options["user"]) {
            $user = $options["user"];
        }else{
            throw new OMK_Exception(_("Missing user."));
        }
        if (array_key_exists("password", $options) && NULL != $options["password"]) {
            $password = $options["password"];
        } else {
            throw new OMK_Exception(_("Missing password."));
        }
        if (array_key_exists("prefix", $options) && NULL != $options["prefix"]) {
            $this->prefix = $options["prefix"];
            $this->prefixedColsNames = array();
            foreach($this->colsName as $col_name){
                $this->prefixedColsNames[] = $this->prefix.$col_name;
            }
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
    
    private function buildData( &$data, &$vals, &$i, &$cols = array()){
        $srcData = $data;
        foreach ($srcData as $col => $val) {
            unset($data[$col]);
            $quoted_col     = $this->quoteIdentifier($col);
            $cols[]         = $quoted_col;
            switch( (string) $val ){
                case OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP:
                    $vals[] = $quoted_col.' = NOW()';
                break;
                case OMK_Database_Adapter::REQ_INCREMENT:
                    $vals[] = "{$quoted_col} = {$quoted_col} + 1";
                break;
                default:
                    $data[':col'.$i] = $val;
                    $vals[] = $quoted_col.' = :col'.$i;
                    break;
            }

            $i++;
        }
    }

    private function buildDataInsert( &$data, &$vals, &$i, &$cols = array()){
        $srcData = $data;
        foreach ($srcData as $col => $val) {
            unset($data[$col]);
            $quoted_col     = $this->quoteIdentifier($col);
            $cols[]         = $quoted_col;
            switch( (string) $val ){
                case OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP:
                    $vals[] = 'NOW()';
                break;
                case OMK_Database_Adapter::REQ_INCREMENT:
                    $vals[] = "{$quoted_col} + 1";
                break;
                default:
                    $data[':col'.$i] = $val;
                    $vals[] = ':col'.$i;
                    break;
            }

            $i++;
        }
    }

    protected function buildWhere( &$where, &$data, &$i){
        $srcWhere = $where;
        foreach ($srcWhere as $clause => $val) {
            unset($where[$clause]);
            if( is_array($val)){
                foreach($val as $in_key => $in_val){
                    $val[$in_key] = $this->dbConnection->quote($in_val);
                }
                $where[]    = str_replace("?", "(".implode(",", $val).")", $clause);
            } else {
                
                switch((string) $val){
                    case OMK_Database_Adapter::REQ_CURRENT_TIMESTAMP:
                        $where[] = str_replace("?", "NOW()", $clause);
                        break;
                    case OMK_Database_Adapter::REQ_NO_BINDING:
                        $where[] = $clause;
                        break;
                    default:
                        $data[':col'.$i] = $val;
                        $where[] = str_replace("?", ":col{$i}", $clause);
                        break;
                }
            }
            $i++;            
        }

    }
    

    function delete($options = null) {
        parent::delete($options);
    }
    
    
    function exec( $query, $bind = array() ){
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

    public function getLastInsertId() {
        if( NULL == $this->last_insert_id ){
            throw new OMK_Exception(_("Empty last insert id.", self::ERR_EMPTY_LAST_INSERT_ID));
        }
        return $this->last_insert_id;
    }


    function insert($options = null) {
        
        if (array_key_exists("table", $options) && NULL != $options["table"]) {
            $table = $this->prefix.$options["table"];
        } else {
            throw new OMK_Exception(_("Missing table."));
        }
        if (array_key_exists("data", $options) && NULL != $options["data"]) {
            $data = $options["data"];
        } else {
            throw new OMK_Exception(_("Missing data."));
        }
        $cols = array();
        $vals = array();
        $i = 0;
        
        // Builds values to insert
        $this->buildDataInsert($data, $vals, $i, $cols);

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
            
        // Retrieves the last insert id for later use by client
        $this->last_insert_id = $this->dbConnection->lastInsertId();
        
        // Returns success
        return array(
            "code"      => 0,
            "message"   => sprintf(_("Successfully inserted new row in %s"),$table),
            "id"        => $this->last_insert_id
        );
    }
    
    function lock($options = NULL) {
        
        // Checks sanity
        if (array_key_exists("table", $options) && NULL != $options["table"]) {
            $table = $this->prefix.$options["table"];
        } else {
            throw new OMK_Exception(_("Missing table."));
        }
        if (array_key_exists("lock_type", $options) && NULL != $options["lock_type"]) {
            $lock_type = $options["lock_type"];
        } else {
            $lock_type = "WRITE";
        }
        if (array_key_exists("lock", $options)) {
            $lock = $options["lock"];
            if($lock){
                $lock = "LOCK";
            }else{
                $lock = "UNLOCK";
            }
        } else {
            $lock = "LOCK";
        }
        // Builds query
        switch ($lock){
            case "LOCK":
                $sql = "LOCK TABLE ".$this->quoteIdentifier($table)." {$lock_type}";
                break;
            case "UNLOCK":
                $sql = "UNLOCK TABLES";
                break;
            default:
                throw new OMK_Exception(_("Invalid lock method: LOCK or UNLOCK expected"),self::ERR_INVALID_LOCK);
                break;
        }
        // Runs query
        try{
            $affected_rows = $this->exec($sql);
        } catch (OMK_Exception $e){
            $msg          = sprintf(_("Failed to (lock|unlock): %s table %s"), $lock, $table);
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
            "message"       => sprintf(_("Successfull (lock|unlock): %s on table %s"), $lock, $table)
        );

    }
    
    function query( $statement, $data ){
        try { 
            $sth = $this->dbConnection->prepare($statement);
            $sth->execute($data);
            $results = $sth->fetchAll(PDO::FETCH_ASSOC);
            if( false !== $results){
                return $results;
            }
            $error_code     = $this->dbConnection->errorCode();
            $error_info     = implode(" - ", $this->dbConnection->errorInfo() );
            throw new OMK_Exception(sprintf(_("An error occured with mysql exec.\n Code: %s\n Info: %s"),  $error_code, $error_info));
       }  
        catch(PDOException $e) {  
            $msg    = _("Mysql query failed.");
            $this->getClient()->getLoggerAdapter()->log(array(
                "level"     => OMK_Logger_Adapter::WARN,
                "message"   => $msg,
                "exception" => $e
            ));
            throw new OMK_Exception($msg);
        }  
    }
    
    function select($options = null) {
     
        if (array_key_exists("table", $options) && NULL != $options["table"]) {
            $table = $this->prefix.$options["table"];
        } else {
            throw new OMK_Exception(_("Missing table."));
        }
        if (array_key_exists("where", $options) && NULL != $options["where"]) {
            $where = $options["where"];
            if( !is_array($where)){
                throw new OMK_Exception(_("Where clause must be an array."), self::ERR_CLAUSE_WHERE_ARRAY);
            }
        } else {
            $where = array();
        }
        if (array_key_exists("limit", $options) && NULL != $options["limit"]) {
            $limit = $options["limit"];
        }else{
            $limit = "";
        }
        if (array_key_exists("order", $options) && NULL != $options["order"]) {
            $order = $options["order"];
        } else {
            $order = array();
        }
        $i = 0;
        $data = array();
        
        $sql = "SELECT * FROM " . $this->quoteIdentifier($table);
        
        if (count($where)) {
            $this->buildWhere($where, $data, $i);
            $sql .= " WHERE ". implode(" AND ", $where);
        }
        if( $order ){
            if( !is_array($order)){
                $order = array($order);
            }
            $sql .= " ORDER BY ".implode(",",$order);
        }
        if( $limit ){
            $sql .= " LIMIT {$limit} ";
        }
        
        // Runs query
        try{
            
            $rows = $this->query($sql, $data);
            
        } catch (OMK_Exception $e){
            $msg          = sprintf(_("Failed to select row in %s : query %s, data %s"), $table, $sql, str_replace("\n", " ", print_r($data,1)));
            $this->getClient()->getLoggerAdapter()->log(array(
               "level"      => OMK_Logger_Adapter::WARN,
                "message"   => $msg,
                "exception" => $e
            ));
            return array(
                "code"      => self::ERR_SELECT,
                "message"   => $msg
            );
        }
        // Success
        return array(
            "code"          => 0,
            "message"       => sprintf(_("Successfully selected rows in %s %s"),$table,$sql),
            "rows"          => $rows
        );
        
            
        
    }
    
    function unlock( $options = NULL ){
        $options["lock"] = FALSE;
        return $this->lock($options);
    }

    function update($options = null) {
        
        if (array_key_exists("table", $options) && NULL != $options["table"]) {
            $table = $this->prefix.$options["table"];
        } else {
            throw new OMK_Exception(_("Missing table."));
        }
        if (array_key_exists("data", $options) && NULL != $options["data"]) {
            $data = $options["data"];
        } else {
            throw new OMK_Exception(_("Missing data."));
        }
        if (array_key_exists("where", $options) && NULL != $options["where"]) {
            $where = $options["where"];
        } else {
            $where = array();
        }
        if (array_key_exists("id", $options) && NULL != $options["id"]) {
            $where["id = ?"]    = $options["id"];
        } 
        $cols = array();
        $vals = array();
        $i = 0;
        
        // Stores keys/values array $vals to be inserted
        $this->buildData( $data, $vals, $i);
  
        // build the statement
        $sql = "UPDATE "
             . $this->quoteIdentifier($table)
             . ' SET '
             . implode(",", $vals);

        if( count($where) ){
            $this->buildWhere($where, $data, $i);
            $sql .= " WHERE ". implode(" AND ", $where);
        }
        
        // Runs query
        try{
            
            $affected_rows = $this->exec($sql, $data);
            
        } catch (OMK_Exception $e){
            $msg          = sprintf(_("Failed to update in %s"), $table);
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
            "message"       => sprintf(_("Successfully updated row in %s %s"),$table,$sql),
            "id"            => $this->dbConnection->lastInsertId()
        );
        
        
    }
    
    
    function save($options = NULL) {
        
        if (NULL == $options || !count($options)) {
            throw new OMK_Exception(_("Missing options."));
        }
        if ( ! array_key_exists("table", $options) || NULL == $options["table"]) {
            throw new OMK_Exception(_("Missing table."));
        }
        if ( ! array_key_exists("where", $options) || NULL == $options["where"]) {
            throw new OMK_Exception(_("Missing where."));
        }
        if ( ! array_key_exists("data", $options) || NULL == $options["data"]) {
            throw new OMK_Exception(_("Missing data."));
        }
        if ( ! array_key_exists("limit", $options) || NULL == $options["limit"]) {
            $options["limit"] = 1;
        }
        
        $this->recordResult($this->select($options));
        if( !$this->successResult()){
            throw new OMK_Exception($this->result["message"],$this->result["code"]);
        }
        $rows = $this->result["rows"];
        if( count($rows)){
            $this->recordResult($this->update($options));
        }else{
            $this->recordResult($this->insert($options));
        }
        return $this->getResult();
    }


    /**
     * @param type $identifier
     * @return type 
     */
    function quoteIdentifier( $identifier ){
        $identifier = (string) str_replace("`", "", $identifier);
        return "`".$identifier."`";
    }
    
} 
