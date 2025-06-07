#!/bin/bash
set -e

echo "Téléchargement du dataset OMDb..."
wget -q -O /tmp/title.basics.tsv.gz https://datasets.imdbws.com/title.basics.tsv.gz

echo "Décompression..."
gunzip -f /tmp/title.basics.tsv.gz

echo "Importation dans MySQL (cela peut prendre plusieurs minutes)..."

mysql -u root -p$MYSQL_ROOT_PASSWORD <<EOF
USE filmdb;

SET GLOBAL local_infile=1;

LOAD DATA LOCAL INFILE '/tmp/title.basics.tsv'
INTO TABLE films
CHARACTER SET utf8mb4
FIELDS TERMINATED BY '\t'
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(@tconst, @titleType, @primaryTitle, @originalTitle, @isAdult, @startYear, @endYear, @runtimeMinutes, @genres)
SET tconst = @tconst
WHERE @titleType = 'movie';
EOF

echo "Importation terminée avec succès!"

rm /tmp/title.basics.tsv