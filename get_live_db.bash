#!/bin/bash

ssh -p 21984 root@shop.hk-verlag.de mysqldump hkshop > hk_shop-live.sql
ddev import-db --file=hk_shop-live.sql
ddev magento setup:upgrade
ddev magento module:disable Magento_TwoFactorAuth
ddev magento cache:flush
ddev desc