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
 * Python machine learning processor
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace ml_python;

defined('MOODLE_INTERNAL') || die();

/**
 * Research tool site manager.
 *
 * @package   tool_research
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class processor {

    function evaluate_dataset($datasetpath, $outputdir) {

        $absolutescriptpath = escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR .
            'check-classification-singleclass.py');

        $cmd = 'python ' . $absolutescriptpath . ' ' .
            escapeshellarg($datasetpath) . ' ' .
            escapeshellarg($validation) . ' ' .
            escapeshellarg($deviation) . ' ' .
            escapeshellarg($nruns);

        $output = null;
        $exitcode = null;
        $result = exec($cmd, $output, $exitcode);

        if (!$result) {
            throw new \moodle_exception('errornomlresults', 'tool_research');
        }


        if (!$resultobj = json_decode($result)) {
            throw new \moodle_exception('errormlwrongformat', 'tool_research', '', json_last_error_msg());
        }

        return $resultobj;
    }
}