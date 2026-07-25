FROM alpine:3.22

RUN apk add --no-cache \
      age \
      aws-cli \
      bash \
      ca-certificates \
      coreutils \
      gzip \
      mariadb-client \
      postgresql-client

COPY deploy/backup/backup.sh /usr/local/bin/civin-backup
COPY deploy/backup/restore.sh /usr/local/bin/civin-restore

RUN chmod 0555 /usr/local/bin/civin-backup /usr/local/bin/civin-restore \
    && addgroup -S civin \
    && adduser -S -G civin -h /work civin \
    && install -d -o civin -g civin -m 0700 /work

USER civin
WORKDIR /work

ENTRYPOINT ["civin-backup"]
