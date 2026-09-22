/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: 8a104dcc3052e4579cd294e6d42cf086e03e36ab */

ZEND_BEGIN_ARG_INFO_EX(arginfo_class_pocketmine_world_format_SubChunk___construct, 0, 0, 3)
	ZEND_ARG_TYPE_INFO(0, emptyBlockId, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, blockLayers, IS_ARRAY, 0)
	ZEND_ARG_OBJ_INFO(0, biomes, pocketmine\\world\\format\\PalettedBlockArray, 0)
	ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, skyLight, pocketmine\\world\\format\\LightArray, 1, "null")
	ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, blockLight, pocketmine\\world\\format\\LightArray, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_pocketmine_world_format_SubChunk_isEmptyAuthoritative, 0, 0, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_pocketmine_world_format_SubChunk_isEmptyFast arginfo_class_pocketmine_world_format_SubChunk_isEmptyAuthoritative

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_pocketmine_world_format_SubChunk_getEmptyBlockId, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_pocketmine_world_format_SubChunk_getBlockStateId, 0, 3, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, x, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, y, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, z, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_pocketmine_world_format_SubChunk_setBlockStateId, 0, 4, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, x, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, y, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, z, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, block, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_pocketmine_world_format_SubChunk_getBlockLayers, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_pocketmine_world_format_SubChunk_getHighestBlockAt, 0, 2, IS_LONG, 1)
	ZEND_ARG_TYPE_INFO(0, x, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, z, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_pocketmine_world_format_SubChunk_getBiomeArray, 0, 0, pocketmine\\world\\format\\PalettedBlockArray, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_pocketmine_world_format_SubChunk_getBlockSkyLightArray, 0, 0, pocketmine\\world\\format\\LightArray, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_pocketmine_world_format_SubChunk_setBlockSkyLightArray, 0, 1, IS_VOID, 0)
	ZEND_ARG_OBJ_INFO(0, data, pocketmine\\world\\format\\LightArray, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_pocketmine_world_format_SubChunk_getBlockLightArray arginfo_class_pocketmine_world_format_SubChunk_getBlockSkyLightArray

#define arginfo_class_pocketmine_world_format_SubChunk_setBlockLightArray arginfo_class_pocketmine_world_format_SubChunk_setBlockSkyLightArray

#define arginfo_class_pocketmine_world_format_SubChunk___debugInfo arginfo_class_pocketmine_world_format_SubChunk_getBlockLayers

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_pocketmine_world_format_SubChunk_collectGarbage, 0, 0, IS_VOID, 0)
ZEND_END_ARG_INFO()

ZEND_METHOD(pocketmine_world_format_SubChunk, __construct);
ZEND_METHOD(pocketmine_world_format_SubChunk, isEmptyAuthoritative);
ZEND_METHOD(pocketmine_world_format_SubChunk, isEmptyFast);
ZEND_METHOD(pocketmine_world_format_SubChunk, getEmptyBlockId);
ZEND_METHOD(pocketmine_world_format_SubChunk, getBlockStateId);
ZEND_METHOD(pocketmine_world_format_SubChunk, setBlockStateId);
ZEND_METHOD(pocketmine_world_format_SubChunk, getBlockLayers);
ZEND_METHOD(pocketmine_world_format_SubChunk, getHighestBlockAt);
ZEND_METHOD(pocketmine_world_format_SubChunk, getBiomeArray);
ZEND_METHOD(pocketmine_world_format_SubChunk, getBlockSkyLightArray);
ZEND_METHOD(pocketmine_world_format_SubChunk, setBlockSkyLightArray);
ZEND_METHOD(pocketmine_world_format_SubChunk, getBlockLightArray);
ZEND_METHOD(pocketmine_world_format_SubChunk, setBlockLightArray);
ZEND_METHOD(pocketmine_world_format_SubChunk, __debugInfo);
ZEND_METHOD(pocketmine_world_format_SubChunk, collectGarbage);

static const zend_function_entry class_pocketmine_world_format_SubChunk_methods[] = {
	ZEND_ME(pocketmine_world_format_SubChunk, __construct, arginfo_class_pocketmine_world_format_SubChunk___construct, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, isEmptyAuthoritative, arginfo_class_pocketmine_world_format_SubChunk_isEmptyAuthoritative, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, isEmptyFast, arginfo_class_pocketmine_world_format_SubChunk_isEmptyFast, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, getEmptyBlockId, arginfo_class_pocketmine_world_format_SubChunk_getEmptyBlockId, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, getBlockStateId, arginfo_class_pocketmine_world_format_SubChunk_getBlockStateId, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, setBlockStateId, arginfo_class_pocketmine_world_format_SubChunk_setBlockStateId, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, getBlockLayers, arginfo_class_pocketmine_world_format_SubChunk_getBlockLayers, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, getHighestBlockAt, arginfo_class_pocketmine_world_format_SubChunk_getHighestBlockAt, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, getBiomeArray, arginfo_class_pocketmine_world_format_SubChunk_getBiomeArray, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, getBlockSkyLightArray, arginfo_class_pocketmine_world_format_SubChunk_getBlockSkyLightArray, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, setBlockSkyLightArray, arginfo_class_pocketmine_world_format_SubChunk_setBlockSkyLightArray, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, getBlockLightArray, arginfo_class_pocketmine_world_format_SubChunk_getBlockLightArray, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, setBlockLightArray, arginfo_class_pocketmine_world_format_SubChunk_setBlockLightArray, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, __debugInfo, arginfo_class_pocketmine_world_format_SubChunk___debugInfo, ZEND_ACC_PUBLIC)
	ZEND_ME(pocketmine_world_format_SubChunk, collectGarbage, arginfo_class_pocketmine_world_format_SubChunk_collectGarbage, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};
