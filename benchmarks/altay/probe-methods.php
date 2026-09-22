<?php
declare(strict_types=1);
$altay = getenv("ALTAY_DIR");
require $altay . "/vendor/autoload.php";
spl_autoload_register(static function(string $class) use ($altay) : void{
	if(!str_starts_with($class, "pocketmine\\")){ return; }
	$rel = str_replace("\\", "/", substr($class, strlen("pocketmine\\"))) . ".php";
	foreach(["/src/", "/generated/"] as $d){ if(is_file($altay . $d . $rel)){ require $altay . $d . $rel; return; } }
}, true, true);

use pocketmine\world\format\LightArray;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;

$layer = new PalettedBlockArray(0);
for($i = 0; $i < 60; $i++){ $layer->set($i & 0xf, ($i >> 4) & 0xf, 0, 1000 + $i); }
$sub = new SubChunk(0, [$layer], new PalettedBlockArray(1), LightArray::fill(0), LightArray::fill(0));

function bench(string $name, callable $op, int $n) : void{
	$best = INF;
	for($r = 0; $r < 12; $r++){
		$s = hrtime(true);
		$op($n);
		$e = (hrtime(true) - $s) / $n;
		if($e < $best){ $best = $e; }
	}
	printf("%-24s %7.1f ns\n", $name, $best);
}

bench("getBlockStateId", static function(int $n) use ($sub) : void{ for($i = 0; $i < $n; $i++){ $sub->getBlockStateId(1, 2, 3); } }, 300000);
bench("setBlockStateId", static function(int $n) use ($sub) : void{ for($i = 0; $i < $n; $i++){ $sub->setBlockStateId(1, 2, 3, 1005); } }, 300000);
bench("getBlockLayers", static function(int $n) use ($sub) : void{ for($i = 0; $i < $n; $i++){ $sub->getBlockLayers(); } }, 300000);
bench("getBiomeArray", static function(int $n) use ($sub) : void{ for($i = 0; $i < $n; $i++){ $sub->getBiomeArray(); } }, 300000);
bench("getEmptyBlockId", static function(int $n) use ($sub) : void{ for($i = 0; $i < $n; $i++){ $sub->getEmptyBlockId(); } }, 300000);
bench("getBlockLightArray", static function(int $n) use ($sub) : void{ for($i = 0; $i < $n; $i++){ $sub->getBlockLightArray(); } }, 300000);
bench("getHighestBlockAt", static function(int $n) use ($sub) : void{ for($i = 0; $i < $n; $i++){ $sub->getHighestBlockAt(1, 3); } }, 100000);
bench("isEmptyFast", static function(int $n) use ($sub) : void{ for($i = 0; $i < $n; $i++){ $sub->isEmptyFast(); } }, 300000);
bench("collectGarbage", static function(int $n) use ($sub) : void{ for($i = 0; $i < $n; $i++){ $sub->collectGarbage(); } }, 20000);
bench("clone", static function(int $n) use ($sub) : void{ for($i = 0; $i < $n; $i++){ $c = clone $sub; unset($c); } }, 20000);
