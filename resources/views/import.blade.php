@extends('layouts.app')

@section('title', 'Importation Excel')

@push('styles')
    <style>
        .import-page {
            max-width: 1180px;
            margin: 0 auto;
        }

        .template-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .import-card {
            min-height: 100%;
            padding: 0;
            overflow: hidden;
        }

        .import-card-head {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: #fbfcff;
        }

        .import-card-head h2 {
            color: var(--heading);
            font-size: 1.02rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .import-card-head p {
            color: var(--muted);
            font-size: 0.83rem;
            margin-bottom: 0;
        }

        .import-card-body {
            padding: 24px;
        }

        .hint-tag {
            background: var(--ensah-blue-soft);
            border: 1px solid #d4e2ff;
            color: #3159c5;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 0.82rem;
            margin-bottom: 18px;
        }

        .drop-zone {
            min-height: 210px;
            border: 2px dashed #c9d4e5;
            border-radius: 16px;
            background: #fbfcff;
            padding: 28px 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.15s ease, background 0.15s ease;
            margin-bottom: 18px;
        }

        .drop-zone:hover,
        .drop-zone.dragover {
            border-color: var(--ensah-blue);
            background: #f4f7ff;
        }

        .drop-symbol {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            margin: 0 auto 14px;
            background: var(--ensah-blue-soft);
            color: var(--ensah-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .drop-title {
            color: var(--heading);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .drop-sub {
            color: var(--muted);
            font-size: 0.82rem;
            margin-bottom: 16px;
        }

        .chosen-files {
            display: none;
            text-align: left;
            background: #f4f7ff;
            border: 1px solid #dbe6ff;
            border-radius: 12px;
            margin-top: 14px;
            padding: 10px 12px;
            color: #3159c5;
            font-size: 0.82rem;
            font-weight: 500;
        }
        .reset-section {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #fde8e8;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .reset-section-label {
            color: var(--muted);
            font-size: 0.83rem;
            line-height: 1.5;
        }

        .reset-section-label strong {
            color: #b91c1c;
            display: block;
            font-size: 0.88rem;
            margin-bottom: 2px;
        }

        /* ── Confirmation Modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(3px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 18px;
            padding: 36px 32px 28px;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
            text-align: center;
            animation: modalPop 0.2s ease;
        }

        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.92); }
            to   { opacity: 1; transform: scale(1); }
        }

        .modal-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .modal-box h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .modal-box p {
            color: #64748b;
            font-size: 0.88rem;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
    </style>
@endpush

@section('content')
    <div class="import-page">
        <div class="page-actions">
            <div>
                <div class="section-subtitle">Chargez les fichiers professeurs et étudiants avant de lancer l'affectation.
                </div>
            </div>
            <div class="template-actions">
                <a href="{{ route('import.template.etudiants') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-download"></i>
                    Télécharger modèle étudiants complet
                </a>
                <a href="{{ route('import.template.etudiants.emails') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-download"></i>
                    Télécharger modèle étudiants avec E-mails
                </a>
                <a href="{{ route('import.template.profs') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-download"></i>
                    Télécharger modèle professeurs
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Erreur :</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-4 align-items-stretch">
            <div class="col-lg-6">
                <div class="card import-card h-100">
                    <div class="import-card-head">
                        <h2>Liste des étudiants</h2>
                        <p>Vous pouvez sélectionner plusieurs fichiers Excel, un par filière si nécessaire.</p>
                    </div>
                    <div class="import-card-body">
                        <div class="hint-tag">La filière est détectée automatiquement depuis le nom du fichier.</div>

                        <form action="{{ route('import.etudiants') }}" method="POST" enctype="multipart/form-data"
                            id="formEtudiants">
                            @csrf

                            <input type="file" name="file_etudiants[]" id="fileEtu" hidden accept=".xlsx,.xls"
                                multiple>

                            <div class="drop-zone" id="dropEtu" onclick="document.getElementById('fileEtu').click()">
                                <div class="drop-symbol"><i class="bi bi-file-earmark-excel"></i></div>
                                <p class="drop-title">Déposer les fichiers étudiants</p>
                                <p class="drop-sub">Formats acceptés : .xls, .xlsx</p>
                                <button type="button" class="btn btn-primary"
                                    onclick="event.stopPropagation(); document.getElementById('fileEtu').click()">
                                    Parcourir
                                </button>
                                <div id="chosenEtu" class="chosen-files"></div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Importer les étudiants</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card import-card h-100">
                    <div class="import-card-head">
                        <h2>Liste des professeurs</h2>
                        <p>Importez le fichier des enseignants, puis revenez ici pour importer les étudiants.</p>
                    </div>
                    <div class="import-card-body">
                        <div class="hint-tag">Format requis : Nom, Prénom, Spécialité à partir de la ligne 3.</div>

                        <form action="{{ route('import.profs') }}" method="POST" enctype="multipart/form-data"
                            id="formProfesseurs">
                            @csrf
                            <input type="file" name="file_profs" id="fileProf" hidden accept=".xlsx,.xls">

                            <div class="drop-zone" id="dropProf" onclick="document.getElementById('fileProf').click()">
                                <div class="drop-symbol"><i class="bi bi-file-earmark-excel"></i></div>
                                <p class="drop-title">Déposer le fichier professeurs</p>
                                <p class="drop-sub">Un seul fichier Excel est attendu.</p>
                                <button type="button" class="btn btn-primary"
                                    onclick="event.stopPropagation(); document.getElementById('fileProf').click()">
                                    Parcourir
                                </button>
                                <div id="chosenProf" class="chosen-files"></div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Importer les professeurs</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Affectation shortcut ── --}}
        @if ($hasStudents && $hasProfessors)
            <div class="d-flex justify-content-end mt-4">
                <a href="{{ route('affectation.index') }}" class="btn btn-success">
                    <i class="bi bi-arrow-right-circle"></i>
                    Accéder à la page d'affectation
                </a>
            </div>
        @endif

        {{-- ── Reset Database Section ── --}}
        <div class="reset-section">
            <div class="reset-section-label">
                <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Zone dangereuse</strong>
                Réinitialiser la base de données supprime toutes les données et recrée les tables vides.
            </div>
            <button type="button" id="btnResetDb" class="btn btn-outline-danger"
                    style="white-space:nowrap; flex-shrink:0;">
                <i class="bi bi-arrow-counterclockwise"></i>
                Réinitialiser la BDD
            </button>
        </div>
    </div>

    {{-- ── Confirmation Modal ── --}}
    <div class="modal-overlay" id="resetModal">
        <div class="modal-box">
            <div class="modal-icon"><i class="bi bi-trash3-fill"></i></div>
            <h3>Réinitialiser la base de données ?</h3>
            <p>
                Cette action est <strong>irréversible</strong>.<br>
                Toutes les données (étudiants, enseignants, jurys, plannings…) seront
                <strong>définitivement supprimées</strong> et les tables recréées vides.
            </p>
            <div class="modal-actions">
                <button type="button" id="btnCancelReset" class="btn btn-outline-secondary">
                    Annuler
                </button>
                <form method="POST" action="{{ route('import.reset-db') }}" id="formResetDb">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3"></i>
                        Oui, réinitialiser
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const fileEtu = document.getElementById('fileEtu');
        const dropEtu = document.getElementById('dropEtu');
        const chosenEtu = document.getElementById('chosenEtu');
        const fileProf = document.getElementById('fileProf');
        const dropProf = document.getElementById('dropProf');
        const chosenProf = document.getElementById('chosenProf');

        function showFiles(target, files) {
            if (!files.length) return;
            target.style.display = 'block';
            target.innerHTML = Array.from(files).map(file => `<div>${file.name}</div>`).join('');
        }

        fileEtu.addEventListener('change', () => showFiles(chosenEtu, fileEtu.files));
        fileProf.addEventListener('change', () => showFiles(chosenProf, fileProf.files));

        dropEtu.addEventListener('dragover', event => {
            event.preventDefault();
            dropEtu.classList.add('dragover');
        });

        dropProf.addEventListener('dragover', event => {
            event.preventDefault();
            dropProf.classList.add('dragover');
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropEtu.addEventListener(eventName, () => dropEtu.classList.remove('dragover'));
            dropProf.addEventListener(eventName, () => dropProf.classList.remove('dragover'));
        });

        dropEtu.addEventListener('drop', event => {
            event.preventDefault();
            const transfer = new DataTransfer();
            Array.from(event.dataTransfer.files).forEach(file => transfer.items.add(file));
            fileEtu.files = transfer.files;
            showFiles(chosenEtu, transfer.files);
        });

        dropProf.addEventListener('drop', event => {
            event.preventDefault();
            if (!event.dataTransfer.files.length) return;
            const transfer = new DataTransfer();
            transfer.items.add(event.dataTransfer.files[0]);
            fileProf.files = transfer.files;
            showFiles(chosenProf, transfer.files);
        });

        // ── Reset DB modal ──
        const resetModal   = document.getElementById('resetModal');
        const btnResetDb   = document.getElementById('btnResetDb');
        const btnCancel    = document.getElementById('btnCancelReset');

        btnResetDb.addEventListener('click', () => resetModal.classList.add('open'));
        btnCancel.addEventListener('click',  () => resetModal.classList.remove('open'));
        resetModal.addEventListener('click', e => {
            if (e.target === resetModal) resetModal.classList.remove('open');
        });
    </script>
@endpush
