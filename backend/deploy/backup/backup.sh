#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

required=(DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD AWS_BUCKET BACKUP_AGE_PUBLIC_KEY)
for name in "${required[@]}"; do
    if [[ -z "${!name:-}" ]]; then
        echo "Missing required environment variable: ${name}" >&2
        exit 2
    fi
done

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
prefix="${BACKUP_PREFIX:-backups/civin}"
prefix="${prefix#/}"
prefix="${prefix%/}"
archive="civin-${timestamp}.sql.gz.age"
checksum="${archive}.sha256"
destination="s3://${AWS_BUCKET}/${prefix}/database/${archive}"
endpoint_args=()

if [[ -n "${AWS_ENDPOINT:-}" ]]; then
    endpoint_args+=(--endpoint-url "${AWS_ENDPOINT}")
fi

cleanup() {
    rm -f "/work/${archive}" "/work/${checksum}"
}
trap cleanup EXIT

export MYSQL_PWD="${DB_PASSWORD}"
mysql_ssl_args=()
if [[ -n "${MYSQL_ATTR_SSL_CA:-}" && -r "${MYSQL_ATTR_SSL_CA}" ]]; then
    mysql_ssl_args+=(--ssl-ca="${MYSQL_ATTR_SSL_CA}")
fi

case "${DB_CONNECTION:-mysql}" in
    mysql|mariadb)
        mariadb-dump \
            --host="${DB_HOST}" \
            --port="${DB_PORT:-3306}" \
            --user="${DB_USERNAME}" \
            "${mysql_ssl_args[@]}" \
            --single-transaction \
            --quick \
            --routines \
            --events \
            --triggers \
            --hex-blob \
            --databases "${DB_DATABASE}"
        ;;
    pgsql)
        export PGPASSWORD="${DB_PASSWORD}"
        export PGSSLMODE="${DB_SSLMODE:-require}"
        pg_dump \
            --host="${DB_HOST}" \
            --port="${DB_PORT:-5432}" \
            --username="${DB_USERNAME}" \
            --dbname="${DB_DATABASE}" \
            --clean \
            --if-exists \
            --no-owner \
            --no-privileges
        ;;
    *)
        echo "Unsupported DB_CONNECTION: ${DB_CONNECTION}" >&2
        exit 2
        ;;
esac | gzip -9 \
    | age --recipient "${BACKUP_AGE_PUBLIC_KEY}" --output "/work/${archive}"

(
    cd /work
    sha256sum "${archive}" > "${checksum}"
)

aws "${endpoint_args[@]}" s3 cp "/work/${archive}" "${destination}" --only-show-errors
aws "${endpoint_args[@]}" s3 cp "/work/${checksum}" "${destination}.sha256" --only-show-errors

retention_days="${BACKUP_RETENTION_DAYS:-30}"
if [[ "${retention_days}" =~ ^[0-9]+$ ]] && (( retention_days > 0 )); then
    cutoff="$(date -u -d "${retention_days} days ago" +%Y-%m-%dT%H:%M:%SZ)"
    while IFS= read -r key; do
        [[ -z "${key}" || "${key}" == "None" ]] && continue
        aws "${endpoint_args[@]}" s3 rm "s3://${AWS_BUCKET}/${key}" --only-show-errors
    done < <(
        aws "${endpoint_args[@]}" s3api list-objects-v2 \
            --bucket "${AWS_BUCKET}" \
            --prefix "${prefix}/database/" \
            --query "Contents[?LastModified<=\`${cutoff}\`].Key" \
            --output text | tr '\t' '\n'
    )
fi

echo "Encrypted database backup uploaded to ${destination}"
