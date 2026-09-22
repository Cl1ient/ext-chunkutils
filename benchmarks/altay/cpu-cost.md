# CPU cost

Companion to `README.md` in this folder, which measures elapsed time. This one measures the work
itself: **instructions retired per operation**, counted with `perf stat` on Altay's own chunk code.

## Why instructions and not cycles

Both were collected. Across seven repetitions of the same measurement on this machine:

| counter | spread between repetitions |
|---|---|
| instructions | **0.2%** |
| cycles | 22% to 122% |

Cycle counts (and therefore IPC, and cache-miss deltas) are unusable here — a desktop with other
processes running, SMT and boost clocks moves them far more than the difference being measured. One
cell of the first attempt claimed the native SubChunk burned 70% more cycles on deserialization
while the clock said it was 12% faster; that was noise, not a finding. Instruction counts are
deterministic, so they're what this report is built on. Elapsed time is in `README.md`, measured
separately with min-of-N.

Instruction counts were re-measured on the PocketMine PHP binary; those numbers, the price of the
`getBlockLayers()` regression, and why caching that array makes the server slower despite executing
fewer instructions, are in `server-run.md`.

## Method

`perf-phase.php` performs a fixed setup (24 chunks built and serialized), then repeats one phase R
times. Each phase is measured twice with two large values of R and the counters subtracted, so
interpreter startup, autoloading and chunk building cancel out and what remains is steady-state
work. Median of five pairs, pinned to one core. Three configurations as in `README.md`: stock,
palette-bytes only, and both changes.

## Instructions per operation

| phase | unit | baseline | palette | full | palette | full |
|---|---|---|---|---|---|---|
| generate | chunk | 70,595,240 | 70,596,174 | 50,828,246 | ±0% | **-28.0%** |
| serializeTerrain | chunk | 142,664 | 109,484 | 108,693 | **-23.3%** | -23.8% |
| serializeTerrain, rich palettes | chunk | 279,609 | 128,124 | 128,787 | **-54.2%** | -53.9% |
| deserializeTerrain | chunk | 363,339 | 325,836 | 305,145 | **-10.3%** | -16.0% |
| block read | block | 1,657 | 1,656 | 1,176 | ±0% | **-29.0%** |
| block write | block | 1,977 | 1,977 | 1,410 | ±0% | **-28.7%** |
| clone | chunk | 100,550 | 100,557 | 59,329 | ±0% | **-41.0%** |

The palette-only column lands within 0.1% of the baseline on every phase that doesn't serialize,
which is what it should do and a good sign the measurement is sound.

## What the palette encoding costs

The same change measured on two palette sizes gives its cost as a fixed part plus a per-entry part.
Plain chunks carry 48 palettes and 82 entries each, rich ones 48 palettes and 1018 entries, so:

```
48a +   82b =  33,180 instructions saved per plain chunk
48a + 1018b = 151,485 instructions saved per rich chunk
```

which solves to **475 instructions per palette** and **126 per palette entry**.

The fixed part is allocating the PHP array, destroying it afterwards, and calling `pack()` with its
format string. The per-entry part is inserting a zval into the hash table, then having `pack()`
read it back out and type-check it. `getPaletteBytes()` replaces all of it with a `zend_string`
allocation and four byte stores per entry.

A profile of the serialization phase (`perf record`, 4000 passes) shows the symbols that disappear:

| symbol | baseline | full |
|---|---|---|
| `zend_hash_next_index_insert_new` | 1.54% | — |
| `paletted_block_array_get_palette` | 1.52% | — |
| `zend_array_destroy` | 1.30% | 0.82% |
| `_emalloc` / `_efree` | 2.96% / 2.14% | 1.87% / 1.12% |
| `PalettedBlockArray_getPaletteBytes` | — | 1.59% |
| `SubChunk_getBlockLayers` | — | 1.27% |

`pack()` itself has no symbol in the profile: the distribution's PHP binary is stripped, so only the
extension's own symbols (built with `-g`) are resolved. Everything inside the interpreter shows up
as `execute_ex`, which is 43-50% of the phase in every configuration — that's FastChunkSerializer's
own PHP code and is untouched by this work.

## What native SubChunk costs

Every block access through a chunk goes `Chunk::getBlockStateId()` -> `SubChunk::getBlockStateId()`
-> `PalettedBlockArray::get()`. Making the middle step native removes **481 instructions per read**
and **567 per write** — the cost of entering and leaving a PHP method frame, plus the property
fetches and the `count()` on the layer array that the PHP version does on every call.

Generation saves far more per chunk (19.8M instructions) than 20,480 writes at 567 would suggest,
because generation writes new block states that grow the palette, and that path crosses the
PHP/native boundary more than a repeated write to an existing entry does.

## In server terms

A player joining with view distance 8 pulls roughly 200 chunks.

| | instructions saved |
|---|---|
| serializing 200 chunks for the chunk-sending thread | 6.6 M |
| generating 200 fresh chunks | 4.0 G |

At the ~9 G instructions/s this machine sustains on this workload, that's about 0.7 ms for the
sending and 0.44 s for the generation. The palette change is a real but small saving on an already
cheap operation; the native SubChunk is what moves the needle, and it moves it most where the server
is already working hardest — generating terrain and reading blocks.
