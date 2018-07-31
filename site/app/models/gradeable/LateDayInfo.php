<?php

namespace app\models\gradeable;


use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\AbstractModel;
use app\models\User;

/**
 * Class LateDayInfo
 * @package app\models\gradeable
 *
 * @method int getLateDaysAvailable()
 */
class LateDayInfo extends AbstractModel {

    /** @var GradedGradeable */
    private $graded_gradeable = null;
    /** @var User */
    private $user = null;

    /** @property @var int The number of unused late days the user has for this gradeable, not including exceptions */
    protected $late_days_available = null;

    public function __construct(Core $core, User $user, GradedGradeable $graded_gradeable, int $cumulative_late_days_used, int $late_days_available) {
        parent::__construct($core);
        if (!$graded_gradeable->getSubmitter()->hasUser($user)) {
            throw new \InvalidArgumentException('Provided user did not match provided GradedGradeable');
        }

        $this->user = $user;
        $this->graded_gradeable = $graded_gradeable;

        // Get the late days available as of this gradeable's due date
        if($late_days_available < 0) {
            throw new \InvalidArgumentException('Late days available must be at least 0');
        }
        $this->late_days_available = $late_days_available;
    }

    public function toArray() {
        return [
            'gradeable_title' => $this->graded_gradeable->getGradeable()->getTitle(),
            'submission_due_date' => $this->graded_gradeable->getGradeable()->getSubmissionDueDate()->format('m/d/y'),
            'g_allowed_late_days' => $this->graded_gradeable->getGradeable()->getLateDays(),
            'exceptions' => $this->getLateDayExceptions(),
            'status' => $this->getStatus(),
            'late_days_available' => $this->late_days_available,
            'days_late' => $this->hasLateDaysInfo() ? $this->getDaysLate() : null,
            'charged_late_days' => $this->hasLateDaysInfo()? $this->getLateDaysCharged() : null
        ];
    }

    public function getGradedGradeable() {
        return $this->graded_gradeable;
    }

    public function getLateDaysAllowed() {
        return min($this->graded_gradeable->getGradeable()->getLateDays(), $this->late_days_available);
    }

    public function getStatus() {
        // No late days info, so NO_SUBMISSION
        if(!$this->hasLateDaysInfo()) {
            return LateDays::STATUS_NO_SUBMISSION;
        }

        $days_late = $this->getDaysLate();
        // If the number of days late is more than the minimum of: the max for this gradeable and the days the user has
        //  left, then this is a BAD status
        if ($days_late > $this->getLateDaysAllowed()) {
            return LateDays::STATUS_BAD;
        }

        // If the number of days late is more 0, it is LATE
        if ($days_late > 0) {
            return LateDays::STATUS_LATE;
        }

        // ... otherwise, its GOOD
        return LateDays::STATUS_GOOD;
    }

    public function hasLateDaysInfo() {
        return $this->graded_gradeable->getAutoGradedGradeable()->hasActiveVersion();
    }

    public function getLateDaysCharged() {
        return max($this->getLateDayExceptions() - $this->getDaysLate(), $this->getLateDaysAllowed());
    }

    public function getDaysLate() {
        return $this->graded_gradeable->getAutoGradedGradeable()->getActiveVersionInstance()->getDaysLate();
    }

    public function getLateDayExceptions() {
        return $this->graded_gradeable->getLateDayException($this->user);
    }
}