#!/bin/bash

set -e  # Exit immediately if a command exits with a non-zero status

BLUE="\e[34m"
GREEN="\e[32m"
RED="\e[31m"
YELLOW="\e[33m"
ENDCOLOR="\e[0m"
START="[${GREEN}trustos/${ENDCOLOR}${BLUE}hestiacp-nodejs${ENDCOLOR}]"

echo -e "${GREEN}       ______                __
      /_  __/______  _______/ /_____  _____
       / / / ___/ / / / ___/ __/ __ \/ ___/
      / / / /  / /_/ (__  ) /_/ /_/ (__  )
     /_/ /_/   \__,_/____/\__/\____/____/${ENDCOLOR}"

echo -e "${BLUE}┬ ┬┌─┐┌─┐┌┬┐┬┌─┐┌─┐┌─┐   ┌┐┌┌─┐┌┬┐┌─┐ ┬┌─┐ v2
├─┤├┤ └─┐ │ │├─┤│  ├─┘───││││ │ ││├┤  │└─┐
┴ ┴└─┘└─┘ ┴ ┴┴ ┴└─┘┴     ┘└┘└─┘─┴┘└─┘└┘└─┘${ENDCOLOR}"

echo -e "───────────────────────────────────────────────"
echo -e "${YELLOW}This project is a fork of 'hestiacp-nodejs' by JLFdzDev${ENDCOLOR}"
echo -e "${YELLOW}Original project: https://github.com/JLFdzDev/hestiacp-nodejs${ENDCOLOR}"
echo -e "${YELLOW}Licensed under GNU General Public License v3.0 (GPL-3.0)${ENDCOLOR}"
echo -e "───────────────────────────────────────────────"

# Function to handle errors
handle_error() {
    echo -e "${RED}Error: $1${ENDCOLOR}"
    exit 1
}

# Copy QuickInstall App
sudo cp -r quickinstall-app/NodeJs /usr/local/hestia/web/src/app/WebApp/Installers/ || handle_error "Failed to copy QuickInstall App"
echo -e "${START} Copy QuickInstall App ✅"

# Copy Templates
sudo cp templates/* /usr/local/hestia/data/templates/web/nginx || handle_error "Failed to copy templates"
echo -e "${START} Copy Templates ✅"

# Change permissions
sudo chmod 644 /usr/local/hestia/data/templates/web/nginx/NodeJS.tpl || handle_error "Failed to change permissions for NodeJS.tpl"
sudo chmod 644 /usr/local/hestia/data/templates/web/nginx/NodeJS.stpl || handle_error "Failed to change permissions for NodeJS.stpl"

sudo chmod -R 644 /usr/local/hestia/web/src/app/WebApp/Installers/NodeJs/ || handle_error "Failed to change permissions for NodeJs directory"
sudo chmod 755 /usr/local/hestia/web/src/app/WebApp/Installers/NodeJs || handle_error "Failed to change permissions for NodeJs directory"
sudo chmod 755 /usr/local/hestia/web/src/app/WebApp/Installers/NodeJs/NodeJsUtils || handle_error "Failed to change permissions for NodeJsUtils"
sudo chmod 755 /usr/local/hestia/web/src/app/WebApp/Installers/NodeJs/templates || handle_error "Failed to change permissions for templates directory"
sudo chmod 755 /usr/local/hestia/web/src/app/WebApp/Installers/NodeJs/templates/nginx || handle_error "Failed to change permissions for nginx directory"
sudo chmod 755 /usr/local/hestia/web/src/app/WebApp/Installers/NodeJs/templates/web || handle_error "Failed to change permissions for web directory"
echo -e "${START} Templates and QuickInstall App Permissions changed ✅"

# Add pm2 manager
sudo cp bin/v-add-pm2-app /usr/local/hestia/bin || handle_error "Failed to copy v-add-pm2-app"
sudo chmod 755 /usr/local/hestia/bin/v-add-pm2-app || handle_error "Failed to change permissions for v-add-pm2-app"
sudo cp bin/v-add-nvm-nodejs /usr/local/hestia/bin || handle_error "Failed to copy v-add-nvm-nodejs"
sudo chmod 755 /usr/local/hestia/bin/v-add-nvm-nodejs || handle_error "Failed to change permissions for v-add-nvm-nodejs"

echo -e "${START} Add pm2 manager to /usr/local/hestia/bin ✅"

echo -e "${GREEN}Installation completed successfully!${ENDCOLOR}"
