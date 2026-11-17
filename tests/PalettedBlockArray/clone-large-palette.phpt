--TEST--
Test that a cloned PalettedBlockArray with a large palette stays independent and writable
--SKIPIF--
<?php if(!extension_loaded("chunkutils2")) die("skip extension not loaded"); ?>
--FILE--
<?php

use pocketmine\world\format\PalettedBlockArray;

$array = new PalettedBlockArray(0);
for($i = 0; $i < 4096; $i++){
	$array->set($i & 15, ($i >> 4) & 15, ($i >> 8) & 15, $i % 300);
}

$clone = clone $array;
var_dump($clone->getPalette() === $array->getPalette());

//writing existing and new entries to the clone must reuse or extend its own palette
$clone->set(0, 0, 0, 299);
$clone->set(1, 0, 0, 5000);
$clone->set(2, 0, 0, 5000);
var_dump($clone->get(0, 0, 0), $clone->get(1, 0, 0), $clone->get(2, 0, 0));
var_dump(count($clone->getPalette()) === count($array->getPalette()) + 1);
var_dump(count(array_unique($clone->getPalette())) === count($clone->getPalette()));

//the original is untouched, and still writable
var_dump($array->get(0, 0, 0), $array->get(1, 0, 0));
$array->set(3, 0, 0, 7000);
var_dump($array->get(3, 0, 0), $clone->get(3, 0, 0));

$same = true;
for($i = 3; $i < 4096; $i++){
	$x = $i & 15; $y = ($i >> 4) & 15; $z = ($i >> 8) & 15;
	if(($x !== 3 || $y !== 0 || $z !== 0) && $clone->get($x, $y, $z) !== $i % 300){
		$same = false;
	}
}
var_dump($same);

?>
--EXPECT--
bool(true)
int(299)
int(5000)
int(5000)
bool(true)
bool(true)
int(0)
int(1)
int(7000)
int(3)
bool(true)
