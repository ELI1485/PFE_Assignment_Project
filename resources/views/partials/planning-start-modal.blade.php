<div class="app-modal-overlay" id="planningModal" aria-hidden="true">
    <div class="app-modal-box" style="max-width: 640px;">
        <h3 class="h5 fw-bold mb-1">Configuration des soutenances</h3>
        <p class="text-muted small mb-3">
            Choisissez la date de début, le nombre de jours et les plages horaires à utiliser. Les créneaux seront générés dynamiquement à partir de ces paramètres.
        </p>

        <div class="constraint-box">
            Contraintes appliquées par le générateur :
            <ul>
                <li>weekends ignorés (samedi/dimanche)</li>
                <li>pause d'une heure entre deux soutenances pour un même enseignant</li>
                <li>au moins 2 enseignants informatique par jury</li>
                <li>salles utilisées intelligemment</li>
            </ul>
        </div>

        <form action="{{ route('planning.run') }}" method="POST" id="planningRunForm">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="date_debut" class="form-label fw-semibold">Date de début</label>
                    <input type="date" id="date_debut" name="date_debut" class="form-control"
                           required min="{{ now()->toDateString() }}"
                           value="{{ old('date_debut') }}">
                </div>

                <div class="col-md-3">
                    <label for="nb_jours" class="form-label fw-semibold">Nombre de jours</label>
                    <input type="number" id="nb_jours" name="nb_jours" class="form-control"
                           min="1" max="30" required value="{{ old('nb_jours', 4) }}">
                </div>

                <div class="col-md-3">
                    <label for="creneau_duree" class="form-label fw-semibold">Durée d'un créneau</label>
                    <select id="creneau_duree" name="creneau_duree" class="form-select">
                        @php $duree = (int) old('creneau_duree', 60); @endphp
                        <option value="30"  @selected($duree === 30)>30 min</option>
                        <option value="45"  @selected($duree === 45)>45 min</option>
                        <option value="60"  @selected($duree === 60)>60 min</option>
                        <option value="90"  @selected($duree === 90)>90 min</option>
                        <option value="120" @selected($duree === 120)>120 min</option>
                    </select>
                </div>
            </div>

            <hr class="my-3">

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="matin_actif" name="matin_actif" value="1"
                               {{ old('matin_actif', '1') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="matin_actif">
                            Plage matinée
                        </label>
                    </div>
                    <div class="d-flex gap-2">
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

                <div class="col-md-6">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="aprem_actif" name="aprem_actif" value="1"
                               {{ old('aprem_actif', '1') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="aprem_actif">
                            Plage après-midi
                        </label>
                    </div>
                    <div class="d-flex gap-2">
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

            <p class="text-muted small mt-3 mb-4">
                Exemple : matinée 09:00–12:00 + après-midi 14:00–18:00 avec une durée de 60 min produit 7 créneaux par jour.
            </p>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="closePlanningModal()">Annuler</button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-play-fill"></i>
                    Générer
                </button>
            </div>
        </form>
    </div>
</div>
