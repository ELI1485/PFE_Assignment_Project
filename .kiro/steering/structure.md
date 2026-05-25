# Project Structure

## Directory Layout

```
pfe_project/
├── app/
│   ├── Exceptions/          # Custom exceptions (e.g. PlanningIncompleteException)
│   ├── Http/Controllers/    # Thin controllers — delegate to Services
│   ├── Models/              # Eloquent models
│   ├── Providers/           # AppServiceProvider
│   ├── Repositories/        # Data-access layer (wraps Eloquent queries)
│   └── Services/            # Business logic layer
├── bootstrap/               # Laravel bootstrap (app.php, providers.php)
├── config/                  # Laravel config files
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── public/
│   ├── css/app-theme.css    # Global custom styles
│   ├── images/              # Static assets (logo)
│   └── templates/           # Downloadable Excel template
├── resources/
│   ├── css/                 # Source CSS (compiled by Vite)
│   ├── js/                  # Source JS (compiled by Vite)
│   └── views/               # Blade templates
│       ├── layouts/app.blade.php   # Main layout (sidebar + topbar)
│       ├── partials/               # Reusable Blade partials
│       ├── affectation/            # Supervisor assignment views
│       ├── conformite/             # Compliance check views
│       ├── dashboard/              # Dashboard view
│       ├── pdf/                    # PDF export templates
│       ├── planning/               # Schedule views
│       ├── pvs/                    # PV generation views
│       ├── salles/                 # Room management views
│       ├── verification/           # Constraint audit views
│       └── import.blade.php        # Excel import view
├── routes/
│   └── web.php              # All application routes (no API routes)
└── storage/                 # Laravel storage (logs, cached files)
```

## Architecture Patterns

### Layered Architecture
```
Controller → Service → Repository → Model
```
- **Controllers** handle HTTP, validate input, redirect with flash messages. Keep them thin.
- **Services** contain all business logic (scheduling algorithm, export generation, verification).
- **Repositories** encapsulate Eloquent queries; return Collections or Models.
- **Models** define relationships and `$fillable`. Avoid putting business logic in models.

### Key Controllers & Their Responsibilities
| Controller | Responsibility |
|---|---|
| `AssignmentController` | Dashboard, affectation, planning generation, history, snapshot downloads |
| `ImportController` | Excel import, database reset |
| `ExportController` | Live-data PDF/Word exports |
| `PvController` | PV generation and download |
| `SalleController` | Room CRUD |
| `ConformiteController` | Planning compliance view |
| `VerificationController` | Constraint audit view |

### Key Services
| Service | Responsibility |
|---|---|
| `AssignmentService` | Core scheduling algorithm — encadrant assignment, slot generation, jury building |
| `VerificationService` | Constraint checking (conflicts, rest rules, jury composition) |
| `ExcelImportService` | Parses uploaded Excel files into DB records |
| `PdfExportService` | Generates PDF documents via DomPDF |
| `WordExportService` | Generates Word documents via PhpWord |
| `PvService` | Builds PV data for individual defenses |
| `HistoryService` | Saves/retrieves planning and affectation snapshots |

## Domain Models & Relationships

```
Etudiant ──< Projet >── Enseignant (encadrant)
Projet ──── Soutenance ──── Creneau
                       ──── Salle
                       ──── Jury ──< jury_enseignant >── Enseignant
```

| Model | Table | Notes |
|---|---|---|
| `Etudiant` | `etudiants` | Fields: cne, nom, prenom, filiere, emails |
| `Enseignant` | `enseignants` | Fields: nom, prenom, discipline |
| `Projet` | `projets` | Links 1–2 students to an encadrant; has one Soutenance |
| `Soutenance` | `soutenances` | The scheduled defense event |
| `Creneau` | `creneaux` | Date + time slot; casts heure_debut/fin as `datetime:H:i` |
| `Salle` | `salles` | Room; has `normalizeNom()` static helper for dedup |
| `Jury` | `juries` | Pivot to Enseignant via `jury_enseignant` with `role` (President/Rapporteur) |

## Blade Conventions

- All views extend `layouts/app.blade.php` via `@extends('layouts.app')`
- Define `@section('title')`, `@section('page-title')`, and `@section('content')`
- Use `@stack('styles')` / `@stack('scripts')` for page-specific assets
- Flash messages (`success`, `error`, `info`) are rendered automatically in the layout
- Custom modals use the `.app-modal-overlay` + `.is-open` CSS pattern; `openPlanningModal()` / `closePlanningModal()` are global JS helpers

## Routing Conventions

- All routes are in `routes/web.php` — no API routes
- Named routes follow `resource.action` pattern (e.g. `affectation.index`, `planning.run`)
- Route model binding is not used; IDs are resolved manually in controllers
- CSRF is disabled for `import/*` routes

## Coding Conventions

- PSR-12 code style enforced by Laravel Pint (`vendor/bin/pint`)
- Constructor property promotion used for injected dependencies
- French is used for domain terminology in variable names, messages, and views (nom, prenom, filiere, soutenance, encadrant, etc.)
- `DB::transaction()` wraps multi-step write operations
- `Log::warning()` / `Log::info()` used for scheduling diagnostics — not exceptions
- `Storage::put()` used to persist `conformite_diagnostic.json` between requests
