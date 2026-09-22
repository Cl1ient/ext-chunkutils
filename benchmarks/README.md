# Palette bytes benchmark

`palette-bytes.php` compares the two ways of moving a block palette across the PHP/C++ boundary:

| | array path (before) | bytes path (after) |
|---|---|---|
| write | `pack("L*", ...$array->getPalette())` | `$array->getPaletteBytes()` |
| read | `array_values(unpack("L*", $bytes))` then `fromData($bits, $words, $palette)` | `fromData($bits, $words, $bytes)` |

The array path is exactly what `FastChunkSerializer::serializePalettedArray()` and
`deserializePalettedArray()` do today. Only the palette handling is measured: the bits-per-block
byte, the word array and the stream writes are byte-for-byte identical in both paths, so leaving
them out keeps the measurement on what actually changes.

## Method

The corpus is one chunk column: 24 terrain palettes plus 24 biome palettes. Terrain palette sizes
are weighted towards the small palettes that make up most of a real world (8 uniform subchunks,
then 2 to 128 entries) with one pathological 4096-entry palette; biome palettes are uniform except
two. That comes to 5089 palette entries per column.

Each path is run 5 times to warm up, then timed with `hrtime()` over N columns. Before timing, both
paths are checked to produce identical bytes and identical arrays, so a broken path can't look
fast. Run it with:

```
php -d extension=modules/chunkutils2.so benchmarks/palette-bytes.php 500
```

## Results

AMD Ryzen 5 5600, PHP 8.5.4 NTS non-debug, extension built with the default `-g -O2`, 500 columns,
best of three runs (the three runs agreed within 5%):

| path | per column | per palette entry |
|---|---|---|
| write: `pack("L*", ...getPalette())` | 45.1 us | 8.9 ns |
| write: `getPaletteBytes()` | 2.7 us | 0.5 ns |
| read: `unpack()` + `fromData(array)` | 94.9 us | 18.6 ns |
| read: `fromData(bytes)` | 27.4 us | 5.4 ns |

Writing is **15.6x to 16.7x faster** (94% less time), reading is **3.3x to 3.5x faster** (70% less
time). Reading gains less because `fromData()` still has to build the container and copy the word
array either way; what disappears is the `unpack()` hash table, `array_values()` copying it, and
the per-element zval bounds check in `palette_data_from_array()`.
