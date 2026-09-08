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

namespace filter_poodll\task;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * This is a scheduled task for cleaning up after adhoc_s3_move when it fails to copy assignment/quiz submissions.
 *
 * @package   filter_poodll
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_failed_s3_move extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task_cleanup_failed_s3_move', 'filter_poodll');
    }

    public function execute() {
        global $DB;

        mtrace('Execute cleanup_failed_s3_move task.');

        $basefilename = 'poodllfile';
        $contenthash = \filter_poodll\poodlltools::fetch_placeholder_hash('audio');
        $component = 'user';

        $like = $DB->sql_like('f.filename', ':basefilename');
        $query = "SELECT * FROM {files} f
        WHERE $like
        AND f.contenthash = :contenthash
        AND f.component != :component";

        $params = [
           'basefilename' => $basefilename,
           'contenthash' => $contenthash,
           'component' => $component
        ];

        $placeholderfiles = $DB->get_records_sql($query, $params);
        mtrace(var_dump($placeholderfiles));
    }

}