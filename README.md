# deployment-tool
Deployment tools for OXID eShop 

## Installation

Run the following command to install the component:

```bash
composer require oxid-esales/deployment-tools
```

## Usage

### Deploy module configurations

If you move module configuration files from one environment to another you can deploy module configurations with 
the following command:

```bash
vendor/bin/oe-console oe:module:deploy-configurations
```

## How to run tests?

To run tests for the component please define OXID eShop bootstrap file:

```bash
vendor/bin/phpunit --bootstrap=../source/bootstrap.php tests/
```

## License

See [LICENSE](LICENSE) file for license details.
