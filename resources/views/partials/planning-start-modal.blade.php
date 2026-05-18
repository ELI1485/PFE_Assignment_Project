<div class="app-modal-overlay" id="planningModal" aria-hidden="true">
    <div class="app-modal-box">
        <h3 class="h5 fw-bold mb-1">Date de début des soutenances</h3>
        <p class="text-muted small mb-3">
            Choisissez la date de début. Le générateur calcule automatiquement les jours nécessaires.
        </p>

        <div class="constraint-box">
            Contraintes appliquées par le générateur :
            <ul>
                <li>7 créneaux par jour</li>
                <li>pause d'une heure enseignant</li>
                <li>weekends ignorés</li>
                <li>2 enseignants informatique minimum</li>
                <li>salles utilisées intelligemment</li>
            </ul>
        </div>

        <form action="{{ route('planning.run') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label for="date_debut" class="form-label fw-semibold">Date de début</label>
                <input type="date" id="date_debut" name="date_debut" class="form-control" required min="{{ now()->toDateString() }}">
            </div>
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
