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
        $this->Lexer->addSpecialPattern('\[\[oracle\:.*?\]\]', $mode, 'plugin_sqlcomp');
        $this->Lexer->addSpecialPattern('\[\[sqlite\:.*?\]\]', $mode, 'plugin_sqlcomp');
        $this->Lexer->addSpecialPattern('\[\[sqlaccess\:.*?\]\]', $mode, 'plugin_sqlcomp');
        $this->Lexer->addSpecialPattern('\[\[postgresql\:.*?\]\]', $mode, 'plugin_sqlcomp');
        $this->Lexer->addSpecialPattern('\[\[sqlcsv\:.*?\]\]', $mode, 'plugin_sqlcomp');

        $aliases = $this->_getAliases();
        if ($aliases) {
            foreach($aliases as $name => $def) {
                $this->Lexer->addSpecialPattern('\[\['.$name.'.*?\]\]', $mode, 'plugin_sqlcomp');
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

        $temp = $match;
        $match = substr($match,2,-2);
        $match = explode("|",$match); # DBTYPE, DBCONN, QUERY, CACHE, EXTRA

        $sqlcomp = $this->_getAliases();
        foreach($sqlcomp as $key => $value)
          if($key == $match[0])
            $match[0] = $value;
            
        $data =  explode(":",$match[0]);
        $data[] = $match[1];
        
        if(isset($match[2]))
          $data[] = $match[2];
        else
          $data[] = $this->getConf('default_cache_timeout');

        if(isset($match[3]))
            $data[] = $match[3];
        else
            $data[] = $this->getConf('show_diffs');
       
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
        $renderer->doc .= $this->_query($data);
        return true;
    }


    //------------------------------------------------------------------------//
    // SQLCOMP FUNCTONS
    //------------------------------------------------------------------------//

    private $sPath = "data/cache/sql/";
    
    /**
     * Layout
     */
    private $aMessages = array(
        "error" => "<div id=\"error\" style=\"text-align:center; font-weight: bold; border: 2px solid #0f0;background-color: #f00; padding: 5px; margin: 5px\">%text%</div>\n",
        "message" => "<div id=\"difference\" style=\"text-align:center; font-weight: bold; border: 2px solid #fd0;background-color: #ffd; padding: 5px; margin: 5px\">%text%</div>\n",
        "pre" => "<table class=\"inline\">\n",
        "post" => "</table>\n",
        //"th" => "<th class=\"row%number%\" style=\"%type%\">%text%</th>",
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
        $sResponse = "";
        foreach($data as $key => $value){
            $sResponse .= "".$key . "=> " .$value ."<br/>\n";
        }
        return $sResponse;
    }

    private function _verifyInput($data){
      if(!is_array($data))
        return false;
      if(count($data) != 8)
        return false;
      return true;    
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
      
        //return $this->_debug($data);
        
        if(!$this->_verifyInput($data))
          return $this->_error($this->getLang("wrong"));
        
        if(!is_dir($this->sPath))
          mkdir($this->sPath);
          
        $filename = $this->sPath.md5($data[0].$data[1].$data[2].$data[3].$data[4].$data[5]);
        
        $Cache = $this->_load($filename);
        $Update = true;
        if(is_array($Cache)){
          $Update = $Cache[0];
          $Cache = $Cache[1];
        }
            
        try{  
          switch($data[0]){
            case "mysql": $rs = $this->_mysql($data[1], $data[2], $data[3],$data[4],$data[5]); break;
            case "mssql": $rs = $this->_mssql($data[1], $data[2], $data[3],$data[4],$data[5]); break;
            case "oracle": $rs = $this->_oracle($data[1], $data[2], $data[3],$data[4],$data[5]); break;
            case "sqlite": $rs = $this->_sqlite($data[1], $data[2], $data[3],$data[4],$data[5]); break;
            case "sqlaccess": $rs = $this->_sqlaccess($data[1], $data[2], $data[3],$data[4],$data[5]); break;
            case "postgresql": $rs = $this->_postgresql($data[1], $data[2], $data[3],$data[4],$data[5]); break;
            case "sqlcsv": $rs = $this->_sqlcsv($data[1], $data[2], $data[3],$data[4],$data[5]); break;
            default: return $this->_error($this->getLang("nohandler"));
          }
        }catch(Exception $ex){
          $sResponse = $this->_error($this->getLang("problem"));
          if(isset($Cache)){
            $sResponse = $this->_print($Cache);    
            $sResponse .= $this->_error($this->getLang("cache"));
          }
          return $sResponse;
        }
        
        if ($rs === false){
          return $this->_error($this->getLang("empty") );
        }
        
        if(isset($type) && $type == "csv")
          return $this->array2csv($rs);
        
        if($this->getConf('show_diffs') == 1) {  
            $difference = $this->_difference($Cache,$rs);
            $sResponse = $difference[0];
            $sResponse .= $difference[1];
        } else {
            $sResponse = $this->_print($rs);
        }
        
        if($Update && isset($rs)){
          $this->_save($filename,$rs,$data[6]);
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
                }else
                  $PrintArray[] = array_merge($New[$i],array("type" => $this->aMessages["same"]));

              }
              
            }

            return array($this->_print($PrintArray),$this->_message($this->getLang("difference")));
      
    }

    private function _sqlaccess($Server,$User,$Pass,$Database,$Query){
                  
        if(!$connection = odbc_connect("DRIVER={Microsoft Access Driver (*.mdb)}; DBQ=$Database", "ADODB.Connection", $Pass, "SQL_CUR_USE_ODBC") or false)
          throw new Exception($this->getLang("problem"));

        $rs = odbc_exec($connection,$Query);

        $dbArray = array();
        while ($row = odbc_fetch_array($rs))
          $dbArray[] = $row;

        odbc_close($connection);
        return $dbArray;

    }

    private function _postgresql($Server,$User,$Pass,$Database,$Query){
                  
        if(!$connection = pg_connect("host=".$Server." dbname=".$Database." user=".$User." password=".$Pass) or false)
          throw new Exception($this->getLang("problem"));
          
        $rs = pg_exec($Query);
        $dbArray = pg_fetch_array($result, NULL, PGSQL_ASSOC);
      
        pg_close($connection);
        return $dbArray;
      
    }
    
    private function _mysql($Server,$User,$Pass,$Database,$Query){
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

    private function _mssql($Server,$User,$Pass,$Database,$Query){
      
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
    
    private function _oracle($Server,$User,$Pass,$Database,$Query){
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
    
    private function _sqlcsv($Server,$User,$Pass,$Database,$Query){  
          
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

    private function _sqlite($Server,$User,$Pass,$Database,$Query){

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
