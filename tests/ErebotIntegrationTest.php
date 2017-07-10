<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

class TestTvRetriever
{
    protected $ID_mappings = array('foo' => 42, 'bar' => 69);

    public static function getInstance()
    {
        $c = __CLASS__;
        $instance = new $c();
        return $instance;
    }

    public function getSupportedChannels()
    {
        return array_keys($this->ID_mappings);
    }

    public function getIdFromChannel($channel)
    {
        $channel = strtolower(trim($channel));
        if (!isset($this->ID_mappings[$channel]))
            return NULL;
        return $this->ID_mappings[$channel];
    }

    public function getChannelsData($timestamp, $ids)
    {
        if (!is_array($ids))
            $ids = array($ids);

        return array(
            'foo' => array(
                'Date_Debut' => "2010-09-02 17:23:00",
                'Date_Fin' => "2010-09-02 17:42:00",
                'Titre' => 'foo',
            ),
            'bar' => array(
                'Date_Debut' => "2010-09-03 17:23:00",
                'Date_Fin' => "2010-09-03 17:42:00",
                'Titre' => 'bar',
            ),
        );
    }
}

class ErebotTestModule_Tv
extends \Erebot\Module\TV
{
    protected $dateParser = array('ErebotTestModule_Tv', 'parseDate');

    public function setTvRetriever($tv)
    {
        $this->tv = $tv;
    }

    public function setCustomMappings($mappings)
    {
        $this->customMappings = $mappings;
    }

    public function setDefaultGroup($group)
    {
        $this->defaultGroup = $group;
    }

    public static function parseDate($date)
    {
        if ($date == 'now')
            return 502031100;
        return 502070400;
    }
}

class   ErebotIntegrationTest
extends Erebot_Testenv_Module_TestCase
{
    public function _mockPrivateText($source, $text)
    {
        $event = $this->getMockBuilder('\\Erebot\\Interfaces\\Event\\PrivateText')->getMock();
        $wrapper = $this->getMockBuilder('\\Erebot\\Interfaces\\TextWrapper')->getMock();

        $text = explode(' ', $text, 3);
        $wrapper
            ->expects($this->any())
            ->method('getTokens')
            ->will($this->onConsecutiveCalls($text[1], $text[2]));

        $event
            ->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($this->_connection));
        $event
            ->expects($this->any())
            ->method('getSource')
            ->will($this->returnValue($source));
        $event
            ->expects($this->any())
            ->method('getText')
            ->will($this->returnValue($wrapper));
        return $event;
    }

    public function setUp()
    {
        $this->_module = new ErebotTestModule_Tv(NULL);
        parent::setUp();

        $this->_module->reloadModule($this->_connection, 0);
        $this->_module->setTvRetriever(new TestTvRetriever());
    }

    public function tearDown()
    {
        $this->_module->unloadModule();
        parent::tearDown();
    }

    public function testMissingDefaultGroup()
    {
        $event = $this->_mockPrivateText('test', '!tv  ');
        $this->_module->handleTv($this->_eventHandler, $event);

        $this->assertEquals(1, count($this->_outputBuffer));
        $this->assertEquals(
            "PRIVMSG test :No channel given and no default.",
            $this->_outputBuffer[0]
        );
    }

    public function testUsingDefaultGroupWithChannelOverride()
    {
        $event = $this->_mockPrivateText('test', '!tv 23h42 foo');
        $this->_module->handleTv($this->_eventHandler, $event);

        // HHVM does not seem to rely on ICU's formats at all for dates/times.
        // Also, ICU's format changed somewhat starting with ICU 50-rc.
        // See http://bugs.icu-project.org/trac/changeset/32275/icu/trunk/source/data/locales/en.txt
        // for the commit that introduced this format change.
        if (defined('HHVM_VERSION')) {
            $expected = "PRIVMSG test :TV programs for ".
                        "\0371985 M11 28 23:42:00\037: ".
                        "\002foo\002: foo (17:23 - 17:42) - ".
                        "\002bar\002: bar (17:23 - 17:42)";
        } elseif (version_compare(INTL_ICU_DATA_VERSION, '50', '>=')) {
            $expected = "PRIVMSG test :TV programs for ".
                        "\037November 28, 1985 at 11:42:00 PM\037: ".
                        "\002foo\002: foo (17:23 - 17:42) - ".
                        "\002bar\002: bar (17:23 - 17:42)";
        } else {
            $expected = "PRIVMSG test :TV programs for ".
                        "\037November 28, 1985 11:42:00 PM\037: ".
                        "\002foo\002: foo (17:23 - 17:42) - ".
                        "\002bar\002: bar (17:23 - 17:42)";
        }
        $this->assertEquals(1, count($this->_outputBuffer));
        $this->assertEquals($expected, $this->_outputBuffer[0]);
    }
}
