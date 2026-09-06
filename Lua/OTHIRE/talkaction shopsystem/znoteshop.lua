-- Znote Shop for Znote AAC on OTHire (7.72).
-- Player types !shop to receive the orders bought on the website.
--
-- OTHire is a 7.72 server: there are no mounts and no addon system, so only
-- order type 1 (items) and a plain type 5 (outfit look type, addon ignored by
-- the client) are handled here. Premium / gender / name orders (2, 3, 4) are
-- applied by the website.

function onSay(cid, words, param)
	local storage = 54073 -- unused storage, throttles !shop against SQL spam
	local cooldown = 15 -- seconds

	if getPlayerStorageValue(cid, storage) > os.time() then
		doPlayerSendTextMessage(cid, MESSAGE_STATUS_CONSOLE_BLUE, "You can use !shop again in " .. (getPlayerStorageValue(cid, storage) - os.time()) .. " seconds.")
		return false
	end
	setPlayerStorageValue(cid, storage, os.time() + cooldown)

	local accid = getAccountNumberByPlayerName(getCreatureName(cid))
	local orderQuery = db.storeQuery("SELECT `id`, `type`, `itemid`, `count` FROM `znote_shop_orders` WHERE `account_id` = " .. accid .. ";")
	if orderQuery == false then
		doPlayerSendTextMessage(cid, MESSAGE_STATUS_WARNING, "You have no orders.")
		return false
	end

	local served = false
	repeat
		local q_id = result.getDataInt(orderQuery, "id")
		local q_type = result.getDataInt(orderQuery, "type")
		local q_itemid = result.getDataInt(orderQuery, "itemid")
		local q_count = result.getDataInt(orderQuery, "count")

		-- ORDER TYPE 1 (items)
		if q_type == 1 then
			served = true
			if getPlayerFreeCap(cid) >= getItemWeight(q_itemid, q_count) then
				db.query("DELETE FROM `znote_shop_orders` WHERE `id` = " .. q_id .. ";")
				doPlayerAddItem(cid, q_itemid, q_count)
				doPlayerSendTextMessage(cid, MESSAGE_INFO_DESCR, "Congratulations! You have received " .. q_count .. " " .. getItemName(q_itemid) .. "(s)!")
			else
				doPlayerSendTextMessage(cid, MESSAGE_STATUS_WARNING, "You need more capacity to carry this order!")
			end
		end

		-- ORDER TYPE 5 (outfit look type; addon bit is ignored on 7.72)
		if q_type == 5 then
			served = true
			local itemid = q_itemid
			local outfits = {}
			if itemid > 1000 then
				local first = math.floor(itemid / 1000)
				table.insert(outfits, first)
				itemid = itemid - (first * 1000)
			end
			table.insert(outfits, itemid)

			for _, outfitId in pairs(outfits) do
				if not canPlayerWearOutfit(cid, outfitId, q_count) then
					db.query("DELETE FROM `znote_shop_orders` WHERE `id` = " .. q_id .. ";")
					doPlayerAddOutfit(cid, outfitId, q_count)
					doPlayerSendTextMessage(cid, MESSAGE_INFO_DESCR, "Congratulations! You have received a new outfit!")
				else
					doPlayerSendTextMessage(cid, MESSAGE_STATUS_WARNING, "You already have this outfit!")
				end
			end
		end

		-- Type 6 (mounts) and 7 (houses) do not exist on 7.72 / OTHire.
	until not result.next(orderQuery)
	result.free(orderQuery)

	if not served then
		doPlayerSendTextMessage(cid, MESSAGE_STATUS_CONSOLE_BLUE, "You have no orders to process in-game.")
	end
	return false
end
