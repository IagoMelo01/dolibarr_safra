# SAFRA FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Features

Safra adds crop and satellite monitoring tools inside Dolibarr. Main features include:

* **Crop management** – register farms, fields and cultivars and track planting or harvest events.
* **Vegetation indexes** – retrieve NDVI, NDWI and other bands from the Sentinel Hub service to monitor crop vigour.
* **Interactive maps** – draw parcels directly on maps using Leaflet based tools, measure areas and visualize satellite layers.

Below is a simple view of the module interface:

<!--
![Screenshot safra](img/screenshot_safra.png?raw=true "Safra"){imgmd}
-->

### Basic usage
1. Enable the module from **Setup -> Modules**.
2. Create farms and fields using the Safra menu.
3. Draw field boundaries on the map and save them.
4. Open a field card and use the NDVI/NDWI buttons to fetch satellite data.


Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Translations

Translations can be completed manually by editing files into directories *langs*.

<!--
This module contains also a sample configuration for Transifex, under the hidden directory [.tx](.tx), so it is possible to manage translation using this service.

For more information, see the [translator's documentation](https://wiki.dolibarr.org/index.php/Translator_documentation).

There is a [Transifex project](https://transifex.com/projects/p/dolibarr-module-template) for this module.
-->


## Installation

Prerequisites: You must have the Dolibarr ERP CRM software installed. You can down it from [Dolistore.org](https://www.dolibarr.org).
You can also get a ready to use instance in the cloud from htts://saas.dolibarr.org


### From the ZIP file and GUI interface

If the module is a ready to deploy zip file, so with a name module_xxx-version.zip (like when downloading it from a market place like [Dolistore](https://www.dolistore.com)),
go into menu ```Home - Setup - Modules - Deploy external module``` and upload the zip file.

Note: If this screen tell you that there is no "custom" directory, check that your setup is correct:

<!--

- In your Dolibarr installation directory, edit the ```htdocs/conf/conf.php``` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading ```//```) and assign a sensible value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```
-->

<!--

### From a GIT repository

Clone the repository in ```$dolibarr_main_document_root_alt/safra```

```sh
cd ....../custom
git clone git@github.com:gitlogin/safra.git safra
```

-->


From your browser:

  - Log into Dolibarr as a super-administrator
  - Go to "Setup" -> "Modules"
  - You should now be able to find and enable the module
After the module is enabled, open **Setup -> Safra** to configure API keys.

### API configuration
1. Create an account at [Sentinel Hub](https://www.sentinel-hub.com/) and obtain an API key.
2. Set this key into the `SAFRA_API_SENTINELHUB` field of the Safra setup page.
3. (Optional) Generate public and private keys for the Embrapa services and fill
   `SAFRA_API_EMBRAPA_PUBLIC` and `SAFRA_API_EMBRAPA_PRIVATE`.




## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readmes are licensed under GFDL.
