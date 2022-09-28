<?php

require_once("/home/uesp/secrets/destiny2.secrets");


class CUespDestiny2Search
{
	const SEARCH_TEMPLATE = "./template/search.txt";
	
	
	protected $db = null;
	protected $inputParams = [];
	protected $searchText = "";
	protected $searchResults = [];
	
	
	public function __construct()
	{
		$this->InitDatabase();
		$this->ParseInputParams();
	}
	
	
	protected function InitDatabase()
	{
		global $uespDestiny2ReadDBHost;
		global $uespDestiny2ReadUser;
		global $uespDestiny2ReadPW;
		global $uespDestiny2Database;
		
		$this->db = new mysqli($uespDestiny2ReadDBHost, $uespDestiny2ReadUser, $uespDestiny2ReadPW, $uespDestiny2Database);
		if ($this->db->connect_error) exit("Error: Could not connect to mysql database!");
	}
	
	
	protected function ParseInputParams()
	{
		$this->inputParams = $_REQUEST;
		
		if (array_key_exists('search', $this->inputParams)) $this->searchText = trim($this->inputParams['search']);
	}
	
	
	protected function OutputHtmlHeader()
	{
		ob_start("ob_gzhandler");
		
		header("Expires: 0");
		header("Pragma: no-cache");
		header("Cache-Control: no-cache, no-store, must-revalidate");
		header("Pragma: no-cache");
		header("Access-Control-Allow-Origin: *");
		header("content-type: text/html");
	}
	
	
	public function EscapeHtml($text)
	{
		if (gettype($text) != "string") return $text;
		return htmlspecialchars($text);
	}
	
	
	public function EscapeAttr($text)
	{
		return htmlspecialchars($text);
	}
	
	
	public function MakeAlphaNum($text)
	{
		return preg_replace('/[^a-zA-Z0-9_]/', '', $text);
	}
	
	
	protected function GetJsonData($json, $key1, $key2 = null, $default = null)
	{
		$value = $json[$key1];
		if ($value === null) return $default !== null ? $default : "Unknown $key1";
		
		if ($key2)
		{
			$value = $value[$key2];
			if ($value === null) return $default !== null ? $default : "Unknown $key2";
		}
		
		return $value;
	}
	
	
	protected function CreateSearchResultsHtml()
	{
		if ($this->searchText == "") return "";
		
		$count = count($this->searchResults);
		$safeSearch = $this->EscapeHtml($this->searchText);
		$output = "Found $count results matching: $safeSearch <p/>\n";
		$output .= "<table id=\"uespD2SearchResultsTable\">";
		$output .= "<tr><th></th><th>Name</th><th>Class</th><th>Rarity</th><th>Slot</th><th>Type</th></tr>";
		
		foreach ($this->searchResults as $result)
		{
			$id = intval($result['id']);
			$name = $this->EscapeHtml($result['name']);
			$itemData = json_decode($result['json'], true);
			
			$desc = $this->EscapeHtml($this->GetJsonData($itemData, 'displayProperties', 'description', ''));
			if ($desc) $desc = " -- " . $desc;
			
			$icon = $this->EscapeAttr($this->GetJsonData($itemData, 'displayProperties', 'icon', ''));
			$waterIcon = $this->EscapeAttr($this->GetJsonData($itemData, 'iconWatermark', null, ''));
			
			$imageHtml = "";
			if ($icon) $imageHtml = "<img class=\"uespD2SearchImage\" src=\"https://www.bungie.net/$icon\">";
			
			$waterHtml = "";
			//if ($waterIcon) $waterHtml = "<img src=\"https://www.bungie.net/$waterIcon\">";
			
			$tierTypeName = $this->GetJsonData($itemData, 'inventory', 'tierTypeName', '');
			$textClass = 'uespD2ItemTypeText' .$this->MakeAlphaNum($tierTypeName);
			
			$classType = $this->EscapeAttr($this->GetJsonData($itemData, 'classType', null, ''));
			$itemType = $this->EscapeAttr($this->GetJsonData($itemData, 'itemTypeDisplayName'));
			
			$output .= "<tr>";
			$output .= "<td><a href=\"https://light.gg/db/items/$id/\" itemid=\"$id\" class=\"uespDestiny2Toolip $textClass\">$imageHtml</a></td>";
			$output .= "<td><a href=\"https://light.gg/db/items/$id/\" itemid=\"$id\" class=\"uespDestiny2Toolip $textClass\">$name</a><div class=\"uespD2SearchDescription\">$desc</div></td>";
			$output .= "<td></td>";
			$output .= "<td>$tierTypeName</td>";
			$output .= "<td></td>";
			$output .= "<td>$itemType</td>";
			$output .= "</tr>\n";
		}
		
		$output .= "</table>";
		return $output;
	}
	
	
	protected function OutputHtml()
	{
		$replacePairs = array(
				'{searchText}' => $this->EscapeAttr($this->searchText),
				'{searchResults}' => $this->CreateSearchResultsHtml(),
		);
		
		$template = file_get_contents(self::SEARCH_TEMPLATE);
		$output = strtr($template, $replacePairs);
		
		print ($output);
		
		return true;
	}
	
	
	protected function DoSearch()
	{
		$safeName = preg_replace('/[*+\-@<>()"~]/', '', $this->searchText);
		$safeName = $this->db->real_escape_string($safeName);
		$query = "SELECT * FROM DestinyInventoryItemDefinition WHERE MATCH(name) AGAINST('$safeName*' IN BOOLEAN MODE) ORDER BY name;";
		print("<!-- QUERY: $query -->");
		
		$result = $this->db->query($query);
		if ($result === false) return false;
		
		$this->searchResults = [];
		
		while ($row = $result->fetch_assoc())
		{
			$this->searchResults[] = $row;
		}
		
		return true;
	}
	
	
	public function Run()
	{
		$this->OutputHtmlHeader();
		
		if ($this->searchText != "") $this->DoSearch();
		
		$this->OutputHtml();
		
		return true;
	}
};


$search = new CUespDestiny2Search();
$search->Run();