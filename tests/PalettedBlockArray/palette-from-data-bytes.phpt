--TEST--
Test that PalettedBlockArray::fromData() accepts a palette given as raw bytes
--SKIPIF--
<?php if(!extension_loaded("chunkutils2")) die("skip extension not loaded"); ?>
--FILE--
<?php

use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\PalettedBlockArrayLoadException;

$array = new PalettedBlockArray(0);
for($x = 0; $x < 16; $x++){
	for($y = 0; $y < 16; $y++){
		for($z = 0; $z < 16; $z++){
			$array->set($x, $y, $z, ($x << 8) | ($y << 4) | $z);
		}
	}
}

$fromBytes = PalettedBlockArray::fromData($array->getBitsPerBlock(), $array->getWordArray(), $array->getPaletteBytes());
$matched = 0;
for($x = 0; $x < 16; $x++){
	for($y = 0; $y < 16; $y++){
		for($z = 0; $z < 16; $z++){
			$expect = ($x << 8) | ($y << 4) | $z;
			if($fromBytes->get($x, $y, $z) === $expect){
				$matched++;
			}else{
				echo "Mismatch at $x, $y, $z: actual " . $fromBytes->get($x, $y, $z) . ", expected " . $expect . "\n";
			}
		}
	}
}
var_dump($matched);

//the bytes path and the array path must produce identical arrays
$fromArray = PalettedBlockArray::fromData($array->getBitsPerBlock(), $array->getWordArray(), $array->getPalette());
var_dump(
	$fromBytes->getBitsPerBlock() === $fromArray->getBitsPerBlock(),
	$fromBytes->getWordArray() === $fromArray->getWordArray(),
	$fromBytes->getPalette() === $fromArray->getPalette(),
	$fromBytes->getPaletteBytes() === $fromArray->getPaletteBytes()
);

//values covering the whole uint32 range must survive the round trip
$edgeValues = [0, 1, 0x7fffffff, 0x80000000, 0xfffffffe, 0xffffffff];
$edge = new PalettedBlockArray(0);
foreach($edgeValues as $i => $value){
	$edge->set($i, 0, 0, $value);
}
$edgeReloaded = PalettedBlockArray::fromData($edge->getBitsPerBlock(), $edge->getWordArray(), $edge->getPaletteBytes());
var_dump($edgeReloaded->getPalette() === $edge->getPalette());
var_dump($edgeReloaded->getPalette() === $edgeValues);

foreach([
	$array->getPaletteBytes() . "\n",
	substr($array->getPaletteBytes(), 0, -1),
	"\0",
	"",
] as $badPalette){
	try{
		PalettedBlockArray::fromData($array->getBitsPerBlock(), $array->getWordArray(), $badPalette);
		echo "This is not supposed to work\n";
	}catch(PalettedBlockArrayLoadException $e){
		echo $e->getMessage() . "\n";
	}
}
?>
--EXPECT--
int(4096)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
palette length in bytes must be a multiple of 4, but have 16385 bytes
palette length in bytes must be a multiple of 4, but have 16383 bytes
palette length in bytes must be a multiple of 4, but have 1 bytes
palette cannot have a zero size
