--TEST--
Test basic SubChunk block and light accessors
--SKIPIF--
<?php if(!extension_loaded("chunkutils2")) die("skip extension not loaded"); ?>
--FILE--
<?php

use pocketmine\world\format\LightArray;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;

var_dump(SubChunk::COORD_BIT_SIZE, SubChunk::COORD_MASK, SubChunk::EDGE_LENGTH);

$sub = new SubChunk(0, [], new PalettedBlockArray(1));
var_dump($sub->getEmptyBlockId());
var_dump($sub->isEmptyFast());
var_dump($sub->getHighestBlockAt(0, 0));
var_dump($sub->getBlockStateId(1, 2, 3));
var_dump(count($sub->getBlockLayers()));

$sub->setBlockStateId(1, 2, 3, 5);
var_dump($sub->getBlockStateId(1, 2, 3));
var_dump($sub->getHighestBlockAt(1, 3));
var_dump($sub->isEmptyFast());
var_dump(count($sub->getBlockLayers()));

var_dump($sub->getBiomeArray()->get(0, 0, 0));
var_dump($sub->getBiomeArray() === $sub->getBiomeArray());

$skyLight = $sub->getBlockSkyLightArray();
var_dump($skyLight instanceof LightArray);
var_dump($skyLight === $sub->getBlockSkyLightArray());
$sub->setBlockLightArray(LightArray::fill(15));
var_dump($sub->getBlockLightArray()->get(0, 0, 0));

var_dump($sub->__debugInfo());
?>
--EXPECT--
int(4)
int(15)
int(16)
int(0)
bool(true)
NULL
int(0)
int(0)
int(5)
int(2)
bool(false)
int(1)
int(1)
bool(true)
bool(true)
bool(true)
int(15)
array(0) {
}
