# HestiaCPv2 - add multiple NodeJS apps using QuickApp Installer.

This project is a fork of 'hestiacp-nodejs' by JLFdzDev
Original project: https://github.com/JLFdzDev/hestiacp-nodejs

Copyright (C) [2023] JLFdzDev
Copyright (C) [2024] [Trustos]

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.

Significant changes from the original project:
- Removed option to select Node version, now exclusively supporting NVM
- Added automatic startup for PM2
- Improved .env file handling to prevent overwriting and enhance security

## Description

You can add multiple websites to your HestiaCP using different ports for each one.

When you create the app with the installer it automatically creates:
* **/home/%USER%/%DOMAIN%/private/nodeapp** directory
* Config for **nginx** to use the selected port
* **ecosystem.config.js** with the necessary command to connect **pm2** and run your app Ex. `npm run start`
* **.nvmrc** file with node version (NVM is required)


## Requirements
1. Install [PM2](https://pm2.keymetrics.io/) globally (recommended)
  ```bash
  npm install pm2 -g
  ```

## Quick Install

```bash
wget -qO- https://raw.githubusercontent.com/trustos/hestiacp-nodejsv2/refs/heads/main/quickinstall-ubuntu.sh | sudo bash
```

## Manual Install

1. Install [NVM](https://github.com/nvm-sh/nvm#installing-and-updating)
2. Clone this repository:
	```bash
	cd ~/tmp
	git clone https://github.com/trustos/hestiacp-nodejs-v2.git
	cd hestiacp-nodejs-v2
	```

4. Use **install.sh**
	```bash
	sudo chmod 755 install.sh
	sudo ./install.sh
	```

5. 🚀 You are ready to install an App!!!

## How to use

1. Create new **user** (If you have one no need to create)
2. User needs bash access for app to work, go to **User edit** > **advanced options** > **SSH Access** > **bash**
3. **Add** new web (Ex. acme.com)
4. Go to **edit** this new web and go to **Quick Install App**
5. Select **NodeJS**
   * **Start Script**: It creates a `ecosystem.config.js` file in root of nodeapp with the script that you fill (it should be the one you have in your `package.json`) for PM2 to manage the app.

   * **Port**: You can manage multiple apps with different ports, put different port for each app you have (Ex. 3000).
   It creates `.env` file in root of nodeapp with the selected port, without overwriting existing variables.

   * **PHP Version**: This is only for HestiaCP you can put any value (**NOT IMPORTANT**)
6. Go to Edit web > Advanced Options > Proxy Template > NodeJS
7. Upload your app with filemanager, clone with git... in `/home/<user>/<domain.com>/private/nodeapp`
8. PM2 will automatically start your app and set up to run on system startup
9. 🎉 Congratulations you're done!!!

## FAQ

### How to change the port if i have a web running

First change proxy template to default, reconfigure the app using the QuickInstall and finally change the proxy template to NodeJS.

### I want to remove the domain

Remove it normally, open the filemanager and remove hestiacp_nodejs_config/web/<domain.com>.
