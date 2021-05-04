#!/bin/bash -eu

BASEDIR=$(dirname "$0")
# shellcheck source=ci-settings.sh
. $BASEDIR/ci-settings.sh

echo "Populating databases with the prefix '${CIVICRM_SCHEMA_PREFIX}'"

export PRECREATED_DSN_PATTERN="mysql://${CIVICRM_MYSQL_USERNAME}:${CIVICRM_MYSQL_PASSWORD}@${CIVICRM_MYSQL_CLIENT}:/${CIVICRM_SCHEMA_PREFIX}{{db_seq}}"

if [ "${WORKSPACE}" != "" ]; then
  AMPHOME="${WORKSPACE}/.amp-${BUILD_NUMBER}"
else
  # CI with Docker container. Does not use WORKSPACE, lacks a user home
  # directory and does not need to vary on BUILD_NUMBER.
  AMPHOME="${XDG_CONFIG_HOME}/amp"
fi
echo "AMPHOME set to: '${AMPHOME}'"
export AMPHOME

export NO_SAMPLE_DATA=1

# CI lacks sendmail and Drupal install would fails without it. drush can pass
# extra options to PHP via PHP_OPTIONS - T171724
PHP_OPTIONS="-d sendmail_path=$(which true)"
export PHP_OPTIONS

#FIXME: --web-root="$WORKSPACE/src/crm"

CIVICRM_BUILDKIT="${WORKSPACE}/src/wikimedia/fundraising/civicrm-buildkit"
if [ -d /src/wikimedia/fundraising/crm/civicrm-buildkit ]; then
  # For CI Docker container
  CIVICRM_BUILDKIT=/src/wikimedia/fundraising/crm/civicrm-buildkit
fi

"$CIVICRM_BUILDKIT"/bin/civi-download-tools

"$CIVICRM_BUILDKIT"/bin/amp config:set \
	--httpd_type=none \
	--perm_type=none

"$CIVICRM_BUILDKIT"/bin/amp config:set --mysql_dsn=mysql://root@127.0.0.1:3306

rm -rf "$CIVICRM_BUILDKIT"/build/wmff
mkdir -p "$CIVICRM_BUILDKIT"/build
ln -s "$WORKSPACE"/src/wikimedia/fundraising/crm "$CIVICRM_BUILDKIT"/build/wmff

"$CIVICRM_BUILDKIT"/bin/civibuild reinstall wmff
