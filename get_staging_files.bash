#!/bin/bash

set -euo pipefail

STAGING_SSH_HOST=${STAGING_SSH_HOST:-hk-shop@staging.dlv-shop.de}
STAGING_SSH_PORT=${STAGING_SSH_PORT:-21984}
STAGING_REMOTE_ROOT=${STAGING_REMOTE_ROOT:-src}

RSYNC_BASE_ARGS=( -avz --delete --progress -e "ssh -p $STAGING_SSH_PORT" )

echo "Synchronisiere pub/media ..."
rsync "${RSYNC_BASE_ARGS[@]}" \
  --exclude='cache/' \
  --exclude='catalog/product/cache/' \
  --exclude='tmp/' \
  --exclude='customer/' \
  "$STAGING_SSH_HOST:$STAGING_REMOTE_ROOT/pub/media/" "pub/media/"