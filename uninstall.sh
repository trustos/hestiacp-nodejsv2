#!/bin/bash

set -e  # Exit immediately if a command exits with a non-zero status

BLUE="\e[34m"
GREEN="\e[32m"
RED="\e[31m"
YELLOW="\e[33m"
ENDCOLOR="\e[0m"
START="[${GREEN}trustos/${ENDCOLOR}${BLUE}hestiacp-nodejs${ENDCOLOR}]"

echo -e "${RED}       ______                __
      /_  __/______  _______/ /_____  _____
       / / / ___/ / / / ___/ __/ __ \/ ___/
      / / / /  / /_/ (__  ) /_/ /_/ (__  )
     /_/ /_/   \__,_/____/\__/\____/____/${ENDCOLOR}"

echo -e "${BLUE}┬ ┬┌─┐┌─┐┌┬┐┬┌─┐┌─┐┌─┐   ┌┐┌┌─┐┌┬┐┌─┐ ┬┌─┐ v2
├─┤├┤ └─┐ │ │├─┤│  ├─┘───││││ │ ││├┤  │└─┐
┴ ┴└─┘└─┘ ┴ ┴┴ ┴└─┘┴     ┘└┘└─┘─┴┘└─┘└┘└─┘${ENDCOLOR}"

echo -e "───────────────────────────────────────────────"
echo -e "${YELLOW}Uninstalling HestiaCP NodeJS${ENDCOLOR}"
echo -e "───────────────────────────────────────────────"

# Function to handle errors
handle_error() {
    echo -e "${RED}Error: $1${ENDCOLOR}"
    exit 1
}

# Remove QuickInstall App
sudo rm -rf /usr/local/hestia/web/src/app/WebApp/Installers/NodeJs || handle_error "Failed to remove QuickInstall App"
echo -e "${START} Removed QuickInstall App ✅"

# Remove Templates
sudo rm /usr/local/hestia/data/templates/web/nginx/NodeJS.tpl || handle_error "Failed to remove NodeJS.tpl"
sudo rm /usr/local/hestia/data/templates/web/nginx/NodeJS.stpl || handle_error "Failed to remove NodeJS.stpl"
echo -e "${START} Removed Templates ✅"

# Remove pm2 manager
sudo rm /usr/local/hestia/bin/v-add-pm2-app || handle_error "Failed to remove v-add-pm2-app"
echo -e "${START} Removed pm2 manager from /usr/local/hestia/bin ✅"
sudo rm /usr/local/hestia/bin/v-add-nvm-nodejs || handle_error "Failed to remove v-add-nvm-nodejs"
echo -e "${START} Removed nvm manager from /usr/local/hestia/bin ✅"
sudo rm /usr/local/hestia/bin/v-add-npm-install || handle_error "Failed to remove v-add-npm-install"
echo -e "${START} Removed npm install script from /usr/local/hestia/bin ✅"
sudo rm /usr/local/hestia/bin/v-list-pm2-logs || handle_error "Failed to remove v-list-pm2-logs"
echo -e "${START} Removed list logs for pm2 script from /usr/local/hestia/bin ✅"

echo -e "${GREEN}Uninstallation completed successfully!${ENDCOLOR}"
echo -e "${YELLOW}Note: This script does not remove any Node.js applications or PM2 processes that may have been created.${ENDCOLOR}"
echo -e "${YELLOW}Please manage those separately if needed.${ENDCOLOR}"
