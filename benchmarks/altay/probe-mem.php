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

function rss() : int{
	foreach(file("/proc/self/status") as $line){
		if(str_starts_with($line, "VmRSS:")){ return (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT) * 1024; }
	}
	return 0;
}

function build(int $cx, int $cz, bool $rich) : Chunk{
	$subChunks = [];
	for($y = Chunk::MIN_SUBCHUNK_INDEX; $y <= Chunk::MAX_SUBCHUNK_INDEX; $y++){
		$subChunks[] = new SubChunk(0, [], new PalettedBlockArray(1));
	}
	$chunk = new Chunk($subChunks, true);
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

$count = (int) (getenv("CHUNKS") ?: 400);
$rich = getenv("RICH") === "1";

//warm the allocator so the measurement isn't dominated by first-touch pages
$warm = [];
for($i = 0; $i < 20; $i++){ $warm[] = build($i, 0, $rich); }
unset($warm);
gc_collect_cycles();

$before = rss();
$chunks = [];
for($i = 0; $i < $count; $i++){ $chunks[] = build($i % 32, intdiv($i, 32), $rich); }
$after = rss();

printf("%s chunks=%d  rss delta %.1f MB  => %.2f KB/chunk   (php heap %.2f KB/chunk)\n",
	$rich ? "rich " : "plain", $count, ($after - $before) / 1048576, ($after - $before) / $count / 1024,
	memory_get_usage() / $count / 1024);
