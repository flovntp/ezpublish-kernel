<?php

/**
 * File containing the ConfigSuggestionTest class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\Suggestion\Tests;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\Suggestion\ConfigSuggestion;
use PHPUnit_Framework_TestCase;

class ConfigSuggestionTest extends PHPUnit_Framework_TestCase
{
    public function testEmptyConstructor()
    {
        $suggestion = new ConfigSuggestion();
        $this->assertNull($suggestion->getMessage());
        $this->assertSame(array(), $suggestion->getSuggestion());
        $this->assertFalse($suggestion->isMandatory());
    }

    public function testConfigSuggestion()
    {
        $message = 'some message';
        $configArray = array('foo' => 'bar');

        $suggestion = new ConfigSuggestion($message, $configArray);
        $this->assertSame($message, $suggestion->getMessage());
        $this->assertSame($configArray, $suggestion->getSuggestion());
        $this->assertFalse($suggestion->isMandatory());

        $newMessage = 'foo bar';
        $suggestion->setMessage($newMessage);
        $this->assertSame($newMessage, $suggestion->getMessage());

        $newConfigArray = array('ez' => 'publish');
        $suggestion->setSuggestion($newConfigArray);
        $this->assertSame($newConfigArray, $suggestion->getSuggestion());

        $suggestion->setMandatory(true);
        $this->assertTrue($suggestion->isMandatory());
    }
}
