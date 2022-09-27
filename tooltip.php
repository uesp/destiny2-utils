<?php


require_once("/home/uesp/secrets/destiny2.secrets");


class CUespDestiny2Tooltips 
{
	const ERROR_TEMPLATE = "./template/error_tooltip.txt";
	const ITEM_TEMPLATE = "./template/item_tooltip.txt";
	const BASE_IMAGE_URL = 'https://www.bungie.net';
	
	protected $db = null;
	
	protected $inputParams = [];
	protected $itemId = 0;
	
	
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
		
		if (array_key_exists('item', $this->inputParams)) $this->itemId = intval($this->inputParams['item']);
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
	
	
	public function MakeAlphaNum($text)
	{
		return preg_replace('/[^a-zA-Z0-9_]/', '', $text);
	}
	
	
	protected function GetJsonData($json, $key1, $key2 = null, $default = null)
	{
		$value = $json[$key1];
		if ($value == null) return $default !== null ? $default : "Unknown $key1";
		
		if ($key2)
		{
			$value = $value[$key2];
			if ($value == null) return $default !== null ? $default : "Unknown $key2";
		}
		
		return $value;
	}
	
	
	protected function LoadRecord($tableName, $id)
	{
		$id = intval($id);
		$safeTableName = "Destiny" . $this->MakeAlphaNum($tableName) . "Definition";
		
		$result = $this->db->query("SELECT * FROM `$safeTableName` WHERE id='$id';");
		if ($result === false) return false;
		if ($result->num_rows <= 0) return false;
		
		$json = $result->fetch_assoc()['json'];
		$data = json_decode($json, true);
		
		return $data;
	}
	
	
	protected function LoadRelatedData(&$itemData, $key1, $key2, $key3, $tableName)
	{
		$records1 = &$itemData[$key1];
		if ($records1 == null) return false;
		
		$records2 = &$records1[$key2];
		if ($records2 == null) return false;
		
		foreach ($records2 as $id => &$record)
		{
			if ($record[$key3] == null) continue;
			
			$subRecord = $this->LoadRecord($tableName, $record[$key3]);
			if ($subRecord === false) continue;
			
				//"displayProperties":{"description":"How much or little recoil you will experience while firing the weapon.","name":"Stability","hasIcon":false
			$name = $this->GetJsonData($subRecord, 'displayProperties', 'name', '');
			$icon = $this->GetJsonData($subRecord, 'displayProperties', 'icon', '');
			$desc = $this->GetJsonData($subRecord, 'displayProperties', 'description', '');
			
			$record['name'] = $name;
			$record['icon'] = $icon;
			$record['description'] = $desc;
		}
		
		return true;
	}
	
	
	protected function LoadItem($itemId)
	{
		$itemId = intval($itemId);
		
		$result = $this->db->query("SELECT * FROM DestinyInventoryItemDefinition WHERE id='$itemId';");
		if ($result === false) return false;
		if ($result->num_rows <= 0) return false;
		
		$json = $result->fetch_assoc()['json'];
		$itemData = json_decode($json, true);
		
		$this->LoadRelatedData($itemData, "stats", "stats", "statHash", "Stat");
		$this->LoadRelatedData($itemData, "sockets", "socketEntries", "singleInitialItemHash", "InventoryItem");
		
		return $itemData;
	}
	
	
	protected function MakeStatHtml($statData)
	{
		$name = $this->EscapeHtml($statData['name']);
		$value = intval($statData['value']);
		$min = intval($statData['minimum']);
		$max = intval($statData['displayMaximum']);
		
		if ($value <= 0) return "";
		
		$origValue = $value;
		$value = $value * 2;
		$max = $max * 2;
		if ($max > 200) $max = 200;
		if ($value > $max) $value = $max;
		
		$output = "<div class=\"uespD2StatRow\">";
		$output .= "<div class=\"uespD2StatName\">$name</div>";
		$output .= "<div class=\"uespD2StatValueRoot\" style=\"width: {$max}px;\"><div class=\"uespD2StatValue\" style=\"width: {$value}px;\"></div></div>";
		$output .= "<div class=\"uespD2StatValueText\">$origValue</div>";
		
		$output .= "</div>\n";
		return $output;
	}
	
	
	protected function MakeSocketHtml($socketData)
	{
		$name = $this->EscapeHtml($socketData['name']);
		$desc = $this->EscapeHtml($socketData['description']);
		
		if ($socketData['defaultVisible'] == false) return "";
		if ($name == "") return "";
		
		$output = "<div class=\"uespD2SocketRow\">";
		$output .= "<div class=\"uespD2SocketName\">$name</div>";
		$output .= "<div class=\"uespD2SocketDesc\">$desc</div>";
		$output .= "</div>\n";
		return $output;
	}
	
	
	protected function ShowItemTooltip()
	{
		$itemData = $this->LoadItem($this->itemId);
		if ($itemData === false) return $this->ShowErrorTooltip("Item", $this->itemId);
		
		$name = $this->GetJsonData($itemData, 'displayProperties', 'name'); 
		$icon = $this->GetJsonData($itemData, 'displayProperties', 'icon', '');
		$iconWatermark = $this->GetJsonData($itemData, 'iconWatermark');
		$itemType = $this->GetJsonData($itemData, 'itemTypeDisplayName');
		$tierType = $this->GetJsonData($itemData, 'inventory', 'tierType');
		$tierTypeName = $this->GetJsonData($itemData, 'inventory', 'tierTypeName');
		$flavorText = $this->GetJsonData($itemData, 'flavorText');
		
		$imageHtml = "";
		
		if ($icon != "")
		{
			$imageUrl = self::BASE_IMAGE_URL . $icon; 
			$imageHtml = "<img src=\"$imageUrl\">";
		}
		
		$stats = $this->GetJsonData($itemData, 'stats', 'stats', []);
		$statsHtml = "";
		
		foreach ($stats as $statId => $stat)
		{
			$statsHtml .= $this->MakeStatHtml($stat);
		}
		
		$sockets = $this->GetJsonData($itemData, 'sockets', 'socketEntries', []);
		$socketsHtml = "";
		
		foreach ($sockets as $socket)
		{
			$socketsHtml .= $this->MakeSocketHtml($socket);
		}
		
		$replacePairs = array(
				'{titleClass}' => 'uespD2ItemType' . $this->MakeAlphaNum($tierTypeName),
				'{title}' => $this->EscapeHtml($name),
				'{subtitle}' => $this->EscapeHtml($itemType),
				'{image}' => $imageHtml,
				'{stats}' => $statsHtml,
				'{perks}' => $socketsHtml,
		);
		
		$template = file_get_contents(self::ITEM_TEMPLATE);
		$output = strtr($template, $replacePairs);
		
		print ($output);
		
		return true;
	}
	
	
	protected function ShowErrorTooltip($type = "None", $id = "0")
	{
		$replacePairs = array(
				'{type}' => $this->EscapeHtml($type),
				'{id}' => $this->EscapeHtml($id),
		);
		
		$template = file_get_contents(self::ERROR_TEMPLATE);
		$output = strtr($template, $replacePairs);
		
		print ($output);
		
		return true;
	}
	
	
	public function Run()
	{
		$this->OutputHtmlHeader();
		
		if ($this->itemId > 0)
		{
			return $this->ShowItemTooltip();
		}
		else
		{
			return $this->ShowErrorTooltip();
		}
		
		return true;
	}
};


$tooltip = new CUespDestiny2Tooltips();
$tooltip->Run();
