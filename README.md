# laravel-opensearch

Laravel Scout 的 阿里云 Open Search 驱动。

## Installation

建议使用 composer 方式安装此包

    composer require xiaoguo/laravel-opensearch

## Usage

1. 在阿里云 OpenSearch 控制台配置;

2. Laravel 5.5 以下，`config/app.php`  中添加 `service provider`

        xiaoguo\OpenSearch\OpenSearchServiceProvider::class

    Laravel 5.5 及以上，自动加载 `service provider`，无需手动添加。

3. 修改 `.env` 配置 scout driver

        SCOUT_DRIVER=opensearch
        或者直接在scout配置文件中修改

4. artisan 导入数据同官方 scout 一样：@see [Scout Indexing](https://laravel.com/docs/5.5/scout#indexing)

## Issue

此 package 目前主要自用，修改自https://github.com/ruogoo/laravel-scout-opensearch
由于个人需求不同 稍作修改

[email:hyancat@live.cn](mailto:hyancat@live.cn)

## License

None.
