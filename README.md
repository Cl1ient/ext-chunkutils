# ext-chunkutils2

![CI](https://github.com/pmmp/ext-chunkutils2/workflows/CI/badge.svg)

## What is this?
This extension implements some performance-sensitive components of PocketMine-MP's internal chunk handling system in C++ for better performance and lower memory usage.

## What's in the extension?
At the time of writing:

- `\pocketmine\world\format\PalettedBlockArray`: This class implements paletted block-storages as per modern MCPE since 1.2.13.
- `\pocketmine\world\format\io\SubChunkConverter`: This class contains a series of helper methods for upgrading legacy world terrain.
- `\pocketmine\world\format\LightArray`: Implements a 16x16x16 nibble array used for light storage.
- `\pocketmine\world\format\SubChunk`: Implements a 16x16x16 chunk section, holding its block layers, biome palette and light arrays.

## Palette as raw bytes
`PalettedBlockArray` can hand over its palette as a string of little-endian `uint32`s instead of a
PHP array, and take it back in the same form. The format is byte-for-byte what
`pack("L*", ...$array->getPalette())` produces, so it stays compatible with anything already
storing palettes that way.

```php
public function getPaletteBytes() : string;
public static function fromData(int $bitsPerBlock, string $wordArray, array|string $palette) : PalettedBlockArray;
```

`getPalette()` and the array form of `fromData()` are untouched, so existing code keeps working.
A palette string whose length isn't a multiple of 4 raises `PalettedBlockArrayLoadException`,
like every other malformed input given to `fromData()`.

Applied to `FastChunkSerializer`, the two sides become:

```php
-$serialPalette = pack("L*", ...$array->getPalette());
+$serialPalette = $array->getPaletteBytes();
```

```php
-$unpackedPalette = unpack("L*", $stream->readByteArray($paletteSize));
-$palette = array_values($unpackedPalette);
-return PalettedBlockArray::fromData($bitsPerBlock, $words, $palette);
+return PalettedBlockArray::fromData($bitsPerBlock, $words, $stream->readByteArray($paletteSize));
```

which is 15x faster on the writing side and 3x on the reading side; see `benchmarks/`.

## What's in the folders?
- `benchmarks`: Scripts measuring the paths this fork changes, with their methodology and results
- `gsl`: Subtree merge of https://github.com/microsoft/GSL
- `lib`: Library code implementing various chunk components. The code in here is unfettered by PHP and can be used on its own.
- `src`: Binding code that glues together PHP and the C++ chunkutils2 components.
- `tests`: `.phpt` tests for the extension which can be run with PHP's `run-tests.php` tool (or `make test` when using PHP's build system)
