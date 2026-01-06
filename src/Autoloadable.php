<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.Interface.Suffix
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

use function interface_exists;

if (interface_exists(Autoloadable::class)) {
    return;
}

/**
 * Defines an interface for classes that may register with the spl_autoload
 * registry
 */
interface Autoloadable {
    public const NS_SEPARATOR     = '\\';
    public const PREFIX_SEPARATOR = '_';
    /**
     * Constructor
     *
     * Allow configuration of the autoloader via the constructor.
     *
     * @param  null|array|Traversable $options
     */
    public function __construct(?iterable $options = null);

    /**
     * Configure the autoloader
     *
     * $options should be either an associative array or
     * Traversable object.
     *
     * @param  array|Traversable $options
     * @return Autoloadable
     */
    public function setOptions(iterable $options): static;

    /**
     * Autoload a class
     *
     * @param   string $class
     * @return  mixed
     *          false [if unable to load $class]
     *          get_class($class) [if $class is successfully loaded]
     */
    public function autoload(string $class);

    /**
     * Register the autoloader with spl_autoload registry
     *
     * Typically, the body of this will simply be:
     * <code>
     * spl_autoload_register([$this, 'autoload']);
     * </code>
     *
     * @return void
     */
    public function register(): void;
}
