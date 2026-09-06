-- Znote Shop auto-delivery for Canary (opentibiabr) / otservbr-global.
-- Every 30 seconds it hands pending item / outfit / mount / house orders to the
-- buyer if they are online AND standing in a protection zone. Players can still
-- force delivery with !shop (see znoteshop_talkaction.lua) - keep both.

local znoteShopGlobal = GlobalEvent("ZnoteShopDelivery")

function znoteShopGlobal.onThink(interval)
	local orders = db.storeQuery([[
		SELECT
			MIN(`po`.`player_id`) AS `player_id`,
			`shop`.`id`,
			`shop`.`type`,
			`shop`.`itemid`,
			`shop`.`count`
		FROM `players_online` AS `po`
		INNER JOIN `players` AS `p` ON `po`.`player_id` = `p`.`id`
		INNER JOIN `znote_shop_orders` AS `shop` ON `p`.`account_id` = `shop`.`account_id`
		WHERE `shop`.`type` IN (1, 5, 6, 7)
		GROUP BY `shop`.`id`
	]])
	if orders == false then
		return true
	end

	repeat
		local playerId = result.getNumber(orders, "player_id")
		local oId = result.getNumber(orders, "id")
		local oType = result.getNumber(orders, "type")
		local oItemId = result.getNumber(orders, "itemid")
		local oCount = result.getNumber(orders, "count")

		local player = Player(playerId)
		if player then
			local tile = Tile(player:getPosition())
			if tile and tile:hasFlag(TILESTATE_PROTECTIONZONE) then

				if oType == 1 then
					local itemType = ItemType(oItemId)
					local needSlots = itemType:isStackable() and (math.floor(oCount / 100) + 1) or oCount
					if player:getFreeCapacity() < itemType:getWeight(oCount) then
						player:sendTextMessage(MESSAGE_FAILURE, "You have a pending shop order but not enough capacity.")
					elseif player:getFreeBackpackSlots() < needSlots then
						player:sendTextMessage(MESSAGE_FAILURE, ("You have a pending shop order - free %d backpack slots."):format(needSlots))
					else
						db.query("DELETE FROM `znote_shop_orders` WHERE `id` = " .. oId .. ";")
						player:addItem(oItemId, oCount)
						player:sendTextMessage(MESSAGE_EVENT_ADVANCE, ("You received %dx %s."):format(oCount, itemType:getName()))
					end

				elseif oType == 5 then
					local looks = {}
					local packed = oItemId
					if packed > 1000 then
						local male = math.floor(packed / 1000)
						table.insert(looks, male)
						packed = packed - (male * 1000)
					end
					table.insert(looks, packed)
					for _, lookType in ipairs(looks) do
						if not player:hasOutfit(lookType, oCount) then
							db.query("DELETE FROM `znote_shop_orders` WHERE `id` = " .. oId .. ";")
							player:addOutfit(lookType)
							player:addOutfitAddon(lookType, oCount)
							player:sendTextMessage(MESSAGE_EVENT_ADVANCE, "You received a new outfit.")
						else
							player:sendTextMessage(MESSAGE_FAILURE, "You already own a purchased outfit and addon.")
						end
					end

				elseif oType == 6 then
					if not player:hasMount(oItemId) then
						db.query("DELETE FROM `znote_shop_orders` WHERE `id` = " .. oId .. ";")
						player:addMount(oItemId)
						player:sendTextMessage(MESSAGE_EVENT_ADVANCE, "You received a new mount.")
					else
						player:sendTextMessage(MESSAGE_FAILURE, "You already own a purchased mount.")
					end

				elseif oType == 7 then
					local house = House(oItemId)
					local buyer = db.storeQuery("SELECT `name` FROM `players` WHERE `id` = " .. oCount .. " LIMIT 1;")
					if buyer ~= false then
						local buyerName = result.getString(buyer, "name")
						result.free(buyer)
						if house then
							db.query("DELETE FROM `znote_shop_orders` WHERE `id` = " .. oId .. ";")
							house:setHouseOwner(oCount, true)
							player:sendTextMessage(MESSAGE_EVENT_ADVANCE, ("House %s now belongs to %s."):format(house:getName(), buyerName))
						end
					end
				end

			else
				player:sendTextMessage(MESSAGE_STATUS, "You have a pending shop order - stand in a protection zone to receive it.")
			end
		end
	until not result.next(orders)
	result.free(orders)
	return true
end

znoteShopGlobal:interval(30000)
znoteShopGlobal:register()
