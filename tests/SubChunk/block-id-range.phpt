--TEST--
Test that SubChunk refuses block IDs that don't fit in a palette entry, like PalettedBlockArray does
--SKIPIF--
<?php if(!extension_loaded("chunkutils2")) die("skip extension not loaded"); ?>
--FILE--
<?php

use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;

$sub = new SubChunk(0, [], new PalettedBlockArray(0));
foreach([-1, 0x100000000, PHP_INT_MAX] as $block){
	try{
		$sub->setBlockStateId(0, 0, 0, $block);
		echo "accepted $block\n";
	}catch(\InvalidArgumentException $e){
		echo $e->getMessage() . "\n";
	}
}
var_dump($sub->isEmptyFast());

$sub->setBlockStateId(0, 0, 0, 0xffffffff);
var_dump($sub->getBlockStateId(0, 0, 0));

try{
	new SubChunk(-1, [], new PalettedBlockArray(0));
}catch(\InvalidArgumentException $e){
	echo $e->getMessage() . "\n";
}

$same = $sub->getBlockLightArray();
$sub->setBlockLightArray($same);
var_dump($sub->getBlockLightArray() === $same);

?>
--EXPECT--
value -1 is too large to be used as a palette entry
value 4294967296 is too large to be used as a palette entry
value 9223372036854775807 is too large to be used as a palette entry
bool(true)
int(4294967295)
value -1 is too large to be used as a palette entry
bool(true)
