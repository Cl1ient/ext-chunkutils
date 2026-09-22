# ext-chunkutils2

![CI](https://github.com/Cl1ient/ext-chunkutils/workflows/CI/badge.svg)

## What is this?
This extension implements some performance-sensitive components of PocketMine-MP's internal chunk handling system in C++ for better performance and lower memory usage.

This is a fork of [pmmp/ext-chunkutils2](https://github.com/pmmp/ext-chunkutils2), which was archived
upstream on 8 August 2026. It adds two things on top of the last state before the archive: the block
palette can cross the PHP boundary as raw bytes, and `SubChunk` is implemented natively.

## What's in the extension?

- `\pocketmine\world\format\PalettedBlockArray`: paletted block storage as per modern MCPE since 1.2.13.
- `\pocketmine\world\format\SubChunk`: a 16x16x16 chunk section, holding its block layers, biome palette and light arrays.
- `\pocketmine\world\format\LightArray`: a 16x16x16 nibble array used for light storage.
- `\pocketmine\world\format\io\SubChunkConverter`: helpers for upgrading legacy world terrain.

## What this fork adds

### Palette as raw bytes

`PalettedBlockArray` can hand over its palette as a string of little-endian `uint32`s instead of a
PHP array, and take it back in the same form. The format is byte-for-byte what
`pack("L*", ...$array->getPalette())` produces, so it stays compatible with anything already storing
palettes that way.

```php
public function getPaletteBytes() : string;
public static function fromData(int $bitsPerBlock, string $wordArray, array|string $palette) : PalettedBlockArray;
```

`getPalette()` and the array form of `fromData()` are untouched, so existing code keeps working. A
palette string whose length isn't a multiple of 4 raises `PalettedBlockArrayLoadException`, like
every other malformed input given to `fromData()`.

The encoding lives in `lib/PaletteBytes.h`, outside the PHP binding. It reads and writes one byte at
a time, so the result doesn't depend on the endianness of the build target and unaligned buffers are
never read as `uint32`.

### Native SubChunk

`SubChunk` is registered by the extension, with the same API as the PHP class it replaces:
`getBlockStateId()`, `setBlockStateId()`, `getBlockLayers()`, `getBiomeArray()`,
`getHighestBlockAt()` returning `?int`, the light array accessors, `collectGarbage()`,
`isEmptyFast()`, `isEmptyAuthoritative()`, `__debugInfo()`, plus the `COORD_BIT_SIZE`, `COORD_MASK`
and `EDGE_LENGTH` constants. Clone and `serialize()`/`unserialize()` behave as before.

Because the extension declares the class, **the server's own `SubChunk.php` has to go** — two
declarations of the same name is a fatal error. See the integration notes below.

## Results

Measured against the stock extension, on a real Altay server running the PocketMine PHP 8.2.30 ZTS
binary. Full methodology, scripts and raw figures are in `benchmarks/altay/`.

### CPU — instructions retired per operation

Deterministic, repeatable to within 0.10%.

| Operation | Baseline | This fork | Gain |
|---|---|---|---|
| Generate one chunk | 68,000,966 | 49,735,382 | **−26.9%** |
| Serialize one chunk | 145,265 | 114,924 | **−20.9%** |
| Serialize one chunk, large palettes | 293,225 | 131,692 | **−55.1%** |
| Deserialize one chunk | 365,262 | 303,755 | **−16.8%** |
| Read one block | 1,611 | 1,175 | **−27.1%** |
| Write one block | 1,905 | 1,380 | **−27.5%** |
| Clone one chunk | 104,279 | 60,150 | **−42.3%** |

### Time — on a running server

441 chunks generated through the server's own asynchronous pipeline, median of 6 runs.

| Operation | Baseline | This fork | Gain |
|---|---|---|---|
| Generate 441 chunks | 1.523 s | 1.373 s | **−9.8%** |
| Serialize 441 chunks | 6.61 ms | 5.60 ms | **−15.3%** |
| Deserialize 441 chunks | 21.77 ms | 19.38 ms | **−11.0%** |

### Time — CLI harness

48 chunks on the same binary, median of 5 runs, for the operations the server run doesn't isolate.

| Operation | Baseline | This fork | Gain |
|---|---|---|---|
| Serialize, large palettes | 0.98 ms | 0.54 ms | **−44.6%** |
| Deserialize, large palettes | 3.88 ms | 2.39 ms | **−38.5%** |
| 196k block reads | 20.37 ms | 14.22 ms | **−30.2%** |
| 98k block writes | 12.33 ms | 8.65 ms | **−29.8%** |
| Clone 48 chunks | 0.43 ms | 0.28 ms | **−35.1%** |
| `collectGarbage()`, 1152 subchunks | 0.12 ms | 0.07 ms | **−43.0%** |

### Memory

| Metric | Baseline | This fork | Gain |
|---|---|---|---|
| Server RSS, 441 chunks loaded | 284.2 MB | 280.7 MB | **−3.5 MB (−1.2%)** |
| Per loaded chunk | 20.59 KB | 17.50 KB | **−3.1 KB (−15%)** |

The per-chunk saving is a flat amount — 24 PHP objects with five properties each, replaced by one
native object — so its weight depends on how much block data a chunk carries: −15% on simple chunks,
−3% on chunks with large palettes.

### How to read these numbers

Two things are worth knowing before quoting any of them.

**The time gains are about half the CPU gains, and that's expected.** Instruction counts measure the
code this fork changes; a server also runs everything around it. Terrain generation is the clearest
case: the CPU count drops 26.9% because block writing gets cheaper, but a real generator spends most
of its time in noise functions and population, all PHP, none of it touched here — so the server sees
9.8%.

**The two changes don't contribute equally.** The palette encoding accounts for most of the
serialization gain (−10.6% of the −15.3% on the server) and nothing else. Everything else —
generation, block access, cloning, garbage collection, memory — comes from `SubChunk` being native.
The palette also gains less the more realistic the chunks are: −55% of the instructions on synthetic
chunks with large palettes, −20.9% on the chunks a world actually generates, because the word array
weighs more there and the palette less.

## Using it with PocketMine-MP

Four changes on the server side:

1. **Delete `src/world/format/SubChunk.php`.** The extension declares that class now.
2. **Require `chunkutils2 ^0.4.0`** in `composer.json`, and raise `$wantedVersionLock` from `"0.3"`
   to `"0.4"` in `src/PocketMine.php`.
3. **Add `SubChunk` to the PHPStan stub** (`tests/phpstan/stubs/chunkutils2.stub`), alongside the
   entries already there for `PalettedBlockArray`, and update `fromData()` to accept
   `array|string $palette`.
4. **Switch `FastChunkSerializer` to the bytes path:**

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

The serialized output is unchanged: a chunk written by one version reads back identically under the
other, byte for byte.

### If you build the PocketMine PHP binary yourself

`pmmp/PHP-Binaries`' `compile.sh` links chunkutils2 **statically** into the binary, so a statically
linked stock copy can't be swapped for this one. Either point the script at this fork, or delete the
`--enable-chunkutils2` line from `compile.sh` and load the extension as a shared `.so` instead. Note
also that the build ends by deleting `$INSTALL_DIR/include`, so `phpize` has no headers afterwards —
`benchmarks/altay/server-run.md` explains how to work around that.

## Building and testing

Standard PHP extension build:

```
phpize
./configure
make
make test
```

Tested on PHP 8.1 through 8.5; CI covers both ZTS and NTS, debug and release, with and without
Valgrind. The 34 `.phpt` tests in `tests/` also pass under the PocketMine ZTS binary.

After editing a `.stub.php`, regenerate its `_arginfo.h` with `gen_stub.php` from the PHP source
distribution:

```
php /path/to/php-src/build/gen_stub.php stubs/pocketmine/world/format/PalettedBlockArray.stub.php
```

## What's in the folders?
- `benchmarks`: scripts measuring the paths this fork changes, with their methodology and results
- `gsl`: subtree merge of https://github.com/microsoft/GSL
- `lib`: library code implementing various chunk components. The code in here is unfettered by PHP and can be used on its own.
- `src`: binding code that glues together PHP and the C++ chunkutils2 components.
- `tests`: `.phpt` tests for the extension which can be run with PHP's `run-tests.php` tool (or `make test` when using PHP's build system)

## Tried and dropped

`getBlockLayers()` costs 81 instructions more per call than the PHP version did, because it builds a
fresh array where PHP handed back the one it already held. Caching that array was implemented and
measured: it cuts 3,545 instructions per chunk serialized and makes the call itself 2.2x faster in
isolation, but the server gets **slower** (5.08 ms against 4.83 ms) and uses 2.7 MB more memory.
Allocating and freeing the same small array every time keeps it hot in L1; a cached one lives elsewhere
and is a cache miss on every chunk. Filling the array with `ZEND_HASH_FILL_PACKED` gained nothing
either — the cost is the allocation, not the insertion. The numbers are in
`benchmarks/altay/server-run.md`.

## Upstream

pmmp archived `ext-chunkutils2` on 8 August 2026, so there are no upstream updates to merge. The
palette-bytes API existed upstream briefly and was reverted in October 2025, on the grounds that
converting palettes to bytes is not worth it when the data has to be decomposed into integers
anyway. That reasoning holds for the path pmmp had in mind; the measurements above are for
`FastChunkSerializer`, which passes the bytes straight through.
