<?php

declare(strict_types=1);

namespace ChunkBench;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\format\io\FastChunkSerializer;

/**
 * Orders generation of a square of chunks through the server's own asynchronous pipeline, so the
 * terrain is produced on a worker thread and handed to the main thread exactly as it is in play.
 */
final class Main extends PluginBase{

	private const RADIUS = 10; //21x21 = 441 chunks

	private float $start;
	private int $pending = 0;
	private int $done = 0;

	protected function onEnable() : void{
		$world = $this->getServer()->getWorldManager()->getDefaultWorld();
		if($world === null){
			$this->getLogger()->error("no default world");
			$this->getServer()->shutdown();
			return;
		}

		$this->getLogger()->info("generating " . ((self::RADIUS * 2 + 1) ** 2) . " chunks in " . $world->getFolderName());
		$this->start = microtime(true);

		for($x = -self::RADIUS; $x <= self::RADIUS; $x++){
			for($z = -self::RADIUS; $z <= self::RADIUS; $z++){
				$this->pending++;
				$world->orderChunkPopulation($x, $z, null)->onCompletion(
					function() use ($world) : void{ $this->onChunkDone($world); },
					function() use ($world) : void{ $this->onChunkDone($world); }
				);
			}
		}

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			if($this->done >= $this->pending){
				$this->report();
			}
		}), 1);
	}

	private function onChunkDone(\pocketmine\world\World $world) : void{
		$this->done++;
	}

	private function report() : void{
		$generation = microtime(true) - $this->start;
		$world = $this->getServer()->getWorldManager()->getDefaultWorld();

		//the chunks are now in memory: time the transfer path a player triggers when they load them
		$chunks = [];
		for($x = -self::RADIUS; $x <= self::RADIUS; $x++){
			for($z = -self::RADIUS; $z <= self::RADIUS; $z++){
				$c = $world->loadChunk($x, $z);
				if($c !== null){ $chunks[] = $c; }
			}
		}

		$best = INF;
		for($i = 0; $i < 5; $i++){
			$t = hrtime(true);
			foreach($chunks as $c){ $d = FastChunkSerializer::serializeTerrain($c); unset($d); }
			$best = min($best, (hrtime(true) - $t) / 1e6);
		}
		$serialized = [];
		foreach($chunks as $c){ $serialized[] = FastChunkSerializer::serializeTerrain($c); }
		$bestDe = INF;
		for($i = 0; $i < 5; $i++){
			$t = hrtime(true);
			foreach($serialized as $d){ FastChunkSerializer::deserializeTerrain($d); }
			$bestDe = min($bestDe, (hrtime(true) - $t) / 1e6);
		}

		$rss = 0;
		foreach(file("/proc/self/status") as $line){
			if(str_starts_with($line, "VmRSS:")){ $rss = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT); }
		}

		printf("BENCH_RESULT %s\n", json_encode([
			"chunks" => count($chunks),
			"generation_s" => round($generation, 3),
			"serialize_ms" => round($best, 3),
			"deserialize_ms" => round($bestDe, 3),
			"rss_kb" => $rss,
			"bytes" => array_sum(array_map("strlen", $serialized)),
			"native_subchunk" => (new \ReflectionClass(\pocketmine\world\format\SubChunk::class))->isInternal(),
			"palette_bytes" => method_exists(\pocketmine\world\format\PalettedBlockArray::class, "getPaletteBytes"),
		]));
		$this->getServer()->shutdown();
	}
}
