<?php
// vim: set sw=2 ts=2 enc=utf-8 syntax=php :

/**
 * sqlcomp plugin for DokuWiki
 *
 * @license   GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author    Christoph Lang <calbity@gmx.de>
 *            (modified by Oliver Geisen <oliver.geisen@kreisbote.de>
 * @link			http://www.dokuwiki.org/plugin:tutorial
 *
 * Usage:
 * [[mysql:server:username:password:database|query|refresh]]
 *   or
 * [[PROFILE|query|refresh]]
 *   where PROFILE is one of config.php (which is much more safe)
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_sqlcomp extends DokuWiki_Syntax_Plugin {

  private $sPath = "sqlcomp/"; # relative to $conf['cachedir'] (DOKU_INC/data/cache)
  private $sConfig; # path will be set by constructor
 
  /**
   * Layout
   */
  private $aMessages = array(
    "error" => "<div id=\"error\" style=\"text-align:center; font-weight: bold; border: 2px solid #0f0;background-color: #f00; padding: 5px; margin: 5px\">%text%</div>\n",
    "message" => "<div id=\"difference\" style=\"text-align:center; font-weight: bold; border: 2px solid #fd0;background-color: #ffd; padding: 5px; margin: 5px\">%text%</div>\n",
    "pre" => "<table class=\"inline\">\n",
    "post" => "</table>\n",
    "th" => "<th class=\"row%number%\" style=\"text-align:center;%type%\">%text%</th>",
    "td" => "<td class=\"col%number%\" style=\"%type%\">%text%</td>",
    "tr" => "<tr class=\"row%number%\" style=\"%type%\">%text%</tr>\n",
    "same" => "",
    "new" => "border:2px solid green;",
    "old" => "color:#a0a0a0; text-decoration:line-through;",
    "deleted" => "background:red; text-decoration:line-through;",
    "changed" => "background:#F2EA0D;",
  );

  /**
   * Default Language - German
   */
  private $aString = array(
    //Number of affected Rows
    "affected" => "Anzahl geänderter Zeilen",
    //This Database Type is not yet Supported...
    "nohandler" => "Dieser Datenbanktyp wird (noch) nicht unterstützt...",
    //There are some differences in the table!
    "difference" => "Es wurden Unterschiede in den Tabellen festgestellt!",
    //Everything is allright.
    "same" => "Alles in Ordnung.",
    //The resultset is empty.
    "empty" => "Das Resultset ist leer.",
    //An unkown error occured!
    "problem" => "Es ist ein unbekanntes Problem aufgetreten!",
		//An SQL-Error occured during query
		"sqlerror" => "Die SQL-Abfrage der Datenbank ist fehlerhaft!",
    //Cache is displayed, but new data could not be retrieved.
    "cache" => "Cache wird angezeigt, aber neue Daten konnten nicht abgerufen werden.",
    //Cache was refreshed, or table was collected for the first time.
    "first" => "Der Cache wurde soeben erneuert, oder die Tabelle wurde das erste Mal abgerufen.",
    //New data could not be retrieved.
    "connection" => "Die neuesten Daten konnten nicht abgerufen werden.",
    //The data is not valid. Please review your connection settings!
    "wrong" => "Die eingegebenen Daten sind ungültig! Bitte Überprüfen!",
    //Access denied creating cachedir
    "mkdir_failed" => "Beim Versuch ein Verzeichnis zu erstellen ist ein Fehler aufgetreten!",
		"cachewrite_failed" => "Die Cache-Datei konnte nicht gespeichert werden!",
  );

  private $defaultRefresh = 1;


	// ---------------------------------------------------------------------------

  function __construct()
  {
    # this is needed because class-props can't be assigned global constants directly (03.08.2013/ogeisen)
    $this->sConfig = DOKU_PLUGIN . 'sqlcomp/config.php';
file_put_contents("/tmp/debug", "Add lexer\n", FILE_APPEND);
  }

  function getInfo()
  {
    return array(
      'author'  => 'Christoph Lang',
      'email'   => 'calbity@gmx.de',
      'date'    => '2008-07-10',
      'name'    => 'SQLCOMP Plugin',
      'desc'    => 'This plugin let you display reultsets from various databases and show changes.',
      'url'     => 'http://www.google.de'
    );
  }

	/**
	 * What kind of syntax are we?
	 */
	function getType()
	{
		return 'substition';
	}

	/**
	 * What about paragraphs?
	 */
	function getPType()
	{
		return 'block';
	}

	/**
	 * Where to sort in?
	 */
	function getSort()
	{
		return 267;
	}

	/**
	 * Connect pattern to lexer
	 */
	function connectTo($mode)
	{
		$this->Lexer->addSpecialPattern('\[\[mysql\:.*?\]\]',$mode,'plugin_sqlcomp');
/* 
		if(!file_exists($this->sConfig))
			$this->_createConfig();  
 
		include($this->sConfig);
 
		foreach($sqlcomp as $key => $value)
          #$this->Lexer->addSpecialPattern('\[\['.$key.'.*?\]\]', $mode, 'plugin_sqlcomp');
					#KB IMPORTANT CHANGE TO DISTINGUISH BETWEEN LINKS AND CONFIGS
          $this->Lexer->addSpecialPattern('\[\['.$key.'\|.*?\]\]', $mode, 'plugin_sqlcomp');
*/
	}

	/**
	 * Handle the match
	 */
	function handle($match, $state, $pos, &$handler)
	{
		$temp = $match;
		$match = substr($match,2,-2); # remove braces [[, ]]

		$match = explode("|",$match); # split into DBCON, QUERY, CACHE, EXTRA

		# handle DBCON	
		if(file_exists($this->sConfig)){
			include($this->sConfig);

		foreach($sqlcomp as $key => $value)
			if($key == $match[0])
				$match[0] = $value;
		}

		// Array ( [pdb] => mysql:kbwm-prod.kreisbote.de:pdb_user:use4db:pdb [jps] => mysql:kbwm-prod.kreisbote.de:jps:sq88ll:journal ) 
		$MyData =  explode(":",$match[0]);  # adds 5 fields: type:host:user:pass:db

		# replace spaces with ':'
		for($i=0;$i < 5; $i++)
			$MyData[$i] = str_replace(" ", ":",$MyData[$i]);

		# handle QUERY
		$MyData[] = $match[1];

		# handle CACHE
		if(isset($match[2]))
			$MyData[] = $match[2];    
		else
			$MyData[] = $this->defaultRefresh;  

		# KREISBOTE: handle EXTRA
		if(isset($match[3]))
		$MyData[] = explode(',',$match[3]);
		else
		$MyData[] = array();

		return $MyData;
	}


	/**
	 * Create output
	 */
	function render($format, &$renderer, $data)
	{
		if($format != 'xhtml')
		{
			return FALSE;
		}

		$renderer->doc .= $this->_query($data);

		return TRUE;
	}


	// ---------------------------------------------------------------------------
	// PRIVATES
	// ---------------------------------------------------------------------------

  private function _error($text)
	{
    return str_replace("%text%",$text,$this->aMessages["error"]);      
  }


	private function _message($text)
	{
		return str_replace("%text%",$text,$this->aMessages["message"]);
	}


	private function _debug($data)
	{
		$sResponse = "";
		foreach($data as $key => $value)
		{
			$sResponse .= "".$key . "=> " .$value ."<br/>\n";
		}
		return $sResponse;
	}

 
	private function _verifyInput($data)
	{
		if(!is_array($data))
		{
			return false;
		}

		if(count($data) != 8) # KREISBOTE: changed to 8
		{
			return false;
		}

		return true;    
	}


 	private function _cache_load($filename)
	{
		$Cache = null;
		$Update = true;

		if(file_exists($filename))
		{
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


	private function _cache_save($filename,$rs,$timestamp)
	{
		$timestamp = (time() + ($timestamp*60));

		$Cache["Update"] = $timestamp;
		$Cache["Table"] = $rs;

		$Cache = serialize($Cache);

		if ( ! $handle = fopen($filename,"w"))
		{
			return $this->_error($this->aString["cachewrite_failed"]);
		}
		fwrite($handle,$Cache);
		fclose($handle);

		return TRUE;
	}
 

	// ---------------------------------------------------------------------------

 
	private function _mysql($Server,$User,$Pass,$Database,$Query)
	{
		if(!$connection = mysql_connect($Server, $User, $Pass) or false)
			throw new Exception(mysql_error());
 
		if(!mysql_select_db($Database, $connection))
			throw new Exception(mysql_error());

		// let result be German UTF8 (weekdays, numbers, etc.)
	# TODO: get configured
		if(!mysql_query("SET character_set_results = 'utf8', character_set_connection = 'utf8', character_set_client = 'utf8', lc_time_names = 'de_DE'"))
		{
			throw new Exception(mysql_error());
		}

	// transport UTF8 coded strings/fieldnames to server
#        $Query = utf8_decode($Query); # NOT NEEDED IF SET BEFORE QUERY

	// KREISBOTE: added support for multiple queries
	// NOTE: only the last query gives the resultset!!!
	$q = split("\n",$Query);
	$s = '';
	$multi = array();
	foreach($q as $line){
	  if(preg_match('/[^\\\\];\s*$/',$line)){
	    $s .= substr(rtrim($line),0,-1);
	    $multi[] = $s;
	    $s = '';
	  } else {
	    $s .= $line."\n";
	  }
	}
	$multi[] = $s;

		foreach($multi as $q){
			if(trim($q) == '') continue;  # ignore empty lines
			$q = preg_replace('/\\\\;/',';',$q);  # un-escape ';' chars in query
			#DEBUG: print '<pre style="background:yellow">'.$q.'</pre>';
			$rs = mysql_query($q);
		}

		# If resultset is simply 'true' than an INSERT or UPDATE operation has done.
		# In this case, return only the number of rows affected by this operation. 

		$dbArray = array();

		if ($rs === FALSE)
		{
			throw new Exception(mysql_error());
		}
		elseif($rs === TRUE)
		{
			$dbArray[] = array(
				$this->aString["affected"] => mysql_affected_rows($connection)
			);
		}
		else
		{
			while ($row = mysql_fetch_assoc($rs))
			{
				$dbArray[] = $row;
			}
		}
 
		#print '<pre style="background:yellow">'; print_r($dbArray); print '</pre>';

		mysql_close($connection);

		return $dbArray;
	}

	// ---------------------------------------------------------------------------


	/**
	 *
	 */
		/*
		Array
		(
				[0] => mysql
				[1] => kbwm-prod.kreisbote.de
				[2] => jps
				[3] => PASSWORD
				[4] => journal
				[5] => 
		SELECT
		ORDER BY auftermin.adname DESC,auftrag.kb_modified DESC
		LIMIT 60;
				[6] => 3600
				[7] => nodiff
		*/
	private function _query($data,$type=null)
	{
		global $conf;

		//return $this->_debug($data);

		if(!$this->_verifyInput($data))
		{
			return $this->_error($this->aString["wrong"]);
		}

		$savedir = $conf['cachedir'].'/'.$this->sPath;
		if ( ! is_dir($savedir) && ! mkdir($savedir))
		{
			return $this->_error($this->aString["mkdir_failed"]);
		}
 
		$filename = $savedir.md5($data[0].$data[1].$data[2].$data[3].$data[4].$data[5]);
		$Cache = $this->_cache_load($filename);

		$Update = true;
		if(is_array($Cache))
		{
			$Update = $Cache[0];
			$Cache = $Cache[1];
		}

		try{  
			$rs = $this->_mysql($data[1], $data[2], $data[3],$data[4],$data[5]);
		}
		catch(Exception $ex)
		{
			if($conf['allowdebug'])
			{
				$err = $this->aString["problem"];
				$err .= '<br>"'.$ex->getMessage().'"';
				$err .= '<br>Aufgetreten in Zeile '.$ex->getLine().' von Datei '.$ex->getFile();
				return $this->_error($err);
			}

			return $this->_error($this->aString["sqlerror"].'<br>'.$ex->getMessage());
		}

		if ($rs === false){
			return $this->_error($this->aString["empty"] );
		}
 
		if(isset($type) && $type == "csv")
			return $this->array2csv($rs);

		#KREISBOTE
		$difference = $this->_difference($Cache,$rs,$data[7]);
	  $sResponse = $difference[0];    

		if($Update && isset($rs))
		{
			$res = $this->_cache_save($filename,$rs,$data[6]);
			if ($res !== TRUE)
			{
				return $res;
			}
		}  

		$sResponse .= $difference[1];      
 
		return $sResponse;
	}


	/**
	 *
	 */
	function _print($array)
	{
		#print '<pre style="background:yellow">';print_r($array); print '</pre>';
		$i = 0;
		$th = "";
		$td = "";
		$tr = "";

		# KREISBOTE: here we need to handle empty results not as an error
		if($array[0] === false){
			return $this->_error($this->aString["problem"]);
		}
		if(!isset($array[0])){
			return ""; # no result
		}

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
 
	function _difference($Cache,$New,$opts)
	{
		if(in_array('nodiff',$opts))
			$Cache = $New;
 
		if($New == $Cache){
	      # KREISBOTE: doppelt? lieber keine "Alles in Ordnung" meldung...
              return array($this->_print($New),"");
#              return array($this->_print($New),$this->_message($this->aString["same"]));
            }
 
            if(!isset($New) && isset($Cache))
              return array($this->_print($Cache),$this->_message($this->aString["difference"]));
 
            if(isset($New) && !isset($Cache))
              return array($this->_print($New),$this->_message($this->aString["first"]));
 
            if(count($New) <= 0)
              return array($this->_print($Cache),$this->_message($this->aString["connection"]));
 
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
                  $PrintArray[] = array_merge($Cache[$i],array("type" => $this->aMessages["old"]));
                  $PrintArray[] = array_merge($New[$i],array("type" => $this->aMessages["changed"]));
                }else
                  $PrintArray[] = array_merge($New[$i],array("type" => $this->aMessages["same"]));
 
              }                
 
            }
 
            return array($this->_print($PrintArray),$this->_message($this->aString["difference"]));
 
    }


	function _createConfig(){  
 
        $sContent = "";
        $sContent .= "<?php\n";
        $sContent .= "//Sample Configfile\n";
        $sContent .= "//Add as many servers as you want here...\n";
        $sContent .= '$sqlcomp["localhost"] = "mysql:localhost:root::information_schema";';
        $sContent .= '$sqlcomp["sampleconnection"] = "sqltype:servername:username:password:database";';
        $sContent .= "\n?>\n";
 
        $handle = fopen($this->sConfig,"w");
        fwrite($handle,$sContent);
        fclose($handle);
 
	}
 
}
// End of plugin
