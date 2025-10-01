# WooCommerce Mix and Match: Variable Mix and Match Products

## Quickstart

This is a developmental repo. Clone this repo and run `composer install && npm install && npm run build`   
OR    
|[Download latest release](https://github.com/backcourt/wc-mnm-variable/releases/latest)|
|---|

### What's This?

Experimental mini-extension for [WooCommerce Mix and Match Products](https://woocommerce.com/products/woocommerce-mix-and-match-products/) that enables a Variable Mix and Match product type. Allows for customers to pick their variation (typically using a different container size) and then pick their contents. Example: Pick 6 pack vs 12 pack and then fill with contents.

![Screen Recording of a product called "Mix and Match Wine Pack". Shows the 3 options: small, large, x-large boxes. Customer selects large box and several bottles of wine are displayed. Customer selects 4 of each bottle and the product is added to the cart.](https://user-images.githubusercontent.com/507025/196753568-d57cbbe8-1a2e-4c66-8451-559d03495482.gif)


### Choosing a Version

1.x Uses traditional, PHP templates. It's compatible with just about all our other mini-extensions. Can be slow on some hosts. This approach will *not* received further development.
2.x Frontend is rendered entirely in React and is much faster than 1.x. But it's not quite as far along with respect to compatibility. It does not work with most other mini-extensions yet. It's also much more difficult (read currently impossible) to customize the display beyond what is built it.

>**Warning**

1. This is provided _as is_ and does not receive priority support.
2. Please test thoroughly before using in production.
3. Requires Mix and Match 2.8.0+ and WooCommerce 9.4.0+
