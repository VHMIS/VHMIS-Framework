<?php

namespace Vhmis\DateTime\DateRepeat;

use Vhmis\DateTime\DateTime;

class Rule
{
    /**
     * Repeate by, 4-7 for day, week, month, year
     * Default is 4 (repeat by day)
     *
     *
     * @var int
     */
    protected $by = 4;
    protected $baseDate;
    protected $baseDay;
    protected $baseWeekday;
    protected $baseMonth;
    protected $endDate;

    /**
     * Times of repeat (including base date)
     * Default is 2
     *
     * @var int
     */
    protected $times = 2;

    /**
     * Frequency of repeat
     * Default is 1
     *
     * @var int
     */
    protected $freq = 1;

    /**
     * Type of repeated date in month
     *
     * @var string
     */
    protected $type = 'day';

    /**
     *
     * @var int[]
     */
    protected $repeatedWeekdays;

    /**
     *
     * @var int
     */
    protected $repeatedDay;

    /**
     *
     * @var int
     */
    protected $repeatedDayPosition;

    /**
     *
     * @var int[]
     */
    protected $repeatedDays;

    /**
     *
     * @var int[]
     */
    protected $repeatedMonths;

    /**
     * DateTime helper object
     *
     * @var DateTime
     */
    protected $date;

    public function __construct()
    {
        $this->date = new DateTime;
    }

    public function setRepeatByDay()
    {
        $this->by = 4;

        return $this;
    }

    public function setRepeatByWeek()
    {
        $this->by = 5;

        return $this;
    }

    public function setRepeatByMonth()
    {
        $this->by = 6;

        return $this;
    }

    public function setRepeatByYear()
    {
        $this->by = 7;

        return $this;
    }

    /**
     *
     * @param string $date
     *
     * @return \Vhmis\DateTime\DateRepeat\Rule
     *
     * @throws \InvalidArgumentException
     */
    public function setBaseDate($date)
    {
        if ($this->validateISODate($date) === false) {
            throw new \InvalidArgumentException('Date is not valid');
        }

        $this->baseDate = $date;

        $this->baseWeekday = (int) $this->date->modify($date)->format('w');
        $this->baseDay = (int) $this->date->modify($date)->format('d');
        $this->baseMonth = (int) $this->date->modify($date)->format('m');

        // auto
        $this->repeatedDay = $this->baseWeekday;
        $this->repeatedDayPosition = ceil($this->baseDay / 7);

        return $this;
    }

    /**
     *
     * @param string $date
     *
     * @return \Vhmis\DateTime\DateRepeat\Rule
     *
     * @throws \InvalidArgumentException
     */
    public function setEndDate($date)
    {
        if ($this->validateISODate($date) === false) {
            throw new \InvalidArgumentException('Date is not valid');
        }

        $this->endDate = $date;

        return $this;
    }

    /**
     * Set times of repeat
     *
     * @param int $times
     *
     * @return \Vhmis\DateTime\DateRepeat\Rule
     *
     * @throws \InvalidArgumentException
     */
    public function setRepeatTimes($times)
    {
        $this->times = (int) $times;

        if ($this->times < 2) {
            throw new \InvalidArgumentException('Must be int and greater than 1');
        }

        return $this;
    }

    /**
     * Set frequency
     *
     * @param int $freq
     *
     * @return \Vhmis\DateTime\DateRepeat\Rule
     *
     * @throws \InvalidArgumentException
     */
    public function setFrequency($freq)
    {
        $this->freq = (int) $freq;

        if ($this->freq < 1) {
            throw new \InvalidArgumentException('Must be int and greater than 0');
        }

        return $this;
    }

    /**
     * Set repeated weekdays
     * Use 0-6 for sunday to staturday
     * Accept int array or int string spec by ','
     *
     * @param int[]|string $weekdays
     *
     * @return \Vhmis\DateTime\DateRepeat\Week
     *
     * @throws \InvalidArgumentException
     */
    public function setRepeatWeekdays($weekdays)
    {
        $this->repeatedWeekdays = $this->fixIntArray($weekdays, 0, 6);

        return $this;
    }

    /**
     * Set type of repeat
     *
     * - Repeat by day in month: 2nd, 3rd ....
     * - Repeat by relative day in month: first Monday, second Tuesday, last day ...
     */
    public function setType($type)
    {
        $this->type = 'day';

        if ($type === 'relative_day') {
            $this->type = 'relative_day';
        }

        return $this;
    }

    /**
     * Set day of repeated day in month
     * 0 - 6 for sunday to saturday, 7 for a common day in month
     *
     * @param int $day
     */
    public function setRepeatedDay($day)
    {
        $this->repeatedDay = $this->fixInt($day, 0, 7);

        return $this;
    }

    /**
     * Set position of day of repeated day in month
     * (for type = relative_day)
     *
     * @param string|int position
     */
    public function setRepeatedDayPosition($position)
    {
        $this->repeatedDayPosition = $this->fixInt($position, 0, 4);

        return $this;
    }

    /**
     * Set repeated days, use 1-31, accept int array or int string spec by ','
     * (for type = month)
     *
     * @param int[]|string $days
     *
     * @return \Vhmis\DateTime\DateRepeat\Week
     *
     * @throws \InvalidArgumentException
     */
    public function setRepeatedDays($days)
    {
        $this->repeatedDays = $this->fixIntArray($days, 0, 31);

        return $this;
    }

    /**
     * Set repeated months, use 1-12, accept int array or int string spec by ','
     *
     * @param int[]|string $months
     *
     * @return \Vhmis\DateTime\DateRepeat\Week
     *
     * @throws \InvalidArgumentException
     */
    public function setRepeatedMonths($months)
    {
        $this->repeatedMonths = $this->fixIntArray($months, 1, 12);

        return $this;
    }

    /**
     * Get valid of rule
     *
     * @return boolean
     */
    public function isValid()
    {
        if ($this->baseDate === null) {
            return false;
        }

        switch ($this->by) {
            case 5:
                return $this->isValidRepeatByWeek();
            case 6:
                return $this->isValidRepeatByMonth();
            case 7:
                return $this->isValidRepeatByYear();
            default:
                return true; // case 4
        }
    }

    public function getInfo()
    {
        return array(
            'by'       => $this->by,
            'base'     => $this->baseDate,
            'end'      => $this->endDate,
            'times'    => $this->times,
            'freq'     => $this->freq,
            'type'     => $this->type,
            'days'     => $this->repeatedDays,
            'weekdays' => $this->repeatedWeekdays,
            'months'   => $this->repeatedMonths,
            'day'      => $this->repeatedDay,
            'position' => $this->repeatedDayPosition
        );
    }

    public function reset()
    {
        $this->baseDate = $this->endDate = null;
        $this->repeatedDay = $this->repeatedDayPosition = $this->repeatedDays = null;
        $this->repeatedMonths = $this->repeatedWeekdays = null;

        $this->by = 4;
        $this->times = 2;
        $this->freq = 1;
        $this->type = 'day';

        return $this;
    }

    protected function isValidRepeatByWeek()
    {
        if ($this->repeatedWeekdays === null) {
            return false;
        }

        if (array_search($this->baseWeekday, $this->repeatedWeekdays) === false) {
            return false;
        }

        return true;
    }

    protected function isValidRepeatByMonth()
    {
        if ($this->type === 'day') {

            if ($this->repeatedDays === null) {
                return false;
            }

            if (array_search($this->baseDay, $this->repeatedDays) === false) {
                return false;
            }

            return true;
        }

        return $this->isValidRelativeDay();
    }

    protected function isValidRepeatByYear()
    {
        if ($this->repeatedMonths === null) {
            return false;
        }

        if (array_search($this->baseMonth, $this->repeatedMonths) === false) {
            return false;
        }

        if ($this->type === 'day') {
            return true;
        }

        return $this->isValidRelativeDay();
    }

    protected function isValidRelativeDay()
    {
        $allDays = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'day');
        $allPositions = array('first', 'second', 'third', 'fourth', 'last');

        $this->date->modify($this->baseDate)->modify(
            $allPositions[$this->repeatedDayPosition] . ' ' . $allDays[$this->repeatedDay] . ' of this month'
        );

        if ($this->baseDate !== $this->date->formatISO(0)) {
            return false;
        }

        return true;
    }

    protected function validateISODate($date)
    {
        $dtObj = DateTime::createFromFormat('Y-m-d', $date);

        return ($dtObj && $dtObj->formatISO(0) == $date);
    }

    /**
     * Check and fix int value
     *
     * @param int $number
     * @param int $min
     * @param int $max
     *
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    protected function fixInt($number, $min, $max)
    {
        $number = (int) $number;
        if ($number < $min || $number > $max) {
            throw new \InvalidArgumentException('Only int from ' . $min . ' - ' . $max);
        }

        return $number;
    }

    /**
     * Check and fix int array
     *
     * @param int[]|string $data
     * @param int          $min
     * @param int          $max
     *
     * @return int[]
     *
     * @throws \InvalidArgumentException
     */
    protected function fixIntArray($data, $min, $max)
    {
        $data = is_string($data) ? explode(',', $data) : $data;

        if (!is_array($data)) {
            throw new \InvalidArgumentException(
                'Only int array or int string spec by `,`. From ' . $min . ' - ' . $max
            );
        }

        // Check
        foreach ($data as &$number) {
            $number = $this->fixInt($number, $min, $max);
        }

        $data = array_unique($data);
        sort($data);

        return $data;
    }
}
