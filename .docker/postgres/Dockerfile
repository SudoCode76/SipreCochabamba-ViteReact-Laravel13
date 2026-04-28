FROM postgres:16-bookworm

COPY init/10-restore-backup.sh /docker-entrypoint-initdb.d/10-restore-backup.sh

RUN sed -i 's/\r$//' /docker-entrypoint-initdb.d/10-restore-backup.sh \
    && chmod +x /docker-entrypoint-initdb.d/10-restore-backup.sh
