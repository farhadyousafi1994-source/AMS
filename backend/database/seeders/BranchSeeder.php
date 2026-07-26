<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }

        // Reuse the branches created in DatabaseSeeder (don't duplicate them).
        $herat = Branch::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Herat Site Office'],
            ['address' => 'Herat, Afghanistan', 'phone' => '040-000-000', 'active' => true]
        );
        $kabul = Branch::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Kabul Head Office'],
            ['address' => 'Kabul, Afghanistan', 'phone' => '020-000-000', 'active' => true]
        );

        // Assign projects: Kabul-named ones to Kabul; the rest alternate so both
        // branches have data to demonstrate branch separation.
        // Real Herat landmarks (coordinates read from Google Maps) so the demo
        // sites sit on recognisable places. Matched by a keyword in the name.
        $landmarks = [
            'ورزشگاه' => ['branch' => $herat, 'location' => 'ورزشگاه هرات (Herat Stadium)', 'lat' => 34.3667, 'lng' => 62.1830],
            'Ring Road' => ['branch' => $herat, 'location' => 'مسجد جامع هرات (Masjid Jameh)', 'lat' => 34.3436, 'lng' => 62.1994],
            'Aria Town' => ['branch' => $herat, 'location' => 'پوهنتون هرات (Herat University)', 'lat' => 34.3520, 'lng' => 62.2287],
        ];
        // A rolling set of further real Herat/Kabul coordinates for any extra
        // projects, so additional demo sites spread across recognisable places.
        $extra = [
            ['location' => 'دروازه ملک، هرات', 'lat' => 34.3419, 'lng' => 62.2031],
            ['location' => 'میدان هوایی هرات', 'lat' => 34.2100, 'lng' => 62.2283],
            ['location' => 'باغ ملت، هرات', 'lat' => 34.3390, 'lng' => 62.1890],
            ['location' => 'ناحیهٔ ۳، هرات', 'lat' => 34.3308, 'lng' => 62.2115],
            ['location' => 'گذرگاه، هرات', 'lat' => 34.3712, 'lng' => 62.1655],
            ['location' => 'انجیل، هرات', 'lat' => 34.3625, 'lng' => 62.2450],
            ['location' => 'کابل، ناحیهٔ ۱۰', 'lat' => 34.5553, 'lng' => 69.2075],
            ['location' => 'کابل، ناحیهٔ ۶', 'lat' => 34.5011, 'lng' => 69.1350],
            ['location' => 'کابل، دارالامان', 'lat' => 34.4650, 'lng' => 69.1400],
        ];

        foreach (Project::withoutGlobalScopes()->where('company_id', $company->id)->get()->values() as $i => $project) {
            $match = null;
            foreach ($landmarks as $needle => $info) {
                if (stripos((string) $project->name, $needle) !== false || stripos((string) $project->location, $needle) !== false) {
                    $match = $info;
                    break;
                }
            }
            $match ??= array_merge(['branch' => $herat], $extra[$i % count($extra)]);

            if (! $project->branch_id) {
                $project->branch_id = $match['branch']->id;
            }
            if (empty($project->lat) || empty($project->lng)) {
                $project->lat = $match['lat'];
                $project->lng = $match['lng'];
                if (empty($project->location)) {
                    $project->location = $match['location'];
                }
            }
            $project->saveQuietly();
        }

        // Spread assets across both branches so cross-branch locking is visible
        // (e.g. bulldozers split between Herat and Kabul).
        $assets = Asset::where('company_id', $company->id)->get();
        foreach ($assets as $i => $asset) {
            if ($asset->branch_id) {
                continue;
            }
            $asset->branch_id = $i % 2 === 0 ? $herat->id : $kabul->id;
            $asset->saveQuietly();
        }

        // Admin belongs to both branches and defaults to the all-branches view.
        $admin = User::where('email', 'admin@ariaherat.af')->first();
        if ($admin) {
            $admin->branches()->syncWithoutDetaching([$herat->id, $kabul->id]);
        }
    }
}
