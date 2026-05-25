<div class="app-modal-overlay" id="planningModal" aria-hidden="true">
    <div class="app-modal-box planning-modal-box">
        <header class="planning-modal-header">
            <div>
                <h3 class="h5 fw-bold mb-1">Configuration des soutenances</h3>
                <p class="text-muted small mb-0">
                    Choisissez la date de début, le nombre de jours et les plages horaires à utiliser. Les créneaux seront générés dynamiquement à partir de ces paramètres.
                </p>
            </div>
            <button type="button" class="btn-close" aria-label="Fermer" onclick="closePlanningModal()"></button>
        </header>

        <div class="constraint-box">
            <strong>Contraintes appliquées par le générateur :</strong>
            <ul>
                <li>Weekends ignorés (samedi/dimanche)</li>
                <li>Pause d'une heure entre deux soutenances pour un même enseignant</li>
                <li>Au moins 2 enseignants informatique par jury</li>
                <li>Salles utilisées intelligemment</li>
            </ul>
        </div>

        <form action="{{ route('planning.run') }}" method="POST" id="planningRunForm" class="planning-modal-form">
            @csrf

            <section class="planning-modal-section">
                <h6 class="planning-section-title">Période</h6>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="date_debut" class="form-label fw-semibold">Date de début</label>
                        <input type="date" id="date_debut" name="date_debut" class="form-control"
                               required min="{{ now()->toDateString() }}"
                               value="{{ old('date_debut') }}">
                    </div>

                    <div class="col-md-2">
                        <label for="nb_jours" class="form-label fw-semibold">Nb jours</label>
                        <input type="number" id="nb_jours" name="nb_jours" class="form-control"
                               min="1" max="30" required value="{{ old('nb_jours', 4) }}">
                    </div>

                    <div class="col-md-3">
                        <label for="creneau_duree" class="form-label fw-semibold">Durée créneau</label>
                        <select id="creneau_duree" name="creneau_duree" class="form-select">
                            @php $duree = (int) old('creneau_duree', 60); @endphp
                            <option value="30"  @selected($duree === 30)>30 min</option>
                            <option value="45"  @selected($duree === 45)>45 min</option>
                            <option value="60"  @selected($duree === 60)>60 min</option>
                            <option value="90"  @selected($duree === 90)>90 min</option>
                            <option value="120" @selected($duree === 120)>120 min</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="nb_jurys" class="form-label fw-semibold" title="Total des membres du jury (incluant l'encadrant)">
                            Membres jury
                        </label>
                        <input type="number" id="nb_jurys" name="nb_jurys" class="form-control"
                               min="2" max="6" required value="{{ old('nb_jurys', 3) }}">
                        <div class="form-text text-muted small mt-1">Encadrant inclus</div>
                    </div>
                </div>
            </section>

            <section class="planning-modal-section">
                <h6 class="planning-section-title">Plages horaires</h6>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="planning-slot-card">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="matin_actif" name="matin_actif" value="1"
                                       {{ old('matin_actif', '1') ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="matin_actif">
                                    Matinée
                                </label>
                            </div>
                            <div class="d-flex gap-3">
                                <div class="flex-fill">
                                    <label for="matin_debut" class="form-label small text-muted mb-1">De</label>
                                    <input type="time" id="matin_debut" name="matin_debut" class="form-control"
                                           value="{{ old('matin_debut', '09:00') }}">
                                </div>
                                <div class="flex-fill">
                                    <label for="matin_fin" class="form-label small text-muted mb-1">À</label>
                                    <input type="time" id="matin_fin" name="matin_fin" class="form-control"
                                           value="{{ old('matin_fin', '12:00') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="planning-slot-card">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="aprem_actif" name="aprem_actif" value="1"
                                       {{ old('aprem_actif', '1') ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="aprem_actif">
                                    Après-midi
                                </label>
                            </div>
                            <div class="d-flex gap-3">
                                <div class="flex-fill">
                                    <label for="aprem_debut" class="form-label small text-muted mb-1">De</label>
                                    <input type="time" id="aprem_debut" name="aprem_debut" class="form-control"
                                           value="{{ old('aprem_debut', '14:00') }}">
                                </div>
                                <div class="flex-fill">
                                    <label for="aprem_fin" class="form-label small text-muted mb-1">À</label>
                                    <input type="time" id="aprem_fin" name="aprem_fin" class="form-control"
                                           value="{{ old('aprem_fin', '18:00') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="planning-modal-example">
                    <i class="bi bi-info-circle me-1"></i>
                    Exemple : matinée 09:00–12:00 + après-midi 14:00–18:00 avec 60&nbsp;min produit 7 créneaux par jour.
                </p>
            </section>

            <footer class="planning-modal-actions">
                <button type="button" class="btn btn-outline-secondary" onclick="closePlanningModal()">Annuler</button>
                <button type="submit" class="btn btn-success" id="planningSubmitBtn">
                    <i class="bi bi-play-fill"></i>
                    Générer
                </button>
            </footer>
        </form>
    </div>
</div>

{{-- Full-screen loading overlay shown during planning generation --}}
<div id="planningLoadingOverlay" style="
    display: none;
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    justify-content: center;
    align-items: center;
    flex-direction: column;
    gap: 24px;
">
    <div style="
        width: 64px; height: 64px;
        border: 4px solid rgba(255,255,255,0.15);
        border-top-color: #22c55e;
        border-radius: 50%;
        animation: planningSpinner 0.8s linear infinite;
    "></div>
    <div style="text-align: center;">
        <div style="color: #fff; font-size: 1.25rem; font-weight: 700; margin-bottom: 8px;">
            Génération du planning en cours…
        </div>
        <div style="color: rgba(255,255,255,0.6); font-size: 0.88rem; max-width: 400px;">
            L'algorithme optimise les créneaux, jurys et salles.<br>
            Cela peut prendre quelques minutes selon le nombre d'étudiants.
        </div>
        <div style="margin-top: 18px; display: flex; justify-content: center; gap: 6px;">
            <span class="planning-dot" style="animation-delay: 0s;"></span>
            <span class="planning-dot" style="animation-delay: 0.2s;"></span>
            <span class="planning-dot" style="animation-delay: 0.4s;"></span>
        </div>
    </div>
</div>

<style>
    /* ── Planning configuration modal ─────────────────────────────── */
    .planning-modal-box {
        width: min(720px, 96vw);
        padding: 32px 36px 28px;
    }

    .planning-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
        padding-bottom: 18px;
        border-bottom: 1px solid var(--border);
    }

    .planning-modal-header h3 {
        color: var(--heading);
    }

    .planning-modal-header .btn-close {
        margin-top: 2px;
    }

    .planning-modal-form {
        margin-top: 6px;
    }

    .planning-modal-section {
        margin-bottom: 26px;
    }

    .planning-modal-section:last-of-type {
        margin-bottom: 18px;
    }

    .planning-section-title {
        margin: 0 0 14px;
        color: var(--heading);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .planning-slot-card {
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px 18px;
        background: var(--surface-soft);
        height: 100%;
    }

    .planning-modal-example {
        margin: 18px 0 0;
        padding: 12px 14px;
        background: var(--surface-soft);
        border: 1px dashed var(--border);
        border-radius: 10px;
        color: var(--muted);
        font-size: 0.82rem;
    }

    .planning-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 22px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }

    @media (max-width: 576px) {
        .planning-modal-box {
            padding: 22px 18px 20px;
        }

        .planning-modal-section {
            margin-bottom: 20px;
        }
    }

    /* ── Loading overlay ─────────────────────────────────────────── */
    @keyframes planningSpinner {
        to { transform: rotate(360deg); }
    }
    .planning-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #22c55e;
        display: inline-block;
        animation: planningDotPulse 1.2s ease-in-out infinite;
    }
    @keyframes planningDotPulse {
        0%, 80%, 100% { opacity: 0.25; transform: scale(0.8); }
        40% { opacity: 1; transform: scale(1.2); }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('planningRunForm');
        const overlay = document.getElementById('planningLoadingOverlay');

        if (form && overlay) {
            form.addEventListener('submit', function () {
                // Close the config modal
                closePlanningModal();
                // Show loading overlay
                overlay.style.display = 'flex';
            });
        }
    });
</script>
