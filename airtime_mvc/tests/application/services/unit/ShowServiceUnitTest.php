<?php
require_once "../application/configs/conf.php";
require_once "ShowService.php";
require_once "ShowServiceData.php";

class ShowServiceUnitTest extends PHPUnit_Framework_TestCase
{
    // needed for accessing private methods
    protected $_reflectionOfShowService;

    protected $_showService;

    public function setUp()
    {
        $this->_reflectionOfShowService = new ReflectionClass('Application_Service_ShowService');

        $this->_showService = new Application_Service_ShowService();
    }

    public function testFormatShowDuration()
    {
        $duration = Application_Service_ShowService::formatShowDuration("01h 00m");
        $this->assertEquals("01:00", $duration);

        $duration = Application_Service_ShowService::formatShowDuration("00h 05m");
        $this->assertEquals("00:05", $duration);

        $duration = Application_Service_ShowService::formatShowDuration("03h 55m");
        $this->assertEquals("03:55", $duration);
    }

    public function testCalculateEndDate()
    {
        $method = $this->_reflectionOfShowService->getMethod('calculateEndDate');
        $method->setAccessible(true);

        $end = $method->invokeArgs($this->_showService, array(ShowServiceData::getNoRepeatNoRRData()));
        $this->assertEquals(null, $end);

        $end = $method->invokeArgs($this->_showService, array(ShowServiceData::getWeeklyRepeatWithEndNoRRData()));
        $this->assertEquals(new DateTime("2016-01-27", new DateTimeZone("UTC")), $end);

        $end = $method->invokeArgs($this->_showService, array(ShowServiceData::getWeeklyRepeatNoEndNoRRData()));
        $this->assertEquals(null, $end);
    }

    public function testGetMonthlyWeeklyRepeatInterval()
    {
        $method = $this->_reflectionOfShowService->getMethod('getMonthlyWeeklyRepeatInterval');
        $method->setAccessible(true);

        $repeatInterval = $method->invokeArgs($this->_showService, array(new DateTime("2016-01-01"), new DateTimeZone("UTC")));
        $this->assertEquals(array("first", "Friday"), $repeatInterval);

        $repeatInterval = $method->invokeArgs($this->_showService, array(new DateTime("2016-01-12"), new DateTimeZone("UTC")));
        $this->assertEquals(array("second", "Tuesday"), $repeatInterval);

        $repeatInterval = $method->invokeArgs($this->_showService, array(new DateTime("2016-01-18"), new DateTimeZone("UTC")));
        $this->assertEquals(array("third", "Monday"), $repeatInterval);

        $repeatInterval = $method->invokeArgs($this->_showService, array(new DateTime("2016-01-28"), new DateTimeZone("UTC")));
        $this->assertEquals(array("fourth", "Thursday"), $repeatInterval);

        $repeatInterval = $method->invokeArgs($this->_showService, array(new DateTime("2016-01-30"), new DateTimeZone("UTC")));
        $this->assertEquals(array("fifth", "Saturday"), $repeatInterval);
    }

    public function testGetNextMonthlyMonthlyRepeatDate()
    {
        $method = $this->_reflectionOfShowService->getMethod('getNextMonthlyMonthlyRepeatDate');
        $method->setAccessible(true);

        $next = $method->invokeArgs($this->_showService, array(new DateTime("2016-01-01"), "UTC", "00:00"));
        $this->assertEquals(new DateTime("2016-02-01", new DateTimeZone("UTC")), $next);

        $next = $method->invokeArgs($this->_showService, array(new DateTime("2016-01-30"), "UTC", "00:00"));
        $this->assertEquals(new DateTime("2016-03-30", new DateTimeZone("UTC")), $next);
    }

    public function testCreateUTCStartEndDateTime()
    {
        
    }
}