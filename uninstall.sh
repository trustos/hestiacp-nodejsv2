#!/bin/bash

BLUE="\e[34m"
GREEN="\e[32m"
RED="\e[31m"
ENDCOLOR="\e[0m"
START="[${RED}JLFdzDev/${ENDCOLOR}${BLUE}hestiacp-nodejs${ENDCOLOR}]"

echo -e "${RED}Uninstalling HestiaCP NodeJS...${ENDCOLOR}"

# Remove QuickInstall App
sudo rm -rf /usr/local/hestia/web/src/app/WebApp/Installers/NodeJs
echo -e "${START} Removed QuickInstall App ✅"

# Remove Templates
sudo rm /usr/local/hestia/data/templates/web/nginx/NodeJS.tpl
sudo rm /usr/local/hestia/data/templates/web/nginx/NodeJS.stpl
echo -e "${START} Removed Templates ✅"

# Remove PM2 manager
sudo rm /usr/local/hestia/bin/v-add-pm2-app
echo -e "${START} Removed PM2 manager from /usr/local/hestia/bin ✅"

# Optional: Remove any configuration directories created during use
# Note: This might delete user data, so use with caution
# sudo rm -rf /home/*/hestiacp_nodejs_config

echo -e "${GREEN}HestiaCP NodeJS has been uninstalled.${ENDCOLOR}"
echo -e "${RED}Note: This script does not remove any NodeJS applications or PM2 processes that were created.${ENDCOLOR}"
echo -e "${RED}You may need to manually stop and remove any running PM2 processes.${ENDCOLOR}"
