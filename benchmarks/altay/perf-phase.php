<?php
declare(strict_types=1);
$altay = getenv("ALTAY_DIR");
require $altay . "/vendor/autoload.php";
spl_autoload_register(static function(string $class) use ($altay) : void{
	if(!str_starts_with($class, "pocketmine\\")){ return; }
	$rel = str_replace("\\", "/", substr($class, strlen("pocketmine\\"))) . ".php";
	foreach(["/src/", "/generated/"] as $d){ if(is_file($altay . $d . $rel)){ require $altay . $d . $rel; return; } }
}, true, true);

use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\format\io\FastChunkSerializer;

/**
 * Runs one phase R times on top of a fixed setup. Running it with two values of R and subtracting
 * the counters cancels out interpreter startup, autoloading and chunk building.
 */

const CHUNK_COUNT = 24;

function newChunk() : Chunk{
	$subChunks = [];
	for($y = Chunk::MIN_SUBCHUNK_INDEX; $y <= Chunk::MAX_SUBCHUNK_INDEX; $y++){
		$subChunks[] = new SubChunk(0, [], new PalettedBlockArray(1));
	}
	return new Chunk($subChunks, true);
}

function build(int $cx, int $cz, bool $rich) : Chunk{
	$chunk = newChunk();
	$seed = $cx * 341873128712 + $cz * 132897987541;
	for($x = 0; $x < 16; $x++){
		for($z = 0; $z < 16; $z++){
			$n = ($seed + $x * 7919 + $z * 104729) & 0x7fffffff;
			$h = 58 + (($n >> 5) % 26);
			for($y = -64; $y <= $h; $y++){
				$chunk->setBlockStateId($x, $y, $z, $rich ? 1000 + (($n + $y * 31 + $x * 7 + $z * 13) % 96) : ($y <= -60 ? 7 : ($y > $h - 4 ? ($y === $h ? 12 : 10) : 1)));
			}
			for($y = $h + 1; $y <= 62; $y++){ $chunk->setBlockStateId($x, $y, $z, 89); }
		}
	}
	return $chunk;
}

$phase = getenv("PHASE");
$reps = (int) getenv("REPS");

$chunks = [];
for($i = 0; $i < CHUNK_COUNT; $i++){ $chunks[] = build($i % 8, intdiv($i, 8), $phase === "serialize_rich"); }
$serialized = [];
foreach($chunks as $c){ $serialized[] = FastChunkSerializer::serializeTerrain($c); }

for($r = 0; $r < $reps; $r++){
	switch($phase){
		case "generate":
			for($i = 0; $i < CHUNK_COUNT; $i++){ build($i % 8, intdiv($i, 8), false); }
			break;
		case "serialize":
		case "serialize_rich":
			foreach($chunks as $c){ $d = FastChunkSerializer::serializeTerrain($c); unset($d); }
			break;
		case "deserialize":
			foreach($serialized as $d){ FastChunkSerializer::deserializeTerrain($d); }
			break;
		case "block_reads":
			$sum = 0;
			foreach($chunks as $c){
				for($i = 0; $i < 4096; $i++){ $sum += $c->getBlockStateId($i & 0xf, ($i >> 4) & 0x7f, ($i >> 8) & 0xf); }
			}
			break;
		case "block_writes":
			foreach($chunks as $c){
				for($i = 0; $i < 2048; $i++){ $c->setBlockStateId($i & 0xf, 40 + (($i >> 4) & 0x1f), ($i >> 8) & 0xf, 1); }
			}
			break;
		case "clone":
			foreach($chunks as $c){ $copy = clone $c; unset($copy); }
			break;
	}
}
