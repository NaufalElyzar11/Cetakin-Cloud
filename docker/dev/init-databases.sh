#!/bin/sh
set -eu

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres \
    --set=dev_password="$DEV_DB_PASSWORD" --set=test_password="$DEV_TEST_PASSWORD" <<'SQL'
CREATE ROLE cetakin_dev LOGIN PASSWORD :'dev_password' NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE ROLE cetakin_test LOGIN PASSWORD :'test_password' NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE DATABASE cetakin_dev OWNER cetakin_dev;
CREATE DATABASE cetakin_test OWNER cetakin_test;
REVOKE CONNECT ON DATABASE cetakin_dev FROM PUBLIC;
REVOKE CONNECT ON DATABASE cetakin_test FROM PUBLIC;
GRANT CONNECT ON DATABASE cetakin_dev TO cetakin_dev;
GRANT CONNECT ON DATABASE cetakin_test TO cetakin_test;
SQL

# Restrict network logins to the corresponding database, before default rules.
# Admin is used only over the container-local Unix socket for initialization.
{
    printf '%s\n' \
        'host cetakin_dev cetakin_dev 0.0.0.0/0 scram-sha-256' \
        'host cetakin_test cetakin_test 0.0.0.0/0 scram-sha-256' \
        'host all all 0.0.0.0/0 reject' \
        'host cetakin_dev cetakin_dev ::/0 scram-sha-256' \
        'host cetakin_test cetakin_test ::/0 scram-sha-256' \
        'host all all ::/0 reject'
    cat "$PGDATA/pg_hba.conf"
} > "$PGDATA/pg_hba.conf.cetakin"
mv "$PGDATA/pg_hba.conf.cetakin" "$PGDATA/pg_hba.conf"
