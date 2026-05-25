# Product Overview

**PFE Admin** is an internal web application for ENSAH (École Nationale des Sciences Appliquées d'Al Hoceima) that automates the management of end-of-study project defenses (soutenances de PFE).

## Core Workflow

1. **Import** — Upload student and teacher data via Excel
2. **Affectation** — Auto-assign supervisors (encadrants) to student projects using a load-balancing algorithm
3. **Planning** — Generate a conflict-free defense schedule (planning) given configurable date ranges, time slots, and room counts
4. **Conformité** — Validate the generated planning against constraints (room conflicts, rest rules, jury composition)
5. **Audit** — Run a full constraint audit to surface anomalies in affectations and planning
6. **PV Generation** — Export individual or bulk defense reports (procès-verbaux) as Word documents

## Key Business Rules

- Each defense jury has exactly 3 members: 1 president (the encadrant) + 2 rapporteurs
- At least 2 jury members must be "informatique" professors
- A professor cannot be assigned to two consecutive time slots on the same day (rest rule)
- A room can only host one defense per time slot
- Encadrants supervise 3–4 students on average
- Planning is only saved to history when 100% of students are scheduled

## Target Users

Single-user admin tool (no authentication) used by the academic coordination team at ENSAH.
