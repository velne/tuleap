<?php
/**
 * Copyright (c) STMicroelectronics, 2004-2011. All rights reserved
 *
 * This file is a part of Codendi.
 *
 * Codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */
require_once 'Zend/Mail.php';

class Codendi_Mail {

    const FORMAT_TEXT  = 0;
    const FORMAT_HTML  = 1;

    var $mailHtml;

    /**
     * Constructor
     */
    function __construct() {
        $this->mailHtml = new Zend_Mail();
    }

    /**
     * Return Zend_Mail object
     *
     * @return Zend_Mail object
     */
    function getMailHtml() {
        return $this->mailHtml;
    }

    function setTo($email) {
        $this->mailHtml->addTo($email);
    }

    function setFrom($email) {
        $this->mailHtml->setFrom($email);
    }

    function setSubject($subject) {
        $this->mailHtml->setSubject($subject);
    }

    function setBcc($email) {
        $this->mailHtml->addBcc($email);
    }

    function setBody($message, $format = FORMAT_TEXT) {
        if ($format == Codendi_Mail::FORMAT_HTML) {
            $this->mailHtml->setBodyHtml(stripslashes($message));
        } else {
            $this->mailHtml->setBodyText(stripslashes($message));
        }
    }

    function send() {
        $this->mailHtml->send();
    }
}

?>
