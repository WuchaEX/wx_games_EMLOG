-- 统一道具类型命名：将 Niuniu 旧别名改为标准名
-- 运行前请备份！

-- 1) 商城商品表：更新 item_type
UPDATE `emlog_wx_games_shop_items` SET `item_type` = 'title_colored' WHERE `item_type` = 'nickname_color';
UPDATE `emlog_wx_games_shop_items` SET `item_type` = 'title_effect'  WHERE `item_type` = 'effect';
UPDATE `emlog_wx_games_shop_items` SET `item_type` = 'title_badge'  WHERE `item_type` = 'title';
-- buff 类型没有对应标准名，且 Niuniu 后台也不再提供该选项
-- 已有的 buff 类型道具会被 use_item 的 else 分支当作一般消耗品处理，仍可使用

-- 2) 用户背包表：通过 JOIN shop_items 更新已激活状态
-- 注意：标准名互斥组简化后用 item_type 精确匹配即可，不再需要跨命名映射
-- 已激活的道具不受影响，激活/去激活逻辑由 use_item 按统一后的 item_type 处理
