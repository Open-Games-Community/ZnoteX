#!/bin/sh
set -e

engine=$(printf "%s" "${ZNOTE_SERVER_ENGINE:-TFS_10}" | tr '[:upper:]' '[:lower:]')
schema="/schemas/${engine}.sql"

if [ ! -f "$schema" ]; then
	case "$engine" in
		tfs_02)
			echo "[znotex] No dedicated TFS 0.2.13+ schema is bundled; falling back to the TFS_03 schema (TFS 0.3.6+/0.4/OTX). Ultra-legacy tables some TFS_02-only pages use may be missing." >&2
			schema="/schemas/tfs_03.sql"
			;;
		*)
			echo "[znotex] Unknown ZNOTE_SERVER_ENGINE '${ZNOTE_SERVER_ENGINE}', falling back to TFS_10." >&2
			schema="/schemas/tfs_10.sql"
			;;
	esac
fi

echo "[znotex] Importing game schema: $schema"
mysql --user=root --password="$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < "$schema"

if [ "$engine" = "tfs_10" ] && [ -f /demo-data-tfs_10.sql ]; then
	echo "[znotex] Importing TFS_10 demo accounts/players/guild."
	mysql --user=root --password="$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /demo-data-tfs_10.sql
else
	echo "[znotex] No demo game data for engine '$engine' - the database will have ZnoteX's own tables only, no demo accounts/characters."
fi

echo "[znotex] Importing ZnoteX's own schema."
mysql --user=root --password="$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /znote-schema.sql

if [ "$engine" = "tfs_10" ]; then
	echo "[znotex] Activating the demo account."
	mysql --user=root --password="$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" \
		-e "UPDATE znote_accounts SET active = 1, active_email = 1 WHERE account_id = 1;"
fi
