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
        
        
        // add CDATA tags back in if they are missing
        $xmlstr = str_replace('<comment> ', '<comment><![CDATA[', $xmlstr);
        $xmlstr = str_replace(' </comment>', ']]></comment>', $xmlstr);
        $xmlstr = str_replace('<algorithm> ', '<algorithm><![CDATA[', $xmlstr);
        $xmlstr = str_replace(' </algorithm>', ']]></algorithm>', $xmlstr);
        $xmlstr = str_replace('<text> ', '<text><![CDATA[', $xmlstr);
        $xmlstr = str_replace(' </text>', ']]></text>', $xmlstr);
        
        echo "<pre style='display:none'>".print_r($xmlstr,true)."</pre>";

/*

// MathML to LaTeX conversion

// This approach could perhaps be made to work (using the mml2tex project and PHP's XSLT transformation package. In the meantime, this conversion should be done as a pre-processing step (see the README for details). 

        $xslt = new XSLTProcessor();
        $xslDoc = new DOMDocument();
        $xslDoc->load("format/mapleta/mml2tex/xsl/invoke-mml2tex.xsl");
        libxml_use_internal_errors(true);
        $result = $xslt->importStyleSheet($xslDoc);
        if (!$result) {
            foreach (libxml_get_errors() as $error) {
                echo "<pre>Libxml error: {$error->message}\n</pre>";
            }
        }
        libxml_use_internal_errors(false);
        $root_trans = $xslt->transformToXml(new SimpleXMLElement($xmlstr));

===================================== */

        $root = simplexml_load_string($xmlstr);
        
        //echo "<pre>".print_r($xmlstr)."</pre>";
        echo "<pre style='display:none'>".print_r($root,true)."</pre>";
        
        if($root->module->name) {
          $modulename = trim((string) $root->module->name).': ';            
        } else {
          $modulename = '';
        }
        
        $questions = $root->questions;
        $qarray = $questions->question;

        $result = array();
        $report = array();
        $errors = array();
        
        foreach ($qarray as $mapletaquestion) {
        
            $mode = trim((string) $mapletaquestion->mode);
            $algo = trim((string) $mapletaquestion->algorithm);
            $name = $modulename.trim((string) $mapletaquestion->name);
            $mapletaquestion->name = $name;
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
        $question->questiontext          = $this->mapletext_to_castext((string) $assessmentitem->text);
        $question->questiontextformat    = FORMAT_HTML;
        $question->generalfeedback       = $this->mapletext_to_castext((string) $assessmentitem->comment);
        $question->generalfeedbackformat = FORMAT_HTML;
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
            $question->answer[$x]   = array('text' => $this->mapletext_to_castext((string) $qch->$x), 'format' => FORMAT_HTML);
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
     * Process a single question into an array based on a STACK question.
     * @param SimpleXMLElement $assessmentitem
     * @return the question as an array
     */
    protected function questiontostackformfrom($assessmentitem, $mcq) {

        $errors = array();
        $question = new stdClass();
        $question->qtype                 = 'stack';
        // The "MAPLETA: " here is only to make them easy to find in the question bank.  Please do delete it if you prefer.
        $question->name                  = 'MAPLETA: '. (string) $assessmentitem->name;

        $question->variantsselectionseed = '';
        $question->defaultmark           = 1;
        $question->length                = 1;

        // Question level options.
        $stackconfig = stack_utils::get_config();
        $question->questionsimplify      = (bool) $stackconfig->questionsimplify;
        $question->penalty               = 0.1;
        $question->assumepositive        = (bool) $stackconfig->assumepositive;
        $question->assumereal            = (bool) $stackconfig->assumereal;
        $question->multiplicationsign    = 'none';
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

        echo "<pre style='display:none'>ASSESSMENT ITEM:\n\n".print_r($assessmentitem,true)."</pre>";
        echo "<pre style='display:none'>QUESTION TEXT:\n".print_r($assessmentitem->text,true)."</pre>";
        echo "<pre style='display:none'>QUESTION TEXT:\n".print_r($this->mapletext_to_castext($assessmentitem->text),true)."</pre>";
        
        $question->questiontext          = $this->mapletext_to_castext((string) $assessmentitem->text);
        $question->questiontextformat    = FORMAT_HTML;
        $question->generalfeedback       = $this->mapletext_to_castext((string) $assessmentitem->comment);
        $question->generalfeedbackformat = FORMAT_HTML;

        $algorithm = trim((string) $assessmentitem->algorithm);
        $question->questionvariables = $this->maple_to_maxima($algorithm);

        $question->questionnote  = '';       

        $correctanswers = $this->correct_answers($assessmentitem);
        
        // Create an array of question parts - for multipart questions this consists of all the <parts> but otherwise it is just the question itself.
        $parts = array();
        if(trim((string) $assessmentitem->mode) == "Multipart" || trim((string) $assessmentitem->mode) == "Inline") {
            foreach($assessmentitem->parts->part as $part) {
                $parts[] = $part;
            }
            $qmode = "multipart";
            
        } else {
            $parts[] = $assessmentitem;
            $question->questiontext = ''; // this will be restored in the loop over $parts below
        }
        echo "<pre style='display:none'>QUESTION PARTS:\n".print_r($parts,true)."</pre>";
        
        // Create non-trivial inputs for each part
        $inputs = array();
        $i = 0;
        foreach($parts as $part) {
        
            // add the text of each part to the question stem -- TODO: add part labels e.g. (a)
            if($part->text) {
                $question->questiontext .= $this->mapletext_to_castext((string) $part->text);
            }
            $pmode = ((string) $part->mode->__toString());
            
                    echo "<pre style='display:none'>PART MODE:\n$pmode\n".print_r($part->mode,true)."\n\nQUESTIONTEXT PRE-REPLACEMENT:\n\n".print_r($question->questiontext,true)."</pre>";
        
            $i++; // set up ans1, ans2, etc
            $inputs["$i"] = "ans".$i;
            if($pmode == 'Non Permuting Multiple Choice' || $pmode == 'Multiple Selection'
                    || $pmode == 'Multiple Choice') {
                if (count($part->choices->choice > 1)) {
                    $types["$i"] = 'checkbox';
                } else {
                    $types["$i"] = 'radio';
                }            
                $teacheranswer["$i"] = 'ta'.$i;
                
                // build the STACK MCQ list structure
                $stackmcq = array();
                for ($x = 0; $x < count($part->choices->choice); $x++) {
                    $response = 'false';
                    if (array_key_exists($x, $this->correct_answers($part))) { // TODO - is this indexing out by 1???
                        $response = 'true';
                    }
                    $stackmcq[] = '["' . trim((string) $part->choices->choice->$x) . '", ' . $response .']';
                }
                $qv = "ta{$i}:[" . implode(', ', $stackmcq) . "]\n";
                // TODO - check the <fixed> tag to see if this permutation is needed
                $qv .= "ta{$i}:random_permutation(ta{$i});\n";
                $question->questionvariables .= $qv;
                
                // TODO - check here for corbase/wrongbase-style questions and clean up the maxima code accordingly
                $question->questiontext .= "\n [[input:ans{$i}]] [[validation:ans{$i}]]";
                
            } elseif($pmode == 'Inline') {
                    echo "<pre style='display:none'>INLINE QUESTIONTEXT PRE-REPLACEMENT:\n\n".print_r($question->questiontext,true)."</pre>";
                // Now deal with Inline questions, which have a further layer of parts within them!
                $sp = 1; // counter for the sub-parts
                foreach($part->parts->part as $subpart) {
                    $inputs["$i"] = "ans".$i; // this is needed here as we will increment $i for the next sub-part
                    $types["$i"] = 'algebraic'; // TODO - check that this is sufficient
                    $teacheranswer["$i"] = explode(',', $subpart->answer)[0];
                    // replace the in-text placeholder (e.g. <1>) with input/validation tags
                    $question->questiontext = str_replace(array("<{$sp}>","&lt;{$sp}&gt;"),"[[input:ans{$i}]] [[validation:ans{$i}]]", $question->questiontext);    
                    echo "<pre style='display:none'>INLINE QUESTIONTEXT POST-REPLACEMENT:\n\n".print_r($question->questiontext,true)."</pre>";
                    $sp++;
                    $i++;
                }              
            } elseif($pmode == 'Blanks') {
            // TODO - "Blanks" needs more work, as they don't have sub-parts but have "blanks" which list the options to be shown in a dropdown
                // Now deal with Inline/Blanks questions, which have a further layer of parts within them!
                $blanknum = 1; // counter for the blanks
                foreach($part->blanks->blank as $blank) {
                    if($blank['grader']=='menu') {
                        // These blanks are meant to be a drop-down list, but we replace these with radio boxes and produce a STACK MCQ list structure
                        $menuitems = explode(',',str_replace(array('%24','%7b','%7d'),array('','',''),$blank->__toString())); // 24 = $, 7b = { and 7d = }
                        $blankta = array();
                        foreach($menuitems as $num => $text) {
                            $blankta[] = '['.chr(65+$num).','.($num==0 ? 'true' : 'false').",$text]";
                        }
                        $blankta = '['.implode(',',$blankta).']';
                        $blanktype = 'radio';
                    } else {
                        $blankta = str_replace(array('%24','%7b','%7d'),'',$blank->__toString());
                        $blanktype = 'algebraic';
                    }
                    $inputs["$i"] = "ans".$i; // this is needed here as we will increment $i for the next sub-part
                    $types["$i"] = $blanktype;
                    $teacheranswer["$i"] = $blankta;
                    // replace the in-text placeholder (e.g. <1>) with input/validation tags
                    $question->questiontext = str_replace("<{$blanknum}>","[[input:ans{$i}]] [[validation:ans{$i}]]", $question->questiontext);      
                    $blanknum++;
                    $i++;
                }              
            } else {
                $types["$i"] = 'algebraic';
                // pick out the correct answer and convert MapleTA variables to Maxima ones (i.e. remove ${})
                if($part->answer->num and strlen($part->answer->num->__toString())>0) {
                    $teacheranswer["$i"] = str_replace(array('%24','%7b','%7d','$','{','}'),'',$part->answer->num->__toString());
                } else {
                    $teacheranswer["$i"] = str_replace(array('%24','%7b','%7d','$','{','}'),'',$part->answer->__toString());
                }
                if(strpos($question->questiontext, "<{$i}>") !== false) {
                    $question->questiontext = str_replace("<{$i}>","[[input:ans{$i}]] [[validation:ans{$i}]]", $question->questiontext);      
                } else {
                    $question->questiontext .= "\n [[input:ans{$i}]] [[validation:ans{$i}]]";
                }

            }

        }


        foreach ($inputs as $key => $ip) {
            // This is an odd format, but the "formfrom" fields have to look like $question->ans1type.
            // The foreach above will make it easier to have more than one input if anyone needs it.
            $question->{$ip.'type'}               = $types[$key];
            $question->{$ip.'modelans'}           = trim($teacheranswer[$key]);
            $question->{$ip.'boxsize'}            = 15;
            $question->{$ip.'strictsyntax'}       = false;
            $question->{$ip.'insertstars'}        = 0;
            $question->{$ip.'syntaxhint'}         = '';
            $question->{$ip.'syntaxattribute'}    = 0;
            $question->{$ip.'forbidwords'}        = '';
            $question->{$ip.'allowwords'}         = '';
            $question->{$ip.'forbidfloat'}        = true; // TODO - check numStyle to see if this should be false
            $question->{$ip.'requirelowestterms'} = true;
            $question->{$ip.'checkanswertype'}    = true;
            $question->{$ip.'mustverify'}         = true;
            $question->{$ip.'showvalidation'}     = 1;
            $question->{$ip.'options'}            = '';

        }

        // Create non-trivial potential response tree, just as with inputs we loop now to save time later.
        $specificfeedbackprt = '';
        $potentialresponsetrees = array();
        foreach ($teacheranswer as $key => $ta) {
            $name = 'prt'.$key;
            $prt = array();
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
                $prt['sans'][$node]            = $inputs[$key];
                $prt['tans'][$node]            = trim($teacheranswer[$key]);
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

        /*echo "<pre>";
        print_r($question);
        print_r("\n\nINPUTS AND CORRECTANSWERS\n\n");
        print_r($inputs);
        print_r($correctanswers);
        echo "</pre>";*/
        return array($question, $errors);
    }

    /* This function does basic (tedious) changes to Maple text to castext.
     */
    private function mapletext_to_castext($strin) {
        /* This matches  $a  type patterns. */
        preg_match_all('/\$[a-zA-Z]+/', $strin, $out);
        foreach ($out[0] as $pat) {
            $rep = '{@' . substr($pat, 1) . '@}';
            $strin = str_replace($pat, $rep, $strin);
        }
        preg_match_all('/\$\{([a-zA-Z0-9]+)\}/', $strin, $out);
        foreach ($out[0] as $pat) {
            $rep = '{@' . substr($pat, 2, -1) . '@}';
            $strin = str_replace($pat, $rep, $strin);
        }
        // for plots
        $strin = preg_replace('/\<script\>(.*?)\<\/script\>/','\1',$strin);
        // To deal with the mml2tex output (this will be present if pre-processing is carried out to convert MathML to LaTeX - see the README for details)
        $strin = preg_replace('/\<\?mml2tex(.*?)\?\>/','\\(\1\\)',$strin);
        $strin = preg_replace('/\\\\\$ \{(.*?)\}/','{@\1@}',$strin);  // \$ {var} -> {@var@}      
        if(strlen($strin)<10 and strpos($strin,'(Unset)')!==false) {
            // Empty question text is denoted by "(Unset)" in the XML, so just strip that out entirely
            $strin = '';
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
            // Deal with "switch" statments:
            //   switch(choice,x1,x2,x3) -> [x1,x2,x3][1+choice]
            //
            // Note: this is just a bit naive as it fails to deal with cases where "choice" contains commas: 
            //  $ex = preg_replace('/switch\((.*?),(.*)\)/', '[\2][1+\1]', $ex); 
            if(strpos($ex, 'switch(') !== false) {
                $switchargs = substr($ex, strpos($ex, "switch(") + 7);
                // find the comma after the first argument (ignore commas appearing inside brackets in the first argument, e.g. if "choice" is "ge(x,0)")
                $bracketcount=0;
                for( $i = 0; $i <= strlen($switchargs); $i++ ) {
                    $char = substr( $switchargs, $i, 1 );
                    if($char=='(') {
                        $bracketcount++;
                    } elseif($char==')') {
                        $bracketcount--;
                    } elseif($char==',' and $bracketcount==0) {
                        break;
                    } 
                }
                $firstarg = substr($switchargs,0,$i);
                $otherargs = substr($switchargs,$i+1,-1);
                $ex = substr($ex,0,strpos($ex,'switch('))."[$otherargs][$firstarg]";
            }
            $ex = preg_replace('/rand\((\w+)\.\.(\w+)\)\(\)/', 'rand_range(\1, \2)', $ex);
            $ex = str_replace('rint(', 'rand(', $ex);
            $ex = str_replace('$', '', $ex);
            $ex = str_replace(':=', ':', $ex);
            $ex = str_replace('=', ':', $ex);
            $maxima[] = trim($ex);
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
