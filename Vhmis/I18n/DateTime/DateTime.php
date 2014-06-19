<?php

/**
 * Vhmis Framework
 *
 * @link http://github.com/micti/VHMIS-Framework for git source repository
 * @copyright Le Nhat Anh (http://lenhatanh.com)
 * @license http://opensource.org/licenses/MIT MIT License
 */

namespace Vhmis\I18n\DateTime;

use \Vhmis\Utils\Std\DateTimeInterface;
use \Vhmis\Utils\Std\AbstractDateTime;
use \Vhmis\Utils\Exception\InvalidArgumentException;

/**
 * I18n Datetime class
 *
 * Support many calendars with locale info
 *
 * @method \Vhmis\I18n\DateTime\DateTime setSecond(int $second) Set second
 * @method \Vhmis\I18n\DateTime\DateTime setMinute(int $minute) Set minute
 * @method \Vhmis\I18n\DateTime\DateTime setHour(int $hour) Set hour
 * @method \Vhmis\I18n\DateTime\DateTime setDay(int $day) Set day
 * @method \Vhmis\I18n\DateTime\DateTime setExtendedYear(int $extendYear) Set extended year
 * @method \Vhmis\I18n\DateTime\DateTime setEra(int $era) Set era
 * @method \Vhmis\I18n\DateTime\DateTime addSecond(int $amount) Add second
 * @method \Vhmis\I18n\DateTime\DateTime addMinute(int $amount) Add minute
 * @method \Vhmis\I18n\DateTime\DateTime addHour(int $amount) Add hour
 * @method \Vhmis\I18n\DateTime\DateTime addDay(int $amount) Add day
 * @method \Vhmis\I18n\DateTime\DateTime addWeek(int $amount) Add week
 * @method \Vhmis\I18n\DateTime\DateTime addMonth(int $amount) Add month
 * @method \Vhmis\I18n\DateTime\DateTime addYear(int $amount) Add year
 * @method \Vhmis\I18n\DateTime\DateTime addEra(int $amount) Add era
 * @method \Vhmis\I18n\DateTime\DateTime getSecond() Get second
 * @method \Vhmis\I18n\DateTime\DateTime getMinute() Get minute
 * @method \Vhmis\I18n\DateTime\DateTime getHour() Get hour
 * @method \Vhmis\I18n\DateTime\DateTime getDay() Get day
 * @method \Vhmis\I18n\DateTime\DateTime getWeek() Get week
 * @method \Vhmis\I18n\DateTime\DateTime getMonth() Get month
 * @method \Vhmis\I18n\DateTime\DateTime getYear() Get year
 * @method \Vhmis\I18n\DateTime\DateTime getEra() Get era
 */
class DateTime extends AbstractDateTime implements DateTimeInterface
{
    /**
     * List supported calendars
     *
     * @var array
     */
    public static $calendars = array(
        'gregorian'     => 'gregorian',
        'chinese'       => 'chinese',
        'coptic'        => 'coptic',
        'dangi'         => 'dangi',
        'ethiopic'      => 'ethiopic',
        'hebrew'        => 'hebrew',
        'indian'        => 'indian',
        'islamic-civil' => 'islamic-civil',
        'islamic'       => 'islamic',
        'japanese'      => 'japanese',
        'persian'       => 'persian',
        'taiwan'        => 'taiwan',
        'buddhist'      => 'buddhist'
    );

    /**
     * IntlCalendar object
     *
     * @var \IntlCalendar
     */
    protected $calendar;

    /**
     * Helper namespace
     *
     * @var string
     */
    protected $helperNamespace = '\Vhmis\I18n\DateTime\Helper';

    /**
     * Magic methods
     *
     * @var array
     */
    protected $magicMethods = array(
        'getSecond' => \IntlCalendar::FIELD_SECOND,
        'getMinute' => \IntlCalendar::FIELD_MINUTE,
        'getHour' => \IntlCalendar::FIELD_HOUR_OF_DAY,
        'getDay' => \IntlCalendar::FIELD_DAY_OF_MONTH,
        'getWeek' => \IntlCalendar::FIELD_DAY_OF_MONTH,
        'getMonth' => \IntlCalendar::FIELD_MONTH,
        'getYear' => \IntlCalendar::FIELD_EXTENDED_YEAR,
        'getEra' => \IntlCalendar::FIELD_ERA
    );

    protected $relatedYearAdjust = array(
        'gregorian'     => 622,
        'chinese'       => -2637,
        'coptic'        => 284,
        'dangi'         => -2333,
        'ethiopic'      => 8,
        'hebrew'        => -3760,
        'indian'        => 79,
        'islamic-civil' => 0,
        'islamic'       => 0,
        'japanese'      => 0,
        'persian'       => 0,
        'taiwan'        => 0,
        'buddhist'      => 0
    );

    /**
     * Construct
     *
     * @param mixed  $timezone
     * @param string $calendar
     * @param string $locale
     *
     * @throws InvalidArgumentException
     */
    public function __construct($timezone = null, $calendar = null, $locale = null)
    {
        $calendar = $this->getCalendarType($calendar);

        if ($locale === null) {
            $locale = \Locale::getDefault();
        }

        $locale = $locale . '@calendar=' . $calendar;

        $this->calendar = \IntlCalendar::createInstance($timezone, $locale);

        if ($this->calendar === null) {
            throw new InvalidArgumentException('Timezone is not valid.');
        }
    }

    /**
     * Set date
     *
     * @param int $year
     * @param int $month
     * @param int $day
     *
     * @return DateTime
     */
    public function setDate($year, $month, $day)
    {
        $month = (int) $month - 1;
        $this->calendar->set((int) $year, $month, (int) $day);

        return $this;
    }

    /**
     * Set date with year is extended year
     *
     * @param int $year
     * @param int $month
     * @param int $day
     *
     * @return DateTime
     */
    public function setDateWithExtenedYear($year, $month, $day)
    {
        $month = (int) $month - 1;

        $this->calendar->set(\IntlCalendar::FIELD_EXTENDED_YEAR, (int) $year);
        $this->calendar->set(\IntlCalendar::FIELD_MONTH, $month);
        $this->calendar->set(\IntlCalendar::FIELD_DAY_OF_MONTH, (int) $day);

        return $this;
    }

    /**
     * Set date with year is Gregorian related year
     *
     * @param int $year
     * @param int $month
     * @param int $day
     *
     * @return DateTime
     */
    public function setDateWithGregorianRelatedYear($year, $month, $day)
    {
        $year = $this->getExtendedYearFromGregorianRelatedYear($year);

        return $this->setDateWithExtenedYear($year, $month, $day);
    }

    /**
     * Set time
     *
     * @param int $hour
     * @param int $minute
     * @param int $second
     *
     * @return DateTime
     */
    public function setTime($hour, $minute, $second)
    {
        $this->calendar->set(\IntlCalendar::FIELD_HOUR_OF_DAY, (int) $hour);
        $this->calendar->set(\IntlCalendar::FIELD_MINUTE, (int) $minute);
        $this->calendar->set(\IntlCalendar::FIELD_SECOND, (int) $second);

        return $this;
    }

    /**
     * Set date or/and time by ISO style datetime
     * Year is extended year
     *
     * @param string $string
     *
     * @return DateTime
     */
    public function modify($string)
    {
        $datetime =  '/^(r?)(-?)(\d{1,5})-(\d{1,2})-(\d{1,2})(| (\d{1,2}):(\d{1,2}):(\d{1,2}))$/';
        $time =  '/^(\d{1,2}):(\d{1,2})(|:(\d{1,2}))$/';
        $matches = array();

        if (preg_match($datetime, $string, $matches)) {
            $this->setDateWithExtenedYear($matches[2] . $matches[3], $matches[4], $matches[5]);

            if ($matches[1] === 'r') {
                $this->setDateWithGregorianRelatedYear($matches[2] . $matches[3], $matches[4], $matches[5]);
            }

            if ($matches[6] !== '') {
                $this->setTime($matches[7], $matches[8], $matches[9]);
            }

            return $this;
        }

        if (preg_match($time, $string, $matches)) {
            if ($matches[3] !== '') {
                return $this->setTime($matches[1], $matches[2], $matches[4]);
            }

            $second = $this->calendar->get(\IntlCalendar::FIELD_SECOND);

            return $this->setTime($matches[1], $matches[2], $second);
        }

        return $this;
    }

    /**
     * Set timezone
     *
     * @param mixed $timeZone
     *
     * @return \Vhmis\I18n\DateTime\DateTime
     */
    public function setTimeZone($timeZone)
    {
        $this->calendar->setTimeZone($timeZone);

        return $this;
    }

    /**
     * Set epoch timestamp (UTC 00:00)
     *
     * @param int $timestamp
     *
     * @return DateTime
     */
    public function setTimestamp($timestamp)
    {
        $this->calendar->setTime((int) $timestamp * 1000);

        return $this;
    }

    /**
     * Get date (based on ISO format yyyy-mm-dd)
     *
     * @return string
     */
    public function getDate()
    {
        $year = $this->formatField(\IntlCalendar::FIELD_YEAR);
        $month = $this->formatField(\IntlCalendar::FIELD_MONTH);
        $day = $this->formatField(\IntlCalendar::FIELD_DAY_OF_MONTH);

        $date = $year . '-' . $month . '-' . $day;
        //$date = \IntlDateFormatter::formatObject($this->calendar, 'yyyy-MM-dd');
        return $date;
    }

    public function getDateWithExtendedYear()
    {
        $year = $this->formatField(\IntlCalendar::FIELD_EXTENDED_YEAR);
        $month = $this->formatField(\IntlCalendar::FIELD_MONTH);
        $day = $this->formatField(\IntlCalendar::FIELD_DAY_OF_MONTH);

        $date = $year . '-' . $month . '-' . $day;
        //$date = \IntlDateFormatter::formatObject($this->calendar, 'YYYY-MM-dd');
        return $date;
    }

    /**
     * Get date (based on ISO format hh:mm:ss)
     *
     * @return string
     */
    public function getTime()
    {
        $hour = $this->formatField(\IntlCalendar::FIELD_HOUR_OF_DAY);
        $minute = $this->formatField(\IntlCalendar::FIELD_MINUTE);
        $second = $this->formatField(\IntlCalendar::FIELD_SECOND);

        $time = $hour . ':' . $minute . ':' . $second;
        //$time = \IntlDateFormatter::formatObject($this->calendar, 'HH:mm:ss');
        return $time;
    }

    /**
     * Get date and time (based on ISO format yyyy-mm-dd hh:mm:ss)
     *
     * @return string
     */
    public function getDateTime()
    {
        $dateTime = $this->getDate() . ' ' . $this->getTime();
        //$dateTime = \IntlDateFormatter::formatObject($this->calendar, 'yyyy-MM-dd HH:mm:ss');
        return $dateTime;
    }

    /**
     * Get timezone name
     *
     * @return string
     */
    public function getTimeZone()
    {
        return $this->calendar->getTimeZone()->getDisplayName();
    }

    /**
     * Get epoch timestamp (UTC 00:00)
     *
     * @return int
     */
    public function getTimestamp()
    {
        return (int) ($this->calendar->getTime() / 1000);
    }

    /**
     * Get calendar type
     *
     * @return string
     */
    public function getType()
    {
        return $this->calendar->getType();
    }

    /**
     * Object to string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getDateTime();
    }

    /**
     * Magic methods for some set, get, add methods
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $helperName = array('add', 'set', 'get');

        foreach ($helperName as $helper) {
            if (strpos($name, $helper) === 0) {
                $helper = $this->getHelper($helper);

                return $helper($name, $arguments);
            }
        }

        return null;
    }

    public function __get($name)
    {
        return $this->getHelper($name);
    }

    /**
     * Get Gregorian related year
     *
     * @return int
     */
    public function getGregorianRelatedYear()
    {
        $year = $this->getField(\IntlCalendar::FIELD_EXTENDED_YEAR);
        $calendar = $this->getType();

        $year += $this->relatedYearAdjust[$calendar];
        if (strpos($calendar, 'islamic') !== false) {
            $year = $this->islamicYearToGregorianYear($year);
        }

        return $year;
    }

    /**
     * Get extended year from Gregorian related year
     *
     * @param int $year
     *
     * @return int
     */
    protected function getExtendedYearFromGregorianRelatedYear($year)
    {
        $year = (int) $year;
        $calendar = $this->getType();

        $year -= $this->relatedYearAdjust[$calendar];
        if (strpos($calendar, 'islamic') !== false) {
            $year = $this->gregorianYearToIslamicYear($year);
        }

        return $year;
    }

    /**
     * Set Gregorian related year
     *
     * @param int $year
     *
     * @return DateTime
     */
    public function setGregorianRelatedYear($year)
    {
        return $this->setField(
            \IntlCalendar::FIELD_EXTENDED_YEAR,
            $this->getExtendedYearFromGregorianRelatedYear($year)
        );
    }

    /**
     * Get value of field
     *
     * @param int $field
     *
     * @return int
     */
    public function getField($field)
    {
        $value = $this->calendar->get($field);

        if ($field === \IntlCalendar::FIELD_MONTH) {
            $value++;
        }

        return $value;
    }

    /**
     * Set value of field
     *
     * @param int $field
     * @param int $value
     *
     * @return boolean
     */
    public function setField($field, $value)
    {
        $value = (int) $value;

        if ($field === \IntlCalendar::FIELD_MONTH) {
            $value--;
        }

        if ($this->isValidFieldValue($field, $value)) {
            return $this->calendar->set($field, $value);
        }

        return false;
    }

    public function getMaximumValueOfField($field)
    {
        return $this->calendar->getMaximum($field);
    }

    public function getMinimumValueOfField($field)
    {
        return $this->calendar->getMinimum($field);
    }

    public function getActualMaximumValueOfField($field)
    {
        return $this->calendar->getActualMaximum($field);
    }

    public function getActualMinimumValueOfField($field)
    {
        return $this->calendar->getActualMinimum($field);
    }

    public function getLeastMaximumValueOfField($field)
    {
        return $this->calendar->getLeastMaximum($field);
    }

    public function getGreatestMinimumValueOfField($field)
    {
        return $this->calendar->getGreatestMinimum($field);
    }

    /**
     * Add or sub value of field
     *
     * @param int $field
     * @param int $amount
     *
     * @return DateTime
     */
    public function addField($field, $amount)
    {
        $amount = (int) $amount;
        $this->calendar->add($field, $amount);

        return $this;
    }

    public function isValidFieldValue($field, $value)
    {
        $max = $this->calendar->getMaximum($field);
        $min = $this->calendar->getMinimum($field);

        if ($value < $min || $value > $max) {
            return false;
        }

        return true;
    }

    /**
     * Format field
     *
     * @param int $field
     *
     * @return string
     */
    protected function formatField($field)
    {
        $pad = array(
            \IntlCalendar::FIELD_YEAR => 4,
            \IntlCalendar::FIELD_MONTH => 2,
            \IntlCalendar::FIELD_DAY_OF_MONTH => 2,
            \IntlCalendar::FIELD_HOUR_OF_DAY => 2,
            \IntlCalendar::FIELD_MINUTE => 2,
            \IntlCalendar::FIELD_SECOND => 2
        );

        return str_pad($this->getField($field), $pad[$field], '0', STR_PAD_LEFT);
    }

    /**
     * Get calendar
     *
     * @param string $calendar
     *
     * @return string
     */
    protected function getCalendarType($calendar)
    {
        if (!isset(static::$calendars[$calendar])) {
            $calendar = 'gregorian';
        }

        return $calendar;
    }

    /**
     * Get Gregorian related year from Islamic calendar year
     *
     * @param int $islamicYear
     *
     * @return int
     */
    protected function islamicYearToGregorianYear($islamicYear)
    {
        $cycle = ($islamicYear - 1396) / 67 - 1;
        $offset = -($islamicYear - 1396) % 67;
        $shift = 2 * $cycle + (($offset <= 33) ? 1 : 0);

        if ($islamicYear >= 1397) {
            $cycle = ($islamicYear - 1397) / 67;
            $offset = ($islamicYear - 1397) % 67;
            $shift = 2 * $cycle + (($offset >= 33) ? 1 : 0);
        }

        return $islamicYear + 579 - $shift;
    }

    /**
     * Get Islamic year from Gregorian related year
     *
     * @param int $gregorianYear
     *
     * @return int
     */
    protected function gregorianYearToIslamicYear($gregorianYear)
    {
        $cycle = ($gregorianYear - 1977) / 65;
        $offset = ($gregorianYear - 1977) % 65;
        $shift = 2 * $cycle + (($offset >= 32)? 1: 0);

        if ($gregorianYear < 1977) {
            $cycle = ($gregorianYear - 1976) / 65 - 1;
            $offset = -($gregorianYear - 1976) % 65;
            $shift = 2 * $cycle + (($offset <= 32)? 1: 0);
        }

        return $gregorianYear - 579 + $shift;
    }
}
