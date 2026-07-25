#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 ]]; then
    echo "Usage: civin-restore s3://bucket/path/to/backup.sql.gz.age" >&2
    exit 2
fi

required=(DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD BACKUP_AGE_PRIVATE_KEY)
for name in "${required[@]}"; do
    if [[ -z "${!name:-}" ]]; then
        echo "Missing required environment variable: ${name}" >&2
        exit 2
    fi
done

source_uri="$1"
archive="/work/restore.sql.gz.age"
checksum="${archive}.sha256"
identity="/work/restore.agekey"
endpoint_args=()

if [[ -n "${AWS_ENDPOINT:-}" ]]; then
    endpoint_args+=(--endpoint-url "${AWS_ENDPOINT}")
fi

cleanup() {
    rm -f "${archive}" "${checksum}" "${identity}"
}
trap cleanup EXIT

printf '%s\n' "${BACKUP_AGE_PRIVATE_KEY}" > "${identity}"
aws "${endpoint_args[@]}" s3 cp "${source_uri}" "${archive}" --only-show-errors
aws "${endpoint_args[@]}" s3 cp "${source_uri}.sha256" "${checksum}" --only-show-errors

expected="$(cut -d' ' -f1 "${checksum}")"
actual="$(sha256sum "${archive}" | cut -d' ' -f1)"
if [[ "${expected}" != "${actual}" ]]; then
    echo "Backup checksum verification failed." >&2
    exit 1
fi

if [[ "${RESTORE_CONFIRM_DATABASE:-}" != "${DB_DATABASE}" ]]; then
    echo "Refusing restore: set RESTORE_CONFIRM_DATABASE=${DB_DATABASE}" >&2
    exit 3
fi

export MYSQL_PWD="${DB_PASSWORD}"
mysql_ssl_args=()
if [[ -n "${MYSQL_ATTR_SSL_CA:-}" && -r "${MYSQL_ATTR_SSL_CA}" ]]; then
    mysql_ssl_args+=(--ssl-ca="${MYSQL_ATTR_SSL_CA}")
fi
case "${DB_CONNECTION:-mysql}" in
    mysql|mariadb)
        restore_command=(
            mariadb
            "--host=${DB_HOST}"
            "--port=${DB_PORT:-3306}"
            "--user=${DB_USERNAME}"
            "${mysql_ssl_args[@]}"
        )
        ;;
    pgsql)
        export PGPASSWORD="${DB_PASSWORD}"
        export PGSSLMODE="${DB_SSLMODE:-require}"
        restore_command=(
            psql
            "--host=${DB_HOST}"
            "--port=${DB_PORT:-5432}"
            "--username=${DB_USERNAME}"
            "--dbname=${DB_DATABASE}"
            "--set=ON_ERROR_STOP=1"
        )
        ;;
    *)
        echo "Unsupported DB_CONNECTION: ${DB_CONNECTION}" >&2
        exit 2
        ;;
esac

age --decrypt --identity "${identity}" "${archive}" \
    | gzip -dc \
    | "${restore_command[@]}"

echo "Restore completed from ${source_uri}"
