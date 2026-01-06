<?php
/**
 * This file is part of the Rodas\Loader library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Rodas\Loader
 * @copyright 2026 Marcos Porto <php@marcospor.to>
 * @license https://opensource.org/license/mit The MIT License
 * @link https://marcospor.to/repositories/loader
 */

declare(strict_types=1);

namespace Rodas\Loader;

use Traversable;

use function dirname;
use function file_exists;
use function in_array;
use function is_array;
use function preg_match;
use function rtrim;
use function spl_autoload_register;
use function str_replace;
use function stream_resolve_include_path;
use function strlen;
use function strpos;
use function substr;

use const DIRECTORY_SEPARATOR;

// Grab Autoloadable interface
require_once __DIR__ . '/Autoloadable.php';

/**
 * PSR-0 compliant autoloader
 *
 * Allows autoloading both namespaced and vendor-prefixed classes. Class
 * lookups are performed on the filesystem. If a class file for the referenced
 * class is not found, a PHP warning will be raised by include().
 */
class FallbackAutoloader implements Autoloadable {
    public const LOAD_NS          = 'namespaces';
    public const LOAD_PREFIX      = 'prefixes';

    /** @var array Namespace/directory pairs to search */
    protected $namespaces = [];

    /** @var array Prefix/directory pairs to search */
    protected $prefixes = [];

    /**
     * Constructor
     *
     * @param null|array|Traversable $options
     */
    public function __construct(?iterable $options = null) {
        if (null !== $options) {
            $this->setOptions($options);
        }
    }

    /**
     * Configure autoloader
     *
     * Allows specifying both "namespace" and "prefix" pairs, using the
     * following structure:
     * <code>
     * array(
     *     'namespaces' => array(
     *         'Laminas'     => '/path/to/Laminas/library',
     *         'Doctrine' => '/path/to/Doctrine/library',
     *     ),
     *     'prefixes' => array(
     *         'Phly_'     => '/path/to/Phly/library',
     *     )
     * )
     * </code>
     *
     * @param array|Traversable $options
     * @throws InvalidArgumentException
     * @return FallbackAutoloader
     */
    public function setOptions(iterable $options) {
        if (! is_array($options) && ! $options instanceof Traversable) {
            // TODO: Log
            throw new InvalidArgumentException('Options must be either an array or Traversable');
        }

        foreach ($options as $type => $pairs) {
            switch ($type) {
                case self::LOAD_NS:
                    if (is_array($pairs) ||
                        $pairs instanceof Traversable) {

                        $this->registerNamespaces($pairs);
                    }
                    break;
                case self::LOAD_PREFIX:
                    if (is_array($pairs) ||
                        $pairs instanceof Traversable) {

                        $this->registerPrefixes($pairs);
                    }
                    break;
                default:
                    // ignore
            }
        }
        return $this;
    }

    /**
     * Register a namespace/directory pair
     *
     * @param  string $namespace
     * @param  string $directory
     * @return FallbackAutoloader
     */
    public function registerNamespace(string $namespace, string $directory): static {
        $namespace                    = rtrim($namespace, self::NS_SEPARATOR) . self::NS_SEPARATOR;
        $this->namespaces[$namespace] = static::normalizeDirectory($directory);
        return $this;
    }

    /**
     * Register many namespace/directory pairs at once
     *
     * @param  array $namespaces
     * @throws InvalidArgumentException
     * @return FallbackAutoloader
     */
    public function registerNamespaces(iterable $namespaces): static {
        if (! is_array($namespaces) && ! $namespaces instanceof Traversable) {
            // TODO: Log
            throw new InvalidArgumentException('Namespace pairs must be either an array or Traversable');
        }

        foreach ($namespaces as $namespace => $directory) {
            if (! is_string($namespace)) {
                $namespace = str_replace(['\\', '/'], self::NS_SEPARATOR, $namespace);
            }
            $this->registerNamespace($namespace, $directory);
        }
        return $this;
    }

    /**
     * Register a prefix/directory pair
     *
     * @param  string $prefix
     * @param  string $directory
     * @return FallbackAutoloader
     */
    public function registerPrefix(string $prefix, string $directory): static {
        $prefix                  = rtrim($prefix, self::PREFIX_SEPARATOR) . self::PREFIX_SEPARATOR;
        $this->prefixes[$prefix] = static::normalizeDirectory($directory);
        return $this;
    }

    /**
     * Register many namespace/directory pairs at once
     *
     * @param  iterable $prefixes
     * @throws InvalidArgumentException
     * @return FallbackAutoloader
     */
    public function registerPrefixes(iterable $prefixes): static {
        if (! is_array($prefixes) &&
            ! $prefixes instanceof Traversable) {
            // TODO: Log
            throw new InvalidArgumentException('Prefix pairs must be either an array or Traversable');
        }

        foreach ($prefixes as $prefix => $directory) {
            $this->registerPrefix($prefix, $directory);
        }
        return $this;
    }

    /**
     * Defined by Autoloadable; autoload a class
     *
     * @param  string $class
     * @return false|string
     */
    public function autoload(string $class): false|string {
        if (false !== strpos($class, self::NS_SEPARATOR) &&
            $this->loadClass($class, self::LOAD_NS)) {

            return $class;
        }
        if (false !== strpos($class, self::PREFIX_SEPARATOR) &&
            $this->loadClass($class, self::LOAD_PREFIX)) {

            return $class;
        }
        return $this->loadClass($class);
    }

    /**
     * Register the autoloader with spl_autoload
     *
     * @return void
     */
    public function register(): void {
        spl_autoload_register([$this, 'autoload']);
    }

    /**
     * Transform the class name to a filename
     *
     * @param  string $class
     * @return string
     */
    protected static function transformClassNameToFilename(string $class): string {
        // $class may contain a namespace portion, in  which case we need
        // to preserve any underscores in that portion.
        $matches = [];
        preg_match('/(?P<namespace>.+\\\)?(?P<class>[^\\\]+$)/', $class, $matches);

        $class     = $matches['class'] ?? '';
        $namespace = $matches['namespace'] ?? '';

        return str_replace(static::NS_SEPARATOR, '/', $namespace)
             . str_replace(static::PREFIX_SEPARATOR, '/', $class)
             . '.php';
    }

    /**
     * Load a class, based on its type (namespaced or prefixed)
     *
     * @param  string $class
     * @param  string $type
     * @return bool|string
     * @throws InvalidArgumentException
     */
    protected function loadClass(string $class, string $type) {
        if (! in_array($type, [static::LOAD_NS, static::LOAD_PREFIX])) {
            // TODO: Log
            throw new InvalidArgumentException();
        }

        // Fallback autoloading
        $filename     = static::transformClassNameToFilename($class);
        $resolvedName = stream_resolve_include_path($filename);
        if ($resolvedName !== false) {
            return include $resolvedName;
        }
        // Namespace and/or prefix autoloading
        foreach ($this->$type as $leader => $path) {
            if (0 === strpos($class, $leader)) {
                // Trim off leader (namespace or prefix)
                $trimmedClass = substr($class, strlen($leader));

                // create filename
                $filename = static::transformClassNameToFilename($trimmedClass, $path);
                if (file_exists($filename)) {
                    return include $filename;
                }
            }
        }
        // TODO: Log verbose
        return false;
    }

    /**
     * Normalize the directory to include a trailing directory separator
     *
     * @param  string $directory
     * @return string
     */
    protected static function normalizeDirectory(string $directory): string {
        $last = $directory[strlen($directory) - 1];
        if (in_array($last, ['/', '\\'])) {
            $directory[strlen($directory) - 1] = DIRECTORY_SEPARATOR;
            return $directory;
        }
        $directory .= DIRECTORY_SEPARATOR;
        return $directory;
    }
}
