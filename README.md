# Loader

`rodas/loader` provides autoloading and plugin loading strategies for PHP classes.

This project is based on the code of the now-abandoned projects [laminas/loader](https://github.com/laminas/laminas-loader) and [zend/loader](https://github.com/zendframework/zend-loader), its predecessor.

However, it only implements two basic functionalities:

- A Fallback autoloader, simplifying the `laminas/loader` code.
- An plugin loader, based on the one from `zend/loader`.

## Requirements

- PHP 8.4 (x64) or higher

## Dependencies

- `rodas/system`
- `rodas/psr-scaffold` ,v2.0 or higher
  - Only `rodas/psr-log` (Virtual package)

---

### References

- [Laminas components](https://getlaminas.org/)
- [Zend Framework](https://framework.zend.com/)
- [PHP-FIG PSR-4](https://www.php-fig.org/psr/psr-4/)
