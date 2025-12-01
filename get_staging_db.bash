#!/bin/bash

ssh -p 21984 root@staging.shop.hk-verlag.de mysqldump hk_shop > hk_shop-staging.sql
ddev import-db --file=hk_shop-staging.sql
ddev magento setup:upgrade
ddev magento module:disable Magento_TwoFactorAuth
ddev magento cache:flush
ddev desc