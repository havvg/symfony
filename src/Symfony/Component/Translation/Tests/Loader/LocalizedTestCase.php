<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;

abstract class LocalizedTestCase extends \PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        if (!extension_loaded('intl')) {
            self::markTestSkipped('The "intl" extension is not available');
        }
    }
}
