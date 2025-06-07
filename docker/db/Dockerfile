FROM mysql:9.3.0

COPY init.sql /docker-entrypoint-initdb.d/
COPY import-data.sh /docker-entrypoint-initdb.d/

RUN chmod +x /docker-entrypoint-initdb.d/import-data.sh

RUN apt-get update && apt-get install -y wget && rm -rf /var/lib/apt/lists/*