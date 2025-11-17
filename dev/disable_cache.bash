#!/usr/bin/env bash

set -euo pipefail

bin/magento cache:disable layout block_html full_page
