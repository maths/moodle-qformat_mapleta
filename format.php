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
        $report = array();
        $errors = array();

        foreach ($qarray as $mapletaquestion) {
            $mode = trim((string) $mapletaquestion->mode);
            $algo = trim((string) $mapletaquestion->algorithm);
            $mcq = false;
            $type = '';
            $imported = false;
            $err = array();

            if ($mode == 'Non Permuting Multiple Choice' || $mode == 'Multiple Selection'
                    || $mode == 'Multiple Choice') {
               $mcq = true;
               $type = 'mcq';
            }
            if ($mode == 'maple') {
                $type = 'stack';
            }
            if ($algo != '') {
               $type = 'stack';
            }

            if ($type == 'mcq') {
                list($question, $err) = $this->questiontomcqformfrom($mapletaquestion);
                $imported = true;
            } else if ($type == 'stack'){
                list($question, $err) = $this->questiontostackformfrom($mapletaquestion, $mcq);
                $imported = true;
            }
            $report[] = array('name' => trim((string) $mapletaquestion->name),
                              'type' => trim((string) $mapletaquestion->mode),
                              'converted' => $imported);
            $result[] = $question;
            $errors = array_merge($errors, $err);
        }

        echo "<pre>";
        print_r($report);
        echo "</pre>";

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

        // Assume all correct answers are equally weighted.
        $correctanswer = $this->correct_answers($assessmentitem);
        $fraction = 1/count($correctanswer);
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
    protected function questiontostackformfrom($assessmentitem, $mcq) {

        $errors = array();
        $question = new stdClass();
        $question->qtype                 = 'stack';
        // The "RAW IMPORT" here is only to make them easy to find in the question bank.  Please do delete it if you prefer.
        $question->name                  = 'RAW IMPORT: '. (string) $assessmentitem->name;

        $question->variantsselectionseed = '';
        $question->defaultmark           = 1;
        $question->length                = 1;

        $question->questiontext          = $this->mapletext_to_castext((string) $assessmentitem->text);
        $question->questiontextformat    = FORMAT_HTML;
        $question->generalfeedback       = '';
        $question->generalfeedbackformat = FORMAT_HTML;

        $algorithm = trim((string) $assessmentitem->algorithm);
        $question->questionvariables = $this->maple_to_maxima($algorithm);

        $question->questionnote  = '';

        // Question level options.
        $stackconfig = stack_utils::get_config();
        $question->questionsimplify      = (bool) $stackconfig->questionsimplify;
        $question->penalty               = 0.1;
        $question->assumepositive        = (bool) $stackconfig->assumepositive;
        $question->assumereal            = (bool) $stackconfig->assumereal;
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

        $correctanswers = $this->correct_answers($assessmentitem);
        // Create non-trivial inputs.
        $inputs = array('ans1');
        $types = array('algebraic');
        $teacheranswer = array($correctanswers[0]);

        // Create randomly generated MCQ.
        if ($mcq) {
            $teacheranswer[0] = 'ta';
            $qch = $assessmentitem->choices->choice;
            $types[0] = 'radio';
            if (count($qch > 1)) {
                $types[0] = 'checkbox';
            }
            $stackmcq = array();
            for ($x = 0; $x < count($assessmentitem->choices->choice); $x++) {
                $response = 'false';
                if (array_key_exists($x, $correctanswers)) {
                    $response = 'true';
                }
                $stackmcq[] = '["' . trim((string) $qch->$x) . '", ' . $response .']';
            }
            $qv = 'ta:[' . implode(', ', $stackmcq) . "]\n";
            $qv .= "ta:random_permutation(ta);\n";
            $question->questionvariables .= $qv;
        }


        foreach ($inputs as $key => $ip) {
            // This is an odd format, but the "formfrom" fields have to look like $question->ans1type.
            // The foreach above will make it easier to have more than one input if anyone needs it.
            $question->{$ip.'type'}               = $types[$key];
            $question->{$ip.'modelans'}           = $teacheranswer[$key];
            $question->{$ip.'boxsize'}            = 15;
            $question->{$ip.'strictsyntax'}       = false;
            $question->{$ip.'insertstars'}        = 0;
            $question->{$ip.'syntaxhint'}         = '';
            $question->{$ip.'syntaxattribute'}    = 0;
            $question->{$ip.'forbidwords'}        = '';
            $question->{$ip.'allowwords'}         = '';
            $question->{$ip.'forbidfloat'}        = true;
            $question->{$ip.'requirelowestterms'} = true;
            $question->{$ip.'checkanswertype'}    = true;
            $question->{$ip.'mustverify'}         = true;
            $question->{$ip.'showvalidation'}     = 1;
            $question->{$ip.'options'}            = '';

            // Add in blank stubs for an input at the end of the question variables.
            $question->questiontext         .= "\n [[input:{$ip}]] [[validation:{$ip}]]";
        }

        // Create non-trivial potential response tree, just as with inputs we loop now to save time later.
        $specificfeedbackprt = '';
        $prts = array('prt1');
        $potentialresponsetrees = array();
        foreach ($prts as $name) {
            $prt = array();
            $pr = array();
            $prt['value']             = 1;
            $prt['autosimplify']      = true;
            $prt['feedbackvariables'] = '';
            // We need arrays of these things, one entry for each node.
            $prt['answertest']        = array();
            $prt['sans']              = array();
            $prt['tans']              = array();
            $prt['testoptions']       = array();
            $prt['quiet']             = array();
            $prt['truescore']         = array();
            $prt['falsescore']        = array();
            $prt['truepenalty']       = array();
            $prt['falsepenalty']      = array();
            $prt['truescoremode']     = array();
            $prt['falsescoremode']    = array();
            $prt['truenextnode']      = array();
            $prt['falsenextnode']     = array();
            $prt['truefeedback']      = array();
            $prt['falsefeedback']     = array();

            // Now loop over the nodes.
            $prtnodes = array('0');
            foreach ($prtnodes as $node) {
                $prt['answertest'][$node]  = 'AlgEquiv';
                // Hard wire these values.
                $prt['sans'][$node]            = $inputs[0];
                $prt['tans'][$node]            = $correctanswers[0];;
                $prt['testoptions'][$node]     = '';
                $prt['quiet'][$node]           = false;
                $prt['truescore'][$node]       = 1;
                $prt['falsescore'][$node]      = 0;
                $prt['truepenalty'][$node]     = 0;
                $prt['falsepenalty'][$node]    = 0.1;
                $prt['truescoremode'][$node]   = '=';
                $prt['falsescoremode'][$node]  = '=';
                $prt['truenextnode'][$node]    = -1;
                $prt['falsenextnode'][$node]   = -1;
                $prt['truefeedback'][$node]    = array('text' => '', 'format' => FORMAT_HTML, 'files' => array());
                $prt['falsefeedback'][$node]   = array('text' => '', 'format' => FORMAT_HTML, 'files' => array());
                $basicnote = $name.'-'.$node.'-';
                $prt['trueanswernote'][$node]  = $basicnote.'T';
                $prt['falseanswernote'][$node] = $basicnote.'F';
            }

            $potentialresponsetrees[$name] = $prt;
            // Link the PRT into the question.
            $specificfeedbackprt .= "[[feedback:{$name}]]";
        }

        // Note, we might want to add in the PRTs in the question next, not in the specific feedback....
        $question->specificfeedback = array('text' => $specificfeedbackprt, 'format' => FORMAT_HTML, 'files' => array());

        // Build the formfrom data.
        foreach ($potentialresponsetrees as $name => $prt) {
            foreach ($prt as $key => $val) {
                $question->{$name . $key} = $val;
            }
        }

        //echo "<pre>";
        //print_r($question);
        //echo "</pre>";
        return array($question, $errors);
    }

    /* This function does basic (tedious) changes to Maple text to castext.
     */
    private function mapletext_to_castext($strin) {
        /* This matches  $a  type patterns. */
        preg_match_all('/\$[a-zA-Z]+/', $strin, $out);
        foreach ($out[0] as $pat) {
            $rep = '@' . substr($pat, 1) . '@';
            $strin = str_replace($pat, $rep, $strin);
        }
        preg_match_all('/\$\{([a-zA-Z0-9]+)\}/', $strin, $out);
        foreach ($out[0] as $pat) {
            $rep = '@' . substr($pat, 2, -1) . '@';
            $strin = str_replace($pat, $rep, $strin);
        }
        return $strin;
    }

    /* This function does basic (tedious) changes to Maple text to transform it into something
     * which Maxima might digest.  Could be extended forever...
     */
    private function maple_to_maxima($strin) {
        //echo "<pre>";
        //print_r($strin);

        // Very very lazy way of getting rid of maple("...");
        $ex = str_replace('maple("', '', $strin);
        $ex = str_replace('");', ';', $ex);
        $rawmaxima = explode(';', $ex);
        $maxima = array();
        foreach ($rawmaxima as $ex) {
            $ex = str_replace('range(', 'rand_range(', $ex);
            $ex = preg_replace('/rand\((\w+)\.\.(\w+)\)\(\)/', 'rand_range(\1, \2)', $ex);
            $ex = str_replace('$', '', $ex);
            $ex = str_replace(':=', ':', $ex);
            $ex = str_replace('=', ':', $ex);
            $maxima[] = $ex;
        }

        $ret = "/* Automatically converted from the following Maple code: */\n";
        $ret .= "/* ' . $strin . '*/\n\n";
        $ret .= implode(";\n", $maxima);

        //print_r($ret);
        //echo "</pre>";
        return $ret;
    }

    /**
     * Process a the incoming correct answers into an array.
     * @param SimpleXMLElement $assessmentitem
     * @return the question as an array
     */
    private function correct_answers($assessmentitem) {

        $correctanswer = (string) $assessmentitem->answer;
        $correctanswer = explode(',', $correctanswer);

        return $correctanswer;

    }
}
