![GitHub last commit](https://img.shields.io/github/last-commit/rubenperezlopez/almacil-php-core)
![GitHub tag (latest by date)](https://img.shields.io/github/v/tag/rubenperezlopez/almacil-php-core?label=last%20version)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/almacil/php-core)
![GitHub](https://img.shields.io/github/license/rubenperezlopez/almacil-php-core)
# ♥️ Almacil PHP Core

This is a **simple MVC framework** written in PHP for rapid development of small projects.

<br>

*Do you want to contribute?*<br>
[![Donate 1€](https://img.shields.io/badge/Buy%20me%20a%20coffee-1%E2%82%AC-brightgreen?logo=buymeacoffee&logoColor=white&labelColor=grey&style=for-the-badge
)](https://www.paypal.com/paypalme/rubenperezlopez/1?target=_blank)

## Features
- Routes
- Internationalization
- Database
- Cache
- HTTP Requests
- Minimum dependencies

## Installation
Installation is possible using Composer
```bash
composer require almacil/php-core
```
## Usage
Create an instance of \Almacil\App:
```php
// Require composer autoloader
require __DIR__ . '/vendor/autoload.php';

$rootDir = __DIR__;
$configFile = 'app.config.json';

// Create de instance
$app = new \Almacil\App($rootDir, $configFile);
```

## app.config.json example
```javascript
{
  "display_errors": true,
  "languages": [
    "es_ES",
    "en_GB"
  ],
  "timezone": "Europe/Madrid",

  "spa": {
    "enabled": true
  },
  "cache": {
    "enabled": true,
    "directory": "disk/cache",
    "time": 10
  },
  "database": {
    "enabled": true,
    "directory": "disk/database/",
    "extension": "json"
  },
  "http": { // Developing
    "enabled": true,
    "defaultPrefix": ""
  },
  "translate": {
    "enabled": true,
    "directory": "disk/i18n/",
    "findMissingTranslations": false
  },
  "router": {
    "enabled": true,
    "routesFile": "app.routes.json"
  },

  "environments": {
    "baseFile": "environments/base.json",
    "production": {
      "domain": "id.core.almacil.com",
      "propertiesFile": "environments/production.json"
    },
    "development": {
      "domain": "localhost:8000",
      "propertiesFile": "environments/development.json"
    }
  }
}
```

**display_errors**: true | false  
Con el valor en *true* se mostrarán los errores de PHP, útil para depuración. En producción se recomienda ponerlo en *false*.

**languages**: array  
Definimos un array con los idiomas válidos para nuestra aplicación web. Almacil Core recupera el idioma del navegador del usuario y, si es necesario, lo redirecciona para poner la abreviación del idioma en el primer segmento de la url.

**timezone**: string  
Define el timezone de PHP.

**spa**: object  
    **enabled**: true | false  
    Esta funcionalidad permite navegar a través de la aplicación web sin refrescar la página.  
    Más información [aquí](#spa).  

**cache**: object  
    **enabled**: true | false  
    **directory**: string  
    **time**: number  

<a id="spa"></a>

### SPA (Single Page Application)
Podemos hacer lo siguiente con Javascript para cambiar de página:  
``` javascript 
const route = '/contact';
captain.navigate(route);
```
    

## app.routes.json example
```javascript
{
  "middlewares": [
    "middlewares/variables"
  ],
  "default": {
    "responseType": "html",
    "head": "layout/head",
    "beforeContent": "layout/before-content",
    "afterContent": "layout/after-content",
  },
  "routes": [
    {
      "path": "/contact",
      "component": "pages/contact"
    },
    {
      "path": "/articles",
      "component": "pages/articles",
      "cache": {
        "enabled": false
      },
      "data": {}
    },
    {
      "path": "/articles/{article_id}",
      "component": "pages/article",
      "data": {
          "pageType": "article"
      }
    },
    {
      "path": "/",
      "component": "pages/home",
      "cache": {
        "enabled": true,
        "time": 60, // seconds
      }
    }
  ],
  "notFound": {
    "component": "pages/404"
  }
}

```

<br>
<br>

> Writting in my spare time...

<br>
<br>

*Do you want to contribute?*<br>
[![Donate 1€](https://img.shields.io/badge/Buy%20me%20a%20coffee-1%E2%82%AC-brightgreen?logo=buymeacoffee&logoColor=white&labelColor=grey&style=for-the-badge
)](https://www.paypal.com/paypalme/rubenperezlopez/1?target=_blank)

---

Made with ❤️ by developer for developers
