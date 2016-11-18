<?php
// This file is part of Stack - https://stack.maths.ed.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version information for the qformat_mapleta plugin.
 *
 * @package   qformat_mapleta
 * @copyright 2016 The University of Edinburgh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2016111600;
$plugin->requires  = 2014051200; // Moodle 2.7.0 is required.
$plugin->cron      = 0;
$plugin->component = 'qformat_mapleta';
$plugin->maturity  =  MATURITY_ALPHA;
$plugin->release   = '0.1 for Moodle 2.6+';

$plugin->dependencies = array(
    'qtype_stack' => 2014092500,
);
