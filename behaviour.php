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

/**
 * Adaptive question behaviour for multi-part questions.
 *
 * @package   qbehaviour_adaptivemultipart
 * @copyright 2012 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/behaviour/adaptive/behaviour.php');


/**
 * Adaptive question behaviour for multi-part questions.
 *
 * @copyright 2012 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface question_automatically_gradable_with_multiple_parts
        extends question_automatically_gradable {

    /**
     * Grade those parts of the question that can be graded, and return the grades and penalties.
     * @param array $response the current response being processed. Response variable name => value.
     * @param array $lastgradedresponses array part name => $response array from the last
     *      time this part registered a try. If a particular part has not yet registered a
     *      try, then there will not be an entry in the array for it.
     * @param bool $finalsubmit set to true when the student click submit all and finish,
     *      since the question is ending, we make a final attempt to award the student as much
     *      credit as possible for what they did.
     * @return array part name => qbehaviour_adaptivemultipart_part_result. There should
     *      only be entries in this array for those parts of the question where this
     *      sumbission counts as a new try at that part.
     */
    public function grade_parts_that_can_be_graded(array $response, array $lastgradedresponses, $finalsubmit);

    /**
     * Get a list of all the parts of the question, and the weight they have within
     * the question.
     * @return array part identifier => weight. The sum of all the weights should be 1.
     */
    public function get_parts_and_weights();

    /**
     * @param array $response the current response being processed. Response variable name => value.
     * @return bool true if any part of the response is invalid.
     */
    public function is_any_part_invalid(array $response);
}


/**
 * Holds the result of grading a try at one part of an adaptive question.
 *
 * @copyright 2012 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbehaviour_adaptivemultipart_part_result {

    /** @var string the name of the part this relates to. */
    public $partname;

    /** @var float the fraction for this response, before any penaly is applied. */
    public $rawfraction;

    /** @var float the additional penalty that this try incurs. */
    public $penalty;

    public function __construct($partname, $rawfraction, $penalty) {
        $this->partname    = $partname;
        $this->rawfraction = $rawfraction;
        $this->penalty     = $penalty;
    }
}


/**
 * Adaptive question behaviour for multi-part questions.
 *
 * This allows each part of the question to be graded as soon as the
 * corresponding inputs have been completed, and so counts the tries, and
 * does the penalty calculations for each part separately.
 *
 * TODO not sure if subclassing qbehaviour_adaptive is feasible. We may have to
 * subclass question_behaviour_with_save instead.
 *
 * @copyright 2012 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbehaviour_adaptivemultipart extends qbehaviour_adaptive {
    const IS_ARCHETYPAL = false;

    protected $applypenalties;

    public function __construct(question_attempt $qa, $preferredbehaviour) {
        parent::__construct($qa, $preferredbehaviour);
        $this->applypenalties = 'adaptivenopenalty' !== $preferredbehaviour;
    }

    public function is_compatible_question(question_definition $question) {
        return $question instanceof question_automatically_gradable_with_multiple_parts;
    }

    public function get_state_string($showcorrectness) {
        // TODO probably needs to be changed.
        return parent::get_state_string($showcorrectness);
    }

    public function adjust_display_options(question_display_options $options) {
        // TODO probably needs to be changed.
        parent::adjust_display_options($options);
        $options->feedback = true;
    }

    public function process_action(question_attempt_pending_step $pendingstep) {
        if ($pendingstep->has_behaviour_var('comment')) {
            return $this->process_comment($pendingstep);
        } else if ($pendingstep->has_behaviour_var('finish')) {
            return $this->process_finish($pendingstep);
        } else if ($pendingstep->has_behaviour_var('submit')) {
            return $this->process_submit($pendingstep);
        } else {
            return $this->process_save($pendingstep);
        }
    }

    protected function adjusted_fraction($fraction, $prevtries) {
        return $fraction - $this->question->penalty * $prevtries;
    }

    protected function process_parts_that_can_be_graded(question_attempt_pending_step $pendingstep, $finalsubmit) {

        // Get last graded response for each part.
        $lastgradedresponses = array();
        $currenttries = array();
        $currentpenalties = array();
        $currentfractions = array();
        $currentrawfractions = array();

        $steps = $this->qa->get_reverse_step_iterator();
        if ($finalsubmit) {
            $steps->next();
        }

        foreach ($steps as $step) {
            foreach ($step->get_behaviour_data() as $name => $value) {
                if (!preg_match('~_tries_(.*)$~', $name, $matches)) {
                    continue;
                }

                $partname = $matches[1];
                if (array_key_exists($partname, $currenttries)) {
                    continue; // Already found more recent data for this PRT.
                }

                $lastgradedresponses[$partname] = $step->get_qt_data();
                $currenttries[$partname] = $value;
                $currentpenalties[$partname] = $step->get_behaviour_var('_penalty_' . $partname);
                $currentfractions[$partname] = $step->get_behaviour_var('_fraction_' . $partname);
                $currentrawfractions[$partname] = $step->get_behaviour_var('_rawfraction_' . $partname);
            }
        }

        if ($finalsubmit) {
            $laststep = $this->qa->get_last_step();
            $response = $laststep->get_qt_data();
        } else {
            $response = $pendingstep->get_qt_data();
        }
        $partscores = $this->question->grade_parts_that_can_be_graded($response, $lastgradedresponses, $finalsubmit);

        foreach ($partscores as $partname => $partscore) {
            if (!array_key_exists($partname, $currentpenalties)) {
                $currenttries[$partname]     = 0;
                $currentpenalties[$partname] = 0;
                $currentfractions[$partname] = 0;
            }

            $pendingstep->set_behaviour_var('_tries_' . $partname, $currenttries[$partname] + 1);
            if ($this->applypenalties) {
                $pendingstep->set_behaviour_var('_penalty_' . $partname, $currentpenalties[$partname] + $partscore->penalty);
            } else {
                $pendingstep->set_behaviour_var('_penalty_' . $partname, 0);
            }
            $pendingstep->set_behaviour_var('_rawfraction_' . $partname, $partscore->rawfraction);
            $currentrawfractions[$partname] = $partscore->rawfraction;
            $currentfractions[$partname] = max($partscore->rawfraction - $currentpenalties[$partname], $currentfractions[$partname]);
            $pendingstep->set_behaviour_var('_fraction_' . $partname, $currentfractions[$partname]);
        }

        if (empty($currentfractions)) {
            $totalfraction = null;
            $overallstate = question_state::$gaveup;
        } else {
            $totalweight = 0;
            $totalfraction = 0;
            foreach ($this->question->get_parts_and_weights() as $index => $weight) {
                $totalweight += $weight;
                if (array_key_exists($index, $currentfractions)) {
                    $totalfraction += $weight * $currentfractions[$index];
                }
            }
            $totalfraction = $totalfraction/$totalweight;

            $allright = true;
            $allwrong = true;
            foreach ($this->question->get_parts_and_weights() as $index => $weight) {
                if (array_key_exists($index, $currentrawfractions)) {
                    $partstate = question_state::graded_state_for_fraction($currentrawfractions[$index]);
                    if ($partstate != question_state::$gradedright) {
                        $allright = false;
                    }
                    if ($partstate != question_state::$gradedwrong) {
                        $allwrong = false;
                    }
                } else {
                    $allright = false;
                }
            }
            if ($allright) {
                $overallstate = question_state::$gradedright;
            } else if ($allwrong) {
                $overallstate = question_state::$gradedwrong;
            } else {
                $overallstate = question_state::$gradedpartial;
            }
        }

        return array($totalfraction, $overallstate);
    }

    public function process_submit(question_attempt_pending_step $pendingstep) {
        $status = $this->process_save($pendingstep);
        if ($status == question_attempt::DISCARD) {
            return question_attempt::DISCARD;
        }

        list($totalfraction, $overallstate) = $this->process_parts_that_can_be_graded($pendingstep, false);
        $pendingstep->set_fraction($totalfraction);

        $prevstep = $this->qa->get_last_step();
        if ($this->question->is_any_part_invalid($pendingstep->get_qt_data())) {
            $pendingstep->set_state(question_state::$invalid);
        } else if ($prevstep->get_state() == question_state::$complete) {
            $pendingstep->set_state(question_state::$complete);
        } else if ($overallstate == question_state::$gradedright) {
            $pendingstep->set_state(question_state::$complete);
        } else {
            $pendingstep->set_state(question_state::$todo);
        }
        $pendingstep->set_new_response_summary($this->question->summarise_response($pendingstep->get_qt_data()));

        return question_attempt::KEEP;
    }

    public function process_finish(question_attempt_pending_step $pendingstep) {
        if ($this->qa->get_state()->is_finished()) {
            return question_attempt::DISCARD;
        }

        list($totalfraction, $overallstate) = $this->process_parts_that_can_be_graded($pendingstep, true);

        $pendingstep->set_fraction($totalfraction);
        $pendingstep->set_state($overallstate);
        $pendingstep->set_new_response_summary($this->question->summarise_response($pendingstep->get_qt_data()));
        return question_attempt::KEEP;
    }
}
