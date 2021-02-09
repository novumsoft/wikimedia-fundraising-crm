#!/bin/bash -eu

BASEDIR=$(dirname "$0")
# shellcheck source=ci-settings.sh
. $BASEDIR/ci-settings.sh

echo "Creating databases with the prefix '${CIVICRM_SCHEMA_PREFIX}'"

for i in 1 2 3; do
	mysql -u root <<EOS
	drop database if exists ${CIVICRM_SCHEMA_PREFIX}${i};
	create database ${CIVICRM_SCHEMA_PREFIX}${i} CHARACTER SET utf8 COLLATE utf8_general_ci;
	grant all on ${CIVICRM_SCHEMA_PREFIX}${i}.* to '${CIVICRM_MYSQL_USERNAME}'@'${CIVICRM_MYSQL_CLIENT}' identified by '${CIVICRM_MYSQL_PASSWORD}';
EOS
done

echo "Creating fredge database"

mysql -u root <<EOS
create database fredge;
grant all on fredge.* to '${CIVICRM_MYSQL_USERNAME}'@'${CIVICRM_MYSQL_CLIENT}' identified by '${CIVICRM_MYSQL_PASSWORD}';
EOS
