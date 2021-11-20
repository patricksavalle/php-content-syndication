# php-content-syndication

Library that contains utility funtions typically used for content syndication.

- Extracting HTML metadata (og:tags, twitter:tags, etc.)
- URL normalisation and absolutization
- Text parsing and conversion
- RSS/ATOM reading
- Storing and retrieving at archive.org

It's in use for this production system: https://zaplog.pro (https://gitlab.com/zaplog)

## Install with [Composer](https://packagist.org/packages/patricksavalle/slim-request-params) ###

- Update your `composer.json` to require `patricksavalle/slim-request-params`.
- Run `composer install` to add slim-request-params your vendor folder.

    ```json
    {
      "require": {
        "patricksavalle/php-content-syndication": "^0.1"
      }
    }
    ```

- Include in your source.

    ```php
    <?php
   
    require './vendor/autoload.php';
    ```

## Usage

### Retrieving HTML metadata

Returned URL's will be normalized. 

    return (new HtmlMetadata)("https://gizmodo.com/the-real-reason-gas-prices-are-so-high-right-now-1848088360");

    Array
    (
      [url] => https://gizmodo.com/the-real-reason-gas-prices-are-so-high-right-now-1848088360
      [title] => The Real Reason Gas Prices Are So High Right Now
      [description] => It's not the Biden administration's energy policies nor Big Oil illegally manipulating prices.
      [image] => https://i.kinja-img.com/gawker-media/image/upload/c_fill,f_auto,fl_progressive,g_center,h_675,pg_1,q_80,w_1200/1aafc02b452854117b46237d555e4879.jpg
      [video] =>
      [video:type] =>
      [video:release_date] =>
      [video:duration] =>
      [video:series] =>
      [site_name] => Gizmodo
      [language] => en
      [rss] => https://gizmodo.com/rss
      [atom] =>
      [author] =>
      [copyright] =>
      [keywords] => Array
      (
        [0] => OPEC
        [1] => american petroleum institute
        [2] => Business
        [3] => Finance
        [4] => Primary sector of the economy
        [5] => Joe Biden
        [6] => Cartels
        [7] => Ed Markey
        [8] => Big Oil
        [9] => Clark Williams-Derry
        [10] => Price of oil
        [11] => Petroleum industry
        [12] => Chronology of world oil market events
        [13] => Petroleum
        [14] => Energy
        [15] => West Texas Intermediate
        [16] => Gizmodo
      ) 
    )



