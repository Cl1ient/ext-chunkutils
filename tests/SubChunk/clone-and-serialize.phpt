--TEST--
Test that SubChunk clone and serialize/unserialize preserve contents without sharing state
--SKIPIF--
<?php if(!extension_loaded("chunkutils2")) die("skip extension not loaded"); ?>
--FILE--
<?php

use pocketmine\world\format\LightArray;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;

$layer = new PalettedBlockArray(0);
$layer->set(0, 0, 0, 3);
$biomes = new PalettedBlockArray(1);
$sub = new SubChunk(0, [$layer], $biomes, null, LightArray::fill(4));

$clone = clone $sub;
var_dump($clone->getBlockStateId(0, 0, 0));
var_dump($clone->getBlockLayers()[0] === $layer);
var_dump($clone->getBiomeArray() === $biomes);
$clone->setBlockStateId(0, 0, 0, 9);
var_dump($sub->getBlockStateId(0, 0, 0));
var_dump($clone->getBlockStateId(0, 0, 0));
var_dump($clone->getBlockLightArray()->get(0, 0, 0));

$restored = unserialize(serialize($sub));
var_dump($restored->getBlockStateId(0, 0, 0));
var_dump($restored->getEmptyBlockId());
var_dump($restored->getBiomeArray()->get(0, 0, 0));
var_dump($restored->getBlockLightArray()->get(0, 0, 0));
var_dump(count($restored->getBlockLayers()));
$restored->setBlockStateId(0, 0, 0, 11);
var_dump($sub->getBlockStateId(0, 0, 0));
?>
--EXPECT--
int(3)
bool(false)
bool(false)
int(3)
int(9)
int(4)
int(3)
int(0)
int(1)
int(4)
int(1)
int(3)
