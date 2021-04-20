# CloudCone Partner WHMCS Module

Our WHMCS Partner Account module provides an automated solution for provisioning compute servers through WHMCS allowing you to offer them to your clients. The module allows you to provision servers on your [Partner account](https://cloudcone.com/vps-reseller-platform)

## Prerequisites

Before setting up the module on your WHMCS server, you need to first generate an API key from CloudCone's client area. To do this;

Log in and visit the [API Settings](https://app.cloudcone.com/user/api) page.

![](https://storage.crisp.chat/users/helpdesk/website/2d4cd5516f670e00/screenshot3_skii3r.png)

Generate an API key pair by clicking on the Generate new button and enter a name for you to identify your key.

![](https://storage.crisp.chat/users/helpdesk/website/b4a6582f-f407-4054-b73c-d6e4bf698b1e/41921562-b130-4689-af86-8c4cb0273441.jpg)
The generated key will be displayed to you on the screen. We'll use these later when setting up the server on WHMCS.


## Installation

1. Download the latest release of the CloudCone WHMCS Module.
2. Unzip and upload the `cloudcpartner` directory from the downloaded zip file to the `/modules/servers` directory on your WHMCS installation.


## Setting up WHMCS

1. Log in to the admin dashboard of your WHMCS server and navigate to Tools > App & Integration and search for CloudCone Partner.
2. On the server details section, select CloudCone Partner as the Module, and enter the host name as ` api.cloudcone.com`. Your Partner Account ID as the Username, The API key as the password, and the API hash as the Hash (these are the ones we geenrated at the begining of this guide.

![](https://storage.crisp.chat/users/helpdesk/website/2d4cd5516f670e00/screenshot1_18hh8l.png)

3. Navigate again to Tools > App & Integration and search for CloudCone Partner and create a new product.
4. Select **Other** as the product type.
5. Under Module Settings for the product, select CloudCone Partner for the Module Name.
6. Enter the configuration for the resources of your product and **save your changes**. 

![](https://storage.crisp.chat/users/helpdesk/website/2d4cd5516f670e00/screenshot2_qu1k0n.png)

7. Generate the required configurable options and the custom fields by clicking on the Generate button. Make sure to save any changes you have made to the product before clicking this.

8. Enter the other details of your product such as the price and disable domain registration. 

You're now ready to start selling your first CloudCone server on your own platform

