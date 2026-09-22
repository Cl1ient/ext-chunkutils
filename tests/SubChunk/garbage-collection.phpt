--TEST--
Test that SubChunk::collectGarbage() discards empty layers and uniform light arrays
--SKIPIF--
<?php if(!extension_loaded("chunkutils2")) die("skip extension not loaded"); ?>
--FILE--
<?php

use pocketmine\world\format\LightArray;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;

$emptyLayer = new PalettedBlockArray(0);
$usedLayer = new PalettedBlockArray(0);
$usedLayer->set(0, 0, 0, 7);

$sub = new SubChunk(0, [$emptyLayer, $usedLayer], new PalettedBlockArray(1), LightArray::fill(0), LightArray::fill(15));
var_dump(count($sub->getBlockLayers()));
var_dump($sub->isEmptyAuthoritative());
var_dump(count($sub->getBlockLayers()));
var_dump($sub->getBlockLayers()[0] === $usedLayer);
var_dump($sub->getBlockSkyLightArray()->isUniform(0));
var_dump($sub->getBlockLightArray()->get(0, 0, 0));

$empty = new SubChunk(0, [new PalettedBlockArray(0)], new PalettedBlockArray(1));
var_dump($empty->isEmptyAuthoritative());
var_dump(count($empty->getBlockLayers()));
?>
--EXPECT--
int(2)
bool(false)
int(1)
bool(true)
bool(true)
int(15)
bool(true)
int(0)
