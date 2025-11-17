#!/bin/bash
echo "Starting staging deployment..."
git pull origin dev
composer install --ignore-platform-reqs

echo "Building assets..."
cd app/design/frontend/DieMayrei/hyva_child
npm ci
npm run prod
cd -

echo "Starting Magento deployment..."
bin/magento setup:upgrade -n
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f --jobs=$(nproc)
bin/magento cache:flush