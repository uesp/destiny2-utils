<?php


if (php_sapi_name() != "cli") die("Error: Can only be run from command line!");

if ($argc <= 1) exit("Error: Missing text to search for!");

$textToFind = $argv[1];
print("\tLooking for ID '$textToFind' in all Destiny2 tables...\n");


require_once("/home/uesp/secrets/destiny2.secrets");


$db = new mysqli($uespDestiny2ReadDBHost, $uespDestiny2ReadUser, $uespDestiny2ReadPW, $uespDestiny2Database);
if ($db->connect_error) exit("Error: Could not connect to mysql database!");

$result = $db->query("SHOW TABLES;");
if ($result === false) exit("Error: Failed to list tables!");

$tables = [];

while ($row = $result->fetch_row())
{
	$tables[] = $row[0];
}

$count = count($tables);
print("\tFound $count tables in the Destiny2 database!\n");

$textToFind = $db->real_escape_string($textToFind);

foreach ($tables as $table)
{
	$result = $db->query("SELECT COUNT(*) as c FROM `$table` WHERE json LIKE '%$textToFind%';");
	if ($result === false) exit("Error: Failed to get row count from table $table!");
	
	
	$row = $result->fetch_assoc();
	$count = intval($row['c']);
	
	if ($count > 0) print("\t$table: Found $count matching rows!\n");
}