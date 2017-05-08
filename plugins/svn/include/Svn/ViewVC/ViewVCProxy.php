<?php
/**
 * Copyright (c) Enalean, 2015-2017. All rights reserved
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/
 */

namespace Tuleap\Svn\ViewVC;

use HTTPRequest;
use Tuleap\Svn\Repository\RepositoryManager;
use ProjectManager;
use Project;
use ForgeConfig;
use CrossReferenceFactory;
use ReferenceManager;
use Codendi_HTMLPurifier;

class ViewVCProxy
{

    /**
     * @var AccessHistorySaver
     */
    private $access_history_saver;
    private $repository_manager;
    private $project_manager;

    public function __construct(
        RepositoryManager $repository_manager,
        ProjectManager $project_manager,
        AccessHistorySaver $access_history_saver
    ) {
        $this->repository_manager   = $repository_manager;
        $this->project_manager      = $project_manager;
        $this->access_history_saver = $access_history_saver;
    }

    private function displayViewVcHeader(HTTPRequest $request)
    {
        $request_uri = $request->getFromServer('REQUEST_URI');

        if (strpos($request_uri, "annotate=") !== false) {
            return true;
        }

        if ($this->isViewingPatch($request) ||
            $this->isCheckoutingFile($request) ||
            strpos($request_uri, "view=graphimg") !== false ||
            strpos($request_uri, "view=redirect_path") !== false ||
            // ViewVC will redirect URLs with "&rev=" to "&revision=". This is needed by Hudson.
           strpos($request_uri, "&rev=") !== false ) {
            return false;
        }

        if (strpos($request_uri, "/?") === false &&
            strpos($request_uri, "&r1=") === false &&
            strpos($request_uri, "&r2=") === false &&
            strpos($request_uri, "view=") === false
        ) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function isViewingPatch(HTTPRequest $request)
    {
        $request_uri = $request->getFromServer('REQUEST_URI');
        return strpos($request_uri, "view=patch") !== false;
    }

    /**
     * @return bool
     */
    private function isCheckoutingFile(HTTPRequest $request)
    {
        $request_uri = $request->getFromServer('REQUEST_URI');
        return strpos($request_uri, "view=co") !== false;
    }

    private function buildQueryString(HTTPRequest $request)
    {
        parse_str($request->getFromServer('QUERY_STRING'), $query_string_parts);
        unset($query_string_parts['roottype']);
        return http_build_query($query_string_parts);
    }

    private function escapeStringFromServer(HTTPRequest $request, $key)
    {
        $string = $request->getFromServer($key);

        return escapeshellarg($string);
    }

    private function setLocaleOnFileName($path)
    {
        $current_locales = setlocale(LC_ALL, "0");
        // to allow $path filenames with French characters
        setlocale(LC_CTYPE, "en_US.UTF-8");

        $encoded_path = escapeshellarg($path);
        setlocale(LC_ALL, $current_locales);

        return $encoded_path;
    }

    private function setLocaleOnCommand($command, &$return_var)
    {
        ob_start();
        putenv("LC_CTYPE=en_US.UTF-8");
        passthru($command, $return_var);

        return ob_get_clean();
    }

    private function getViewVcLocationHeader($location_line)
    {
        // Now look for 'Location:' header line (e.g. generated by 'view=redirect_pathrev'
        // parameter, used when browsing a directory at a certain revision number)
        $location_found = false;

        while ($location_line && !$location_found && strlen($location_line) > 1) {
            $matches = array();

            if (preg_match('/^Location:(.*)$/', $location_line, $matches)) {
                return $matches[1];
            }

            $location_line = strtok("\n\t\r\0\x0B");
        }

        return false;
    }

    private function getPurifier()
    {
        return Codendi_HTMLPurifier::instance();
    }

    public function getContent(HTTPRequest $request)
    {
        $user = $request->getCurrentUser();
        if ($user->isAnonymous()) {
            return $GLOBALS['Language']->getText('plugin_svn', 'anonymous_browse_access_denied');
        }

        $project    = $this->project_manager->getProject($request->get('group_id'));
        $repository = $this->repository_manager->getById($request->get('repo_id'), $project);

        $this->access_history_saver->saveAccess($user, $repository);

        //this is very important. default path must be /
        $path = $request->getPathInfoFromFCGI();

        $command = 'HTTP_COOKIE='.$this->escapeStringFromServer($request, 'HTTP_COOKIE').' '.
            'REMOTE_USER=' . escapeshellarg($user->getUserName()) . ' '.
            'PATH_INFO='.$this->setLocaleOnFileName($path).' '.
            'QUERY_STRING='.escapeshellarg($this->buildQueryString($request)).' '.
            'SCRIPT_NAME='.$this->escapeStringFromServer($request, 'SCRIPT_NAME').' '.
            'HTTP_ACCEPT_ENCODING='.$this->escapeStringFromServer($request, 'HTTP_ACCEPT_ENCODING').' '.
            'HTTP_ACCEPT_LANGUAGE='.$this->escapeStringFromServer($request, 'HTTP_ACCEPT_LANGUAGE').' '.
            'TULEAP_PROJECT_NAME='.escapeshellarg($repository->getProject()->getUnixNameMixedCase()).' '.
            'TULEAP_REPO_NAME='.escapeshellarg($repository->getName()).' '.
            'TULEAP_REPO_PATH='.escapeshellarg($repository->getSystemPath()).' '.
            ForgeConfig::get('tuleap_dir').'/'.SVN_BASE_URL.'/bin/viewvc-epel.cgi 2>&1';

        $content = $this->setLocaleOnCommand($command, $return_var);

        if ($return_var === 128) {
            return $this->getPermissionDeniedError($project);
        }

        list($headers, $body) = http_split_header_body($content);

        $content_type_line   = strtok($content, "\n\t\r\0\x0B");

        $content = substr($content, strpos($content, $content_type_line));

        $location_line   = strtok($content, "\n\t\r\0\x0B");
        $viewvc_location = $this->getViewVcLocationHeader($location_line);

        if ($viewvc_location) {
            $GLOBALS['Response']->redirect($viewvc_location);
        }

        $parse = $this->displayViewVcHeader($request);
        if ($parse) {
            //parse the html doc that we get from viewvc.
            //remove the http header part as well as the html header and
            //html body tags
            $cross_ref = "";
            if ($request->get('revision')) {
                $crossref_fact = new CrossReferenceFactory(
                    $repository->getName()."/".$request->get('revision'),
                    ReferenceManager::REFERENCE_NATURE_SVNREVISION,
                    $repository->getProject()->getID()
                );
                $crossref_fact->fetchDatas();
                if ($crossref_fact->getNbReferences() > 0) {
                    $cross_ref .= '<div class="viewvc-epel-references">';
                    $cross_ref .= '<h4>'.$GLOBALS['Language']->getText('cross_ref_fact_include', 'references').'</h4>';
                    $cross_ref .= $crossref_fact->getHTMLDisplayCrossRefs();
                    $cross_ref .= '</div>';
                }

                $body = str_replace(
                    "<h4>Modified files</h4>",
                    $cross_ref . "<h4>Modified files</h4>",
                    $body
                );
            }

            // Now insert references, and display
            return util_make_reference_links(
                $body,
                $request->get('group_id')
            );
        } else {
            if ($this->isViewingPatch($request)) {
                header('Content-Type: text/plain');
            } else {
                header('Content-Type: application/octet-stream');
            }
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: attachment');

            echo $body;
            exit();
        }
    }

    private function getPermissionDeniedError(Project $project)
    {
        $purifier = $this->getPurifier();
        $url      = session_make_url("/project/memberlist.php?group_id=" . urlencode($project->getID()));

        $title  = $purifier->purify($GLOBALS['Language']->getText('svn_viewvc', 'access_denied'));
        $reason = $GLOBALS['Language']->getText('svn_viewvc', 'acc_den_comment', $purifier->purify($url));

        return '<link rel="stylesheet" href="/viewvc-theme-tuleap/style.css">
            <div class="tuleap-viewvc-header">
                <h3>'. $title .'</h3>
                '. $reason .'
            </div>';
    }

    public function getBodyClass()
    {
        return 'viewvc-epel';
    }
}
