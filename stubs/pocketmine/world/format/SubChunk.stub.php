<?php

/**
 * @generate-function-entries
 */

namespace pocketmine\world\format;

class SubChunk{
	public const COORD_BIT_SIZE = 4;
	public const COORD_MASK = 15;
	public const EDGE_LENGTH = 16;

	/**
	 * @param PalettedBlockArray[] $blockLayers
	 * @phpstan-param list<PalettedBlockArray> $blockLayers
	 */
	public function __construct(int $emptyBlockId, array $blockLayers, \pocketmine\world\format\PalettedBlockArray $biomes, ?\pocketmine\world\format\LightArray $skyLight = null, ?\pocketmine\world\format\LightArray $blockLight = null){}

	/**
	 * Returns whether this subchunk contains any non-air blocks.
	 * This function will do a slow check, usually by garbage collecting first.
	 * This is typically useful for disk saving.
	 */
	public function isEmptyAuthoritative() : bool{}

	/**
	 * Returns a non-authoritative bool to indicate whether the chunk contains any blocks.
	 * This may report non-empty erroneously if the chunk has been modified and not garbage-collected.
	 */
	public function isEmptyFast() : bool{}

	/**
	 * Returns the block used as the default. This is assumed to refer to air.
	 * If all the blocks in a subchunk layer are equal to this block, the layer is assumed to be empty.
	 */
	public function getEmptyBlockId() : int{}

	public function getBlockStateId(int $x, int $y, int $z) : int{}

	public function setBlockStateId(int $x, int $y, int $z, int $block) : void{}

	/**
	 * @return PalettedBlockArray[]
	 * @phpstan-return list<PalettedBlockArray>
	 */
	public function getBlockLayers() : array{}

	public function getHighestBlockAt(int $x, int $z) : ?int{}

	public function getBiomeArray() : \pocketmine\world\format\PalettedBlockArray{}

	public function getBlockSkyLightArray() : \pocketmine\world\format\LightArray{}

	public function setBlockSkyLightArray(\pocketmine\world\format\LightArray $data) : void{}

	public function getBlockLightArray() : \pocketmine\world\format\LightArray{}

	public function setBlockLightArray(\pocketmine\world\format\LightArray $data) : void{}

	/**
	 * @return mixed[]
	 */
	public function __debugInfo() : array{}

	public function collectGarbage() : void{}
}
