<?php
if (php_sapi_name() != "cli") die("Error: Can only be run from command line!");

require_once("/home/uesp/secrets/destiny2.secrets");


class CUespDestiny2DbUpdater 
{
	const MANIFEST_URL = 'https://www.bungie.net/Platform/Destiny2/Manifest/';
	const BASE_CONTENT_URL = 'https://www.bungie.net';
	const DOWNLOAD_PATH = '/home/destiny2/downloads/';
	const SQLITE_PATH = '/home/destiny2/sqlitedbs/';
	
	const SKIP_DOWNLOAD = true;	// For testing only
	
	
	protected $manifestData = [];
	protected $worldContentUrl = "";
	protected $sqliteDbFile = "";
	protected $db = null;
	
	
	public function __construct()
	{
		$this->InitDatabase();
	}
	
	
	protected function ReportError($msg)
	{
		print("$msg\n");
		return false;
	}
	
	
	protected function InitDatabase()
	{
		global $uespDestiny2WriteDBHost;
		global $uespDestiny2WriteUser;
		global $uespDestiny2WritePW;
		global $uespDestiny2Database;
		
		$this->db = new mysqli($uespDestiny2WriteDBHost, $uespDestiny2WriteUser, $uespDestiny2WritePW, $uespDestiny2Database);
		if ($this->db->connect_error) exit("Error: Could not connect to mysql database!");
	}
	
	
	public function GetManifest()
	{
		$jsonText = file_get_contents(self::MANIFEST_URL);
		if ($jsonText === false) return $this->ReportError("\tError: Failed to load manifest from '" . self::MANIFEST_URL . "'!");
		
		//print($jsonText);
		
		$result = json_decode($jsonText, true);
		if ($result === false || $result === null) return $this->ReportError("\tError: Failed to convert JSON manifest data!");
		
		$this->manifestData = $result;
		return $result;
	}
	
	
	protected function DownloadContent($url)
	{
		if (self::SKIP_DOWNLOAD)
		{
			$this->sqliteDbFile = self::SQLITE_PATH . basename($url) . ".sqlite3";
			
			if (!file_exists($this->sqliteDbFile)) return $this->ReportError("Error: Expected SQLITE database file '{$this->sqliteDbFile}' doesn't exist (download turned off)!");
			$this->ReportError("\tUsing existing SQLITE database file '{$this->sqliteDbFile}'!");
			return true;
		}
		
		$filename = basename($url);
		$downloadZip = self::DOWNLOAD_PATH . $filename . ".zip";
		
		$this->ReportError("\tDownloading '$url' to '$downloadZip'...");
		
		$result = file_put_contents($downloadZip, fopen($url, 'r'));
		if (!$result) return $this->ReportError("\tError: Failed to download '$url' to '$downloadZip'!");
		
		$this->ReportError("\tUnzipping '$downloadZip'...");
		
		$zip = new ZipArchive;
		$result = $zip->open($downloadZip);
		if (!$result) return $this->ReportError("\tError: Failed to open ZIP file '$downloadZip'!");
		
		$outputPath = self::SQLITE_PATH;
		
		$result = $zip->extractTo($outputPath);
		$zip->close();
		
		if (!$result) return $this->ReportError("\tError: Failed to decompress ZIP file '$downloadZip' to '$outputPath'!\n\t" . $zip->getStatusString());
		//$this->ReportError("Unziped to '$outputPath'!");
		
		$oldFilename = $outputPath . $filename;
		$newFilename = $outputPath . $filename . ".sqlite3";
		
		if (!rename($oldFilename, $newFilename)) $this->ReportError("\tError: Failed to rename '$oldFilename' to '$newFilename'!");
		
		$this->sqliteDbFile = $newFilename;
		$this->ReportError("\tUnziped to '$newFilename'!");
		
		return true;
	}
	
	
	public function TestSqliteDb($filename)
	{
		$sqliteDb = new SQLite3($filename);
		
		$version = $sqliteDb->querySingle('SELECT SQLITE_VERSION()');
		$this->ReportError("\tSQLITE Version: $version");
		
		$tablesQuery = $sqliteDb->query("SELECT name FROM sqlite_master WHERE type='table';");
		if ($tablesQuery === false) return $this->ReportError("\tError: Failed to find tables in SQLITE database!");
		
		$tableCount = 0;
		$tables = [];
		
		while ($table = $tablesQuery->fetchArray(SQLITE3_ASSOC)) 
		{
			++$tableCount;
			$tables[] = $table['name'];
		}
		
		$this->ReportError("\tFound $tableCount tables in SQLITE database!");
		
		return true;
	}
	
	
	protected function CopySqliteToMysql($filename)
	{
		global $uespDestiny2Database;
		
		$sqliteDb = new SQLite3($filename);
		
		$this->ReportError("\tCopying SQLITE database '$filename' to MySQL database '$uespDestiny2Database'...");
		
		$tablesQuery = $sqliteDb->query("SELECT name FROM sqlite_master WHERE type='table';");
		if ($tablesQuery === false) return $this->ReportError("\tError: Failed to find tables in SQLITE database!");
		
		$tables = [];
		
		while ($table = $tablesQuery->fetchArray(SQLITE3_ASSOC)) 
		{
			$tables[] = $table['name'];
		}
		
		$tableCount = count($tables);
		$this->ReportError("\tFound $tableCount tables in SQLITE database!");
		
		foreach ($tables as $table)
		{
					// Make sure table name is safe
			if (!preg_match('/[a-zA-Z0-9_]+/', $table))
			{
				$this->ReportError("\tError: Table '$table' is not a valid table name (skipping copy)!");
				continue;
			}
			
			$countQuery = $sqliteDb->querySingle("SELECT count(*) FROM $table;");
			
			$this->ReportError("\tCopying $countQuery rows from table '$table'...");
			
			$createQuery = $sqliteDb->querySingle("SELECT sql FROM sqlite_master WHERE name = '$table';");
			
			if ($createQuery === false) 
			{
				$this->ReportError("\tError: Failed to get schema of SQLITE table '$table' (skipping copy)!");
				continue;
			}
			
			$deleteQuery = $this->db->query("DROP TABLE IF EXISTS `$table`;");
			
			if ($deleteQuery === false) 
			{
				$this->ReportError("\tError: Failed to delete existing MySQL table '$table' (skipping copy)!");
				continue;
			}
			
				// Transform SQLITE format to SQL
			$createQuery = preg_replace('/\[([a-zA-Z_]+)\]/', '\1', $createQuery) . ';';
			$createQuery = str_replace('DEFAULT (null)', 'DEFAULT NULL', $createQuery);
			$createQuery = str_replace(' key ', ' `key` ', $createQuery);
			$createQuery = str_replace('id INTEGER PRIMARY KEY', 'id INT UNSIGNED PRIMARY KEY', $createQuery);
			
			if (preg_match('/key TEXT PRIMARY KEY/', $createQuery))
			{
				$createQuery = str_replace('key TEXT PRIMARY KEY', '`key` TEXT', $createQuery);
				$createQuery = str_replace(');', " , \nPRIMARY KEY idx_key(`key`(100))\n);", $createQuery);
			}
			
			//print("$createQuery");
			
			$mysqlCreate = $this->db->query($createQuery);
			
			if ($mysqlCreate === false) 
			{
				$this->ReportError("\tError: Failed to create the MySQL table '$table' (skipping copy)!");
				$this->ReportError("\tCreate Query: $createQuery");
				continue;
			}
			
			
			$selectQuery = $sqliteDb->query("SELECT * FROM $table;");
			
			while ($row = $selectQuery->fetchArray(SQLITE3_ASSOC)) 
			{
				$values = [];
				$cols = [];
				
				foreach ($row as $col => $value)
				{
						// Check for unsafe column names
					if (!preg_match('/[a-zA-Z0-9_]+/', $col))
					{
						$this->ReportError("\tError: Column '$table:$col' is not a valid column name (skipping copy)!");
						continue;
					}
					
					if ($col == "id")
					{
						$value = intval($value);
						if ($value < 0) $value += 4294967296;
					}
					
					$value = $this->db->real_escape_string($value);
					$values[] = "'$value'";
					$cols[] = "`$col`";
				}
				
				$colValues = implode(",", $cols);
				$strValues = implode(",", $values);
				$query = "INSERT INTO `$table`($colValues) VALUES($strValues);";
				
				$insertQuery = $this->db->query($query);
				
				if ($insertQuery === false) 
				{
					$this->ReportError("\tError: Failed to insert row into MySQL table '$table'!");
					$this->ReportError("\t$query");
					$this->ReportError("\tError: " . $this->db->error);
				}
			}
		}
		
		return true;
	}
	
	
	public function LoadWorldData()
	{
		$response = $this->manifestData['Response'];
		if (!$response) $this->ReportError("\tError: Missing 'Response' in JSON manifest data!");
		
		$worldContentRoot = $response['mobileWorldContentPaths'];
		if (!$worldContentRoot) $this->ReportError("\tError: Missing 'mobileWorldContentPaths' in JSON Response data!");
		
		$worldContent = $worldContentRoot['en'];
		if (!$worldContent) $this->ReportError("\tError: Missing 'en' in JSON mobileWorldContentPaths data!");
		
		$worldContent = self::BASE_CONTENT_URL . $worldContent;
		$this->worldContentUrl =  $worldContent;
		$this->ReportError("\tFound world content at '$worldContent'...");
		
		if (!$this->DownloadContent($worldContent)) return false;
		
		if (!$this->TestSqliteDb($this->sqliteDbFile)) return false;
		
		if (!$this->CopySqliteToMysql($this->sqliteDbFile)) return false;
		
		return true;
	}
	
	
	public function Run()
	{
		if ($this->GetManifest() === false) return false;
		$this->ReportError("\tSuccessfully loaded manifest data from '" . self::MANIFEST_URL . "'!");
		
		
		$this->LoadWorldData();
		
		return true;
	}
	
};


$dbUpdate = new CUespDestiny2DbUpdater();
$dbUpdate->Run();