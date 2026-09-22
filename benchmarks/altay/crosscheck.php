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

$file = getenv("DUMP");
$mode = getenv("MODE");

if($mode === "write"){
	$subChunks = [];
	for($y = Chunk::MIN_SUBCHUNK_INDEX; $y <= Chunk::MAX_SUBCHUNK_INDEX; $y++){
		$subChunks[] = new SubChunk(0, [], new PalettedBlockArray(1));
	}
	$chunk = new Chunk($subChunks, true);
	$expected = [];
	for($x = 0; $x < 16; $x++){
		for($z = 0; $z < 16; $z++){
			for($y = -64; $y <= 90; $y++){
				$id = 1000 + ((($x * 31 + ($y + 64) * 7 + $z * 13) * 2654435761) & 0xfff);
				$chunk->setBlockStateId($x, $y, $z, $id);
				$expected["$x:$y:$z"] = $id;
			}
		}
	}
	$chunk->setBiomeId(3, 20, 5, 42);
	file_put_contents($file, FastChunkSerializer::serializeTerrain($chunk));
	file_put_contents($file . ".expected", json_encode($expected));
	echo "written ", strlen(file_get_contents($file)), " bytes\n";
}else{
	$chunk = FastChunkSerializer::deserializeTerrain(file_get_contents($file));
	$expected = json_decode(file_get_contents($file . ".expected"), true);
	$bad = 0;
	foreach($expected as $key => $id){
		[$x, $y, $z] = array_map("intval", explode(":", $key));
		if($chunk->getBlockStateId($x, $y, $z) !== $id){ $bad++; }
	}
	printf("blocks checked %d, mismatches %d, biome %d, populated %d\n", count($expected), $bad, $chunk->getBiomeId(3, 20, 5), (int) $chunk->isPopulated());
}
