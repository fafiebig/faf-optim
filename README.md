# DEPRECATED - DO NOT USE IT ANY LONGER!


# faf-optim

Wordpress Plugin for Image Optimization.
You have to install JPEGOptim and OptiPNG on serverside.


# Installation

* Unzip and upload the plugin to the **/wp-content/plugins/** directory
* Activate the plugin in WordPress
* Got to plugins page and use the optimization.

# Installation with composer

* Add the repo to your composer.json

```json

"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/fafiebig/faf-optim.git"
    }
],

```

* require the package with composer

```shell

composer require fafiebig/faf-optim 1.*

```
