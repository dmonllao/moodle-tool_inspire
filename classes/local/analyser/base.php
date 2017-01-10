<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_research\local\analyser;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {

    protected $modelid;

    protected $target;
    protected $indicators;
    protected $rangeprocessors;

    protected $options;

    public function __construct($modelid, $target, $indicators, $rangeprocessors) {
        $this->modelid = $modelid;
        $this->target = $target;
        $this->indicators = $indicators;
        $this->rangeprocessors = $rangeprocessors;

        // Checks if the analyser satisfies the indicators requirements.
        $this->check_indicators_requirements();
    }

    /**
     * This function is used to check calculables needs against the info provided in the analyser rows.
     *
     * @return string[]
     */
    abstract function rows_info();

    /**
     * This function returns the list of rows that will be calculated.
     *
     * @param \tool_research\analysable $analysable
     * @return array
     */
    abstract function get_rows(\tool_research\analysable $analysable);

    /**
     * Main analyser method which processes the site analysables.
     *
     * \tool_research\local\analyser\by_course and \tool_research\local\analyser\sitewide are implementing
     * this method returning site courses (by_course) and the whole system (sitewide) as analysables.
     * In most of the cases you should have enough extending from one of these classes so you don't need
     * to reimplement this method.
     *
     * @param array $options
     * @return array Array containing a status codes for each analysable and a list of files, one for each range processor
     */
    abstract function analyse($options);

    /**
     * Checks if the analyser satisfies all the model indicators requirements.
     *
     * @throws requirements_exception
     * @return void
     */
    protected function check_indicators_requirements() {

        $rowsinfo = $this->rows_info();

        foreach ($this->indicators as $indicator) {
            foreach ($indicator::get_requirements() as $requirement) {
                if (empty($rowsinfo[$requirement])) {
                    throw new \tool_research\requirements_exception($indicator->get_codename() . ' indicator requires ' .
                        $requirement . ' which is not provided by ' . get_class($this));
                }
            }
        }
    }

    /**
     * Processes an analysable
     *
     * This method returns the general analysable status and an array of files by range processor
     * all error & status reporting at analysable + range processor level should not be returned
     * but shown, through mtrace(), debugging() or through exceptions depending on the case.
     *
     * @param \tool_research\analysable $analysable
     * @return array Analysable general status code AND (files by range processor OR error code)
     */
    public function process_analysable($analysable) {

        $files = [];
        $message = null;

        $result = $this->target->check_analysable($analysable);
        if ($result !== true) {
            return [
                \tool_research\model::ANALYSABLE_STATUS_INVALID_FOR_TARGET,
                $result
            ];
        }

        foreach ($this->rangeprocessors as $rangeprocessor) {
            // Until memory usage shouldn't be specially intensive, process_analysable should
            // be where things start getting serious, memory usage at this point should remain
            // more or less stable (only new \stored_file objects) as all objects should be
            // garbage collected by php.

            if ($file = $this->process_range($rangeprocessor, $analysable)) {
                $files[$rangeprocessor->get_codename()] = $file;
            }
        }

        if (empty($files)) {
            // Flag it as invalid if the analysable wasn't valid for any of the range processors.
            $status = \tool_research\model::ANALYSABLE_STATUS_INVALID_FOR_RANGEPROCESSORS;
            $message = 'Analysable not valid for any of the range processors';
        } else {
            $status = \tool_research\model::ANALYSE_OK;
        }

        // TODO This looks confusing 1 for range processor? 1 for all? Should be 1 for analysable.
        return [
            $status,
            $files,
            $message
        ];
    }

    protected function process_range($rangeprocessor, $analysable) {

        mtrace($rangeprocessor->get_codename() . ' analysing analysable with id ' . $analysable->get_id());

        $rangeprocessor->set_analysable($analysable);
        if (!$rangeprocessor->is_valid_analysable()) {
            mtrace(' - Invalid analysable for this processor');
            return false;
        }

        $recentlyanalysed = $this->recently_analysed($rangeprocessor->get_codename(), $analysable->get_id());
        if ($recentlyanalysed && empty($this->options['analyseall'])) {
            // Returning the previously created file.
            mtrace(' - Already analysed');
            return \tool_research\dataset_manager::get_analysable_file($this->modelid, $analysable->get_id(), $rangeprocessor->get_codename());
        }

        // What is a row is defined by the analyser, it can be an enrolment, a course, a user, a question
        // attempt... it is on what we will base indicators calculations.
        $rows = $this->get_rows($analysable);
        $rangeprocessor->set_rows($rows);

        $dataset = new \tool_research\dataset_manager($this->modelid, $analysable->get_id(), $rangeprocessor->get_codename());

        // Flag the model + analysable + rangeprocessor as being analysed (prevent concurrent executions).
        $dataset->init_process();

        // Here we start the memory intensive process that will last until $data var is
        // unset (until the method is finished basically).
        $data = $rangeprocessor->calculate($this->target, $this->indicators);

        if (!$data) {
            mtrace(' - No data available');
            return false;
        }

        // Write all calculated data to a file.
        $file = $dataset->store($data);

        // Flag the model + analysable + rangeprocessor as analysed.
        $dataset->close_process();

        mtrace(' - Successfully analysed');
        return $file;
    }

    protected function recently_analysed($rangeprocessorcodename, $analysableid) {
        $prevrun = \tool_research\dataset_manager::get_run($this->modelid, $analysableid, $rangeprocessorcodename);
        if (!$prevrun) {
            return false;
        }

        if (time() > $prevrun->timecompleted + WEEKSECS) {
            return false;
        }

        return true;
    }
}
