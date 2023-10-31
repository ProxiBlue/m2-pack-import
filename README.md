# ProxiBlue_PackImport module

This module allows import using FireBear ImportExport module, of product Pack Options as defined by eNanobots Pack Options module.


## Requirements

* firebear/importexport >= 3.8
* enanobots/m2-product-pack >= 1.0.0

## Installation details

* Install the module via composer: ```composer require proxi-blue/m2-pack-import```
* Enable the module via bin/magento: ```module:enable ProxiBlue_PackImport```
* Run bin/magento: ```setup:upgrade```
* Run bin/magento: ```setup:di:compile```
* Run bin/magento: ```setup:static-content:deploy```
* Run bin/magento: ```cache:flush```

## Usage

Add a new column to your import file, called `pack_data` and add the pack options as a json string.
In the below example, 3 pack options will be added to a product.
Set the ```product_type``` to 'pack' to indicate the product is a pack product.

```json
[
    {
        "package_name": "BOX",
        "discount_type": "percent",
        "discount_value": 3,
        "pack_size": 50,
        "extra_weight": 100,
        "sort_order": 3
    },
    {
        "package_name": "4 Boxes",
        "discount_type": "percent",
        "discount_value": 5,
        "pack_size": 200,
        "extra_weight": 400,
        "sort_order": 1
    },
    {
        "package_name": "Half Pallet",
        "discount_type": "percent",
        "discount_value": 10,
        "pack_size": 1800,
        "extra_weight": 1000,
        "sort_order": 2
    }
]
```

## Importing Rules

The following import / data rules apply:

* if product type == 'pack' and pack_data field is not empty, then the product is a pack product and the pack options will be imported.
* if product type == 'pack' and pack_data field is empty, then the product entry will be ignored, and a warning placed.
* if product type != 'pack' and pack_data field is not empty, then the product entry will be ignored, and a warning placed.
* if product type == 'simple' and pack_data field is empty, and current pack data exists, the product will be reverted back to a simple, and pack options removed from database.

## License

[MIT](https://opensource.org/licenses/MIT)