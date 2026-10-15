#!/bin/sh
set -eu
# Only recognizable disposable local credentials; never a production IAM setup.
case "$CETAKIN_DEV_WORKTREE_ID" in
  *[!0-9a-f]*|'') echo 'Invalid local worktree identity.' >&2; exit 1 ;;
esac
[ "${#CETAKIN_DEV_WORKTREE_ID}" -eq 12 ]
[ "$CETAKIN_DEV_PROJECT" = "cetakin-$CETAKIN_DEV_WORKTREE_ID" ]
umask 077
cat > /tmp/cetakin-s3.json <<EOF
{
  "identities": [
    {"name":"synthetic-bootstrap", "credentials":[{"accessKey":"$CETAKIN_DEV_PROJECT-bootstrap","secretKey":"$CETAKIN_DEV_PROJECT-bootstrap-synthetic-only"}], "actions":["Admin"]},
    {"name":"synthetic-dev", "credentials":[{"accessKey":"$CETAKIN_DEV_PROJECT-dev","secretKey":"$CETAKIN_DEV_PROJECT-dev-synthetic-only"}], "actions":["Read:cetakin-dev-$CETAKIN_DEV_WORKTREE_ID","Write:cetakin-dev-$CETAKIN_DEV_WORKTREE_ID","List:cetakin-dev-$CETAKIN_DEV_WORKTREE_ID"]},
    {"name":"synthetic-test", "credentials":[{"accessKey":"$CETAKIN_DEV_PROJECT-test","secretKey":"$CETAKIN_DEV_PROJECT-test-synthetic-only"}], "actions":["Read:cetakin-test-$CETAKIN_DEV_WORKTREE_ID","Write:cetakin-test-$CETAKIN_DEV_WORKTREE_ID","List:cetakin-test-$CETAKIN_DEV_WORKTREE_ID"]},
    {"name":"synthetic-readonly", "credentials":[{"accessKey":"$CETAKIN_DEV_PROJECT-readonly","secretKey":"$CETAKIN_DEV_PROJECT-readonly-synthetic-only"}], "actions":["Read:cetakin-test-$CETAKIN_DEV_WORKTREE_ID","List:cetakin-test-$CETAKIN_DEV_WORKTREE_ID"]}
  ]
}
EOF
# Internal unauthenticated master/filer/volume listeners stay on this container's
# loopback. Only the authenticated S3 gateway is reachable on the project network.
exec weed server -dir=/data -ip=127.0.0.1 -ip.bind=127.0.0.1 \
  -master.peers=none -master.telemetry=false -master.volumeSizeLimitMB=64 \
  -master.vacuumIntervalSeconds=0 -volume.max=8 -volume.preStopSeconds=1 \
  -filer -filer.exposeDirectoryData=false -s3 -s3.ip.bind=0.0.0.0 \
  -s3.config=/tmp/cetakin-s3.json -s3.port.iceberg=0 -s3.port.lance=0 \
  -s3.iam=false -s3.autoCreateBucket=false -s3.allowDeleteBucketNotEmpty=false
