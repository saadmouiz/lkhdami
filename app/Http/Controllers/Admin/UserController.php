<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Departement;
use App\Models\Groupe;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = \App\Models\User::with('departement')->latest()->get();
        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'role' => 'required|in:admin,enseignant,etudiant',
                'telephone' => 'nullable|string|max:20',
                'departement' => 'nullable|string|max:255',
                'groupe_id' => 'required_if:role,etudiant|nullable|exists:groupes,id',
            ], [
                'name.required' => 'Le nom est obligatoire.',
                'email.required' => 'L\'email est obligatoire.',
                'email.email' => 'L\'email doit être valide.',
                'email.unique' => 'Cet email est déjà utilisé.',
                'password.required' => 'Le mot de passe est obligatoire.',
                'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
                'role.required' => 'Le rôle est obligatoire.',
                'groupe_id.required_if' => 'Le groupe est obligatoire pour les étudiants.',
                'groupe_id.exists' => 'Le groupe sélectionné n\'existe pas.',
            ]);

            // Trouver ou créer le département
            $departement = null;
            if ($request->filled('departement')) {
                $departement = Departement::firstOrCreate(['nom' => $request->departement]);
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'telephone' => $validated['telephone'] ?? null,
                'departement_id' => $departement ? $departement->id : null,
            ]);

            // Si c'est un étudiant, l'attacher au groupe
            if ($validated['role'] === 'etudiant' && !empty($validated['groupe_id'])) {
                $groupe = Groupe::findOrFail($validated['groupe_id']);
                $user->groupes()->attach($groupe->id);
                // Mettre à jour l'effectif du groupe
                $groupe->refresh();
                $groupe->update(['effectif' => $groupe->etudiants()->count()]);
            }

            // Notifier l'utilisateur de la création de son compte
            NotificationHelper::notifyUserCreated($user->id, $user->name, $validated['role']);

            return redirect()->route('admin.users.index')
                ->with('success', 'Utilisateur créé avec succès');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('admin.users.index')
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Une erreur est survenue : ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Export users to CSV (compatible Excel)
     */
    public function export()
    {
        $users = User::with(['departement', 'groupes'])->get();
        
        $filename = 'utilisateurs_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($users) {
            $file = fopen('php://output', 'w');
            
            // Ajouter BOM UTF-8 pour Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // En-têtes
            fputcsv($file, [
                'ID',
                'Nom',
                'Email',
                'Rôle',
                'Téléphone',
                'Département',
                'Groupes',
                'Date de création',
                'Date de mise à jour'
            ], ';');
            
            // Données
            foreach ($users as $user) {
                $groupes = $user->groupes->pluck('nom')->implode(', ');
                
                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    ucfirst($user->role),
                    $user->telephone ?? '',
                    $user->departement ? $user->departement->nom : 'N/A',
                    $groupes ?: 'N/A',
                    $user->created_at->format('d/m/Y H:i'),
                    $user->updated_at->format('d/m/Y H:i')
                ], ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
