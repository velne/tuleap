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

class Search_SearchWiki {
    const NAME = 'wiki';

    const SEARCH_PAGENAME_EN = 'FullTextSearch';
    const SEARCH_PAGENAME_FR = 'RechercheEnTexteIntégral';

    /**
     * @var WikiDao
     */
    private $dao;


    public function __construct(WikiDao $dao) {
        $this->dao = $dao;
    }

    public function search(Search_SearchQuery $query) {
        if ($query->getProject()->isError()) {
            return;
        }

        $search_page = self::SEARCH_PAGENAME_EN;
        if ($this->dao->searchLanguage($query->getProject()->getId()) == 'fr_FR') {
            $search_page = self::SEARCH_PAGENAME_FR;
        }

        $GLOBALS['Response']->redirect('/wiki/index.php?group_id=' . $query->getProject()->getId() . '&pagename=' . $search_page . '&s=' . urlencode($query->getWords()));
    }
}
