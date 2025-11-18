#!/bin/bash
echo "Starting staging deployment..."
git pull origin dev
composer install --ignore-platform-reqs

echo "Building assets..."
cd app/design/frontend/DieMayrei/hyva_child/web/tailwind
npm ci
npm run build
cd -

echo "Starting Magento deployment..."
bin/magento setup:upgrade -n
bin/magento cache:flush