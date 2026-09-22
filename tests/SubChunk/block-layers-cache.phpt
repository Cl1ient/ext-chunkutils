--TEST--
Test that the array returned by SubChunk::getBlockLayers() is detached from the subchunk and kept up to date
--SKIPIF--
<?php if(!extension_loaded("chunkutils2")) die("skip extension not loaded"); ?>
--FILE--
<?php

use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;

$layer = new PalettedBlockArray(0);
$layer->set(0, 0, 0, 5);
$sub = new SubChunk(0, [$layer], new PalettedBlockArray(1));

//repeated calls agree, and hand back the same objects
var_dump($sub->getBlockLayers() == $sub->getBlockLayers());
var_dump($sub->getBlockLayers()[0] === $layer);

//writing to the returned array must not reach the subchunk
$layers = $sub->getBlockLayers();
$layers[] = new PalettedBlockArray(2);
unset($layers[0]);
var_dump(count($sub->getBlockLayers()));
var_dump($sub->getBlockLayers()[0] === $layer);

//a layer created by setBlockStateId must show up
$empty = new SubChunk(0, [], new PalettedBlockArray(1));
var_dump(count($empty->getBlockLayers()));
$empty->setBlockStateId(1, 2, 3, 7);
var_dump(count($empty->getBlockLayers()));
var_dump($empty->getBlockLayers()[0]->get(1, 2, 3));

//a layer dropped by collectGarbage must disappear
$used = new PalettedBlockArray(0);
$used->set(0, 0, 0, 9);
$gc = new SubChunk(0, [new PalettedBlockArray(0), $used], new PalettedBlockArray(1));
var_dump(count($gc->getBlockLayers()));
$gc->collectGarbage();
var_dump(count($gc->getBlockLayers()));
var_dump($gc->getBlockLayers()[0] === $used);

//holding on to the array must not stop the subchunk being collected, nor corrupt the layers
$held = $gc->getBlockLayers();
unset($gc);
var_dump($held[0]->get(0, 0, 0));

//clones keep their own layers
$c1 = new SubChunk(0, [$layer], new PalettedBlockArray(1));
$before = $c1->getBlockLayers();
$c2 = clone $c1;
var_dump($c2->getBlockLayers()[0] === $before[0]);
$c2->setBlockStateId(0, 0, 0, 11);
var_dump($c1->getBlockStateId(0, 0, 0), $c2->getBlockStateId(0, 0, 0));

//serialize round trip still sees the layers
$restored = unserialize(serialize($c1));
var_dump(count($restored->getBlockLayers()));
var_dump($restored->getBlockLayers()[0]->get(0, 0, 0));
?>
--EXPECT--
bool(true)
bool(true)
int(1)
bool(true)
int(0)
int(1)
int(7)
int(2)
int(1)
bool(true)
int(9)
bool(false)
int(5)
int(11)
int(1)
int(5)
