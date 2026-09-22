<?php

/**
 * Compares the two ways of moving a block palette in and out of the extension:
 *
 *  - the array path, as used by PocketMine-MP's FastChunkSerializer today:
 *    pack("L*", ...$array->getPalette()) when writing, unpack()/array_values() feeding
 *    fromData() when reading;
 *  - the bytes path added by this fork: getPaletteBytes() when writing, fromData() given
 *    the bytes directly when reading.
 *
 * Only the palette handling is measured. Everything else FastChunkSerializer does (bits-per-block
 * byte, word array, stream writes) is identical in both paths, so leaving it out keeps the
 * measurement focused on what actually changes.
 *
 * Usage: php -d extension=modules/chunkutils2.so benchmarks/palette-bytes.php [iterations]
 */

declare(strict_types=1);

use pocketmine\world\format\PalettedBlockArray;

if(!extension_loaded("chunkutils2")){
	fwrite(STDERR, "chunkutils2 is not loaded\n");
	exit(1);
}

$iterations = (int) ($argv[1] ?? 200);

/**
 * Palette sizes of one chunk column: 24 subchunks of terrain plus their biome arrays.
 * Terrain sizes are weighted towards the small palettes that make up most of a real world,
 * with a couple of heavily mixed subchunks and one pathological full palette.
 */
function buildChunkColumn() : array{
	$paletteSizes = [
		1, 1, 1, 1, 1, 1, 1, 1,
		2, 2, 3, 4, 6, 8, 12, 16,
		24, 32, 48, 64, 96, 128, 512, 4096,
	];
	$biomeSizes = array_fill(0, 24, 1);
	$biomeSizes[0] = 4;
	$biomeSizes[1] = 2;

	$arrays = [];
	foreach([$paletteSizes, $biomeSizes] as $sizes){
		foreach($sizes as $size){
			$array = new PalettedBlockArray(0);
			for($i = 0; $i < $size; $i++){
				$array->set($i & 0xf, ($i >> 4) & 0xf, ($i >> 8) & 0xf, $i * 2654435761 & 0xffffffff);
			}
			$arrays[] = $array;
		}
	}

	return $arrays;
}

$column = buildChunkColumn();
$entryCount = 0;
foreach($column as $array){
	$entryCount += count($array->getPalette());
}

//sanity check: both paths must agree before it's worth timing them
foreach($column as $array){
	$packed = pack("L*", ...$array->getPalette());
	if($packed !== $array->getPaletteBytes()){
		fwrite(STDERR, "getPaletteBytes() disagrees with pack(\"L*\")\n");
		exit(1);
	}
	$viaArray = PalettedBlockArray::fromData($array->getBitsPerBlock(), $array->getWordArray(), array_values(unpack("L*", $packed)));
	$viaBytes = PalettedBlockArray::fromData($array->getBitsPerBlock(), $array->getWordArray(), $packed);
	if($viaArray->getWordArray() !== $viaBytes->getWordArray() || $viaArray->getPalette() !== $viaBytes->getPalette()){
		fwrite(STDERR, "fromData() disagrees between the array and the bytes path\n");
		exit(1);
	}
}

$writeArrayPath = static function(array $column) : array{
	$out = [];
	foreach($column as $array){
		$out[] = pack("L*", ...$array->getPalette());
	}
	return $out;
};

$writeBytesPath = static function(array $column) : array{
	$out = [];
	foreach($column as $array){
		$out[] = $array->getPaletteBytes();
	}
	return $out;
};

$readArrayPath = static function(array $column, array $palettes) : void{
	foreach($column as $i => $array){
		/** @var int[] $unpacked */
		$unpacked = unpack("L*", $palettes[$i]);
		PalettedBlockArray::fromData($array->getBitsPerBlock(), $array->getWordArray(), array_values($unpacked));
	}
};

$readBytesPath = static function(array $column, array $palettes) : void{
	foreach($column as $i => $array){
		PalettedBlockArray::fromData($array->getBitsPerBlock(), $array->getWordArray(), $palettes[$i]);
	}
};

$serialPalettes = $writeBytesPath($column);

function measure(callable $run, int $iterations) : float{
	for($i = 0; $i < 5; $i++){ //warmup
		$run();
	}
	$start = hrtime(true);
	for($i = 0; $i < $iterations; $i++){
		$run();
	}
	return (hrtime(true) - $start) / 1e9;
}

$results = [
	"write: pack(\"L*\", ...getPalette())" => measure(static fn() => $writeArrayPath($column), $iterations),
	"write: getPaletteBytes()"            => measure(static fn() => $writeBytesPath($column), $iterations),
	"read: unpack() + fromData(array)"    => measure(static fn() => $readArrayPath($column, $serialPalettes), $iterations),
	"read: fromData(bytes)"               => measure(static fn() => $readBytesPath($column, $serialPalettes), $iterations),
];

printf("PHP %s, %d chunk columns, %d palettes/column, %d palette entries/column\n\n", PHP_VERSION, $iterations, count($column), $entryCount);
printf("%-38s %12s %14s %14s\n", "path", "total (s)", "per column", "per entry");
foreach($results as $label => $seconds){
	printf("%-38s %12.4f %11.1f us %11.1f ns\n", $label, $seconds, $seconds / $iterations * 1e6, $seconds / $iterations / $entryCount * 1e9);
}

$speedup = static fn(float $before, float $after) : string => sprintf("%.2fx faster (%.1f%% less time)", $before / $after, (1 - $after / $before) * 100);
printf("\nwrite: %s\n", $speedup($results["write: pack(\"L*\", ...getPalette())"], $results["write: getPaletteBytes()"]));
printf("read:  %s\n", $speedup($results["read: unpack() + fromData(array)"], $results["read: fromData(bytes)"]));
