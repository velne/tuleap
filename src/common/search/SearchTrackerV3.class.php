<?php
/**
 * Copyright (c) Enalean, 2014. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

class Search_SearchTrackerV3 {
    const NAME = 'tracker';

    /**
     * @var ArtifactDao
     */
    private $dao;


    public function __construct(ArtifactDao $dao) {
        $this->dao = $dao;
    }

    public function search(Search_SearchQuery $query) {
        include_once('www/tracker/include/ArtifactTypeHtml.class.php');
        include_once('www/tracker/include/ArtifactHtml.class.php');

        $project = $query->getProject();
        if ($project->isError()) {
            return;
        }
        $group_id = $project->getId();
        $words    = $query->getWords();
        $exact    = $query->getExact();
        $offset   = $query->getOffset();
        $atid     = $query->getTrackerV3Id();

        ob_start();
        //
        //      Create the ArtifactType object
        //
        $ath = new ArtifactTypeHtml($project, $atid);
        if (!$ath || !is_object($ath)) {
            exit_error($GLOBALS['Language']->getText('global', 'error'), $GLOBALS['Language']->getText('global', 'error'));
        }
        if ($ath->isError()) {
            exit_error($GLOBALS['Language']->getText('global', 'error'), $ath->getErrorMessage());
        }
        // Check if this tracker is valid (not deleted)
        if (!$ath->isValid()) {
            exit_error($GLOBALS['Language']->getText('global', 'error'), $GLOBALS['Language']->getText('global', 'error'));
        }

        $results = $this->dao->searchGlobal($words, $exact, $offset, $atid, UserManager::instance()->getCurrentUser()->getUgroups($group_id, $atid));
        $rows_returned = $this->dao->foundRows();

        if ($rows_returned < 1) {
            echo '<H2>' . $GLOBALS['Language']->getText('search_index', 'no_match_found', htmlentities(stripslashes($words), ENT_QUOTES, 'UTF-8')) . '</H2>';
        } else {
            // Create field factory
            $art_field_fact = new ArtifactFieldFactory($ath);

            echo '<H3>' . $GLOBALS['Language']->getText('search_index', 'search_res', array(htmlentities(stripslashes($words), ENT_QUOTES, 'UTF-8'), $rows_returned)) . "</H3><P>\n";

            $title_arr = array();

            $summary_field = $art_field_fact->getFieldFromName("summary");
            if ($summary_field->userCanRead($group_id, $atid))
                $title_arr[] = $GLOBALS['Language']->getText('search_index', 'artifact_summary');
            $submitted_field = $art_field_fact->getFieldFromName("submitted_by");
            if ($submitted_field->userCanRead($group_id, $atid))
                $title_arr[] = $GLOBALS['Language']->getText('search_index', 'submitted_by');
            $date_field = $art_field_fact->getFieldFromName("open_date");
            if ($date_field->userCanRead($group_id, $atid))
                $title_arr[] = $GLOBALS['Language']->getText('search_index', 'date');
            $status_field = $art_field_fact->getFieldFromName("status_id");
            if ($status_field->userCanRead($group_id, $atid))
                $title_arr[] = $GLOBALS['Language']->getText('global', 'status');

            echo html_build_list_table_top($title_arr);

            echo "\n";


            $art_displayed = 0;
            $rows = 0;
            foreach ($results as $arr) {
                $rows++;
                $curArtifact = new Artifact($ath, $arr['artifact_id']);
                if ($curArtifact->isStatusClosed($curArtifact->getStatusID())) {
                    $status = $GLOBALS['Language']->getText('global', 'closed');
                } else {
                    $status = $GLOBALS['Language']->getText('global', 'open');
                }
                // Only display artifacts that the user is allowed to see
                if ($curArtifact->userCanView(user_getid())) {
                    print "\n<TR class=\"" . html_get_alt_row_color($art_displayed) . "\">";
                    if ($summary_field->userCanRead($group_id, $atid))
                        print "<TD><A HREF=\"/tracker/?group_id=$group_id&func=detail&atid=$atid&aid="
                                . $arr['artifact_id'] . "\"><IMG SRC=\"" . util_get_image_theme('msg.png') . "\" BORDER=0 HEIGHT=12 WIDTH=10> "
                                . $arr['summary'] . "</A></TD>";
                    if ($submitted_field->userCanRead($group_id, $atid))
                        print "<TD>" . $arr['user_name'] . "</TD>";
                    if ($date_field->userCanRead($group_id, $atid))
                        print "<TD>" . format_date($GLOBALS['Language']->getText('system', 'datefmt'), $arr['open_date']) . "</TD>";
                    if ($status_field->userCanRead($group_id, $atid))
                        print "<TD>" . $status . "</TD>";
                    print "</TR>";
                    $art_displayed++;
                    if ($art_displayed > 24) {
                        break;
                    } // Only display 25 results.
                }
            }
            echo "</TABLE>\n";
        }

        return new Search_SearchTrackerV3ResultPresenter(ob_get_clean());
    }
}
