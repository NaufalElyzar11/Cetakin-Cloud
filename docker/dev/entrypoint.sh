#!/bin/sh
set -eu
mkdir -p storage/app/private storage/framework/cache/data storage/framework/cache/locks \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
exec "$@"
