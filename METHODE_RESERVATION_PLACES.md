# Méthode de Réservation des Places - Documentation

## Vue d'ensemble

Le système de réservation des places permet aux étudiants de réserver une place spécifique dans une salle pour un cours donné. Chaque étudiant peut réserver une seule place par cours, et chaque place ne peut être réservée qu'une seule fois.

---

## Architecture du Système

### 1. **Table de Base de Données : `reservation_places`**

```sql
- id (bigint, PK)
- etudiant_id (bigint, FK → users)
- emploi_du_temps_id (bigint, FK → emploi_du_temps)
- cours_id (bigint, FK → cours)
- numero_place (integer) - Numéro de la place dans la salle
- statut (enum) - 'reservee' ou 'annulee'
- created_at, updated_at
```

**Index:** `['emploi_du_temps_id', 'numero_place', 'statut']` pour optimiser les vérifications de disponibilité.

---

## Flux de Fonctionnement

### **Étape 1 : Affichage de l'Interface** 
**Route:** `GET /etudiant/reservations`  
**Méthode:** `EmploiDuTempsController@reservations`

#### Processus :
1. Récupère tous les cours de l'étudiant (basés sur ses groupes)
2. Si un cours et un emploi du temps sont sélectionnés :
   - Récupère la capacité de la salle
   - Récupère toutes les réservations actives (`statut = 'reservee'`) pour cet emploi du temps
3. Affiche l'interface avec :
   - Un formulaire de sélection (cours → séance)
   - La visualisation de la salle avec les places disponibles/occupées

#### Code clé :
```php
// Récupération des cours de l'étudiant
$groupes = auth()->user()->groupes;
$cours = Cour::whereHas('groupes', function($q) use ($groupes) {
    $q->whereIn('groupes.id', $groupes->pluck('id'));
})->with(['emploisDuTemps.salle', 'enseignant'])->get();

// Récupération des réservations pour l'emploi du temps sélectionné
$reservations = ReservationPlace::where('emploi_du_temps_id', $emploiId)
    ->where('statut', 'reservee')
    ->get();
```

---

### **Étape 2 : Visualisation de la Salle**

L'interface affiche la salle sous forme de grille :
- **Tableau** en haut (représentant le professeur)
- **Rangées de places** organisées en grille (4 places par rangée)
- **Couleurs** :
  - 🟢 **Vert** : Ma place réservée
  - ⚪ **Gris** : Place disponible (cliquable)
  - 🔴 **Rouge** : Place occupée par un autre étudiant

#### Calcul de la disposition :
```php
$rows = ceil($capacite / 4); // 4 places par rangée
$placeNum = ($row - 1) * 4 + $place; // Numéro de la place
```

---

### **Étape 3 : Réservation d'une Place**
**Route:** `POST /etudiant/reservations`  
**Méthode:** `EmploiDuTempsController@storeReservation`

#### Validations effectuées :

1. **Validation des données** :
   ```php
   - cours_id: required, exists dans la table cours
   - emploi_du_temps_id: required, exists dans la table emploi_du_temps
   - numero_place: required, integer, min:1
   ```

2. **Vérification d'accès au cours** :
   ```php
   // Vérifie que l'étudiant appartient au groupe du cours
   $groupes = auth()->user()->groupes;
   $hasAccess = $emploi->cours->groupes
       ->whereIn('id', $groupes->pluck('id'))
       ->isNotEmpty();
   ```
   ❌ **Erreur si** : L'étudiant n'a pas accès à ce cours

3. **Vérification de la capacité de la salle** :
   ```php
   if ($numero_place > $salle->capacite) {
       // Erreur : Place dépasse la capacité
   }
   ```
   ❌ **Erreur si** : Le numéro de place dépasse la capacité de la salle

4. **Vérification de double réservation (étudiant)** :
   ```php
   $existingReservation = ReservationPlace::where('etudiant_id', auth()->id())
       ->where('emploi_du_temps_id', $emploi_du_temps_id)
       ->where('statut', 'reservee')
       ->first();
   ```
   ❌ **Erreur si** : L'étudiant a déjà réservé une place pour ce cours

5. **Vérification de disponibilité de la place** :
   ```php
   $placeReserved = ReservationPlace::where('emploi_du_temps_id', $emploi_du_temps_id)
       ->where('numero_place', $numero_place)
       ->where('statut', 'reservee')
       ->exists();
   ```
   ❌ **Erreur si** : La place est déjà réservée par un autre étudiant

#### Création de la réservation :
```php
ReservationPlace::create([
    'etudiant_id' => auth()->id(),
    'emploi_du_temps_id' => $validated['emploi_du_temps_id'],
    'cours_id' => $validated['cours_id'],
    'numero_place' => $validated['numero_place'],
    'statut' => 'reservee',
]);
```

✅ **Succès** : Redirection avec message de confirmation

---

### **Étape 4 : Annulation d'une Réservation**
**Route:** `DELETE /etudiant/reservations/{id}/cancel`  
**Méthode:** `EmploiDuTempsController@cancelReservation`

#### Processus :
1. Vérifie que la réservation appartient à l'étudiant connecté
2. Met à jour le statut à `'annulee'` (soft delete)
3. La place redevient disponible pour les autres étudiants

#### Code :
```php
$reservation = ReservationPlace::where('etudiant_id', auth()->id())
    ->findOrFail($id);

$reservation->update(['statut' => 'annulee']);
```

---

## Règles Métier

### ✅ **Règles de Réservation**

1. **Un étudiant = Une place par cours**
   - Un étudiant ne peut réserver qu'une seule place par créneau d'emploi du temps
   - S'il veut changer de place, il doit d'abord annuler sa réservation actuelle

2. **Une place = Un étudiant**
   - Chaque place ne peut être réservée qu'une seule fois par créneau
   - La vérification se fait sur `statut = 'reservee'`

3. **Accès basé sur les groupes**
   - Un étudiant ne peut réserver que pour les cours assignés à ses groupes
   - Vérification automatique avant la réservation

4. **Capacité de la salle**
   - Le numéro de place ne peut pas dépasser la capacité de la salle
   - La capacité est définie dans la table `salles`

### 🔄 **Gestion des Statuts**

- **`reservee`** : Place active et réservée
- **`annulee`** : Place libérée (l'étudiant a annulé sa réservation)

> **Note** : Les places annulées ne sont pas supprimées de la base de données, mais leur statut est changé. Cela permet de garder un historique.

---

## Interface Utilisateur

### **Visualisation de la Salle**

```
┌─────────────────────────────────────┐
│   📋 Tableau et Professeur          │
└─────────────────────────────────────┘

Rang 1:  [Place 1] [Place 2] [Place 3] [Place 4]
Rang 2:   [Place 5] [Place 6] [Place 7] [Place 8]
Rang 3:   [Place 9] [Place 10] [Place 11] [Place 12]
...
```

### **États Visuels**

- **🟢 Ma place** : Bouton vert avec icône utilisateur, non cliquable
- **⚪ Disponible** : Bouton gris clair, cliquable, hover bleu
- **🔴 Occupée** : Bouton rouge clair, non cliquable, avec icône X

### **Actions Disponibles**

1. **Sélectionner un cours** : Dropdown avec tous les cours de l'étudiant
2. **Sélectionner une séance** : Dropdown avec les créneaux du cours sélectionné
3. **Réserver une place** : Cliquer sur une place disponible
4. **Annuler ma réservation** : Bouton visible si l'étudiant a déjà réservé

---

## Modèle Eloquent

### **ReservationPlace**

```php
class ReservationPlace extends Model
{
    protected $fillable = [
        'etudiant_id',
        'emploi_du_temps_id',
        'cours_id',
        'numero_place',
        'statut',
    ];

    // Relations
    public function etudiant() {
        return $this->belongsTo(User::class, 'etudiant_id');
    }

    public function emploiDuTemps() {
        return $this->belongsTo(EmploiDuTemps::class);
    }

    public function cours() {
        return $this->belongsTo(Cour::class);
    }
}
```

---

## Routes

```php
// Étudiant
Route::get('reservations', [EtudiantEmploiDuTempsController::class, 'reservations'])
    ->name('reservations');
Route::post('reservations', [EtudiantEmploiDuTempsController::class, 'storeReservation'])
    ->name('reservations.store');
Route::delete('reservations/{id}/cancel', [EtudiantEmploiDuTempsController::class, 'cancelReservation'])
    ->name('reservations.cancel');
```

---

## Messages d'Erreur

| Erreur | Message |
|-------|---------|
| Accès refusé | "Vous n'avez pas accès à ce cours." |
| Capacité dépassée | "Le numéro de place dépasse la capacité de la salle." |
| Double réservation | "Vous avez déjà réservé une place pour ce cours." |
| Place occupée | "Cette place est déjà réservée." |
| Validation échouée | Messages de validation Laravel standards |

---

## Messages de Succès

| Action | Message |
|--------|---------|
| Réservation réussie | "Place réservée avec succès !" |
| Annulation réussie | "Réservation annulée avec succès" |

---

## Exemple de Données

### **Réservation créée :**
```json
{
    "etudiant_id": 5,
    "emploi_du_temps_id": 12,
    "cours_id": 8,
    "numero_place": 7,
    "statut": "reservee"
}
```

### **Requête de réservation :**
```http
POST /etudiant/reservations
Content-Type: application/x-www-form-urlencoded

cours_id=8
emploi_du_temps_id=12
numero_place=7
```

---

## Points d'Attention

1. **Concurrence** : Si deux étudiants tentent de réserver la même place simultanément, la première requête réussira et la seconde échouera avec "Cette place est déjà réservée."

2. **Performance** : L'index sur `['emploi_du_temps_id', 'numero_place', 'statut']` optimise les vérifications de disponibilité.

3. **Intégrité** : Les clés étrangères avec `onDelete('cascade')` garantissent que si un cours ou un emploi du temps est supprimé, les réservations associées sont également supprimées.

4. **Historique** : Les réservations annulées restent en base avec `statut = 'annulee'` pour garder un historique.

---

## Améliorations Possibles

1. **Notification** : Envoyer une notification à l'étudiant lors de la confirmation de réservation
2. **Expiration** : Ajouter une date d'expiration pour les réservations
3. **File d'attente** : Permettre aux étudiants de s'inscrire sur une liste d'attente si toutes les places sont prises
4. **Préférences** : Permettre aux étudiants de définir des préférences de places
5. **Statistiques** : Afficher des statistiques sur les places les plus populaires

