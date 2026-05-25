<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside a DB transaction when the planning algorithm fails to
 * reach 100 % completion. Carrying diagnostic data lets the controller
 * flash a meaningful recommendation without saving anything to the DB.
 */
class PlanningIncompleteException extends RuntimeException
{
    public int $pct;
    public int $affectes;
    public int $totalEtudiants;
    public int $totalProjects;
    public int $scheduledProjects;
    public string $recommendation;
    public array $diagnostic;

    public function __construct(
        int    $pct,
        int    $affectes,
        int    $totalEtudiants,
        int    $totalProjects,
        int    $scheduledProjects,
        string $recommendation,
        array  $diagnostic
    ) {
        parent::__construct("Planning incomplet : seulement {$pct}% des étudiants planifiés.");

        $this->pct               = $pct;
        $this->affectes          = $affectes;
        $this->totalEtudiants    = $totalEtudiants;
        $this->totalProjects     = $totalProjects;
        $this->scheduledProjects = $scheduledProjects;
        $this->recommendation    = $recommendation;
        $this->diagnostic        = $diagnostic;
    }
}
