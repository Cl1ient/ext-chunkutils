--TEST--
Test that PalettedBlockArray::getPaletteBytes() matches pack("L*", ...getPalette())
--SKIPIF--
<?php if(!extension_loaded("chunkutils2")) die("skip extension not loaded"); ?>
--FILE--
<?php

use pocketmine\world\format\PalettedBlockArray;

function check(PalettedBlockArray $array) : void{
	$bytes = $array->getPaletteBytes();
	$expected = pack("L*", ...$array->getPalette());
	var_dump(strlen($bytes) === count($array->getPalette()) * 4, $bytes === $expected);
}

//single entry
check(new PalettedBlockArray(1));

//values covering the whole uint32 range
$edgeValues = [0, 1, 0x7fffffff, 0x80000000, 0xfffffffe, 0xffffffff];
$edge = new PalettedBlockArray(0);
foreach($edgeValues as $i => $value){
	$edge->set($i, 0, 0, $value);
}
check($edge);
var_dump($edge->getPaletteBytes() === pack("V*", ...$edgeValues));

//dense palette
$dense = new PalettedBlockArray(0);
for($x = 0; $x < 16; $x++){
	for($y = 0; $y < 16; $y++){
		for($z = 0; $z < 16; $z++){
			$dense->set($x, $y, $z, ($x << 8) | ($y << 4) | $z);
		}
	}
}
check($dense);

$expected = "";
for($i = 0; $i < 4096; $i++){
	$expected .= pack("V", $i);
}
var_dump($expected === $dense->getPaletteBytes());

//the array form must be left untouched
var_dump($dense->getPalette() === range(0, 4095));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
