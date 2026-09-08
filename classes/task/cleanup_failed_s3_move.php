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

use moodle_exception;

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
        global $CFG;

        mtrace('Execute cleanup_failed_s3_move task.');

        $contenthash = \filter_poodll\poodlltools::fetch_placeholder_hash('audio');
        $today = \core\di::get(\core\clock::class)->now()->setTime(0, 0);
        $timestamp = $today->getTimestamp();

        try {
            $placeholderfiles = $this->get_placeholder_files($contenthash, $timestamp);
        }
        catch (moodle_exception $exception) {
            $errormessage = $exception->getMessage();
            mtrace("ERROR: Could not get placeholder files: $errormessage.");
            return;
        }

        if (count($placeholderfiles) == 0) {
            mtrace('No placeholder files to be replaced.');
            return;
        }

        foreach ($placeholderfiles as $placeholder) {
            try {
                $converteddrafts = $this->get_converted_draft_files($placeholder->filename, $contenthash, $timestamp);
            }
            catch (moodle_exception $exception) {
                $errormessage = $exception->getMessage();
                mtrace("ERROR: Could not get converted draft files for {$placeholder->filename}: $errormessage.");
                continue;
            }

            $totalconverteddrafts = count($converteddrafts);
            if ($totalconverteddrafts == 0) {
                mtrace("Could not find converted draft files for {$placeholder->filename}.");
                continue;
            }
            else if ($totalconverteddrafts > 1) {
                mtrace("ERROR: Multiple converted draft files found for {$placeholder->filename}.");
                continue;
            }

            $converteddraft = $converteddrafts[0];
            $tempfilepath = $CFG->tempdir . "/" . $placeholder->filename;

            try {
                \filter_poodll\poodlltools::replace_placeholderfile_in_moodle($converteddraft, $placeholder, $tempfilepath);
                mtrace("Updated $placeholder->filename:
                 component: $placeholder->component
                 filearea: $placeholder->filearea
                 itemid: $placeholder->itemid");
            }
            catch (moodle_exception $exception) {
                $errormessage = $exception->getMessage();
                mtrace("ERROR: failed to replace $placeholder->filename: $errormessage");
            }
        }
    }

    /**
     * Get files using the placeholder content hash that are not user draft files.
     * @param string $contenthash - Placeholder content hash.
     * @param int $timestamp - Look for files created since this timestamp.
     * 
     * @return array
     */
    private function get_placeholder_files($contenthash, $timestamp) {
        global $DB;

        $basefilename = 'poodllfile';
        $component = 'user';

        $like = $DB->sql_like('f.filename', ':basefilename');
        $query = "SELECT * FROM {files} f
            WHERE $like
            AND f.contenthash = :contenthash
            AND f.component != :component
            AND f.timecreated >= :today";

        $params = [
           'basefilename' => "%$basefilename%",
           'contenthash' => $contenthash,
           'component' => $component,
           'today' => $timestamp
        ];

        return $DB->get_records_sql($query, $params);
    }

    /**
     * Get draft files for a given poodll file that are not using the placeholder content hash.
     * @param string $filename - Name of poodll file.
     * @param int $timestamp - Limit query to files created since timestamp.
     * 
     * @return array
     */
    private function get_converted_draft_files($filename, $contenthash, $timestamp) {
        global $DB;

        $component = 'user';
        $filearea = 'draft';

        $query = "SELECT * FROM {files} f
            WHERE f.filename = :filename
            AND f.contenthash != :contenthash
            AND f.component = :component
            AND f.filearea = :filearea
            AND f.timecreated >= :today";

        $params = [
           'filename' => $filename,
           'contenthash' => $contenthash,
           'component' => $component,
           'filearea' => $filearea,
           'today' => $timestamp
        ];

        return $DB->get_records_sql($query, $params);
    }

}