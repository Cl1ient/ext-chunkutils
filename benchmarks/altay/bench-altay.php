<?php

declare(strict_types=1);

/**
 * Runs Altay's own chunk code paths and times them. Meant to be run twice: once against the
 * stock extension + PHP SubChunk, once against the modified extension + native SubChunk.
 */

$altay = getenv("ALTAY_DIR");
require $altay . "/vendor/autoload.php";

//the vendor directory is shared between the two checkouts, so its psr-4 paths point at the wrong
//src; take pocketmine\ over and resolve it against the checkout being benchmarked
spl_autoload_register(static function(string $class) use ($altay) : void{
	if(!str_starts_with($class, "pocketmine\\")){
		return;
	}
	$relative = str_replace("\\", "/", substr($class, strlen("pocketmine\\"))) . ".php";
	foreach(["/src/", "/generated/"] as $dir){
		$file = $altay . $dir . $relative;
		if(is_file($file)){
			require $file;
			return;
		}
	}
}, true, true);

use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\format\io\FastChunkSerializer;

const CHUNK_COUNT = 48;
const SEA_LEVEL = 62;

//arbitrary but plausible block state ids, in the range real ones live in
const AIR = 0, BEDROCK = 7, STONE = 1, DIRT = 10, GRASS = 12, WATER = 89, GRAVEL = 15, ANDESITE = 4;
const ORES = [65, 71, 77, 83, 95, 101];

function buildChunk(int $chunkX, int $chunkZ) : Chunk{
	$subChunks = [];
	for($y = Chunk::MIN_SUBCHUNK_INDEX; $y <= Chunk::MAX_SUBCHUNK_INDEX; $y++){
		$subChunks[] = new SubChunk(AIR, [], new PalettedBlockArray(1));
	}
	$chunk = new Chunk($subChunks, true);

	$seed = $chunkX * 341873128712 + $chunkZ * 132897987541;
	for($x = 0; $x < 16; $x++){
		for($z = 0; $z < 16; $z++){
			$n = ($seed + $x * 7919 + $z * 104729) & 0x7fffffff;
			$height = 58 + (($n >> 5) % 26); //58..83
			for($y = Chunk::MIN_SUBCHUNK_INDEX * 16; $y <= $height; $y++){
				if($y <= Chunk::MIN_SUBCHUNK_INDEX * 16 + 4){
					$block = BEDROCK;
				}elseif($y > $height - 4){
					$block = $y === $height ? GRASS : DIRT;
				}else{
					$r = ($n + $y * 2654435761) & 0xffff;
					if($r < 40){
						$block = ORES[$r % 6];
					}elseif($r < 900){
						$block = $r % 2 === 0 ? GRAVEL : ANDESITE;
					}else{
						$block = STONE;
					}
				}
				$chunk->setBlockStateId($x, $y, $z, $block);
			}
			for($y = $height + 1; $y <= SEA_LEVEL; $y++){
				$chunk->setBlockStateId($x, $y, $z, WATER);
			}
		}
	}

	return $chunk;
}

/**
 * Same terrain, but every stone block gets one of 96 distinct states, standing in for a chunk full
 * of varied blocks: a built-up area, a decorated cave system, a world with lots of block entities.
 */
function buildRichChunk(int $chunkX, int $chunkZ) : Chunk{
	$subChunks = [];
	for($y = Chunk::MIN_SUBCHUNK_INDEX; $y <= Chunk::MAX_SUBCHUNK_INDEX; $y++){
		$subChunks[] = new SubChunk(AIR, [], new PalettedBlockArray(1));
	}
	$chunk = new Chunk($subChunks, true);

	$seed = $chunkX * 341873128712 + $chunkZ * 132897987541;
	for($x = 0; $x < 16; $x++){
		for($z = 0; $z < 16; $z++){
			$n = ($seed + $x * 7919 + $z * 104729) & 0x7fffffff;
			$height = 58 + (($n >> 5) % 26);
			for($y = Chunk::MIN_SUBCHUNK_INDEX * 16; $y <= $height; $y++){
				$chunk->setBlockStateId($x, $y, $z, 1000 + (($n + $y * 31 + $x * 7 + $z * 13) % 96));
			}
			for($y = $height + 1; $y <= SEA_LEVEL; $y++){
				$chunk->setBlockStateId($x, $y, $z, WATER);
			}
		}
	}

	return $chunk;
}

function countPaletteEntries(Chunk $chunk) : int{
	$total = 0;
	for($y = Chunk::MIN_SUBCHUNK_INDEX; $y <= Chunk::MAX_SUBCHUNK_INDEX; $y++){
		$sub = $chunk->getSubChunk($y);
		foreach($sub->getBlockLayers() as $layer){
			$total += count($layer->getPalette());
		}
		$total += count($sub->getBiomeArray()->getPalette());
	}
	return $total;
}

function measure(callable $run, int $repeats) : float{
	$best = INF;
	for($i = 0; $i < $repeats; $i++){
		$start = hrtime(true);
		$run();
		$elapsed = (hrtime(true) - $start) / 1e9;
		if($elapsed < $best){
			$best = $elapsed;
		}
	}
	return $best;
}

$results = [];

//1. terrain generation: writing blocks into fresh chunks, as a generator does
$memBefore = memory_get_usage();
$chunks = [];
$results["generate"] = measure(static function() use (&$chunks) : void{
	$chunks = [];
	for($i = 0; $i < CHUNK_COUNT; $i++){
		$chunks[] = buildChunk($i % 8, intdiv($i, 8));
	}
}, 3);
$results["memory_per_chunk"] = (memory_get_usage() - $memBefore) / CHUNK_COUNT;

//2. thread transfer: every chunk handed from the world thread to the main thread, and to players
$results["serializeTerrain"] = measure(static function() use ($chunks) : void{
	foreach($chunks as $chunk){
		$data = FastChunkSerializer::serializeTerrain($chunk);
		unset($data);
	}
}, 9);

$serialized = [];
foreach($chunks as $chunk){
	$serialized[] = FastChunkSerializer::serializeTerrain($chunk);
}

$results["deserializeTerrain"] = measure(static function() use ($serialized) : void{
	foreach($serialized as $data){
		FastChunkSerializer::deserializeTerrain($data);
	}
}, 9);

$results["serialized_bytes"] = array_sum(array_map("strlen", $serialized));

//3. gameplay reads: lighting, physics, pathfinding, block queries
$results["block_reads"] = measure(static function() use ($chunks) : void{
	$sum = 0;
	foreach($chunks as $chunk){
		for($i = 0; $i < 4096; $i++){
			$sum += $chunk->getBlockStateId($i & 0xf, ($i >> 4) & 0x7f, ($i >> 8) & 0xf);
		}
	}
}, 5);

//4. gameplay writes: block placement, explosions, world edits
$results["block_writes"] = measure(static function() use ($chunks) : void{
	foreach($chunks as $chunk){
		for($i = 0; $i < 2048; $i++){
			$chunk->setBlockStateId($i & 0xf, 40 + (($i >> 4) & 0x1f), ($i >> 8) & 0xf, STONE);
		}
	}
}, 5);

//5. chunk copies, as async tasks and chunk sending do
$results["clone"] = measure(static function() use ($chunks) : void{
	foreach($chunks as $chunk){
		$copy = clone $chunk;
		unset($copy);
	}
}, 5);

//6. garbage collection, run before saving and periodically
$results["collectGarbage"] = measure(static function() use ($chunks) : void{
	foreach($chunks as $chunk){
		for($y = Chunk::MIN_SUBCHUNK_INDEX; $y <= Chunk::MAX_SUBCHUNK_INDEX; $y++){
			$chunk->getSubChunk($y)->collectGarbage();
		}
	}
}, 5);

//7. the same transfers on chunks whose palettes are large, which is where the palette encoding weighs most
$richChunks = [];
for($i = 0; $i < CHUNK_COUNT; $i++){
	$richChunks[] = buildRichChunk($i % 8, intdiv($i, 8));
}
$results["rich_serializeTerrain"] = measure(static function() use ($richChunks) : void{
	foreach($richChunks as $chunk){
		$data = FastChunkSerializer::serializeTerrain($chunk);
		unset($data);
	}
}, 9);

$richSerialized = [];
foreach($richChunks as $chunk){
	$richSerialized[] = FastChunkSerializer::serializeTerrain($chunk);
}
$results["rich_deserializeTerrain"] = measure(static function() use ($richSerialized) : void{
	foreach($richSerialized as $data){
		FastChunkSerializer::deserializeTerrain($data);
	}
}, 9);
$results["rich_serialized_bytes"] = array_sum(array_map("strlen", $richSerialized));
$results["palette_entries_plain"] = countPaletteEntries($chunks[0]);
$results["palette_entries_rich"] = countPaletteEntries($richChunks[0]);

$results["peak_memory"] = memory_get_peak_usage(true);
$results["native_subchunk"] = (new ReflectionClass(SubChunk::class))->isInternal();
$results["palette_bytes"] = method_exists(PalettedBlockArray::class, "getPaletteBytes");

echo json_encode($results, JSON_PRETTY_PRINT), "\n";
