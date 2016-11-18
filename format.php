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
 * This question importer class will import questions in the MapleTA format.
 * However, this is incomplete work in progress.
 *
 * @package   qformat_mapleta
 * @copyright 2016 The University of Edinburgh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/stack/questiontype.php');
require_once($CFG->dirroot . '/question/type/stack/stack/utils.class.php');

class qformat_mapleta extends qformat_default {

    public function provide_import() {
        return true;
    }

    public function provide_export() {
        return false;
    }

    public function readquestions($lines) {
        $data = $this->questionstoformfrom(implode($lines));
        return $data;
    }

    public function mime_type() {
        return 'application/xml';
    }

    protected function text_field($text) {
        return array(
            'text'   => htmlspecialchars(trim($text), ENT_NOQUOTES),
            'format' => FORMAT_HTML,
            'files'  => array(),
        );
    }

    public function readquestion($lines) {
        // This is no longer needed but might still be called by default.php.
        return;
    }

    /**
     * Read MapleTA questions from a string and process it to a list of question arrays
     * @param string $xmlstr MapleTA questions as an XML string
     * @return array containing question arrays
     */
    protected function questionstoformfrom($xmlstr) {

        // Slight hack, since SimpleXMLElement does not like these names...
        $xmlstr = str_replace('<dc:', '<dc', $xmlstr);
        $xmlstr = str_replace('</dc:', '</dc', $xmlstr);

        $root = simplexml_load_string($xmlstr);
        $questions = $root->questions;
        $qarray = $questions->question;

        $result = array();
        $errors = array();
/*
        $types = array();
        foreach ($qarray as $mapletaquestion) {
        	$mode = trim((string) $mapletaquestion->mode);
        	$types[] = $mode;
        }
        print_r($types);
Array
(
    [0] => Non Permuting Multiple Choice
    [1] => Multiple Selection
    [2] => Non Permuting Multiple Choice
    [3] => Non Permuting Multiple Choice
    [4] => Essay
    [5] => Non Permuting Multiple Choice
    [6] => Non Permuting Multiple Choice
    [7] => Multiple Choice
    [8] => Non Permuting Multiple Choice
    [9] => Non Permuting Multiple Choice
    [10] => Non Permuting Multiple Choice
    [11] => Non Permuting Multiple Choice
    [12] => Multiple Choice
    [13] => Essay
    [14] => Non Permuting Multiple Choice
    [15] => Non Permuting Multiple Selection
    [16] => Maple
    [17] => Numeric
    [18] => Multiple Choice
    [19] => Multiple Choice
    [20] => Multiple Selection
    [21] => Multiple Choice
    [22] => Maple
    [23] => Maple
    [24] => Non Permuting Multiple Choice
    [25] => Non Permuting Multiple Choice
    [26] => Non Permuting Multiple Selection
    [27] => Multiple Choice
    [28] => Non Permuting Multiple Choice
    [29] => Multiple Choice
    [30] => Non Permuting Multiple Selection
    [31] => Non Permuting Multiple Choice
    [32] => Non Permuting Multiple Choice
    [33] => Non Permuting Multiple Choice
    [34] => Non Permuting Multiple Choice
)
*/

//        foreach ($qarray as $mapletaquestion) {
        $mapletaquestion = $qarray[22];
            $mode = trim((string) $mapletaquestion->mode);
            $algo = trim((string) $mapletaquestion->algorithm);
            $type = 'stack';
            if ($mode == 'Non Permuting Multiple Choice' || $mode == 'Multiple Selection'
                    || $mode == 'Multiple Choice') {
               $type = 'mcq';
            }
            if ($algo != '') {
               $type = 'stack';
            }

            if ($type == 'mcq') {
                list($question, $err) = $this->questiontomcqformfrom($mapletaquestion);
            } else {
                list($question, $err) = $this->questiontostackformfrom($mapletaquestion);
            }

            $result[] = $question;
            $errors = array_merge($errors, $err);
//        }

        if (!empty($errors)) {
            throw new stack_exception(implode('<br />', $errors));
        }
        return $result;
    }

    /**
     * Process a single question into an array based on a multichoice question.
     * @param SimpleXMLElement $assessmentitem
     * @return the question as an array
     */
    protected function questiontomcqformfrom($assessmentitem) {

        $errors = array();
        $question = new stdClass();
        $question->qtype                 = 'multichoice';
        $question->name                  = (string) $assessmentitem->name;
        $question->questiontext          = (string) $assessmentitem->text;
        $question->questiontextformat    = FORMAT_HTML;
        $question->generalfeedback       = '';
        $question->defaultmark           = 1;
        $question->answernumbering       = 'abc';
        $mode = trim((string) $assessmentitem->mode);

        $question->shuffleanswers        = false;
        if ($mode == $mode = 'Multiple Choice') {
            $question->shuffleanswers = true;
        }
        if ($mode == 'Non Permuting Multiple Choice' || $mode = 'Multiple Choice') {
            $question->single = true;
        } else {
            $question->single = false;
        }

        $correctanswer = (string) $assessmentitem->answer;
        $correctanswer = explode(',', $correctanswer);
        // Assume all correct answers are equally weighted.
        $fraction = 1/count($correctanswer);
        if (count($correctanswer) == 3) {
            $fraction = 0.33;
        }
        $correctanswers = array();
        foreach ($correctanswer as $c) {
            $s = ((int) $c) - 1;
            $correctanswers[$s] = true;
        }

        $qch = $assessmentitem->choices->choice;
        for ($x = 0; $x < count($assessmentitem->choices->choice); $x++) {
            $question->answer[$x]   = array('text' => trim((string) $qch->$x), 'format' => FORMAT_HTML);
            $question->feedback[$x] = array('text' => '', 'format' => FORMAT_HTML);
            $question->fraction[$x] = 0;
            if (array_key_exists($x, $correctanswers)) {
                $question->fraction[$x] = $fraction;
            }
        }

        $question->correctfeedback          = array('text' => get_string('defaultprtcorrectfeedback', 'qtype_stack'),
                                                                'format' => FORMAT_HTML, 'files' => array());
        $question->partiallycorrectfeedback = array('text' => get_string('defaultprtpartiallycorrectfeedback', 'qtype_stack'),
                                                                'format' => FORMAT_HTML, 'files' => array());
        $question->incorrectfeedback        = array('text' => get_string('defaultprtincorrectfeedback', 'qtype_stack'),
                                                                'format' => FORMAT_HTML, 'files' => array());
        return array($question, $errors);
    }

    /**
     * Process a single question into an array based on a stack question.
     * @param SimpleXMLElement $assessmentitem
     * @return the question as an array
     */
    protected function questiontostackformfrom($assessmentitem) {

        $errors = array();
        $question = new stdClass();
        $question->qtype                 = 'stack';
        $question->name                  = (string) $assessmentitem->name;

        $question->variantsselectionseed = '';
        $question->defaultmark           = 1;
        $question->length                = 1;

        $question->questiontext          = (string) $assessmentitem->text;
        // Add in blank stubs for an input.
        $question->questiontext         .= ' [[input:ans1]] [[validation:ans1]]';
        $question->questiontextformat    = FORMAT_HTML;
        // Always blank on import - we assume PRT feedback is embedded in the question.
        $question->specificfeedback      = array('text' => '', 'format' => FORMAT_HTML, 'files' => array());
        $question->generalfeedback       = '[[feedback:prt1]]';
        $question->generalfeedbackformat = FORMAT_HTML;

        $algorithm = trim((string) $assessmentitem->algorithm);
        $question->questionvariables = $this->maple_to_maxima($algorithm);

        $question->questionnote  = '';

        // Question level options.
        $stackconfig = stack_utils::get_config();
        $question->questionsimplify      = (bool) $stackconfig->questionsimplify;
        $question->penalty               = 0.1;
        $question->assumepositive        = (bool) $stackconfig->assumepositive;
        $question->multiplicationsign    = $stackconfig->multiplicationsign;
        $question->sqrtsign              = (bool) $stackconfig->sqrtsign;
        $question->complexno             = $stackconfig->complexno;
        $question->inversetrig           = $stackconfig->inversetrig;
        $question->matrixparens          = $stackconfig->matrixparens;
        $question->prtcorrect            = array('text' => get_string('defaultprtcorrectfeedback', 'qtype_stack'),
                                                                'format' => FORMAT_HTML, 'files' => array());
        $question->prtpartiallycorrect   = array('text' => get_string('defaultprtpartiallycorrectfeedback', 'qtype_stack'),
                                                                'format' => FORMAT_HTML, 'files' => array());
        $question->prtincorrect          = array('text' => get_string('defaultprtincorrectfeedback', 'qtype_stack'),
                                                                'format' => FORMAT_HTML, 'files' => array());

        return array($question, $errors);
    }

    /* This function does basic (tedious) changes to Maple text to transform it into something
     * which Maxima might digest.  Could be extended forever...
     */
    private function maple_to_maxima($strin) {
        //echo "<pre>";
        //print_r($strin);

        // Very very lazy way of getting rid of maple("...");
        $ex = str_replace('maple("', '', $strin);
        $ex = str_replace('");', '', $ex);
        $rawmaxima = explode(';', $ex);
        $maxima = array();
        foreach ($rawmaxima as $ex) {
            $ex = str_replace('$', '', $ex);
            $ex = str_replace('=', ':', $ex);
            $maxima[] = $ex;
        }

        $ret = "/* Automatically converted from the following Maple code: */\n";
        $ret .= "/* ' . $strin . '*/\n\n";
        $ret .= implode(";\n", $maxima);
        print_r($ret);

        //echo "</pre>";
        //return $ret;
    }
}
