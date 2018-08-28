<?php
/**
 * DokuWiki Plugin sqlcomp (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Oliver Geisen <oliver@rehkopf-geisen.de>
 * @author  Christoph Lang <calbity@gmx.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_sqlcomp extends DokuWiki_Syntax_Plugin {
    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substition';
    }
    /**
     * @return string Paragraph type
     */
    public function getPType() {
        return 'block';
    }
    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 267;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
//        $this->Lexer->addEntryPattern('<FIXME>',$mode,'plugin_sqlcomp');
        $this->Lexer->addSpecialPattern('\[\[mysql\:.*?\]\]', $mode, 'plugin_sqlcomp');
        $this->Lexer->addSpecialPattern('\[\[mssql\:.*?\]\]', $mode, 'plugin_sqlcomp');
        $this->Lexer->addSpecialPattern('\[\[sqlsrv\:.*?\]\]', $mode, 'plugin_sqlcomp');	    
        $this->Lexer->addSpecialPattern('\[\[oracle\:.*?\]\]', $mode, 'plugin_sqlcomp');
        $this->Lexer->addSpecialPattern('\[\[sqlite\:.*?\]\]', $mode, 'plugin_sqlcomp');
        $this->Lexer->addSpecialPattern('\[\[sqlaccess\:.*?\]\]', $mode, 'plugin_sqlcomp');
        $this->Lexer->addSpecialPattern('\[\[postgresql\:.*?\]\]', $mode, 'plugin_sqlcomp');
        $this->Lexer->addSpecialPattern('\[\[sqlcsv\:.*?\]\]', $mode, 'plugin_sqlcomp');

        $aliases = $this->_getAliases();
        if ($aliases) {
            foreach($aliases as $name => $def) {
                $this->Lexer->addSpecialPattern('\[\['.$name.'\|.*?\]\]', $mode, 'plugin_sqlcomp');
            }
        }
    }

//    public function postConnect() {
//        $this->Lexer->addExitPattern('</FIXME>','plugin_sqlcomp');
//    }

    /**
     * Handle matches of the sqlcomp syntax
     *
     * @param string          $match   The match of the syntax
     * @param int             $state   The state of the handler
     * @param int             $pos     The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler){
        $data = array();

        $match = substr($match,2,-2);
        $match = explode("|",$match); # CONNECTION, SQL, OPTIONS

        // replace dbaliases with connection-string from config
        $dbaliases = $this->_getAliases();
        foreach($dbaliases as $key => $value) {
            if($key == strtolower($match[0])) {
                $match[0] = $value;
            }
        }
        $con = explode(":",$match[0]); # DBTYPE, DBSERVER, DBUSER, DBPASS, DBNAME
        if(count($con) != 5) {
            msg($this->getLang("syntax_dbcon"), -1);
            return;
        }
        $data[] = $con;

        $data[] = $match[1]; # SQL (multiline)

        $opts = array();        
        if(isset($match[2])) {
            // handle options
            $o = explode("&", $match[2]);
            foreach($o as $opt) {
                $opt = strtolower($opt);
                if(ctype_digit($opt)) {
                    $opts['refresh'] = $opt;
                }
                else {
                    msg($this->getLang("syntax_option").': "'.$opt.'"', -1);
                    return;
                }
            }
        } else {
            // apply defaults
            $opts['refresh'] = $this->getConf('default_refresh');
        }
        $data[] = $opts;

        return $data;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        if($mode != 'xhtml') return false;
        $renderer->doc .= utf8_encode($this->_query($data));
        return true;
    }


    //------------------------------------------------------------------------//
    // SQLCOMP FUNCTONS
    //------------------------------------------------------------------------//

    /**
     * Layout
     */
    private $aMessages = array(
        "error" => "<div id=\"error\" style=\"text-align:center; font-weight: bold; border: 2px solid #0f0;background-color: #f00; padding: 5px; margin: 5px\">%text%</div>\n",
        "message" => "<div id=\"difference\" style=\"text-align:center; font-weight: bold; border: 2px solid #fd0;background-color: #ffd; padding: 5px; margin: 5px\">%text%</div>\n",
        "pre" => "<table class=\"inline\">\n",
        "post" => "</table>\n",
        #"th" => "<th class=\"row%number%\" style=\"%type%\">%text%</th>",
        "th" => "<th class=\"row%number%\" style=\"text-align:center;%type%\">%text%</th>",
        "td" => "<td class=\"col%number%\" style=\"%type%\">%text%</td>",
        "tr" => "<tr class=\"row%number%\" style=\"%type%\">%text%</tr>\n",
        "same" => "",
        "new" => "border:2px solid green;",
        "old" => "color:#a0a0a0; text-decoration:line-through;",
        #"deleted" => "border:2px solid red;",
        "deleted" => "background:red; text-decoration:line-through;",
        #"changed" => "border:2px solid blue;"
        "changed" => "background:#F2EA0D;",
    );

    private function _getAliases() {
        $aliases = trim($this->getConf('dbaliases'));
        if ($aliases == '') return;

        $data = array();
        $aliases = explode("\r", $aliases);
        foreach($aliases as $rec) {
            if (substr_count($rec, '=') == 1) {
                list($name, $def) = explode('=', trim($rec));
                $name = strtolower($name);
                $data[$name] = $def;
            }
        }
        return($data);
    }

    private function _error($text){
      return str_replace("%text%",$text,$this->aMessages["error"]);
    }

    private function _message($text){
      return str_replace("%text%",$text,$this->aMessages["message"]);
    }

    private function _debug($data){
        $sResponse = "<pre>";
        if (is_array($data) && !empty($data)) {
            foreach($data as $key => $value){
                $sResponse .= "".$key . "=> " .$value ."<br/>\n";
            }
        } else {
            $sResponse .= "data IS EMPTY";
        }
        $sResponse .= "</pre>";
        return $sResponse;
    }

    private function _load($filename){
      
        $Cache = null;
        $Update = true;
        if(file_exists($filename)){
          $Cache = file_get_contents($filename);
          $Cache = unserialize($Cache);
                    
          $Update = $Cache["Update"];
          if(time() > $Update)
            $Update = true;
          else
            $Update = false;
          $Cache = $Cache["Table"];
        }        
        
        return array($Update,$Cache);
    }
    
    private function _save($filename,$rs,$timestamp){
        $timestamp = (time() + ($timestamp*60));
        $Cache["Update"] = $timestamp;
        $Cache["Table"] = $rs;
        
        $Cache = serialize($Cache);
                
        $handle = fopen($filename,"w");
        fwrite($handle,$Cache);
        fclose($handle);
    }

    private function _query($data,$type=null) {
        #return $this->_debug($data);
        
        if(!is_array($data) || count($data) != 3) {
            msg($this->_error($this->getLang("wrong")), -1);
            return;
        }

        $dbcon = $data[0];
        $sql = $data[1];
        $opts = $data[2];

        $Update = false;
        if($opts['refresh'] > 0) {  
            $sPath = DOKU_INC . "data/cache/sql/";
            if(!is_dir($sPath)) {
                if (!@mkdir($sPath)) {
                    msg($this->_error($this->getLang("cachedir")), -1);
                    return;
                }
            }
            $filename = $sPath.md5(implode('',$dbcon));
            $Cache = $this->_load($filename);
            $Update = true;
            if(is_array($Cache)){
              $Update = $Cache[0];
              $Cache = $Cache[1];
            }
        }
                
        try{  
          switch($dbcon[0]){
            case "mysql": $rs = $this->_mysql($dbcon[1], $dbcon[2], $dbcon[3], $dbcon[4], $sql, $opts); break;
            case "mssql": $rs = $this->_mssql($dbcon[1], $dbcon[2], $dbcon[3], $dbcon[4], $sql, $opts); break;           
	    case "sqlsrv": $rs = $this->_sqlsrv($dbcon[1], $dbcon[2], $dbcon[3], $dbcon[4], $sql, $opts); break;
            case "oracle": $rs = $this->_oracle($dbcon[1], $dbcon[2], $dbcon[3], $dbcon[4], $sql, $opts); break;
            case "sqlite": $rs = $this->_sqlite($dbcon[1], $dbcon[2], $dbcon[3], $dbcon[4], $sql, $opts); break;
            case "sqlaccess": $rs = $this->_sqlaccess($dbcon[1], $dbcon[2], $dbcon[3], $dbcon[4], $sql, $opts); break;
            case "postgresql": $rs = $this->_postgresql($dbcon[1], $dbcon[2], $dbcon[3], $dbcon[4], $sql, $opts); break;
            case "sqlcsv": $rs = $this->_sqlcsv($dbcon[1], $dbcon[2], $dbcon[3], $dbcon[4], $sql, $opts); break;
            default: msg($this->_error($this->getLang("nohandler")), -1); return;
          }
        }catch(Exception $ex){
          msg($this->_error($this->getLang("problem")), -1);
          if(isset($Cache)){
            msg($this->_error($this->getLang("cache")), -1);
            return($this->_print($Cache));
          }
          return;
        }
        
        if ($rs === false){
          return $this->_error($this->getLang("empty") );
        }
        
        if(isset($type) && $type == "csv")
          return $this->array2csv($rs);
        
        if($opts['refresh'] > 0) {  
            $difference = $this->_difference($Cache,$rs);
            $sResponse = $difference[0];
            $sResponse .= $difference[1];
        } else {
            $sResponse = $this->_print($rs);
        }
        
        if($opts['refresh'] > 0) {  
            if($Update && isset($rs)){
              $this->_save($filename,$rs,$data[6]);
            }
        }
        
        return $sResponse;
    }

    private function _print($array){
      
        $i = 0;
        
        $th = "";
        $td = "";
        $tr = "";
        if(!isset($array[0]))
          return $this->_error($this->getLang("problem"));
          
        $temp = array_keys($array[0]);
        foreach($temp as $column){
          if($column == "type")
            continue;  
          $th .= str_replace(array("%number%","%text%","%type%"),array(0,$column,""),$this->aMessages["th"]);
        }
        $tr = str_replace(array("%number%","%text%","%type%"),array(0,$th,""),$this->aMessages["tr"]);
          
        foreach($array as $row) {
          
          $j = 0;
          $td = "";
          if(!isset($row["type"]))
            $row["type"] = $this->aMessages["same"];
            
          foreach($row as $key => $Value){
            if($key == "type")
              continue;
            $td .= str_replace(array("%number%","%text%","%type%"),array($j,$Value,$row["type"]),$this->aMessages["td"]);
            $j++;
          }
          $tr .= str_replace(array("%number%","%text%","%type%"),array($i,$td,$row["type"]),$this->aMessages["tr"]);
          $i++;
        }
        
        $sResponse = $this->aMessages["pre"];
        $sResponse .= $tr;
        $sResponse .= $this->aMessages["post"];
        
        return $sResponse;
      
    }

    private function _difference($Cache,$New){
             
        if($New == $Cache){
          return array($this->_print($New),"");
          return array($this->_print($New),$this->_message($this->getLang("same")));
        }

        if(!isset($New) && isset($Cache))
          return array($this->_print($Cache),$this->_message($this->getLang("difference")));

        if(isset($New) && !isset($Cache))
          return array($this->_print($New),$this->_message($this->getLang("first")));

        if(count($New) <= 0)
          return array($this->_print($Cache),$this->_message($this->getLang("connection")));

        $Max = count($Cache);
        if(count($New) > count($Cache))
          $Max = count($New);
        
        $PrintArray = array();

        for($i=0; $i < $Max; $i++){
          if(isset($Cache[$i]) && !isset($New[$i]))
            $PrintArray[] = array_merge($Cache[$i],array("type" => $this->aMessages["deleted"]));

          if(!isset($Cache[$i]) && isset($New[$i]))
            $PrintArray[] = array_merge($New[$i],array("type" => $this->aMessages["new"]));

          if(isset($Cache[$i]) && isset($New[$i])){
            if($Cache[$i] != $New[$i]){
              $PrintArray[] = array_merge($Cache[$i],array("type" => $this->aMessages["changed"]));
              $PrintArray[] = array_merge($New[$i],array("type" => $this->aMessages["changed"]));
            }else{
              $PrintArray[] = array_merge($New[$i],array("type" => $this->aMessages["same"]));
            }
          }
          
        }

        return array($this->_print($PrintArray),$this->_message($this->getLang("difference")));
    }

    private function _sqlaccess($Server,$User,$Pass,$Database,$Query,$Opts){
                  
        if(!$connection = odbc_connect("DRIVER={Microsoft Access Driver (*.mdb)}; DBQ=$Database", "ADODB.Connection", $Pass, "SQL_CUR_USE_ODBC") or false)
          throw new Exception($this->getLang("problem"));

        $rs = odbc_exec($connection,$Query);

        $dbArray = array();
        while ($row = odbc_fetch_array($rs))
          $dbArray[] = $row;

        odbc_close($connection);
        return $dbArray;

    }

    private function _postgresql($Server,$User,$Pass,$Database,$Query,$Opts){
                  
        if(!$connection = pg_connect("host=".$Server." dbname=".$Database." user=".$User." password=".$Pass) or false)
          throw new Exception($this->getLang("problem"));
          
        $rs = pg_exec($Query);
        $dbArray = pg_fetch_array($result, NULL, PGSQL_ASSOC);
      
        pg_close($connection);
        return $dbArray;
      
    }
    
    private function _mysql($Server,$User,$Pass,$Database,$Query,$Opts){
        if(!$connection = mysqli_connect($Server, $User, $Pass) or false)
          throw new Exception(mysqli_connect_error());
          
        if (!mysqli_select_db($connection, $Database))
          throw new Exception(mysqli_error($connection));

        $rs = mysqli_query($connection, $Query);
        $dbArray = array();
        
        if($rs === true)
          $dbArray[] = array( $this->getLang("affected") => mysqli_affected_rows ($connection));
        else
          while ($row = mysqli_fetch_assoc($rs))
            $dbArray[] = $row;
        
        
        mysqli_close($connection);
        return $dbArray;
      
    }

    private function _mssql($Server,$User,$Pass,$Database,$Query,$Opts){
      
        if(!$dbhandle = mssql_connect($Server, $User, $Pass))
          throw new Exception($this->getLang("problem"));
          
        mssql_select_db($Database, $dbhandle);
        
        $rs = mssql_query($Query);

        $dbArray = array();
        
        if($rs === true)
          $dbArray[] = array( $this->getLang("affected") => mssql_rows_affected ($connection));
        else
          while ($row = mssql_fetch_assoc($rs))
            $dbArray[] = $row;

        mssql_close($dbhandle);
        return $dbArray;

    }

    private function _sqlsrv($Server,$User,$Pass,$Database,$Query,$Opts){
 
		/*
		Connect to the local server using Windows Authentication and specify
		the AdventureWorks database as the database in use. To connect using
		SQL Server Authentication, set values for the "UID" and "PWD"
		 attributes in the $connectionInfo parameter. For example:
		$connectionInfo = array("UID" => $uid, "PWD" => $pwd, "Database"=>"AdventureWorks");
		*/
	$serverName = $Server;
	$connectionInfo = array( 
			"Database" => $Database,
			"Uid" => $User,
			"PWD" => $Pass,
			);
	$conn = sqlsrv_connect( $serverName, $connectionInfo);

	if( $conn )
	{
		//  echo "Connection established.\n";
	}
	else
	{
		 throw new Exception($this->getLang("problem mssql_connect ? ".print_r( sqlsrv_errors(), true) ));
		// die( print_r( sqlsrv_errors(), true));
	}
 
		//-----------------------------------------------
		// Perform operations with connection.
		//-----------------------------------------------
 
	$stmt = sqlsrv_query( $conn, $Query);
	$dbArray = array();
 
	if($stmt=== true) {
		$dbArray[] = array( $this->getLang("affected") => sqlsrv_rows_affected ($stmt));
	} else {
	while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))
		$dbArray[] =  $row;
	}
		/* Close the connection. */
		sqlsrv_close($conn);
		return $dbArray;
    }
 
    private function _oracle($Server,$User,$Pass,$Database,$Query,$Opts){
        if(!$connection = oci_connect($User, $Pass, $Server) or false)
            throw new Exception(oci_error());

        // execute query
        $rs = oci_parse($connection, $Query);
        oci_execute($rs);
        $dbArray = array();

        if($rs === true) {
            $dbArray[] = array($this->aString["affected"] => oci_affected_rows($connection));
        }
        else {
            while($row = oci_fetch_assoc($rs)) {
                $dbArray[] = $row;
            }
        }

        oci_free_statement($rs);
        oci_close($connection);

        return $dbArray;
    }
    
    private function _sqlcsv($Server,$User,$Pass,$Database,$Query,$Opts){
          
        if(!$handle = fopen($Database,"r"))
          throw new Exception($this->getLang("nohandler"));
          
        $dbArray = array();
        $keys = fgetcsv ( $handle , 1000, $Query);

        while ($row = fgetcsv ( $handle , 1000, $Query)){
          $temprow = array();
          foreach($row as $key => $value)
            $temprow[$keys[$key]] = $value;

          $dbArray[] = $temprow;

        }

        fclose($handle);
        return $dbArray;

    }

    private function _sqlite($Server,$User,$Pass,$Database,$Query,$Opts){

        $dbHandle = new PDO('sqlite:'.$Database);

        $result = $dbHandle->query($Query);
        if(!$result)
          throw new PDOException;
        $dbArray = array();
        
        if($result->rowCount() > 0)
          $dbArray[] = array( $this->getLang("affected") => $result->rowCount() );
        else
          while($row = $result->fetch(PDO::FETCH_ASSOC))
            $dbArray[] = $row;
          
        return $dbArray;
        
    }

    private function array2csv($data){
      
      $sResponse = "";
      
      $keys = array_keys($data[0]);
      $sResponse .= implode(";",$keys)."\n";
      foreach($data as $row)
        $sResponse .= implode(";",$row)."\n";
      
      return $sResponse;
      
    }

 }
// vim:ts=4:sw=4:et:
