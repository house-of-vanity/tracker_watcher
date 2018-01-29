#!/bin/bash
mysqldump --login-path=localhost rutracker_bot --no-data | \
sed -e 's/AUTO_INCREMENT=.* /AUTO_INCREMENT=1 /g' -e 's/latin1/utf8/g' > schema.sql 
