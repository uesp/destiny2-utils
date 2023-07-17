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
	protected $powerCapData = [];
	
	public $PERMIT_DUPLICATE_NAMES = false;
	public $PERMIT_DUPLICATE_PERK_NAMES = false;
	public $REMOVE_SUNSET_DUPLICATE_NAMES = true;
	public $INCLUDE_SUNSET_ITEMS = true;					// Should be true or removes ALL sunset items
	public $INCLUDE_ALL_SOCKETS = false;
	public $OUTPUT_DUPLICATE_NAMES = false;
	public $IGNORE_DUMMY_ITEMS = true;
	
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
		$id = intval($socketData['singleInitialItemHash']);
		
		if ($this->INCLUDE_ALL_SOCKETS) return true;
		
		if ($id <= 0) return false;
		
		if ($socketData['defaultVisible'] === false) 
		{
			//print("\t$id: Not visible\n");
			return false;
		}
		
		$name = $socketData['name'];
		$desc = $socketData['description'];
		
		if ($name === null || $name === "") 
		{
			//print("\t$id: No Name\n");
			return false;
		}
		
		if (preg_match('/deprecated/i', $name)) 
		{
			//print("\t$id: Deprecated\n");
			return false;
		}
		
		if ($desc && preg_match('/deprecated/i', $desc)) 
		{
			//print("\t$id: Description Deprecated\n");
			return false;
		}
		
		if (self::IGNORE_SOCKET_NAMES[$name]) 
		{
			//print("\t$id: Ignored Name\n");
			return false;
		}
		
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
			if ($id === null) $id = $row['key'];
			
			//if ($tableName == 'InventoryItem' && $id != 1697682876) continue;
			
			$json = json_decode($row['json'], true);
			$row['data'] = $json;
			
			if ($indexField != null)
			{
				$id1 = $this->GetJsonData($row, $indexField, false);
				if ($id1 !== false) $id = $id1;
			}
			
			$equipSlotTypeHash = intval($this->GetJsonData($row, 'equippingBlock::equipmentSlotTypeHash', 0));
			
			if ($equipSlotTypeHash > 0)
			{
				$equipSlotType = "";
				if ($this->equipSlotData[$equipSlotTypeHash]) $equipSlotType = $this->equipSlotData[$equipSlotTypeHash]['name'];
				if ($equipSlotType === null) $equipSlotType = "";
				$row['data']['equipSlotName'] = $equipSlotType;
				//print("\tEquipSlotType for $id is $equipSlotType ($equipSlotTypeHash)\n");
			}
			
			$data[$id] = $row;
		}
		
		$this->LoadPerkDescriptions($data);
		
		if ($tableName == "InventoryItem") 
		{
			$this->IncludeRandomizedSockets($data);
			$this->LoadModDescriptions($data);
			//$count2 = count($data['2145476620']['data']['mods']);
			//print("\tCounts: $count2\n");
		}
		
		$this->LoadSocketDescriptions($data);
		
		$this->tableData[$tableName] = $data;
		$count = count($data);
		print("\t//Loaded $count records from $tableName...\n");
		
		//$count = count($data[1298815317]['data']['sockets']['socketEntries']);
		//$count = count($this->tableData[$tableName][1298815317]['data']['sockets']['socketEntries']);
		//print("\tCount $count\n");
		
		return $data;
	}
	
	
	protected function IncludeRandomizedSockets(&$data)
	{
		foreach ($data as $recordId => &$record)
		{
			$socketEntries = $this->GetJsonData($record, 'sockets::socketEntries');
			if ($socketEntries === null) continue;
			
			$newSockets = [];
			$existingSockets = [];
			
			foreach ($socketEntries as $socketIndex => $socket)
			{
				$id = intval($socket['singleInitialItemHash']);
				if ($id <= 0) continue;
				
				//print("\tSocket #$socketIndex:$id\n");
				
				$existingSockets[$id] = true;
				
				$randomizedPlugSetHash = intval($socket['randomizedPlugSetHash']);
				if ($randomizedPlugSetHash <= 0) continue;
				
				$plugSet = $this->plugSetData[$randomizedPlugSetHash];
				
				if ($plugSet == null) 
				{
					//print("\tNo plugset matching $randomizedPlugSetHash!\n");
					continue;
				}
				
				$items = $plugSet['data']['reusablePlugItems'];
				
				if ($items == null) 
				{
					//print("\tPlugset has no reusablePlugItems!\n");
					continue;
				}
				
				$count = count($items);
				//print("\tFound $count possible new sockets.\n");
				
				foreach ($items as $index => $item)
				{
					$plugItemHash = intval($item['plugItemHash']);
					
					if ($plugItemHash <= 0) 
					{
						//print("\t\tMissing plugItemHash!\n");
						continue;
					}
					
					$newSockets[$plugItemHash] = true;
				}
			}
			
			$newCount = 0;
			
			foreach ($newSockets as $itemId => $v)
			{
					// Ignore existing sockets on item
				if ($existingSockets[$itemId] === true) continue;
				
				$newSocket = [
						'singleInitialItemHash' => $itemId,
						'defaultVisible' => 'true',
				];
				
				$record['data']['sockets']['socketEntries'][] = $newSocket;
				++$newCount;
			}
			
			//$count = count($record['data']['sockets']['socketEntries']);
			//$count = count($data[$recordId]['data']['sockets']['socketEntries']);
			//print("\t$recordId: Added $newCount sockets (total of $count)!\n");
		}
		
		return true;
	}
	
	
	protected function CreateItemSummaryIndex(&$data, $onlyMods = false)
	{
		$index = [];
		
		foreach ($data as $id => &$record)
		{
			$json = json_decode($record['json'], true);
			
			$itemSummaryHash = intval($this->GetJsonData($record, "summaryItemHash", 0));
			if ($itemSummaryHash <= 0) continue;
			
			$itemType = intval($this->GetJsonData($record, "itemType", 0));
			//if ($onlyMods && $itemType != 19 && $itemType != 20) continue;
			if ($onlyMods && $itemType != 19) continue;
			
			$index[$itemSummaryHash][$id] = $record['data'];
			//print("\t$itemSummaryHash\n");
		}
		
		$count = count($index);
		print("\t//Created item summary index size of $count!\n");
		
		return $index;
	}
	
	
	protected function LoadModDescriptions(&$data)
	{
		$modSummaryIndex = $this->CreateItemSummaryIndex($data, true);
		
		//print_r($modSummaryIndex['3520001075']);
		//exit();
		
		foreach ($data as $id => &$record)
		{
			$itemSummaryHash = intval($this->GetJsonData($record, "summaryItemHash", 0));
			if ($itemSummaryHash <= 0) continue;
			
			$mods = $modSummaryIndex[$itemSummaryHash];
			
			if ($mods === null)
			{
				//print("\tEmpty mods for $itemSummaryHash!\n");
			}
			
			$record['data']['mods'] = $mods;
		}
		
		//$count1 = count($modSummaryIndex['3520001075']);
		//$count2 = count($data['2145476620']['data']['mods']);
		//print("\tCounts: $count1:$count2\n");
		//print_r($modSummaryIndex['3520001075']);
		//print("\n\n");
		//print_r();
		//print("\n\n");
		
		return true;
	}
	
	
	protected function LoadSocketDescriptions(&$data)
	{
		foreach ($data as $id => &$record)
		{
			$socketEntries = $this->GetJsonData($record, 'sockets::socketEntries');
			if ($socketEntries === null) continue;
			
			$socketNames = [];
			
			foreach ($socketEntries as $socketIndex => $socket)
			{
				$record['data']['sockets']['socketEntries'][$socketIndex]['shouldDisplay'] = false;
				
				$itemHash = intval($socket['singleInitialItemHash']);
				if ($itemHash <= 0) continue;
				
				$itemRecord = $data[$itemHash];
				if ($itemRecord === null) continue;
				
				$desc = $this->GetJsonData($itemRecord, 'displayProperties::description', '');
				$name = $this->GetJsonData($itemRecord, 'displayProperties::name', '');
				$icon = $this->GetJsonData($itemRecord, 'displayProperties::icon', '');
				
				$record['data']['sockets']['socketEntries'][$socketIndex]['name'] = $name;
				$record['data']['sockets']['socketEntries'][$socketIndex]['icon'] = $icon;
				$record['data']['sockets']['socketEntries'][$socketIndex]['description'] = $desc;
				
				$socket = $record['data']['sockets']['socketEntries'][$socketIndex];
				
				if ($this->ShouldDisplaySocket($socket)) $record['data']['sockets']['socketEntries'][$socketIndex]['shouldDisplay'] = true;
				
				if ($socketNames[$name] != null && !$this->PERMIT_DUPLICATE_PERK_NAMES) $record['data']['sockets']['socketEntries'][$socketIndex]['shouldDisplay'] = false;
				$socketNames[$name] = true;
			}
			
			//print_r($record['data']['sockets']['socketEntries']);
		}
	}
	
	
	protected function LoadPerkDescriptions(&$data)
	{
		foreach ($data as $id => &$record)
		{
			$desc = $this->GetJsonData($record, 'displayProperties::description');
			$name = $this->GetJsonData($record, 'displayProperties::name');
			
			$perks = $this->GetJsonData($record, 'perks');
			if ($perks === null) continue;
			
			foreach ($perks as $perkIndex => $perk)
			{
				$perkId = intval($perk['perkHash']);
				if ($perkId <= 0) continue;
				
				$perkRecord = $this->LoadTableRecord('SandboxPerk', $perkId);
				if ($perkRecord === false) continue;
				
				$perkDesc = $this->GetJsonData($perkRecord, 'displayProperties::description');
				if ($perkDesc === null) continue;
				
				$perkName = $this->GetJsonData($perkRecord, 'displayProperties::name');
				if ($perkName === null) continue;
				
				//print("Perk $perkId description $perkDesc...\n");
				
				if (strtolower($perkName) == strtolower($name) && $perkDesc != '')
				{
					if ($desc === null) $desc = '';
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
		if ($row === null) return $this->ReportError("Error: Failed to load data record '$id' from table '$fullTableName'!");
		
		$json = json_decode($row['json'], true);
		$row['data'] = $json;
		
		return $row;
	}
	
	
	protected function DoesRecordMatchFilter($record, $key, $value)
	{
		$json = $record['data'];
		if (!$json) return false;
		
		$value2 = $json[$key];
		if ($value2 === null) return false;
		
		if ($value == $value2) return true;
		return false;
	}
	
	
	protected function DoesRecordMatchFilters($record, $filters)
	{
		$json = $record['data'];
		if (!$json) return false;
		
		foreach ($filters as $key => $value)
		{
			$value2 = $json[$key];
			if ($value2 === null) return false;
			
			if ($value != $value2) return false;
		}
		
		return true;
	}
	
	
	protected function FilterDuplicateNames($records)
	{
		$newRecords = [];
		$names = [];
		$dupNames = [];
		
		foreach ($records as $id => $record)
		{
			$name = $record['name'];
			if ($name === null) continue;
			
			if ($names[$name] != null) 
			{
				if ($this->REMOVE_SUNSET_DUPLICATE_NAMES)
				{
					$lastId = $names[$name];
					$lastRecord = $records[$lastId];
					
					//print("\t\t$id ($lastId): Checking sunset record....\n");
					
					if ($this->IsRecordSunset($lastRecord))
					{
						unset($newRecords[$lastId]);
						$names[$name] = $id;
						$newRecords[$id] = $record;
						
						//print("\t\t$id: Replaced sunset record $lastId with the same name!\n");
					}
				}
				
				$dupNames[$name]++;
				continue;
			}
			
			$names[$name] = $id;
			$newRecords[$id] = $record;
		}
		
		if ($this->OUTPUT_DUPLICATE_NAMES)
		{
			foreach ($dupNames as $name => $count)
			{
				print("\t\t$name had $count duplicates\n");
			}
		}
		
		return $newRecords;
	}
	
	
	protected function IsRecordSunset($record)
	{
		$powerCapHashes = $this->GetJsonData($record, 'quality::versions', 0);
		if (!is_array($powerCapHashes)) return false;
		
		$firstPowerCap = $powerCapHashes[0];
		$powerCapHash = $firstPowerCap['powerCapHash'];
		if ($powerCapHash == null) return false;
		
		$powerCapData = $this->powerCapData[$powerCapHash];
		if ($powerCapData == null) return false;
		
		$powerCap = $powerCapData['data']['powerCap'];
		if ($powerCap == null) return false;
		
		if (substr($powerCap, 0, 4) === '9999') return false;
		return true;
	}
	
	
	protected function FilterRecords($tableName, $filters)
	{
		$tableName = preg_replace('/[^a-zA-Z0-9_]+/', '', $tableName);
		if ($this->tableData[$tableName] === null) return $this->ReportError("Error: '$tableName' is not a valid table name!");
		
		$filterData = [];
		
		$niceFilterText = http_build_query($filters, '', ', ');
		$niceFilterText = str_replace('+', ' ', $niceFilterText);
		
		foreach ($this->tableData[$tableName] as $id => $record)
		{
			if ($this->IGNORE_DUMMY_ITEMS)
			{
				$itemType = $record['data']['itemType'];
				
				if ($itemType == 20)
				{
					//print("\t\t$id: Ignoring dummy item!\n");
					continue;
				}
			}
			
			if (!$this->INCLUDE_SUNSET_ITEMS)
			{
				if ($this->IsRecordSunset($record)) 
				{
					//print("\t\t$id: Ignoring sunset item!\n");
					continue;
				}
			}
			
			if ($this->DoesRecordMatchFilters($record, $filters))
			{
				$filterData[$id] = $record;
				//print("\t\t$id: Record matches filters!\n");
			}
			else
			{
				//print("\t\t$id: Record doesn't match filters!\n");
			}
		}
		
		if (!$this->PERMIT_DUPLICATE_NAMES)
		{
			$preCount = count($filterData);
			
			$filterData = $this->FilterDuplicateNames($filterData);
			
			$count = count($filterData);
			$diffCount = $preCount - $count;
			print("\t//Found $count $tableName records matching $niceFilterText (removed $diffCount duplicate names)...\n");
		}
		else
		{
			$count = count($filterData);
			print("\t//Found $count $tableName records matching $niceFilterText...\n");
		}
		
		return $filterData;
	}
	
	
	protected function GetJsonData($record, $varString, $default = null, $isJsonData = false)
	{
		$vars = explode("::", $varString);
		
		if ($isJsonData)
			$data = $record;
		else
			$data = $record['data'];
		
		if ($data === null) return $default;
		
		foreach ($vars as $var)
		{
			$data = $data[$var];
			if ($data === null) return $default;
		}
		
		return $data;
	}
	
	
	protected function GetArrayRecordForDump($record, $varArray)
	{
		$dataId = $varArray['_'];
		if ($dataId === null) return [];
		
		//print("\tRecord Hash: {$record['data']['hash']}\n");
		
		$dataArray = $this->GetJsonData($record, $dataId);
		
		if ($dataArray === null)
		{
			//print_r($record);
			//print("\tEmpty value for $dataId!\n");
			return [];
		}
		
		//if ($dataId == "mods") print("\tNon-Empty value for $dataId!\n");
		$origDataId = $dataId;
		
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
				
				$value = $this->GetJsonData($data, $varString, null, true);
				
				if ($value === null) 
				{
					if ($origDataId == "mods") {
						//print("\t\t$varString is NULL\n");
						//print_r($data);
						//exit();
					}
					continue;
				}
				
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
				if ($value === null) continue;
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
		
		//$count1 = count($data['2145476620']['data']['mods']);
		//$count2 = count($this->tableData['InventoryItem']['2145476620']['data']['mods']);
		//print("\tCounts $count1:$count2\n");
		
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
		$this->plugSetData = $this->LoadTableData('PlugSet');
		$this->powerCapData = $this->LoadTableData('PowerCap');
		
		$this->LoadTableData("InventoryItem");
		
		//$count = count($this->tableData[$tableName][1298815317]['data']['sockets']['socketEntries']);
		//$count1 = count($this->tableData['InventoryItem']['1298815317']['data']['sockets']['socketEntries']);
		//print("Socket count = $count1\n");
		
		$superAbilities = $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Super Ability"]);
		$classAbilities = $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Class Ability"]);
		$movementAbilities = $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Movement Ability"]);
		$arcGrenade = $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Arc Grenade"]);
		$arcGrenade += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Solar Grenade"]);
		$arcGrenade += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Stasis Grenade"]);
		$arcGrenade += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Void Grenade"]);
		$arcGrenade += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Strand Grenade"]);
		$arcGrenade += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Grenade"]);
		$arcMelee = $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Arc Melee"]);
		$arcMelee += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Solar Melee"]);
		$arcMelee += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Stasis Melee"]);
		$arcMelee += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Void Melee"]);
		$arcMelee += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Strand Melee"]);
		$arcMelee += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Melee"]);
		
		$aspect = $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Arc Aspect"]);
		$aspect += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Solar Aspect"]);
		$aspect += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Stasis Aspect"]);
		$aspect += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Void Aspect"]);
		$aspect += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Strand Aspect"]);
		$aspect += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Titan Aspect"]);
		$fragment = $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Arc Fragment"]);
		$fragment += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Solar Fragment"]);
		$fragment += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Stasis Fragment"]);
		$fragment += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Void Fragment"]);
		$fragment += $this->FilterRecords("InventoryItem", ["itemTypeDisplayName" => "Strand Fragment"]);
		
		$kineticWeapons = $this->FilterRecords("InventoryItem", ["equipSlotName" => "Kinetic Weapons", "equippable" => "true"]);
		$energyWeapons = $this->FilterRecords("InventoryItem", ["equipSlotName" => "Energy Weapons", "equippable" => "true"]);
		$powerWeapons = $this->FilterRecords("InventoryItem", ["equipSlotName" => "Power Weapons", "equippable" => "true"]);
		
		$exoticArmor = $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Exotic Leg Armor"]);
		$exoticArmor += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Exotic Chest Armor"]);
		$exoticArmor += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Exotic Helmet"]);
		$exoticArmor += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Exotic Gauntlets"]);
		
		$commonArmorMods = $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Common Charged with Light Mod"]);
		$commonArmorMods += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Common Vault of Glass Armor Mod"]);
		$commonArmorMods += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Common General Armor Mod"]);
		$commonArmorMods += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Legendary Armor Mod"]);
		$commonArmorMods += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Elemental Well Mod"]);
		
		$legArmorMods = $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Common Leg Armor Mod"]) + $commonArmorMods;
		$legArmorMods += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Legendary Leg Armor Mod"]);
		$armArmorMods = $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Common Arms Armor Mod"]) + $commonArmorMods;
		$armArmorMods += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Legendary Arms Armor Mod"]);
		$chestArmorMods = $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Common Chest Armor Mod"]) + $commonArmorMods;
		$chestArmorMods += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Legendary Chest Armor Mod"]);
		$headArmorMods = $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Common Helmet Armor Mod"]) + $commonArmorMods;
		$headArmorMods += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Legendary Helmet Armor Mod"]);
		$classArmorMods = $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Common Class Item Armor Mod"]) + $commonArmorMods;
		$classArmorMods += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Legendary Class Item Mod"]);
		$classArmorMods += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Legendary Class Item Armor Mod"]);
		$classArmorMods += $this->FilterRecords("InventoryItem", ["itemTypeAndTierDisplayName" => "Common Class Item Mod"]); 
		
		//$count1 = count($kineticWeapons['2145476620']['data']['mods']);
		//$count2 = count($this->tableData['InventoryItem']['2145476620']['data']['mods']);
		//print("\tCounts $count1:$count2\n");
		
		//$count1 = count($this->tableData['InventoryItem']['1298815317']['data']['sockets']['socketEntries']);
		//print("Socket count = $count1\n");
		//print_r($this->tableData['InventoryItem']['1298815317']['data']['sockets']['socketEntries']);
		//exit();
		
		print("//-----------------------------------------------------------------------------\n\n");
		
		$this->DumpData($superAbilities, "superAbilities", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		$this->DumpData($classAbilities, "classAbilities", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		$this->DumpData($movementAbilities, "movementAbilities", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		$this->DumpData($arcGrenade, "arcGrenade", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		$this->DumpData($arcMelee, "arcMelee", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		
		$this->DumpData($aspect, "aspect", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		$this->DumpData($fragment, "fragment", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon']);
		
		$socketDef = [ '_' => 'sockets::socketEntries', 'id' => 'singleInitialItemHash', 'name' => 'name', 'icon' => 'icon', 'desc' => 'description' ];
		$modDef = [ '_' => 'mods', 'id' => 'hash', 'name' => 'displayProperties::name', 'icon' => 'displayProperties::icon', 'desc' => 'displayProperties::description' ];
		
		$this->DumpData($kineticWeapons, "kineticWeapons", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => $socketDef ]);
		$this->DumpData($energyWeapons, "energyWeapons", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => $socketDef ]);
		$this->DumpData($powerWeapons, "powerWeapons", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => $socketDef ]);
		
		$this->DumpData($exoticArmor, "exoticArmor", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => $socketDef ]);
		
		$this->DumpData($legArmorMods, "LegArmorMods", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => $socketDef ]);
		$this->DumpData($armArmorMods, "ArmArmorMods", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => $socketDef ]);
		$this->DumpData($chestArmorMods, "ChestArmorMods", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => $socketDef ]);
		$this->DumpData($headArmorMods, "HeadArmorMods", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => $socketDef ]);
		$this->DumpData($classArmorMods, "ClassArmorMods", [ 'name' => 'displayProperties::name', 'desc' => 'displayProperties::description', 'icon' => 'displayProperties::icon', 'sockets' => $socketDef ]);
		
		return true;
	}
	
};


$data = new CUespDestiny2CreateWPData();
$data->Run();