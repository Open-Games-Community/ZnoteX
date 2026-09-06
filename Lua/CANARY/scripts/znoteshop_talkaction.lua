-- Znote Shop for Znote AAC on Canary (opentibiabr) / otservbr-global.
-- Players type !shop while standing in a protection zone to receive the orders
-- they bought on the website.
--
-- Order types (znote_shop_orders.type):
--   1  item id            itemid = item id,           count = amount
--   2  premium            handled by the website
--   3  gender change      handled by the website
--   4  name change        handled by the website
--   5  outfit + addons    itemid = male*1000 + female (or a single look type),
--                         count  = addon bitmask (1, 2 or 3)
--   6  mount              itemid = mount id
--   7  instant house      itemid = house id,          count = buyer player id

local znoteShopTalk = TalkAction("!shop")

function znoteShopTalk.onSay(player, words, param)
	local storage = 54073 -- unused storage, throttles !shop against SQL spam
	local cooldown = 15 -- seconds

	local remaining = player:getStorageValue(storage) - os.time()
	if remaining > 0 then
		player:sendTextMessage(MESSAGE_STATUS, ("You can use !shop again in %d seconds."):format(remaining))
		return true
	end
	player:setStorageValue(storage, os.time() + cooldown)

	local orders = db.storeQuery("SELECT `id`, `type`, `itemid`, `count` FROM `znote_shop_orders` WHERE `account_id` = " .. player:getAccountId() .. ";")
	if orders == false then
		player:sendTextMessage(MESSAGE_STATUS, "You have no orders.")
		return true
	end

	local served = false
	repeat
		local oId = result.getNumber(orders, "id")
		local oType = result.getNumber(orders, "type")
		local oItemId = result.getNumber(orders, "itemid")
		local oCount = result.getNumber(orders, "count")

		if oType == 1 then
			served = true
			local itemType = ItemType(oItemId)
			if player:getFreeCapacity() >= itemType:getWeight(oCount) then
				db.query("DELETE FROM `znote_shop_orders` WHERE `id` = " .. oId .. ";")
				player:addItem(oItemId, oCount)
				player:sendTextMessage(MESSAGE_EVENT_ADVANCE, ("You received %dx %s."):format(oCount, itemType:getName()))
			else
				player:sendTextMessage(MESSAGE_FAILURE, "You need more capacity to carry this order.")
			end

		elseif oType == 5 then
			served = true
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
					player:sendTextMessage(MESSAGE_FAILURE, "You already own this outfit and addon.")
				end
			end

		elseif oType == 6 then
			served = true
			if not player:hasMount(oItemId) then
				db.query("DELETE FROM `znote_shop_orders` WHERE `id` = " .. oId .. ";")
				player:addMount(oItemId)
				player:sendTextMessage(MESSAGE_EVENT_ADVANCE, "You received a new mount.")
			else
				player:sendTextMessage(MESSAGE_FAILURE, "You already own this mount.")
			end

		elseif oType == 7 then
			served = true
			local house = House(oItemId)
			local buyer = db.storeQuery("SELECT `name` FROM `players` WHERE `id` = " .. oCount .. " LIMIT 1;")
			if buyer ~= false then
				local buyerName = result.getString(buyer, "name")
				result.free(buyer)
				if house then
					db.query("DELETE FROM `znote_shop_orders` WHERE `id` = " .. oId .. ";")
					house:setHouseOwner(oCount, true)
					player:sendTextMessage(MESSAGE_EVENT_ADVANCE, ("House %s now belongs to %s. Keep the rent in the bank."):format(house:getName(), buyerName))
				end
			end
		end
	until not result.next(orders)
	result.free(orders)

	if not served then
		player:sendTextMessage(MESSAGE_STATUS, "You have no in-game orders to process.")
	end
	return true
end

znoteShopTalk:separator(" ")
znoteShopTalk:register()
