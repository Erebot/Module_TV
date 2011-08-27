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
extends Erebot_Module_TV
{
    protected $_dateParser = array('ErebotTestModule_Tv', 'parseDate');

    public function setTvRetriever($tv)
    {
        $this->_tv = $tv;
    }

    public function setCustomMappings($mappings)
    {
        $this->_customMappings = $mappings;
    }

    public function setDefaultGroup($group)
    {
        $this->_defaultGroup = $group;
    }

    static public function parseDate($date)
    {
        if ($date == 'now')
            return 502031100;
        return 502070400;
    }
}

class   ErebotIntegrationTest
extends ErebotModuleTestCase
{
    public function _mockPrivateText($source, $text)
    {
        $event = $this->getMock(
            'Erebot_Interface_Event_PrivateText',
            array(), array(), '', FALSE, FALSE
        );

        $wrapper = $this->getMock(
            'Erebot_Interface_TextWrapper',
            array(), array(), '', FALSE, FALSE
        );

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
        parent::setUp();
        $this->_module = new ErebotTestModule_Tv(NULL);
        $this->_module->setFactory('!Styling', $this->_factory['!Styling']);
        $this->_module->reload($this->_connection, 0);
        $this->_module->setTvRetriever(new TestTvRetriever());
    }

    public function tearDown()
    {
        $this->_module->unload();
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

        $expected =  'PRIVMSG test :TV programs for <u><var '.
                    'name="Thu, 28 Nov 1985 23:42:00 +0000"/></u>: '.
                    '<for from="array ( \'foo\' => \'foo (17:23 - 17:42)\', '.
                    '\'bar\' => \'bar (17:23 - 17:42)\', )" key="channel" '.
                    'item="timetable" separator=" - "><b><var name="channel"/>'.
                    '</b>: <var name="timetable"/></for>';
        $this->assertEquals(1, count($this->_outputBuffer));
        $this->assertEquals($expected, $this->_outputBuffer[0]);
    }
}

