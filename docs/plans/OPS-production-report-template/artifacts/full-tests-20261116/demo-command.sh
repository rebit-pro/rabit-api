#!/bin/sh
set -e
cp -a /seed-node/. /app/node_modules/
./node_modules/.bin/start-server-and-test e2e:server http://127.0.0.1:4173 "npm run test:e2e -- --parallel 4 --format progress --format json:reports/e2e/cucumber-report.json --format html:reports/e2e/cucumber-report.html"
