--TEST--
Test that a SubChunk whose constructor never ran is refused instead of crashing
--SKIPIF--
<?php if(!extension_loaded("chunkutils2")) die("skip extension not loaded"); ?>
--FILE--
<?php

use pocketmine\world\format\LightArray;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;

class NoParentConstructor extends SubChunk{
	public function __construct(){}

	public function initialize() : void{
		parent::__construct(0, [], new PalettedBlockArray(3));
	}
}

$calls = [
	"isEmptyAuthoritative" => fn(SubChunk $s) => $s->isEmptyAuthoritative(),
	"isEmptyFast" => fn(SubChunk $s) => $s->isEmptyFast(),
	"getEmptyBlockId" => fn(SubChunk $s) => $s->getEmptyBlockId(),
	"getBlockStateId" => fn(SubChunk $s) => $s->getBlockStateId(0, 0, 0),
	"setBlockStateId" => fn(SubChunk $s) => $s->setBlockStateId(0, 0, 0, 1),
	"getBlockLayers" => fn(SubChunk $s) => $s->getBlockLayers(),
	"getHighestBlockAt" => fn(SubChunk $s) => $s->getHighestBlockAt(0, 0),
	"getBiomeArray" => fn(SubChunk $s) => $s->getBiomeArray(),
	"getBlockSkyLightArray" => fn(SubChunk $s) => $s->getBlockSkyLightArray(),
	"setBlockSkyLightArray" => fn(SubChunk $s) => $s->setBlockSkyLightArray(LightArray::fill(0)),
	"getBlockLightArray" => fn(SubChunk $s) => $s->getBlockLightArray(),
	"setBlockLightArray" => fn(SubChunk $s) => $s->setBlockLightArray(LightArray::fill(0)),
	"collectGarbage" => fn(SubChunk $s) => $s->collectGarbage(),
	"serialize" => fn(SubChunk $s) => serialize($s),
];

$uninitialized = [
	(new ReflectionClass(SubChunk::class))->newInstanceWithoutConstructor(),
	new NoParentConstructor(),
];
foreach($uninitialized as $sub){
	foreach($calls as $name => $call){
		try{
			$call($sub);
			echo get_class($sub) . "::$name: no error\n";
		}catch(\Error $e){
			if($e->getMessage() !== get_class($sub) . " has not been initialized, its constructor was not called"){
				echo get_class($sub) . "::$name: " . $e->getMessage() . "\n";
			}
		}
	}
	clone $sub;
}
echo "uninitialized objects refused\n";

$late = new NoParentConstructor();
$late->initialize();
var_dump($late->getBiomeArray()->get(0, 0, 0));

$sub = new SubChunk(0, [new PalettedBlockArray(1)], new PalettedBlockArray(0), LightArray::fill(3), LightArray::fill(4));
try{
	$sub->__construct(0, [new PalettedBlockArray(2)], new PalettedBlockArray(5));
}catch(\Error $e){
	echo $e->getMessage() . "\n";
}
var_dump($sub->getBlockStateId(0, 0, 0));

?>
--EXPECT--
uninitialized objects refused
int(3)
pocketmine\world\format\SubChunk has already been initialized
int(1)
