<?php
/*
 * Creates data used by the Destiny WordPress plugin for the custom post type.
 * 
- Buffs/debuffs (2? + 2?)
	Where do these come from?
- Abilities (1 + 4)
	- Super ability (x1)
	- Class Ability (x1)
	- Movement Ability
	- Arc Grenade
	- Arc Melee
- Aspects (2?)
	- Arc Aspect
- Fragments (3?)
	- Arc Fragment
- Gear (weapon + 2 perks, armor + 1 perk)
	- For weapons look at Slot:
		- Kinetic Weapons
		- Energy Weapons
		- Power Weapons
	- For perks:
		- Exotic is included in weapon.
		- Look at type "Trait" or "Enhanced Trait"
	- Kinetic weapons (4?)
	- Energy weapons (3?)
	- Heavy weapons (2?)
	- Exotic armor (3?)
		- Leg, Helmet, Arms, Chest, Class/Ghost?
		- Perk is included on the amrmor
- Armor stats?
- Important mods
	- Head, Arms, Chest, Legs, Class
	- Types:
		- Charged with Light Mod
		- Arms Armor Mod
		- Leg Armor Mod = 40 items
		- Helmet Armor Mod = 47 items
		- Chest Armor Mod = 38 items
		- Legacy Armor Mod = 5 items
		- Combat Style Armor Mod = 4 items
		- General Armor Mod = 15 items
		- Armor Mod = 23 items
		- Vault of Glass Armor Mod = 12 items
		- Class Item Armor Mod = 14 items
		- Weapon Mod
		- ... Mod ?
			- Deprecated Armor Mod = 214 items
			- Elemental Well Mod = 24 items
			- Leg Armor Mod = 40 items
			- Helmet Armor Mod = 47 items
			- Arms Armor Mod = 55 items
			- Chest Armor Mod = 38 items
			- Activity Ghost Mod = 17 items
			- Warmind Cell Mod = 19 items
			- Economic Ghost Mod = 20 items
			- Charged with Light Mod = 28 items
			- Weapon Mod = 46 items
			- Deep Stone Crypt Raid Mod = 9 items
			- Sparrow Mod = 12 items
			- Legacy Armor Mod = 5 items
			- Vow of the Disciple Raid Mod = 10 items
			- Ship Mod = 4 items
			- Combat Style Armor Mod = 4 items
			- General Armor Mod = 15 items
			- Last Wish Raid Mod = 5 items
			- Armor Mod = 23 items
			- Vault of Glass Armor Mod = 12 items
			- Nightmare Mod = 11 items
			- Gear Mod = 1 items
			- Garden of Salvation Raid Mod = 9 items
			- Class Item Mod = 10 items
			- Aeon Cult Mod = 3 items
			- Tracking Ghost Mod = 13 items
			- King's Fall Mod = 9 items
			- Class Item Armor Mod = 14 items
			- Chest Artifact Mod = 1 items
			- Class Item Artifact Mod = 1 items
			- Experience Ghost Mod = 7 items
			- Leg Artifact Mod = 1 items
			- Helmet Artifact Mod = 1 items
			- Arms Artifact Mod = 1 items
- Playstyle

 */

if (php_sapi_name() != "cli") die("Error: Can only be run from command line!");

require_once("/home/uesp/secrets/destiny2.secrets");


class CUespDestiny2CreateWPData
{
	
	protected $db = null;
	
	protected $tableData = [];
	protected $classData = [];
	protected $equipSlotData = [];
	
	public $PERMIT_DUPLICATE_NAMES = false;
	
	const IGNORE_SOCKET_NAMES = [
			'Default Effect' => true,
			'Default Ornament' => true,
			'Default Shader' => true,
			'Tracker Disabled' => true,
			'Empty Aspect Socket' => true,
			'Empty Catalyst Socket' => true,
			'Empty Fragment Socket' => true,
			'Empty Tubes Socket' => true,
			'Empty Magazines Socket' => true,
			'Empty Traits Socket' => true,
			'Empty Memento Socket' => true,
			'Empty Mod Socket' => true,
	];
	
	
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
		global $uespDestiny2ReadDBHost;
		global $uespDestiny2ReadUser;
		global $uespDestiny2ReadPW;
		global $uespDestiny2Database;
		
		$this->db = new mysqli($uespDestiny2ReadDBHost, $uespDestiny2ReadUser, $uespDestiny2ReadPW, $uespDestiny2Database);
		if ($this->db->connect_error) exit("Error: Could not connect to mysql database!");
	}
	
	
	protected function ShouldDisplaySocket($socketData)
	{
		if ($socketData['defaultVisible'] === false) return false;
		
		$name = $socketData['name'];
		$desc = $socketData['description'];
		
		if ($name == null || $name == "") return false;
		
		if (preg_match('/deprecated/i', $name)) return false;
		if ($desc && preg_match('/deprecated/i', $desc)) return false;
		
		if (self::IGNORE_SOCKET_NAMES[$name]) return false;
		
		return true;
	}
	
	
	protected function LoadTableData($tableName, $indexField = null)
	{
		$tableName = preg_replace('/[^a-zA-Z0-9_]+/', '', $tableName);
		$fullTableName = 'Destiny' . $tableName . 'Definition';
		
		$result = $this->db->query("SELECT * FROM `$fullTableName`;");
		if ($result === false) die("Error: Failed to load data from table '$fullTableName'!");
		
		$data = [];
		
		while ($row = $result->fetch_assoc())
		{
			$id = $row['id'];
			if ($id == null) $id = $row['key'];
			
			$json = json_decode($row['json'], true);
			$row['data'] = $json;
			
			if ($indexField != null)
			{
				$id1 = $this->GetJsonData($json, $indexField, false);
				if ($id1 !== false) $id = $id1;
			}
			
			$equipSlotTypeHash = intval($this->GetJsonData($row, 'equippingBlock::equipmentSlotTypeHash', 0));
			
			if ($equipSlotTypeHash > 0)
			{
				$equipSlotType = "";
				if ($this->equipSlotData[$equipSlotTypeHash]) $equipSlotType = $this->equipSlotData[$equipSlotTypeHash]['name'];
				if ($equipSlotType == null) $equipSlotType = "";
				$row['data']['equipSlotName'] = $equipSlotType;
				//print("\tEquipSlotType for $id is $equipSlotType ($equipSlotTypeHash)\n");
			}
			
			$data[$id] = $row;
		}
		
		$this->LoadPerkDescriptions($data);
		$this->LoadSocketDescriptions($data);
		
		$this->tableData[$tableName] = $data;
		$count = count($data);
		print("\t//Loaded $count records from $tableName...\n");
		
		return $data;
	}
	
	
	protected function LoadSocketDescriptions(&$data)
	{
		foreach ($data as $id => &$record)
		{
			$socketEntries = $this->GetJsonData($record, 'sockets::socketEntries');
			if ($socketEntries == null) continue;
			
			foreach ($socketEntries as $socketIndex => $socket)
			{
				$record['data']['sockets']['socketEntries'][$socketIndex]['shouldDisplay'] = false;
				
				$itemHash = intval($socket['singleInitialItemHash']);
				if ($itemHash <= 0) continue;
				
				$itemRecord = $data[$itemHash];
				if ($itemRecord == null) continue;
				
				$desc = $this->GetJsonData($itemRecord, 'displayProperties::description', '');
				$name = $this->GetJsonData($itemRecord, 'displayProperties::name', '');
				$icon = $this->GetJsonData($itemRecord, 'displayProperties::icon', '');
				
				$record['data']['sockets']['socketEntries'][$socketIndex]['name'] = $name;
				$record['data']['sockets']['socketEntries'][$socketIndex]['icon'] = $icon;
				$record['data']['sockets']['socketEntries'][$socketIndex]['description'] = $desc;
				
				$socket = $record['data']['sockets']['socketEntries'][$socketIndex];
				
				if ($this->ShouldDisplaySocket($socket)) $record['data']['sockets']['socketEntries'][$socketIndex]['shouldDisplay'] = true;
			}
		}
	}
	
	
	protected function LoadPerkDescriptions(&$data)
	{
		foreach ($data as $id => &$record)
		{
			$desc = $this->GetJsonData($record, 'displayProperties::description');
			$name = $this->GetJsonData($record, 'displayProperties::name');
			
			$perks = $this->GetJsonData($record, 'perks');
			if ($perks == null) continue;
			
			foreach ($perks as $perkIndex => $perk)
			{
				$perkId = intval($perk['perkHash']);
				if ($perkId <= 0) continue;
				
				$perkRecord = $this->LoadTableRecord('SandboxPerk', $perkId);
				if ($perkRecord === false) continue;
				
				$perkDesc = $this->GetJsonData($perkRecord, 'displayProperties::description');
				if ($perkDesc == null) continue;
				
				$perkName = $this->GetJsonData($perkRecord, 'displayProperties::name');
				if ($perkName == null) continue;
				
				//print("Perk $perkId description $perkDesc...\n");
				
				if (strtolower($perkName) == strtolower($name) && $perkDesc != '')
				{
					if ($desc == null) $desc = '';
					if ($desc != '') $desc .= '\n';
					$desc .= $perkDesc;
					$record['data']['displayProperties']['description'] = $desc;
				}
			}
		}
	}
	
	
	protected function LoadTableRecord($tableName, $id)
	{
		$tableName = preg_replace('/[^a-zA-Z0-9_]+/', '', $tableName);
		$fullTableName = 'Destiny' . $tableName . 'Definition';
		
		$id = intval($id);
		
		$result = $this->db->query("SELECT * FROM `$fullTableName` WHERE id='$id';");
		if ($result === false) return $this->ReportError("Error: Failed to load data from table '$fullTableName'!");
		
		$row = $result->fetch_assoc();
		if ($row == null) return $this->ReportError("Error: Failed to load data record '$id' from table '$fullTableName'!");
		
		$json = json_decode($row['json'], true);
		$row['data'] = $json;
		
		return $row;
	}
	
	
	protected function DoesRecordMatchFilter($record, $key, $value)
	{
		$json = $record['data'];
		if (!$json) return false;
		
		$value2 = $json[$key];
		if ($value2 == null) return false;
		
		if ($value == $value2) return true;
		return false;
	}
	
	
	protected function FilterDuplicateNames($records)
	{
		$newRecords = [];
		$names = [];
		
		foreach ($records as $id => $record)
		{
			$name = $record['name'];
			if ($name == null) continue;
			if ($names[$name] != null) continue;
			
			$names[$name] = true;
			$newRecords[$id] = $record;
		}
		
		return $newRecords;
	}
	
	
	protected function FilterRecords($tableName, $key, $value)
	{
		$tableName = preg_replace('/[^a-zA-Z0-9_]+/', '', $tableName);
		if ($this->tableData[$tableName] == null) return $this->ReportError("Error: '$tableName' is not a valid table name!");
		
		$filterData = [];
		
		foreach ($this->tableData[$tableName] as $id => $record)
		{
			if ($this->DoesRecordMatchFilter($record, $key, $value))
			{
				$filterData[$id] = $record;
			}
		}
		
		if (!$this->PERMIT_DUPLICATE_NAMES)
		{
			$preCount = count($filterData);
			
			$filterData = $this->FilterDuplicateNames($filterData);
			
			$count = count($filterData);
			$diffCount = $preCount - $count;
			print("\t//Found $count $tableName records matching $key is '$value' (removed $diffCount duplicate names)...\n");
		}
		else
		{
			$count = count($filterData);
			print("\t//Found $count $tableName records matching $key is '$value'...\n");
		}
		
		return $filterData;
	}
	
	
	protected function GetJsonData($record, $varString, $default = null)
	{
		$vars = explode("::", $varString);
		
		$data = $record['data'];
		if ($data == null) return $default;
		
		foreach ($vars as $var)
		{
			$data = $data[$var];
			if ($data == null) return $default;
		}
		
		return $data;
	}
	
	
	protected function GetArrayRecordForDump($record, $varArray)
	{
		$dataId = $varArray['_'];
		if ($dataId == null) return [];
		
		$dataArray = $this->GetJsonData($record, $dataId);
		if ($dataArray == null) return [];
		
		$outputData = [];
		
		foreach ($dataArray as $dataId => $data)
		{
			$newData = [];
			
			$outputId = $dataId;
			$idField = $varArray['id'];
			if ($idField) $idField = $data[$idField];
			if ($idField) $outputId = $idField;
			
			foreach ($varArray as $varName => $varString)
			{
				if ($varName == '_') continue;
				if ($data['shouldDisplay'] === false) continue;
				
				$value = $data[$varString];
				if ($value == null) continue;
				
				$newData[$varName] = $value;
			}
			
			if (count($newData) > 0) $outputData[$outputId] = $newData;
		}
		
		return $outputData;
	}
	
	
	protected function GetRecordForDump($record, $outputVars)
	{
		$outputData = [];
		
		foreach ($outputVars as $varName => $varString)
		{
			if ($record['data']['shouldDisplay'] === false) continue;
			
			if (gettype($varString) == 'array')
			{
				$value = $this->GetArrayRecordForDump($record, $varString);
			}
			else
			{
				$value = $this->GetJsonData($record, $varString);
				if ($value == null) continue;
			}
			
			//$value = mb_convert_encoding($value , 'UTF-8', 'UTF-8');
			$outputData[$varName] = $value;
		}
		
		if (count($outputData) == 0) return null;
		return $outputData;
	}
	
	
	protected function DumpData($data, $varName, $outputVars)
	{
		$records = [];
		
		foreach ($data as $id => $record)
		{
			$records[$id] = $this->GetRecordForDump($record, $outputVars);
		}
		
		$varName = strtoupper($varName);
		print("const DATA_$varName = ");
		var_export($records);
		print(";\n");
		print("//-----------------------------------------------------------------------------\n\n");
	}
	
	
	public function Run()
	{
		$this->classData = $this->LoadTableData('Class', 'classType');
		$this->equipSlotData = $this->LoadTableData('EquipmentSlot');
		
		$this->LoadTableData("InventoryItem");
		
		$superAbilities = $this->FilterRecords("InventoryItem", "itemTypeDisplayName", "Super Ability");
		$classAbilities = $this->FilterRecords("InventoryItem", "itemTypeDisplayName", "Class Ability");
		$movementAbilities = $this->FilterRecords("InventoryItem", "itemTypeDisplayName", "Movement Ability");
		$arcGrenade = $this->FilterRecords("InventoryItem", "itemTypeDisplayName", "Arc Grenade");
		$arcMelee = $this->FilterRecords("InventoryItem", "itemTypeDisplayName", "Arc Melee");
		
		$aspect = $this->FilterRecords("InventoryItem", "itemTypeDisplayName", "Arc Aspect");
		$fragment = $this->FilterRecords("InventoryItem", "itemTypeDisplayName", "Arc Fragment");
		
		$kineticWeapons = $this->FilterRecords("InventoryItem", "equipSlotName", "Kinetic Weapons");
		$energyWeapons = $this->FilterRecords("InventoryItem", "equipSlotName", "Energy Weapons");
		$powerWeapons = $this->FilterRecords("InventoryItem", "equipSlotName", "Power Weapons");
		
		print("//-----------------------------------------------------------------------------\n\n");
		
		$this->DumpData($superAbilities, "superAbilities", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		$this->DumpData($classAbilities, "classAbilities", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		$this->DumpData($movementAbilities, "movementAbilities", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		$this->DumpData($arcGrenade, "arcGrenade", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		$this->DumpData($arcMelee, "arcMelee", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		
		$this->DumpData($aspect, "aspect", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		$this->DumpData($fragment, "fragment", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		
		$this->DumpData($kineticWeapons, "kineticWeapons", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => [ '_' => 'sockets::socketEntries', 'id' => 'singleInitialItemHash', 'name' => 'name', 'icon' => 'icon', 'desc' => 'description' ], ]);
		$this->DumpData($energyWeapons, "energyWeapons", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => [ '_' => 'sockets::socketEntries', 'id' => 'singleInitialItemHash', 'name' => 'name', 'icon' => 'icon', 'desc' => 'description' ], ]);
		$this->DumpData($powerWeapons, "powerWeapons", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => [ '_' => 'sockets::socketEntries', 'id' => 'singleInitialItemHash', 'name' => 'name', 'icon' => 'icon', 'desc' => 'description' ], ]);
		
		return true;
	}
	
};


$data = new CUespDestiny2CreateWPData();
$data->Run();