<?php

/*
 * - Gear
 * 		- Slots: Head, Chest, Arms, Legs, Class, Ghost
 * 		- x5 Mods per slot (Intrinsic/Trait)
 * 		- Element?
 * - Weapons
 * 		- Slots: Energy, Kinetic, Heavy
 * 		- x4 Traits per slot (Intrinsic/Trait)
 * 		- Primary/Special/Heavy Ammo?
 * - Skills
 * 		- Slots: Skills, Fragments, Aspect
 */


if (php_sapi_name() != "cli") die("Error: Can only be run from command line!");


require_once("/home/uesp/secrets/destiny2.secrets");


class CUespDestiny2CreateCategoryData
{
	
	protected $db = null;
	protected $itemData = [];
	protected $classData = [];
	protected $equipSlotData = [];
	protected $itemCategories = [];
	protected $tierCategories = [];
	
	
	public function __construct()
	{
		$this->InitDatabase();
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
	
	
	protected function LoadRecords($tableName)
	{
		$safeTableName = "Destiny" . $this->MakeAlphaNum($tableName) . "Definition";
		
		$result = $this->db->query("SELECT * FROM `$safeTableName`;");
		if ($result === false) return false;
		if ($result->num_rows <= 0) return false;
		
		$data = [];
		
		while ($row = $result->fetch_assoc())
		{
			$json = $row['json'];
			$row['data'] = json_decode($json, true);
			$data[] = $row;
		}
		
		
		$count = count($data);
		print("\tLoaded $count rows from $safeTableName!\n");
		
		return $data;
	}
	
	
	protected function CreateTierCategories()
	{
		$this->tierCategories = [];
		
		foreach ($this->itemData as &$item)
		{
			$itemData = &$item['data'];
			
			$tierType = $this->GetJsonData($itemData, 'itemTypeAndTierDisplayName', null, '');
			
			$this->tierCategories[$tierType][] = $itemData;
		}
		
		ksort($this->tierCategories);
	}
	
	
	protected function CreateCategories()
	{
		$this->itemCategories = [];
		
		foreach ($this->itemData as &$item)
		{
			$itemData = &$item['data'];
			
			$classType = intval($this->GetJsonData($itemData, 'classType', null, ''));
			$classTypeName = "";
			if ($this->classData[$classType]) $classTypeName = $this->classData[$classType]['name'];
			if ($classTypeName == null) $classTypeName = "";
			$itemData['classTypeName'] = $classTypeName;
			
			$equipSlotTypeHash = intval($this->GetJsonData($itemData, 'equippingBlock', 'equipmentSlotTypeHash', 0));
			$equipSlotType = "";
			if ($this->equipSlotData[$equipSlotTypeHash]) $equipSlotType = $this->equipSlotData[$equipSlotTypeHash]['name'];
			if ($equipSlotType == null) $equipSlotType = "";
			$itemData['equipSlotName'] = $equipSlotType;
			
			$itemType = $this->GetJsonData($itemData, 'itemType');
			$itemTypeSubtype = $this->GetJsonData($itemData, 'itemSubType');
			$itemTypeName = $this->GetJsonData($itemData, 'itemTypeDisplayName');
			$rarity = $this->GetJsonData($itemData, 'inventory', 'tierTypeName', '');
			
			if ($itemTypeName == "") $itemTypeName = "$itemType:$itemTypeSubtype";
			
			$this->itemCategories[$itemTypeName][$classTypeName][$equipSlotType][] = $itemData;
		}
	}
	
	
	protected function PrintTierCategoryTree()
	{
		print("Showing Tier Categories:\n");
		
		foreach ($this->tierCategories as $tier => $items)
		{
			$count = count($items);
			print("\t$tier = $count\n");
		}
	}
	
	
	protected function PrintCategoryTree()
	{
		print("Showing All Categories:\n");
		
		foreach ($this->itemCategories as $type => $typeCategories)
		{
			$typeOutput = "";
			$typeCount = 0;
			if ($type == "") $type = "<None>";
			
			foreach ($typeCategories as $class => $classCategories)
			{
				$slotOutput = "";
				$classCount = 0;
				if ($class == "") $class = "<None>";
				
				foreach ($classCategories as $slot => $items)
				{
					$count = count($items);
					$classCount += $count;
					$typeCount += $count;
					
					if ($slot == "") $slot = "<None>";
					$slotOutput .= "\t\t\t$slot = $count items\n";
				}
				
				$typeOutput .= "\t\t$class = $classCount items\n";
				$typeOutput .= $slotOutput;
			}
			
			print("\t$type = $typeCount items\n");
			print($typeOutput);
		}
	}
	
	
	public function Run()
	{
		print("\tLoading all item data...\n");
		
		$this->classData = $this->LoadData('DestinyClassDefinition', 'classType');
		$this->equipSlotData = $this->LoadData('DestinyEquipmentSlotDefinition');
		
		$this->itemData = $this->LoadRecords("InventoryItem");
		if ($this->itemData === false) die("Error: Failed to load item data!");
		
		$this->CreateCategories();
		$this->CreateTierCategories();
		$this->PrintCategoryTree();
		$this->PrintTierCategoryTree();
		
		return true;
	}
	
};


$createData = new CUespDestiny2CreateCategoryData();
$createData->Run();
