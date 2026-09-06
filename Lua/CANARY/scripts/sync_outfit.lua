-- Sync the full outfits (all 3 addons) a character owns into Znote AAC storage,
-- so characterprofile.php can show them. Canary / otservbr-global.
--
-- looktype lists below are the complete Female / Male outfit sets from
-- opentibiabr/canary  data/XML/outfits.xml. Regenerate them from that file if
-- you add custom outfits.

znote_outfit_list = {
	{ -- Female outfits (127)
		136, 137, 138, 139, 140, 141, 142, 147, 148, 149,
		150, 155, 156, 157, 158, 252, 269, 270, 279, 288,
		324, 329, 336, 366, 431, 433, 464, 466, 471, 513,
		514, 542, 575, 578, 618, 620, 632, 635, 636, 664,
		666, 683, 694, 696, 698, 724, 732, 745, 749, 759,
		845, 852, 874, 885, 900, 909, 929, 956, 958, 963,
		965, 967, 969, 971, 973, 975, 1020, 1024, 1043, 1050,
		1057, 1070, 1095, 1103, 1128, 1147, 1162, 1174, 1187, 1203,
		1205, 1207, 1211, 1244, 1246, 1252, 1271, 1280, 1283, 1289,
		1293, 1323, 1332, 1339, 1372, 1383, 1385, 1387, 1416, 1437,
		1445, 1450, 1456, 1461, 1490, 1501, 1569, 1576, 1582, 1598,
		1613, 1619, 1663, 1676, 1681, 1714, 1723, 1726, 1746, 1775,
		1777, 1808, 1825, 1832, 1838, 1860, 1861
	},
	{ -- Male outfits (125)
		128, 129, 130, 131, 132, 133, 134, 143, 144, 145,
		146, 151, 152, 153, 154, 251, 268, 273, 278, 289,
		325, 328, 335, 367, 430, 432, 463, 465, 472, 512,
		516, 541, 574, 577, 610, 619, 633, 634, 637, 665,
		667, 684, 695, 697, 699, 725, 733, 746, 750, 760,
		846, 853, 873, 884, 899, 908, 931, 955, 957, 962,
		964, 966, 968, 970, 972, 974, 1021, 1023, 1042, 1051,
		1056, 1069, 1094, 1102, 1127, 1146, 1161, 1173, 1186, 1202,
		1204, 1206, 1210, 1243, 1245, 1251, 1270, 1279, 1282, 1288,
		1292, 1322, 1331, 1338, 1371, 1382, 1384, 1386, 1415, 1436,
		1444, 1449, 1457, 1460, 1489, 1500, 1568, 1575, 1581, 1597,
		1612, 1618, 1662, 1675, 1680, 1713, 1722, 1725, 1745, 1774,
		1776, 1809, 1824, 1831, 1837
	}
}

local syncOutfit = CreatureEvent("ZnoteSyncOutfit")

function syncOutfit.onLogin(player)
	-- storage_value .. storage_value + highest look type must be free.
	-- Must match $config['EQ_shower'] in the Znote AAC config.php.
	local storage_value = 10000
	for _, lookType in ipairs(znote_outfit_list[player:getSex() + 1]) do
		if player:hasOutfit(lookType, 3) then
			if player:getStorageValue(storage_value + lookType) ~= 3 then
				player:setStorageValue(storage_value + lookType, 3)
			end
		end
	end
	return true
end

syncOutfit:register()
