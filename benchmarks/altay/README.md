# What this fork changes for a running server

Measured by running Altay's own chunk code — its `Chunk`, `SubChunk` and `FastChunkSerializer`,
not a reimplementation — against three builds of the extension.

## The three configurations

| | extension | Altay |
|---|---|---|
| **baseline** | `master` (stock) | `af4f1020a`, PHP `SubChunk`, `pack("L*")`/`unpack()` in FastChunkSerializer |
| **palette** | `master` + palette-bytes commit | baseline + the FastChunkSerializer patch |
| **full** | `sub-chunk-native-v2` | `feat/chunkutils`: native SubChunk, bytes palette |

The middle configuration exists to split the two changes apart: anything it gains comes from the
palette encoding alone, anything the full configuration gains on top of it comes from SubChunk
being native.

## Method

48 chunks, each 24 subchunks tall, generated deterministically so every configuration gets
identical data: bedrock floor, stone with ore and gravel pockets, 4 blocks of dirt, grass, water up
to sea level. That's 82 palette entries per chunk. A second set of 48 "rich" chunks gives every
stone block one of 96 states, for 1018 palette entries per chunk — a built-up area, or a world with
many block states in play.

Each operation is run 9 times in-process and the fastest is kept; the whole harness runs 5 times
per configuration and the median is reported. Everything is pinned to one core with `taskset`.
AMD Ryzen 5 5600, PHP 8.5.4 NTS, extension built with the default `-g -O2`.

PHP 8.5.4 from the distribution is not the ZTS binary a real server runs, and no client was
connected: what is measured is the chunk code a server executes, not a live session.

```
composer install --ignore-platform-reqs
php -d extension=modules/chunkutils2.so -d extension=<ext-encoding>/modules/encoding.so \
    benchmarks/altay/bench-altay.php
```

These numbers come from a CLI harness on a distribution PHP. For the same measurements taken on a
running server with the PocketMine binary — which is what to quote — see `server-run.md`; the gains
there are roughly half of these, because a server also runs the generator this doesn't touch.

## Results

Times for the whole batch of 48 chunks, median of 5 runs. Run-to-run spread was under 4% except on
`rich_serializeTerrain` (18%).

| operation | baseline | palette | full | palette | full |
|---|---|---|---|---|---|
| generate (48 chunks of terrain) | 264.5 ms | 265.5 ms | 202.0 ms | — | **-23.7%** |
| serializeTerrain | 0.58 ms | 0.44 ms | 0.44 ms | **-24.7%** | -25.5% |
| deserializeTerrain | 1.86 ms | 1.67 ms | 1.63 ms | **-10.7%** | -12.7% |
| serializeTerrain, rich palettes | 0.97 ms | 0.50 ms | 0.52 ms | **-48.1%** | -45.8% |
| deserializeTerrain, rich palettes | 3.20 ms | 2.45 ms | 2.40 ms | **-23.4%** | -25.1% |
| 196k block reads | 22.37 ms | 21.72 ms | 15.18 ms | — | **-32.1%** |
| 98k block writes | 12.98 ms | 12.76 ms | 9.77 ms | — | **-24.7%** |
| clone 48 chunks | 0.46 ms | 0.46 ms | 0.26 ms | — | **-42.8%** |
| collectGarbage, 1152 subchunks | 0.14 ms | 0.14 ms | 0.07 ms | — | **-51.4%** |

Per unit: a chunk goes from 5.51 ms to 4.21 ms to build, 12.1 us to 9.2 us to serialize for another
thread, 38.8 us to 34.0 us to read back. A block read costs 114 ns instead of 137 ns at the chunk
level; a write 132 ns instead of 99 ns.

The two changes don't overlap: the palette encoding only touches serialization, and native SubChunk
only touches everything else. The full configuration is marginally behind the palette-only one on
serialization because `getBlockLayers()` regressed (below).

### With OPcache, and with the JIT

A real server runs with OPcache, which helps the PHP `SubChunk` and does nothing for native code,
so the comparison is repeated with it on. The JIT is off in a stock PocketMine, but included here
to show the worst case for this work.

| operation | no opcache | opcache | opcache + JIT |
|---|---|---|---|
| generate | -23.7% | -23.1% | -27.3% |
| serializeTerrain | -25.5% | -24.1% | -29.6% |
| serializeTerrain, rich | -45.8% | -46.5% | -49.6% |
| block reads | -32.1% | -28.8% | -32.9% |
| block writes | -24.7% | -28.2% | -29.9% |
| collectGarbage | -51.4% | -50.8% | -34.7% |

The JIT makes both sides roughly 2.5x faster in absolute terms and leaves the gap intact.

### Memory

Measured as process RSS while holding 400 loaded chunks, since a native SubChunk keeps its data on
the C++ heap where `memory_get_usage()` can't see it.

| | baseline | full | saved |
|---|---|---|---|
| plain chunks | 20.59 KB/chunk | 17.50 KB/chunk | 3.09 KB |
| rich chunks | 92.73 KB/chunk | 89.65 KB/chunk | 3.08 KB |

The saving is a flat ~3.1 KB per chunk — 24 PHP objects with five properties each, replaced by one
native object — so it matters most on worlds with lots of loaded but simple chunks. A server
holding 5000 chunks saves about 15 MB.

## Per method

Cost of one call, fastest of 12 rounds.

| method | PHP | native | |
|---|---|---|---|
| `getHighestBlockAt()` | 452.4 ns | 44.9 ns | 10.1x |
| `collectGarbage()` | 104.9 ns | 22.3 ns | 4.7x |
| `getBlockStateId()` | 52.5 ns | 17.1 ns | 3.1x |
| `setBlockStateId()` | 55.7 ns | 22.6 ns | 2.5x |
| `isEmptyFast()` | 20.5 ns | 8.6 ns | 2.4x |
| `getBlockLightArray()` | 18.8 ns | 9.7 ns | 1.9x |
| `getBiomeArray()` | 16.8 ns | 9.3 ns | 1.8x |
| `getEmptyBlockId()` | 12.5 ns | 8.5 ns | 1.5x |
| `clone` | 1629.7 ns | 1383.8 ns | 1.2x |
| **`getBlockLayers()`** | **14.7 ns** | **24.3 ns** | **0.6x** |

`getBlockLayers()` is the one thing that got slower. The PHP version hands back the array it already
holds and only bumps its refcount; the native one has to build a fresh array of objects on every
call. FastChunkSerializer calls it 24 times per chunk, which costs about 230 ns of the 9.2 us it
spends on a chunk, so it doesn't undo the win — but code that calls it in a tight loop will notice.
Caching the array inside the object and invalidating it on mutation would fix it.

## Correctness

`benchmarks/altay/crosscheck.php` serializes a chunk under one configuration and reads it back under
the other. Both directions: 39680 blocks compared, 0 mismatches, biome preserved. The serialized
output is byte-identical between all three configurations (797132 bytes for the 48 plain chunks,
2125756 for the rich ones), which is the point of matching `pack("L*")` exactly.
