<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class UsersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return User::with(['departement', 'groupes'])->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Nom',
            'Email',
            'Rôle',
            'Téléphone',
            'Département',
            'Groupes',
            'Date de création',
            'Date de mise à jour'
        ];
    }

    /**
     * @param User $user
     * @return array
     */
    public function map($user): array
    {
        $groupes = $user->groupes->pluck('nom')->implode(', ');
        
        return [
            $user->id,
            $user->name,
            $user->email,
            ucfirst($user->role),
            $user->telephone ?? '',
            $user->departement ? $user->departement->nom : 'N/A',
            $groupes ?: 'N/A',
            $user->created_at->format('d/m/Y H:i'),
            $user->updated_at->format('d/m/Y H:i')
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '00175f']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ],
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Utilisateurs';
    }
}

