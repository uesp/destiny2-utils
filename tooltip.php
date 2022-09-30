<?php
/*
 * TODO
 * 		- Tier Names: Exotic, Legendary, Rare, Common, Basic
 */


require_once("/home/uesp/secrets/destiny2.secrets");


class CUespDestiny2Tooltips 
{
	const ERROR_TEMPLATE = "./template/error_tooltip.txt";
	const ITEM_TEMPLATE = "./template/item_tooltip.txt";
	const BASE_IMAGE_URL = 'https://www.bungie.net';
	
	const IGNORE_STAT_NAMES = [
			"Inventory Size" => true,
	];
	
	const VALUE_DISPLAY_STATS = [
			"Rounds Per Minute" => true,
			"Magazine" => true,
	];
	
	const IGNORE_SOCKET_NAMES = [
			'Default Effect' => true,
			'Default Ornament' => true,
			'Default Shader' => true,
			'Tracker Disabled' => true,
			'Empty Aspect Socket' => true,
			'Empty Catalyst Socket' => true,
			'Empty Fragment Socket' => true,
			'Empty Mod Socket' => true,
	];
	
	protected $db = null;
	
	protected $inputParams = [];
	protected $itemId = 0;
	
	protected $classData = [];
	protected $equipSlotData = [];
	
	
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
		if ($value === null) return $default !== null ? $default : "Unknown $key1";
		
		if ($key2)
		{
			$value = $value[$key2];
			if ($value === null) return $default !== null ? $default : "Unknown $key2";
		}
		
		return $value;
	}
	
	
	protected function LoadData($table, $indexField = null)
	{
		$table = $this->MakeAlphaNum($table);
		$query = "SELECT * FROM `$table`";
		$result = $this->db->query($query);
		if ($result === false) return [];
		
		$data = [];
		
		while ($row = $result->fetch_assoc())
		{
			$id = intval($row['id']);
			
			if ($indexField != null)
			{
				$json = json_decode($row['json'], true);
				$id1 = $this->GetJsonData($json, $indexField, null, false);
				if ($id1 !== false) $id = $id1;
			}
			
			$data[$id] = $row;
		}
		
		return $data;
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
	
	
	protected function SetSocketTypes(&$itemData)
	{
		if ($this->GetJsonData($itemData, "sockets", "socketEntries") == null) return false;
		$sockets = &$itemData['sockets']['socketEntries'];
		
		$socketCategories = $this->GetJsonData($itemData, "sockets", "socketCategories");
		
		foreach ($socketCategories as $category => $socketCategory)
		{
			$socketIndexes = $socketCategory['socketIndexes'];
			if ($socketIndexes == null) continue;
			
			foreach($socketIndexes as $index)
			{
				if ($sockets[$index] != null)
				{
					$sockets[$index]['socketCategory'] = $category;
					$sockets[$index]['socketCategoryName'] = $socketCategory['name'];
				}
			}
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
		$this->LoadRelatedData($itemData, "sockets", "socketCategories", "socketCategoryHash", "SocketCategory");
		
		$this->SetSocketTypes($itemData);
		
		return $itemData;
	}
	
	
	protected function MakeStatHtml($statData)
	{
		$name = $this->EscapeHtml($statData['name']);
		$value = intval($statData['value']);
		$min = intval($statData['minimum']);
		$max = intval($statData['displayMaximum']);
		
		if (!$this->ShouldDisplayStat($statData)) return "";
		
		if ($this->IsValueDisplayStat($statData)) return "";
		
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
	
	
	protected function MakeStatValueHtml($statData)
	{
		$name = $this->EscapeHtml($statData['name']);
		$value = intval($statData['value']);
		$min = intval($statData['minimum']);
		$max = intval($statData['displayMaximum']);
		
		if (!$this->ShouldDisplayStat($statData)) return "";
		if (!$this->IsValueDisplayStat($statData)) return "";
		
		$origValue = $value;
		$value = $value * 2;
		$max = $max * 2;
		if ($max > 200) $max = 200;
		if ($value > $max) $value = $max;
		
		$output = "<div class=\"uespD2StatRow\">";
		$output .= "<div class=\"uespD2StatName\">$name</div>";
		$output .= "<div class=\"uespD2StatValueText\">$origValue</div>";
		
		$output .= "</div>\n";
		return $output;
	}
	
	
	protected function IsValueDisplayStat($statData)
	{
		$name = $statData['name'];
		
		if (self::VALUE_DISPLAY_STATS[$name]) return true;
		
		return false;
	}
	
	
	protected function ShouldDisplayStat($statData)
	{
		$name = $statData['name'];
		$value = intval($statData['value']);
		
		if ($value <= 0) return false;
		if ($name == null || $name == "") return false;
		
		if (preg_match('/Deprecated/i', $name)) return false;
		
		if (self::IGNORE_STAT_NAMES[$name]) return false;
		
		return true;
	}
	
	
	protected function ShouldDisplaySocket($socketData)
	{
		if ($socketData['defaultVisible'] == false) return false;
		
		$name = $socketData['name'];
		$desc = $socketData['description'];
		
		if ($name == null || $name == "") return false;
		
		if (preg_match('/deprecated/i', $name)) return false;
		if ($desc && preg_match('/deprecated/i', $desc)) return false;
		
		if (self::IGNORE_SOCKET_NAMES[$name]) return false;
		
		return true;
	}
	
	
	protected function MakeSocketHtml($socketData)
	{
		$socketCategory = $socketData['socketCategoryName'];
		//$name = $this->EscapeHtml($socketData['name'] . " ($socketCategory)");
		$name = $this->EscapeHtml($socketData['name']);
		$desc = $this->EscapeHtml($socketData['description']);
		$icon = $socketData['icon'];
		
		if (!$this->ShouldDisplaySocket($socketData)) return "";
		
		$output = "<div class=\"uespD2SocketRow\">";
		
		if ($icon)
		{
			$iconUrl = self::BASE_IMAGE_URL . $icon;
			$output .= "<div class=\"uespD2SocketIcon\"><img src=\"$iconUrl\"></div>";
		}
		
		$output .= "<div class=\"uespD2SocketName\">$name</div>";
		$output .= "<div class=\"uespD2SocketDesc\">$desc</div>";
		$output .= "</div>\n";
		return $output;
	}
	
	
	protected function ShowItemTooltip()
	{
		$this->classData = $this->LoadData('DestinyClassDefinition', 'classType');
		$this->equipSlotData = $this->LoadData('DestinyEquipmentSlotDefinition');
		
		$itemData = $this->LoadItem($this->itemId);
		if ($itemData === false) return $this->ShowErrorTooltip("Item", $this->itemId);
		
		$name = $this->GetJsonData($itemData, 'displayProperties', 'name'); 
		$desc = $this->GetJsonData($itemData, 'displayProperties', 'description');
		$icon = $this->GetJsonData($itemData, 'displayProperties', 'icon', '');
		$iconWatermark = $this->GetJsonData($itemData, 'iconWatermark', null, '');
		$itemType = $this->GetJsonData($itemData, 'itemTypeDisplayName');
		$tierType = $this->GetJsonData($itemData, 'inventory', 'tierType');
		$tierTypeName = $this->GetJsonData($itemData, 'inventory', 'tierTypeName');
		$flavorText = $this->GetJsonData($itemData, 'flavorText');
		
		$classType = intval($this->GetJsonData($itemData, 'classType', null, ''));
		$classTypeName = "";
		if ($this->classData[$classType]) $classTypeName = $this->classData[$classType]['name'];
		if ($classTypeName == null) $classTypeName = "";
		
		$equipSlotTypeHash = intval($this->GetJsonData($itemData, 'equippingBlock', 'equipmentSlotTypeHash', 0));
		$equipSlotType = "";
		if ($this->equipSlotData[$equipSlotTypeHash]) $equipSlotType = $this->equipSlotData[$equipSlotTypeHash]['name'];
		if ($equipSlotType == null) $equipSlotType = "";
		
		$imageHtml = "";
		
		if ($icon != "")
		{
			$imageUrl = self::BASE_IMAGE_URL . $icon; 
			$imageHtml = "<img src=\"$imageUrl\">";
			
			if ($iconWatermark != "") 
			{
				$waterImageUrl = self::BASE_IMAGE_URL . $iconWatermark;
				$imageHtml .= "<img src=\"$waterImageUrl\">";
			}
		}
		
		$stats = $this->GetJsonData($itemData, 'stats', 'stats', []);
		$statsHtml = "";
		$statValuesHtml = "";
		
		foreach ($stats as $statId => $stat)
		{
			$statsHtml .= $this->MakeStatHtml($stat);
			$statValuesHtml .= $this->MakeStatValueHtml($stat);
		}
		
		$sockets = $this->GetJsonData($itemData, 'sockets', 'socketEntries', []);
		$socketsHtml = "";
		
		foreach ($sockets as $socket)
		{
			$socketsHtml .= $this->MakeSocketHtml($socket);
		}
		
		if ($statsHtml == "" && $statValuesHtml == "")
		{
			$statsHtml = "<div class=\"uespD2TooltipStatsDesc\">" . $this->EscapeHtml($desc) . "</div>";
		}
		
		if (($statsHtml != "" || $statValuesHtml != "") && $socketsHtml != "") 
		{
			$statValuesHtml .= '<hr class="uespD2HorizRule" />';
		}
		
		$itemSubtitle = $itemType;
		if ($classTypeName) $itemSubtitle = $classTypeName . " " . $itemSubtitle;
		if ($tierTypeName) $itemSubtitle = $tierTypeName . " " . $itemSubtitle;
		
		$replacePairs = array(
				'{titleClass}' => 'uespD2ItemType' . $this->MakeAlphaNum($tierTypeName),
				'{title}' => $this->EscapeHtml($name),
				'{subtitle}' => $this->EscapeHtml($itemSubtitle),
				'{image}' => $imageHtml,
				'{stats}' => $statsHtml . $statValuesHtml,
				'{sockets}' => $socketsHtml,
		);
		
		$template = file_get_contents(self::ITEM_TEMPLATE);
		$output = strtr($template, $replacePairs);
		
		print ($output);
		
		return true;
	}
	
	
	protected function ShowErrorTooltip($type = "Type", $id = "?")
	{
		$replacePairs = array(
				'{type}' => $this->EscapeHtml($type),
				'{lowertype}' => $this->EscapeHtml(strtolower($type)),
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
