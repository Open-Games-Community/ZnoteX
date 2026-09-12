#!/bin/sh
set -e

ROOT=/var/www/html

quote() {
	printf "%s" "$1" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g"
}

cat > "$ROOT/config.local.php" <<PHP
<?php
/**
 * Written by the ZnoteX Docker entrypoint on every container start.
 * Edit docker-compose.yml environment values, not this file - it is
 * regenerated each time the container boots.
 */

\$config['sqlHost']     = '$(quote "${ZNOTE_DB_HOST:-db}")';
\$config['sqlUser']     = '$(quote "${ZNOTE_DB_USER:-znotex}")';
\$config['sqlPassword'] = '$(quote "${ZNOTE_DB_PASSWORD:-znotex}")';
\$config['sqlDatabase'] = '$(quote "${ZNOTE_DB_NAME:-znotex}")';

\$config['ServerEngine'] = '$(quote "${ZNOTE_SERVER_ENGINE:-TFS_10}")';
\$config['site_title']   = '$(quote "${ZNOTE_SITE_TITLE:-ZnoteX}")';
\$config['site_url']     = '$(quote "${ZNOTE_SITE_URL:-http://localhost:8080}")';

\$config['mailserver'] = array(
	'register'                 => true,
	'accountRecovery'          => true,
	'myaccount_verify_email'   => true,
	'verify_email_points'      => 0,
	'host'                     => 'mailpit',
	'securityType'             => '',
	'port'                     => 1025,
	'email'                    => 'noreply@znotex.local',
	'username'                 => '',
	'password'                 => '',
	'debug'                    => false,
	'fromName'                 => \$config['site_title'],
);

// Admin access is granted by account name (not character name).
\$config['page_admin_access'] = array(
	'$(quote "${ZNOTE_ADMIN_ACCOUNT:-demo}")',
);
PHP

mkdir -p "$ROOT/install"
if [ ! -f "$ROOT/install/installed.lock" ]; then
	printf "Installed by the ZnoteX Docker entrypoint on %s\nDelete this file only if you mean to run the installer again.\n" "$(date '+%Y-%m-%d %H:%M:%S')" > "$ROOT/install/installed.lock"
fi

mkdir -p "$ROOT/engine/cache" "$ROOT/engine/img/theme"
chown -R www-data:www-data "$ROOT/engine/cache" "$ROOT/engine/img/theme" "$ROOT/config.local.php" 2>/dev/null || true

exec "$@"
