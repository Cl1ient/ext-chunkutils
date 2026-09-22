#pragma once
#ifndef HAVE_PALETTE_BYTES_H
#define HAVE_PALETTE_BYTES_H

#include <cstddef>
#include <cstdint>
#include <vector>
#include <gsl/span>

/**
 * Conversion between a block palette and its serialized form: a sequence of unsigned 32-bit
 * little-endian integers, byte-for-byte identical to what PHP's pack("L*", ...) produces.
 *
 * Bytes are assembled and taken apart one at a time, so that the result doesn't depend on the
 * endianness of the build target, and so that no unaligned multi-byte access is ever performed on
 * the input buffer.
 */
namespace PaletteBytes {
	static const size_t ENTRY_SIZE = 4;

	static inline size_t byteSizeOf(size_t entryCount) {
		return entryCount * ENTRY_SIZE;
	}

	static inline bool isValidByteSize(size_t byteCount) {
		return (byteCount % ENTRY_SIZE) == 0;
	}

	template<typename Block>
	void encode(const gsl::span<const Block> palette, char* output) {
		static_assert(sizeof(Block) <= ENTRY_SIZE, "palette entries must fit into a 32-bit integer");

		unsigned char* out = reinterpret_cast<unsigned char*>(output);
		for (const Block entry : palette) {
			const uint32_t value = static_cast<uint32_t>(entry);
			out[0] = static_cast<unsigned char>(value);
			out[1] = static_cast<unsigned char>(value >> 8);
			out[2] = static_cast<unsigned char>(value >> 16);
			out[3] = static_cast<unsigned char>(value >> 24);
			out += ENTRY_SIZE;
		}
	}

	template<typename Block>
	std::vector<Block> decode(const char* input, size_t entryCount) {
		static_assert(sizeof(Block) <= ENTRY_SIZE, "palette entries must fit into a 32-bit integer");

		std::vector<Block> palette;
		palette.reserve(entryCount);

		const unsigned char* in = reinterpret_cast<const unsigned char*>(input);
		for (size_t i = 0; i < entryCount; ++i) {
			palette.push_back(static_cast<Block>(
				static_cast<uint32_t>(in[0]) |
				(static_cast<uint32_t>(in[1]) << 8) |
				(static_cast<uint32_t>(in[2]) << 16) |
				(static_cast<uint32_t>(in[3]) << 24)
			));
			in += ENTRY_SIZE;
		}

		return palette;
	}
}

#endif
